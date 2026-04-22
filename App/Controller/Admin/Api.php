<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Mailchimp as MailchimpHelper;
use WLMI\App\Helper\Input;
use WLMI\App\Helper\Util;
use WLMI\App\Helper\WC;
use WLMI\App\Helper\Settings as SettingsHelper;
use WLMI\App\Helper\File as FileHelper;
use WLMI\App\Controller\MigrationBatch;
use WLMI\App\Controller\Sync;

defined( 'ABSPATH' ) or die;

class Api {
	/**
	 * Clean up migration data and scheduled actions.
	 *
	 * @param string|null $list_id Optional list ID to clean up specific migration data.
	 *
	 * @return void
	 */
	public static function cleanupMigrationData( ?string $list_id = null ): void {
		if ( ! empty( $list_id ) ) {
			self::cleanupMigrationDataForList( $list_id );
		} else {
			$settings = SettingsHelper::gets();
			if ( ! empty( $settings['list_id'] ) ) {
				self::cleanupMigrationDataForList( $settings['list_id'] );
			}
		}

		self::unscheduleAllMigrationActions();
		self::unscheduleAllSyncActions();
	}

	/**
	 * Clean up migration data for a specific list.
	 *
	 * @param string $list_id
	 *
	 * @return void
	 */
	protected static function cleanupMigrationDataForList( string $list_id ): void {
		delete_option( 'wlmi_migration_batches_' . $list_id );
		delete_option( 'wlmi_migration_stats_' . $list_id );
		delete_option( 'wlmi_migration_running_' . $list_id );
		delete_option( 'wlmi_rate_bucket_' . $list_id );
		delete_option( 'wlmi_sync_after_migration_' . $list_id );
		delete_transient( 'wlmi_scheduling_lock_' . $list_id );

		$log_base_dir = WP_CONTENT_DIR . '/wlmi-migration-logs/';
		$csv_path     = $log_base_dir . $list_id . '/failed-users.csv';
		if ( FileHelper::exists( $csv_path ) ) {
			FileHelper::delete( $csv_path );
		}
	}

	/**
	 * Unschedule all migration batch actions.
	 *
	 * @return void
	 */
	protected static function unscheduleAllMigrationActions(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( MigrationBatch::MIGRATION_ACTION_HOOK );
		as_unschedule_all_actions( MigrationBatch::BATCH_CHECK_HOOK );
	}

	/**
	 * Unschedule all sync actions.
	 *
	 * @return void
	 */
	protected static function unscheduleAllSyncActions(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( Sync::SYNC_ACTION_HOOK );
	}

	/**
	 * Connect and persist Mailchimp API settings.
	 *
	 * @return void
	 */
	public static function connectMailchimp() {
		if ( ! WC::isSecurityValid( 'wlmi_admin_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$api_key = trim( (string) Input::get( 'api_key' ) );
		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'API Key is required', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$dash_pos = strpos( $api_key, '-' );
		if ( $dash_pos === false || $dash_pos === strlen( $api_key ) - 1 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid API key format', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$server = substr( $api_key, $dash_pos + 1 );

		$is_connected = MailchimpHelper::checkConnection( $api_key, $server );

		if ( ! $is_connected ) {
			wp_send_json_error( [ 'message' => __( 'Connection failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$settings            = SettingsHelper::gets();
		$settings['api_key'] = $api_key;
		$settings['server']  = $server;
		update_option( 'wlmi_settings', $settings );

		SettingsHelper::clearCache();
		MailchimpHelper::clearConnectionCache();

		wp_send_json_success( [ 'message' => __( 'Connected successfully!', 'wp-loyalty-mailchimp-integration' ) ] );
	}

	/**
	 * Disconnect Mailchimp and clear integration settings.
	 *
	 * @return void
	 */
	public static function disconnectMailchimp() {
		if ( ! WC::isSecurityValid( 'wlmi_admin_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$settings                                = SettingsHelper::gets();
		$settings['api_key']                     = '';
		$settings['server']                      = '';
		$settings['list_id']                     = '';
		$settings['migration_choice']            = '';
		update_option( 'wlmi_settings', $settings );

		self::cleanupMigrationData();

		SettingsHelper::clearCache();
		MailchimpHelper::clearConnectionCache();

		wp_send_json_success( [ 'message' => __( 'Disconnected successfully!', 'wp-loyalty-mailchimp-integration' ) ] );
	}

	/**
	 * Get Mailchimp lists with pagination and server-side search
	 *
	 * @return void
	 */
	public static function getLists() {
		if ( ! WC::isSecurityValid( 'wlmi_admin_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$offset       = (int) Input::get( 'offset', 0 );
		$count        = (int) Input::get( 'count', 100 );
		$search_term  = trim( Input::get( 'search_term', '' ) );
		$max_batches  = 3;
		$batch_size   = 100;

		$settings = SettingsHelper::gets();

		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			wp_send_json_error( [ 'message' => __( 'API connection not configured', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		try {
			$results       = [];
			$current_offset = $offset;
			$total_items    = 0;
			$batches_fetched = 0;
			$has_search     = ! empty( $search_term );

			//fetch multiple batches if searching and no matches found
			while ( $batches_fetched < $max_batches ) {
				WC::setTimeLimit( 30 );

				$response = MailchimpHelper::getListBatch( $settings, $batch_size, $current_offset );
				if ( empty( $response ) ) {
					break;
				}

				if ( ! isset( $response->lists ) || ! is_array( $response->lists ) ) {
					break;
				}

				$total_items = isset( $response->total_items ) ? (int) $response->total_items : 0;
				$batch_lists = $response->lists;

				foreach ( $batch_lists as $list ) {
					$list_data = [
						'value' => $list->id,
						'label' => $list->name,
						'stats' => isset( $list->stats ) ? $list->stats : null
					];

					if ( $has_search ) {
						if ( stripos( $list->name, $search_term ) !== false || stripos( $list->id, $search_term ) !== false ) {
							$results[] = $list_data;
						}
					} else {
						$results[] = $list_data;
					}
				}

				$batches_fetched++;
				$current_offset += count( $batch_lists );

				// Stop conditions:
				// 1. If we have results (matches found)
				// 2. If we've reached the end of all lists
				// 3. If not searching (only fetch one batch)
				if ( count( $results ) > 0 || $current_offset >= $total_items || ! $has_search ) {
					break;
				}
			}

			$next_offset = $current_offset;
			$has_more    = $next_offset < $total_items;

			// Limit results to the requested count
			if ( count( $results ) > $count ) {
				$results = array_slice( $results, 0, $count );
			}

			wp_send_json_success( [
				'results'      => $results,
				'next_offset'  => $next_offset,
				'total_items'  => $total_items,
				'has_more'     => $has_more,
				'search_term'  => $search_term,
				'batches_fetched' => $batches_fetched
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => __( 'Failed to fetch lists', 'wp-loyalty-mailchimp-integration' ) . ': ' . $e->getMessage() ] );
		}
	}

	/**
	 * Get consolidated migration status for the current list.
	 *
	 * @return void
	 */
	public static function getMigrationStatus() {
		if ( ! WC::isSecurityValid( 'wlmi_admin_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$settings = SettingsHelper::gets();
		$list_id  = isset( $settings['list_id'] ) ? (string) $settings['list_id'] : '';

		if ( empty( $list_id ) ) {
			$last_checked_at = Util::getCurrentTimeFormatted();

			wp_send_json_success( [
				'state'                 => 'no_list',
				'total_operations'      => 0,
				'finished_operations'   => 0,
				'success_operations'    => 0,
				'errored_operations'    => 0,
				'batch_count'           => 0,
				'has_any_pending'       => false,
				'has_first_pending'     => false,
				'first_error_file_url'  => null,
				'failed_users_csv_path' => null,
				'csv_processing_status' => 'not_started',
				'last_checked_at'       => $last_checked_at,
			] );

			return;
		}

		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			wp_send_json_error( [ 'message' => __( 'API connection not configured', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		try {
			$status = MigrationBatch::getConsolidatedStatus( $list_id, $settings );
			wp_send_json_success( $status );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => __( 'Failed to fetch migration status', 'wp-loyalty-mailchimp-integration' ) . ': ' . $e->getMessage() ] );
		}
	}

	/**
	 * Download failed users CSV file.
	 *
	 * @return void
	 */
	public static function downloadFailedUsersCSV() {
		if ( ! WC::isSecurityValid( 'wlmi_admin_settings' ) ) {
			wp_die( esc_html__( 'Security check failed', 'wp-loyalty-mailchimp-integration' ) );
		}

		$settings = SettingsHelper::gets();
		$list_id  = isset( $settings['list_id'] ) ? (string) $settings['list_id'] : '';

		if ( empty( $list_id ) ) {
			wp_die( esc_html__( 'No list configured', 'wp-loyalty-mailchimp-integration' ) );
		}

		// Build expected CSV path
		$log_base_dir = WP_CONTENT_DIR . '/wlmi-migration-logs/';
		$csv_path     = $log_base_dir . $list_id . '/failed-users.csv';

		// Security: Validate path is within expected directory (prevent directory traversal)
		$real_csv_path     = realpath( $csv_path );
		$real_log_base_dir = realpath( $log_base_dir );

		if ( $real_csv_path === false || $real_log_base_dir === false ) {
			wp_die( esc_html__( 'CSV file not found', 'wp-loyalty-mailchimp-integration' ) );
		}

		if ( strpos( $real_csv_path, $real_log_base_dir ) !== 0 ) {
			wp_die( esc_html__( 'Invalid file path', 'wp-loyalty-mailchimp-integration' ) );
		}

		// Check file exists and is readable
		if ( ! FileHelper::exists( $csv_path ) || ! FileHelper::isReadable( $csv_path ) ) {
			wp_die( esc_html__( 'CSV file not found or not readable', 'wp-loyalty-mailchimp-integration' ) );
		}

		// Serve file with proper headers
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="failed-users-' . esc_attr( $list_id ) . '.csv"' );
		header( 'Content-Length: ' . filesize( $csv_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$content = FileHelper::getContent( $csv_path );
		if ( $content !== false ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $content;
		}
		exit;
	}

	/**
	 * Manually trigger a synchronization run for the current list.
	 *
	 * If a sync is already running, it sets a flag to start a fresh run
	 * immediately after the current chained migration finishes.
	 *
	 * @return void
	 */
	public static function performSync() {
		if ( ! WC::isSecurityValid( 'wlmi_admin_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$settings = SettingsHelper::gets();
		$list_id  = isset( $settings['list_id'] ) ? (string) $settings['list_id'] : '';

		if ( empty( $list_id ) ) {
			wp_send_json_error( [ 'message' => __( 'No list configured for synchronization', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		try {
			// Determine if a sync is already running
			$status = MigrationBatch::getConsolidatedStatus( $list_id, $settings );

			if ( $status['has_first_pending'] ) {
				// A fresh sync run starting from ID 0 is already queued or running.
				wp_send_json_success( [
					'message' => __( 'A synchronization run is already in progress and will continue processing all users batch by batch.', 'wp-loyalty-mailchimp-integration' ),
					'queued'  => false,
				] );
			} elseif ( $status['has_any_pending'] || $status['state'] === 'in_progress' ) {
				// A chained migration batch is already in progress, so queue a fresh run after it completes.
				update_option( 'wlmi_sync_after_migration_' . $list_id, 1 );

				wp_send_json_success( [
					'message' => __( 'A new synchronization run has been queued. It will start after the current batch chain finishes.', 'wp-loyalty-mailchimp-integration' ),
					'queued'  => true,
				] );
			} else {
				// No sync is running — startMigrationRun (inside scheduleBatchesForList) resets
				// stats and deletes old CSV automatically.
				$result = MigrationBatch::scheduleBatchesForList( $list_id, $settings );
				$message = __( 'Synchronization run started successfully. The first batch has been queued.', 'wp-loyalty-mailchimp-integration' );
				$queued  = false;

				if ( $result === 'already_pending' || $result === 'locked' ) {
					$message = __( 'A synchronization run is already being started. Please wait for the current batch chain to continue.', 'wp-loyalty-mailchimp-integration' );
				} elseif ( $result === 'unavailable' ) {
					$message = __( 'Synchronization could not be started because Action Scheduler is unavailable.', 'wp-loyalty-mailchimp-integration' );
				}

				wp_send_json_success( [
					'message' => $message,
					'queued'  => $queued,
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => __( 'Failed to trigger synchronization', 'wp-loyalty-mailchimp-integration' ) . ': ' . $e->getMessage() ] );
		}
	}

}
