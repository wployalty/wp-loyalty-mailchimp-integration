<?php

namespace WLMI\App\Controller;

use WLMI\App\Helper\Mailchimp as MailchimpHelper;
use WLMI\App\Helper\Settings as SettingsHelper;
use WLMI\App\Helper\Util;
use WLMI\App\Helper\WC;
use WLMI\App\Helper\File as FileHelper;
use Wlr\App\Models\Users;
use splitbrain\PHPArchive\Tar;

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

		$batch_size  = apply_filters( 'wlmi_default_operations_per_batch_size', 1000 );
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
		$limit          = apply_filters( 'wlmi_default_operations_per_batch_size', 1000 );
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
	 * Get consolidated migration status for a list.
	 *
	 * Aggregates status across all Mailchimp batches and Action Scheduler state
	 * for the given list_id.
	 *
	 * @param string $list_id  The Mailchimp list ID.
	 * @param array  $settings Plugin settings including API credentials.
	 *
	 * @return array Consolidated status data.
	 */
	public static function getConsolidatedStatus( string $list_id, array $settings ): array {
		$default_last_checked_at = Util::getCurrentTimeFormatted();

		$default = [
			'state'                 => 'no_runs',
			'total_operations'      => 0,
			'finished_operations'  => 0,
			'success_operations'   => 0,
			'errored_operations'   => 0,
			'batch_count'           => 0,
			'has_any_pending'       => false,
			'has_first_pending'     => false,
			'first_error_file_url'  => null,
			'failed_users_csv_path' => null,
			'csv_processing_status' => 'not_started',
			'last_checked_at'       => $default_last_checked_at,
		];

		if ( empty( $list_id ) ) {
			return $default;
		}

		// Get Action Scheduler pending state
		$pending_state                = self::getPendingMigrationState( $list_id );
		$default['has_any_pending']   = $pending_state['has_any_batch_pending'];
		$default['has_first_pending'] = $pending_state['has_first_batch_pending'];

		// Get stored batch IDs
		$option_key = 'wlmi_migration_batches_' . $list_id;
		$batch_ids  = get_option( $option_key, [] );

		if ( empty( $batch_ids ) || ! is_array( $batch_ids ) ) {
			// No batches yet, but check if any are pending in Action Scheduler
			if ( $default['has_any_pending'] ) {
				$default['state'] = 'in_progress';
			}

			return $default;
		}

		// Aggregate status from all batches (limit to most recent 50 to avoid overload)
		$batch_ids = array_slice( $batch_ids, - 50 );

		$total_operations    = 0;
		$finished_operations = 0;
		$errored_operations  = 0;
		$batch_count         = 0;
		$first_error_url     = null;
		$raw_states          = [];

		foreach ( $batch_ids as $batch_id ) {
			$batch_id = (string) $batch_id;
			$status   = MailchimpHelper::getBatchStatus( $settings, $batch_id );

			if ( empty( $status ) ) {
				continue;
			}

			$batch_count ++;
			$total_operations    += isset( $status->total_operations ) ? (int) $status->total_operations : 0;
			$finished_operations += isset( $status->finished_operations ) ? (int) $status->finished_operations : 0;
			$errored_operations  += isset( $status->errored_operations ) ? (int) $status->errored_operations : 0;

			if ( isset( $status->status ) ) {
				$raw_states[] = strtolower( (string) $status->status );
			}

			// Capture first error file URL
			if ( $first_error_url === null && isset( $status->response_body_url ) && ! empty( $status->response_body_url ) ) {
				$batch_errored = isset( $status->errored_operations ) ? (int) $status->errored_operations : 0;
				if ( $batch_errored > 0 ) {
					$first_error_url = $status->response_body_url;
				}
			}
		}

		$success_operations = max( 0, $finished_operations - $errored_operations );

		// Determine overall state
		$state = 'completed';
		if ( $default['has_any_pending'] ) {
			$state = 'in_progress';
		} elseif ( ! empty( $raw_states ) ) {
			// Check if any batch is still running/pending on Mailchimp side
			$in_progress_states = [ 'pending', 'started', 'running', 'finalizing' ];
			foreach ( $raw_states as $rs ) {
				if ( in_array( $rs, $in_progress_states, true ) ) {
					$state = 'in_progress';
					break;
				}
			}
		}

		// Check for CSV file and process if needed
		$csv_path         = null;
		$csv_status       = 'not_started';
		$log_base_dir     = WP_CONTENT_DIR . '/wlmi-migration-logs/';
		$expected_csv_path = $log_base_dir . $list_id . '/failed-users.csv';
		$status_key       = 'wlmi_csv_processing_status_' . $list_id;

		// If all batches completed and there are errors, check/process CSV
		if ( $state === 'completed' && $errored_operations > 0 ) {
			// Check if CSV already exists
			if ( FileHelper::exists( $expected_csv_path ) && FileHelper::isReadable( $expected_csv_path ) ) {
				$csv_path   = $expected_csv_path;
				$csv_status = 'completed';
				// Clear any processing status
				delete_transient( $status_key );
			} else {
				// Check processing status
				$processing_status = get_transient( $status_key );

				if ( $processing_status === 'processing' ) {
					// Check if Action Scheduler job is still pending/running
					if ( function_exists( 'as_get_scheduled_actions' ) ) {
						$csv_actions = as_get_scheduled_actions( [
							'hook'   => 'wlmi_process_csv_errors',
							'args'   => [ [ 'list_id' => $list_id ] ],
							'status' => class_exists( 'ActionScheduler_Store' ) ? [
								\ActionScheduler_Store::STATUS_PENDING,
								\ActionScheduler_Store::STATUS_RUNNING,
							] : [ 'pending', 'running' ],
						] );

						if ( empty( $csv_actions ) ) {
							// Job completed but CSV missing - mark as failed
							$csv_status = 'failed';
							delete_transient( $status_key );
						} else {
							$csv_status = 'processing';
						}
					} else {
						$csv_status = 'processing';
					}
				} elseif ( $processing_status === 'failed' ) {
					$csv_status = 'failed';
				} else {
					// No processing started yet - trigger async processing
					if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' ) ) {
						$job_args = [ [ 'list_id' => $list_id ] ];
						$group    = 'wlmi_csv_processing';

						if ( false === as_next_scheduled_action( 'wlmi_process_csv_errors', $job_args, $group ) ) {
							as_schedule_single_action( time(), 'wlmi_process_csv_errors', $job_args, $group );
							set_transient( $status_key, 'processing', HOUR_IN_SECONDS );
							$csv_status = 'processing';
						}
					} else {
						// Fallback to synchronous processing if Action Scheduler unavailable
						WC::setTimeLimit( 300 );
						$csv_path = self::processErrorsToCSV( $list_id, $settings );
						if ( $csv_path !== false ) {
							$csv_status = 'completed';
						} else {
							$csv_status = 'failed';
						}
					}
				}
			}
		}

		// Only expose error links when migration is fully completed.
		// This prevents premature display of raw error files while batches are still running.
		if ( $state !== 'completed' ) {
			$first_error_url = null;
		}

		$last_checked_at = Util::getCurrentTimeFormatted();

		return [
			'state'                 => $state,
			'total_operations'      => $total_operations,
			'finished_operations'   => $finished_operations,
			'success_operations'    => $success_operations,
			'errored_operations'    => $errored_operations,
			'batch_count'           => $batch_count,
			'has_any_pending'       => $default['has_any_pending'],
			'has_first_pending'     => $default['has_first_pending'],
			'first_error_file_url'  => $first_error_url,
			'failed_users_csv_path' => $csv_path,
			'csv_processing_status' => $csv_status,
			'last_checked_at'       => $last_checked_at,
		];
	}

	/**
	 * Download error tar.gz files from Mailchimp for batches with errors.
	 *
	 * @param string $list_id  The Mailchimp list ID.
	 * @param array  $settings Plugin settings including API credentials.
	 *
	 * @return array Array of local tar.gz file paths.
	 */
	public static function downloadErrorFiles( string $list_id, array $settings ): array {
		$downloaded_files = [];

		if ( empty( $list_id ) ) {
			return $downloaded_files;
		}

		// Get all batch IDs for this list
		$option_key = 'wlmi_migration_batches_' . $list_id;
		$batch_ids  = get_option( $option_key, [] );

		if ( empty( $batch_ids ) || ! is_array( $batch_ids ) ) {
			return $downloaded_files;
		}

		// Create log directory structure: wp-content/wlmi-migration-logs/{list_id}/{batch_id}/
		$log_base_dir = WP_CONTENT_DIR . '/wlmi-migration-logs/';
		wp_mkdir_p( $log_base_dir );

		foreach ( $batch_ids as $batch_id ) {
			$batch_id = (string) $batch_id;
			$status   = MailchimpHelper::getBatchStatus( $settings, $batch_id );

			if ( empty( $status ) ) {
				continue;
			}

			// Check if batch has errors
			$errored_operations = isset( $status->errored_operations ) ? (int) $status->errored_operations : 0;
			if ( $errored_operations > 0 && isset( $status->response_body_url ) && ! empty( $status->response_body_url ) ) {
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
				$saved     = FileHelper::putContent( $file_path, $file_content );

				if ( $saved === false ) {
					wc_get_logger()->add( 'wlmi',
						'Failed to save batch error file for batch_id ' . $batch_id . ' to ' . $file_path );
				} else {
					$downloaded_files[] = $file_path;
					wc_get_logger()->add( 'wlmi',
						'Downloaded batch error file for batch_id ' . $batch_id . ' (' . $errored_operations . ' errors) to ' . $file_path );
				}
			}
		}

		return $downloaded_files;
	}

	/**
	 * Process CSV errors in background (Action Scheduler callback).
	 *
	 * @param array $job_data Job data containing list_id.
	 *
	 * @return void
	 */
	public static function processCSVErrorsBackground( $job_data ) {
		if ( empty( $job_data ) || ! is_array( $job_data ) ) {
			return;
		}

		$list_id = isset( $job_data['list_id'] ) ? (string) $job_data['list_id'] : '';
		if ( empty( $list_id ) ) {
			return;
		}

		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			wc_get_logger()->add( 'wlmi', 'Mailchimp settings missing for CSV processing.' );
			$status_key = 'wlmi_csv_processing_status_' . $list_id;
			set_transient( $status_key, 'failed', HOUR_IN_SECONDS );

			return;
		}

		$status_key = 'wlmi_csv_processing_status_' . $list_id;
		set_transient( $status_key, 'processing', HOUR_IN_SECONDS * 2 );

		WC::setTimeLimit( 300 );
		$csv_path = self::processErrorsToCSV( $list_id, $settings );

		if ( $csv_path !== false ) {
			set_transient( $status_key, 'completed', HOUR_IN_SECONDS );
			wc_get_logger()->add( 'wlmi', 'CSV processing completed for list_id ' . $list_id . ' at ' . $csv_path );
		} else {
			set_transient( $status_key, 'failed', HOUR_IN_SECONDS );
			wc_get_logger()->add( 'wlmi', 'CSV processing failed for list_id ' . $list_id );
		}
	}

	/**
	 * Process downloaded error files and generate CSV.
	 *
	 * @param string $list_id  The Mailchimp list ID.
	 * @param array  $settings Plugin settings including API credentials.
	 *
	 * @return string|false CSV file path on success, false on failure.
	 */
	public static function processErrorsToCSV( string $list_id, array $settings ) {
		if ( empty( $list_id ) ) {
			return false;
		}

		// Get downloaded tar.gz files
		$tar_gz_files = self::downloadErrorFiles( $list_id, $settings );

		if ( empty( $tar_gz_files ) ) {
			return false;
		}

		$log_base_dir = WP_CONTENT_DIR . '/wlmi-migration-logs/';
		$csv_path     = $log_base_dir . $list_id . '/failed-users.csv';

		// Ensure directory exists
		wp_mkdir_p( dirname( $csv_path ) );

		$errors = []; // Associative array keyed by email for deduplication

		// Process each tar.gz file
		foreach ( $tar_gz_files as $tar_gz_path ) {
			if ( ! FileHelper::exists( $tar_gz_path ) ) {
				continue;
			}

			// Extract tar.gz: use splitbrain/php-archive library
			$extract_dir = dirname( $tar_gz_path ) . DIRECTORY_SEPARATOR . 'extracted_' . basename( $tar_gz_path, '.tar.gz' );
			$extracted   = false;

			try {
				// Ensure the tar.gz file exists and is readable
				if ( ! FileHelper::exists( $tar_gz_path ) || ! FileHelper::isReadable( $tar_gz_path ) ) {
					throw new \Exception( 'Tar.gz file does not exist or is not readable' );
				}

				// Clean up extract directory if it exists (handle dirty state from previous runs)
				self::cleanupDirectory( $extract_dir );

				// Create fresh extract directory
				if ( ! wp_mkdir_p( $extract_dir ) ) {
					throw new \Exception( 'Failed to create extraction directory' );
				}

				// Get real path to handle spaces and symlinks properly
				$real_tar_gz_path = realpath( $tar_gz_path );
				if ( $real_tar_gz_path === false ) {
					throw new \Exception( 'Cannot resolve real path for tar.gz file' );
				}

				// Use splitbrain/php-archive to extract tar.gz directly (handles gzip automatically)
				$tar = new Tar();
				$tar->open( $real_tar_gz_path );

				// Extract to directory (library handles gzip decompression automatically)
				$extracted_files = $tar->extract( $extract_dir );
				$tar->close();

				$extracted = true;
			} catch ( \Exception $e ) {
				// Clean up failed extraction directory
				self::cleanupDirectory( $extract_dir );
				continue;
			}

			if ( ! $extracted ) {
				continue;
			}

			// Find all JSON files in extracted directory (top-level and in subdirs when extractTo preserved structure)
			$json_files = [];
			if ( FileHelper::isDir( $extract_dir ) ) {
				$dir_iter  = new \RecursiveDirectoryIterator( $extract_dir, \RecursiveDirectoryIterator::SKIP_DOTS );
				$flat_iter = new \RecursiveIteratorIterator( $dir_iter );
				foreach ( $flat_iter as $file ) {
					if ( $file->isFile() && strtolower( $file->getExtension() ) === 'json' ) {
						$json_files[] = $file->getPathname();
					}
				}
			}

			foreach ( $json_files as $json_file ) {
				if ( ! FileHelper::exists( $json_file ) || ! FileHelper::isReadable( $json_file ) ) {
					continue;
				}

				$json_content = FileHelper::getContent( $json_file );
				if ( empty( $json_content ) ) {
					continue;
				}

				$operations = json_decode( $json_content, true );

				if ( ! is_array( $operations ) ) {
					continue;
				}

				// Mailchimp may return a single operation object per file; normalize to array of operations
				if ( isset( $operations['status_code'] ) || isset( $operations['operation_id'] ) ) {
					$operations = [ $operations ];
				}

				// Process each operation
				foreach ( $operations as $operation ) {
					if ( ! is_array( $operation ) ) {
						continue;
					}

					$status_code = isset( $operation['status_code'] ) ? (int) $operation['status_code'] : 0;

					// Only process errors (status_code 400)
					if ( $status_code !== 400 ) {
						continue;
					}

					$email = isset( $operation['operation_id'] ) ? (string) $operation['operation_id'] : '';
					if ( empty( $email ) ) {
						continue;
					}

					// Extract reason from response
					$reason = 'Unknown error';
					if ( isset( $operation['response'] ) && ! empty( $operation['response'] ) ) {
						$response_obj = json_decode( $operation['response'], true );
						if ( is_array( $response_obj ) && isset( $response_obj['detail'] ) ) {
							$reason = (string) $response_obj['detail'];
						}
					}

					// Deduplicate by email (keep first occurrence)
					if ( ! isset( $errors[ $email ] ) ) {
						$errors[ $email ] = $reason;
					}
				}
			}

			// Clean up extracted directory after processing (remove temporary files)
			self::cleanupDirectory( $extract_dir );
		}

		// If no errors found, return false
		if ( empty( $errors ) ) {
			return false;
		}

		// Generate CSV using ParseCsv library
		if ( ! class_exists( '\ParseCsv\Csv' ) ) {
			return false;
		}

		try {
			$csv = new \ParseCsv\Csv();
			$csv->titles = [ 'email_address', 'reason' ];

			// Convert errors array to CSV rows format
			$csv_rows = [];
			foreach ( $errors as $email => $reason ) {
				$csv_rows[] = [
					'email_address' => $email,
					'reason'        => $reason,
				];
			}

			// Save CSV file (false = overwrite, single write)
			$saved = $csv->save( $csv_path, $csv_rows, false );

			if ( $saved ) {
				return $csv_path;
			} else {
				return false;
			}
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Recursively remove a directory and all its contents.
	 * Handles dirty state cleanup before extraction and post-extraction cleanup.
	 *
	 * @param string $dir Directory path to remove.
	 *
	 * @return void
	 */
	private static function cleanupDirectory( string $dir ): void {
		if ( ! FileHelper::exists( $dir ) ) {
			return;
		}

		try {
			FileHelper::delete( $dir, true );
		} catch ( \Exception $e ) {
			wc_get_logger()->add( 'wlmi', 'Failed to clean up directory ' . $dir . ': ' . $e->getMessage() );
		}
	}
}
