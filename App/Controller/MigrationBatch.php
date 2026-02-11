<?php

namespace WLMI\App\Controller;

use WLMI\App\Helper\Mailchimp as MailchimpHelper;
use WLMI\App\Helper\Settings as SettingsHelper;
use Wlr\App\Models\Users;

defined( 'ABSPATH' ) || exit;

class MigrationBatch {
	/**
	 * Get pending migration state for a list in a single paginated scan.
	 *
	 * @param   string  $list_id
	 *
	 * @return array{has_first_batch_pending: bool, has_any_batch_pending: bool}
	 */
	private static function getPendingMigrationState( string $list_id ): array {
		$state = [
			'has_first_batch_pending' => false,
			'has_any_batch_pending'   => false,
		];
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return $state;
		}

		$per_page = 50;
		$offset   = 0;
		$statuses = class_exists( 'ActionScheduler_Store' ) ? [
			\ActionScheduler_Store::STATUS_PENDING,
			\ActionScheduler_Store::STATUS_RUNNING
		] : [ 'pending', 'running' ];

		do {
			$actions = as_get_scheduled_actions( [
				'hook'     => 'wlmi_process_mailchimp_migration_batch',
				'group'    => 'wlmi_migration_queue',
				'status'   => $statuses,
				'per_page' => $per_page,
				'offset'   => $offset,
			], OBJECT );

			if ( empty( $actions ) || ! is_array( $actions ) ) {
				break;
			}

			foreach ( $actions as $action ) {
				if ( ! is_object( $action ) || ! method_exists( $action, 'get_args' ) ) {
					continue;
				}
				$args = $action->get_args();
				if ( empty( $args ) || ! is_array( $args ) ) {
					continue;
				}
				$job_data   = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : [];
				$job_list   = (string) ( $job_data['list_id'] ?? '' );
				$job_start  = (int) ( $job_data['start_id'] ?? - 1 );
				$list_match = $job_list === $list_id;
				if ( $list_match ) {
					$state['has_any_batch_pending'] = true;
					if ( $job_start === 0 ) {
						$state['has_first_batch_pending'] = true;
					}
				}
				if ( $state['has_first_batch_pending'] && $state['has_any_batch_pending'] ) {
					return $state;
				}
			}

			$count  = count( $actions );
			$offset += $per_page;
		} while ( $count >= $per_page );

		return $state;
	}

	/**
	 * Check if the first migration batch (start_id = 0) is still pending or running for a list.
	 *
	 * @param   string  $list_id
	 *
	 * @return bool
	 */
	protected static function isFirstBatchPending( string $list_id ): bool {
		return self::getPendingMigrationState( $list_id )['has_first_batch_pending'];
	}

	/**
	 * Check if there are any pending migration batches for a given list.
	 *
	 * @param   string  $list_id
	 *
	 * @return bool
	 */
	protected static function hasPendingMigrationBatches( string $list_id ): bool {
		return self::getPendingMigrationState( $list_id )['has_any_batch_pending'];
	}

	/**
	 * Core logic to schedule migration batches for a given list.
	 *
	 * @param   string  $list_id
	 * @param   array   $settings
	 *
	 * @return void
	 */
	protected static function scheduleBatchesForList( string $list_id, array $settings ): void {
		if ( empty( $list_id ) ) {
			return;
		}
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			wc_get_logger()->add( 'wlmi', 'Action Scheduler not available for migration scheduling.' );

			return;
		}

		update_option( 'wlmi_migration_batches_' . $list_id, [] );

		global $wpdb;
		$user_model = new Users();
		$table      = $user_model->getTableName();

		$include_banned = (bool) apply_filters( 'wlmi_migration_include_banned_users', false );
		$include_unsub  = (bool) apply_filters( 'wlmi_migration_include_unsubscribed_users', false );

		$batch_size  = 1000;
		$batch_index = 0;
		$last_id     = 0;
		$group       = 'wlmi_migration_queue';

		while ( true ) {
			$where_parts = [ $wpdb->prepare( 'id > %d', $last_id ) ];

			if ( ! $include_unsub ) {
				$where_parts[] = $wpdb->prepare( 'is_allow_send_email = %d', 1 );
			}

			if ( ! $include_banned ) {
				$where_parts[] = "(is_banned_user IS NULL OR is_banned_user = 0 OR is_banned_user = '')";
			}

			$where = implode( ' AND ', $where_parts );
			$where .= ' ORDER BY id ASC';
			$where .= $wpdb->prepare( ' LIMIT %d', $batch_size );

			$rows = $user_model->getWhere( $where, 'id', false );
			if ( empty( $rows ) ) {
				break;
			}

			$time     = time() + ( $batch_index * 120 );
			$job_args = [ [ 'start_id' => $last_id, 'list_id' => $list_id ] ];
			if ( false === as_next_scheduled_action( 'wlmi_process_mailchimp_migration_batch', $job_args, $group ) ) {
				as_schedule_single_action( $time, 'wlmi_process_mailchimp_migration_batch', $job_args, $group );
			}

			$last_row = end( $rows );
			$last_id  = isset( $last_row->id ) ? (int) $last_row->id : $last_id;
			$batch_index ++;

			if ( count( $rows ) < $batch_size ) {
				break;
			}
		}

		// Schedule error check job after all batches are scheduled (6 hours after last batch)
		if ( $batch_index > 0 ) {
			$error_check_time  = time() + ( $batch_index * 120 ) + ( 6 * HOUR_IN_SECONDS );
			$error_check_args  = [ [ 'list_id' => $list_id ] ];
			$error_check_group = 'wlmi_migration_error_check';
			if ( false === as_next_scheduled_action( 'wlmi_check_migration_errors', $error_check_args,
					$error_check_group ) ) {
				as_schedule_single_action( $error_check_time, 'wlmi_check_migration_errors', $error_check_args,
					$error_check_group );
			}
		}
	}

	/**
	 * Schedule migration batches when migration choice is enabled.
	 *
	 * @param   array  $settings
	 *
	 * @return void
	 */
	public static function scheduleBatches( array $settings ) {
		$migration_choice = isset( $settings['migration_choice'] ) ? (string) $settings['migration_choice'] : '';
		if ( $migration_choice !== 'yes' ) {
			return;
		}
		if ( empty( $settings['list_id'] ) ) {
			return;
		}
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			wc_get_logger()->add( 'wlmi', 'Action Scheduler not available for migration scheduling.' );

			return;
		}

		$list_id = (string) $settings['list_id'];
		if ( self::isFirstBatchPending( $list_id ) ) {
			// Migration already scheduled and will handle all users.
			return;
		}

		self::scheduleBatchesForList( $list_id, $settings );
	}

	/**
	 * Handle loyalty import completion.
	 *
	 * @return void
	 */
	public static function onImportCompleted(): void {
		$settings = SettingsHelper::gets();
		$list_id  = isset( $settings['list_id'] ) ? (string) $settings['list_id'] : '';
		if ( empty( $list_id ) ) {
			return;
		}

		if ( ! MailchimpHelper::getClientFromSettings( $settings ) ) {
			return;
		}

		$state = self::getPendingMigrationState( $list_id );

		if ( $state['has_first_batch_pending'] ) {
			return;
		}

		if ( $state['has_any_batch_pending'] ) {
			update_option( 'wlmi_sync_after_migration_' . $list_id, 1 );

			return;
		}

		self::scheduleBatchesForList( $list_id, $settings );
	}

	/**
	 * Process a single migration batch.
	 *
	 * @param   array  $job_data
	 *
	 * @return void
	 */
	public static function processBatch( $job_data ) {
		if ( empty( $job_data ) || ! is_array( $job_data ) ) {
			return;
		}
		$start_id = isset( $job_data['start_id'] ) ? (int) $job_data['start_id'] : 0;
		$list_id  = isset( $job_data['list_id'] ) ? (string) $job_data['list_id'] : '';
		if ( empty( $list_id ) ) {
			return;
		}

		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			wc_get_logger()->add( 'wlmi', 'Mailchimp settings missing for migration batch.' );

			return;
		}

		global $wpdb;
		$user_model     = new Users();
		$table          = $user_model->getTableName();
		$limit          = 1000;
		$include_banned = (bool) apply_filters( 'wlmi_migration_include_banned_users', false );
		$include_unsub  = (bool) apply_filters( 'wlmi_migration_include_unsubscribed_users', false );

		$where_parts = [ $wpdb->prepare( 'id > %d', $start_id ) ];
		if ( ! $include_unsub ) {
			$where_parts[] = $wpdb->prepare( 'is_allow_send_email = %d', 1 );
		}
		if ( ! $include_banned ) {
			$where_parts[] = "(is_banned_user IS NULL OR is_banned_user = 0 OR is_banned_user = '')";
		}
		$where = implode( ' AND ', $where_parts );
		$where .= ' ORDER BY id ASC';
		$where .= $wpdb->prepare( ' LIMIT %d', $limit );

		$query = "SELECT * FROM {$table} WHERE {$where}";
		$users = $user_model->rawQuery( $query, false );
		if ( empty( $users ) ) {
			return;
		}

		$base_url   = site_url() . '?wlr_ref=';
		$operations = [];
		$last_id    = 0;
		foreach ( $users as $user ) {
			$last_id    = isset( $user->id ) ? (int) $user->id : $last_id;
			$user_email = isset( $user->user_email ) ? sanitize_email( $user->user_email ) : '';
			if ( empty( $user_email ) ) {
				continue;
			}

			$ref_code = isset( $user->refer_code ) ? (string) $user->refer_code : '';
			$ref_url  = '';
			if ( ! empty( $ref_code ) ) {
				$ref_url = $base_url . $ref_code;
			}

			$points          = isset( $user->points ) ? (int) $user->points : 0;
			$subscriber_hash = md5( strtolower( trim( $user_email ) ) );

			$operations[] = [
				'method'       => 'PUT',
				'path'         => "/lists/{$list_id}/members/{$subscriber_hash}",
				'operation_id' => $user_email,
				'body'         => wp_json_encode( [
					'email_address' => $user_email,
					'status_if_new' => 'subscribed',
					'status'        => 'subscribed',
					'merge_fields'  => [
						'REF_CODE' => $ref_code,
						'REF_URL'  => $ref_url,
						'POINTS'   => $points,
					],
				] ),
			];
		}

		if ( empty( $operations ) ) {
			return;
		}

		$response = MailchimpHelper::startBatch( $settings, $operations );
		if ( empty( $response ) ) {
			wc_get_logger()->add( 'wlmi', 'Mailchimp batch migration failed for start_id: ' . $start_id );

			return;
		}

		// Store batch ID in option for error tracking
		if ( isset( $response->id ) ) {
			$batch_id   = (string) $response->id;
			$option_key = 'wlmi_migration_batches_' . $list_id;
			$batch_ids  = get_option( $option_key, [] );
			if ( ! in_array( $batch_id, $batch_ids, true ) ) {
				$batch_ids[] = $batch_id;
				update_option( $option_key, $batch_ids );
			}
		}

		// If migration has finished for this list and a post-migration sync was requested, schedule it now.
		if ( ! self::hasPendingMigrationBatches( $list_id ) ) {
			$flag_key = 'wlmi_sync_after_migration_' . $list_id;
			if ( get_option( $flag_key ) ) {
				delete_option( $flag_key );
				self::scheduleBatchesForList( $list_id, $settings );
			}
		}
	}

	/**
	 * Check migration errors and download error files.
	 *
	 * @param   array  $job_data
	 *
	 * @return void
	 */
	public static function checkMigrationErrors( $job_data ) {
		if ( empty( $job_data ) || ! is_array( $job_data ) ) {
			return;
		}
		$list_id = isset( $job_data['list_id'] ) ? (string) $job_data['list_id'] : '';
		if ( empty( $list_id ) ) {
			return;
		}

		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			wc_get_logger()->add( 'wlmi', 'Mailchimp settings missing for error check.' );

			return;
		}

		// Get all batch IDs for this list
		$option_key = 'wlmi_migration_batches_' . $list_id;
		$batch_ids  = get_option( $option_key, [] );
		if ( empty( $batch_ids ) || ! is_array( $batch_ids ) ) {
			return;
		}

		// Create log directory structure: wp-content/wlmi-migration-logs/{list_id}/{batch_id}/
		$log_base_dir = WP_CONTENT_DIR . '/wlmi-migration-logs/';
		wp_mkdir_p( $log_base_dir );

		$has_errors = false;

		foreach ( $batch_ids as $batch_id ) {
			$batch_id = (string) $batch_id;
			$status   = MailchimpHelper::getBatchStatus( $settings, $batch_id );

			if ( empty( $status ) ) {
				continue;
			}

			// Log full status check response
			wc_get_logger()->add( 'wlmi',
				'Mailchimp batch status check for batch_id ' . $batch_id . ' - Full response: ' . wp_json_encode( $status,
					JSON_PRETTY_PRINT ) );

			// Check if batch has errors
			$errored_operations = isset( $status->errored_operations ) ? (int) $status->errored_operations : 0;
			if ( $errored_operations > 0 && isset( $status->response_body_url ) ) {
				$has_errors = true;

				// Create batch-specific directory
				$batch_log_dir = $log_base_dir . $list_id . '/' . $batch_id . '/';
				wp_mkdir_p( $batch_log_dir );

				// Download the error file
				$download_url = $status->response_body_url;
				$response     = wp_remote_get( $download_url, [
					'timeout'   => 300,
					'sslverify' => true,
				] );

				if ( is_wp_error( $response ) ) {
					wc_get_logger()->add( 'wlmi',
						'Failed to download batch error file for batch_id ' . $batch_id . ': ' . $response->get_error_message() );
					continue;
				}

				$file_content  = wp_remote_retrieve_body( $response );
				$response_code = wp_remote_retrieve_response_code( $response );

				if ( $response_code !== 200 || empty( $file_content ) ) {
					wc_get_logger()->add( 'wlmi',
						'Failed to download batch error file for batch_id ' . $batch_id . ': HTTP ' . $response_code );
					continue;
				}

				// Save the file
				$file_path = $batch_log_dir . $batch_id . '-response.tar.gz';
				$saved     = file_put_contents( $file_path, $file_content );

				if ( $saved === false ) {
					wc_get_logger()->add( 'wlmi',
						'Failed to save batch error file for batch_id ' . $batch_id . ' to ' . $file_path );
				} else {
					wc_get_logger()->add( 'wlmi',
						'Downloaded batch error file for batch_id ' . $batch_id . ' (' . $errored_operations . ' errors) to ' . $file_path );
				}
			}
		}

		if ( ! $has_errors ) {
			wc_get_logger()->add( 'wlmi', 'No errors found in migration batches for list_id ' . $list_id );
		}
	}
}
