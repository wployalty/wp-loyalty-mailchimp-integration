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
	 * Base directory inside plugin for generated files (mirrors wp-loyalty-rules App/File).
	 */
	const PLUGIN_FILE_DIR = 'App/File/';

	/**
	 * Sanitize a path segment to avoid traversal/invalid characters.
	 *
	 * @param string $segment
	 *
	 * @return string
	 */
	protected static function sanitizePathSegment( string $segment ): string {
		$segment = trim( $segment );
		if ( $segment === '' ) {
			return '';
		}

		return preg_replace( '/[^a-zA-Z0-9_\-]/', '', $segment ) ?? '';
	}

	/**
	 * Get plugin file base dir.
	 *
	 * @return string
	 */
	protected static function getPluginFileBaseDir(): string {
		if ( defined( 'WLMI_PLUGIN_PATH' ) ) {
			return rtrim( (string) WLMI_PLUGIN_PATH, '/' ) . '/' . self::PLUGIN_FILE_DIR;
		}

		return WP_CONTENT_DIR . '/plugins/wp-loyalty-mailchimp-integration/' . self::PLUGIN_FILE_DIR;
	}

	/**
	 * Get per-list directory inside plugin.
	 *
	 * @param string $list_id
	 *
	 * @return string
	 */
	protected static function getListDir( string $list_id ): string {
		$safe_list_id = self::sanitizePathSegment( $list_id );

		return self::getPluginFileBaseDir() . $safe_list_id . '/';
	}

	/**
	 * Get failed users CSV absolute path for a list.
	 *
	 * @param string $list_id
	 *
	 * @return string
	 */
	protected static function getFailedUsersCsvPath( string $list_id ): string {
		return self::getListDir( $list_id ) . 'failed-users.csv';
	}

	/**
	 * Get failed users CSV public URL for a list.
	 *
	 * @param string $list_id
	 *
	 * @return string
	 */
	protected static function getFailedUsersCsvUrl( string $list_id ): string {
		$safe_list_id = self::sanitizePathSegment( $list_id );
		if ( $safe_list_id === '' ) {
			return '';
		}
		if ( ! defined( 'WLMI_PLUGIN_URL' ) ) {
			return '';
		}

		return rtrim( (string) WLMI_PLUGIN_URL, '/' ) . '/' . self::PLUGIN_FILE_DIR . $safe_list_id . '/failed-users.csv';
	}

	/**
	 * Get batch log directory inside plugin.
	 *
	 * @param string $list_id
	 * @param string $batch_id
	 *
	 * @return string
	 */
	protected static function getBatchLogDir( string $list_id, string $batch_id ): string {
		$safe_batch_id = self::sanitizePathSegment( $batch_id );

		return self::getListDir( $list_id ) . 'batches/' . $safe_batch_id . '/';
	}

	/**
	 * Best-effort cleanup for empty parent directories after deleting a batch dir.
	 *
	 * @param string $list_id
	 *
	 * @return void
	 */
	protected static function cleanupEmptyBatchParents( string $list_id ): void {
		$list_dir   = self::getListDir( $list_id );
		$batches_dir = $list_dir . 'batches/';

		// Remove batches/ if empty
		if ( is_dir( $batches_dir ) ) {
			$files = @scandir( $batches_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $files ) ) {
				$entries = array_values( array_diff( $files, [ '.', '..' ] ) );
				if ( empty( $entries ) ) {
					FileHelper::delete( $batches_dir, true );
				}
			}
		}

		// Remove list folder if it contains no files
		if ( is_dir( $list_dir ) ) {
			$files = @scandir( $list_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $files ) ) {
				$entries = array_values( array_diff( $files, [ '.', '..' ] ) );
				if ( empty( $entries ) ) {
					FileHelper::delete( $list_dir, true );
				}
			}
		}
	}
	/**
	 * Transient key prefix used to serialize migration run starts per list.
	 */
	const MIGRATION_SCHEDULING_LOCK_PREFIX = 'wlmi_scheduling_lock_';

	/**
	 * Option key prefix used to track migration run state per list.
	 */
	const MIGRATION_RUN_FLAG_PREFIX = 'wlmi_migration_running_';

	/**
	 * Action Scheduler hook used for Mailchimp migration batches.
	 */
	const MIGRATION_ACTION_HOOK = 'wlmi_process_mailchimp_migration_batch';

	/**
	 * Action Scheduler group used for Mailchimp migration batches.
	 */
	const MIGRATION_ACTION_GROUP = 'wlmi_migration_queue';

	/**
	 * Action Scheduler hook for checking a single Mailchimp batch result.
	 */
	const BATCH_CHECK_HOOK = 'wlmi_check_batch_result';

	/**
	 * Action Scheduler group for batch result checking.
	 */
	const BATCH_CHECK_GROUP = 'wlmi_batch_check';

	/**
	 * Option key prefix for accumulated migration stats per list.
	 */
	const STATS_OPTION_PREFIX = 'wlmi_migration_stats_';

	/**
	 * Get pending migration state for a list using flag-based tracking.
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

		$flag = get_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id );

		if ( ! empty( $flag ) && is_array( $flag ) ) {
			$state['has_any_batch_pending']   = true;
			$state['has_first_batch_pending'] = ! empty( $flag['first_batch_scheduled'] );
		}

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
		$flag = get_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id );
		return ! empty( $flag['first_batch_scheduled'] );
	}

	/**
	 * Check if there are any pending migration batches for a given list.
	 *
	 * @param   string  $list_id
	 *
	 * @return bool
	 */
	protected static function hasPendingMigrationBatches( string $list_id ): bool {
		return get_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id ) !== false;
	}

	/**
	 * Schedule the next migration batch with pacing delay and jitter.
	 *
	 * @param string $list_id
	 * @param int    $start_id
	 * @param int    $extra_delay Additional delay in seconds (used for throttle back-off).
	 *
	 * @return bool
	 */
	protected static function scheduleNextBatch( string $list_id, int $start_id, int $extra_delay = 0 ): bool {
		if ( empty( $list_id ) ) {
			return false;
		}
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return false;
		}

		$job_args = [ [
			'start_id' => $start_id,
			'list_id'  => $list_id,
		] ];

		if ( false !== as_next_scheduled_action( self::MIGRATION_ACTION_HOOK, $job_args, self::MIGRATION_ACTION_GROUP ) ) {
			return false;
		}

		$base_delay = (int) apply_filters( 'wlmi_batch_pacing_delay', 15 );
		$jitter     = wp_rand( 0, 10 );
		$delay      = max( 0, $base_delay + $jitter + $extra_delay );

		as_schedule_single_action( time() + $delay, self::MIGRATION_ACTION_HOOK, $job_args, self::MIGRATION_ACTION_GROUP );

		return true;
	}

	/**
	 * Start a fresh migration run by clearing stored state and queueing the first batch.
	 *
	 * @param string $list_id
	 *
	 * @return void
	 */
	protected static function startMigrationRun( string $list_id ): void {
		update_option( 'wlmi_migration_batches_' . $list_id, [] );
		update_option( self::STATS_OPTION_PREFIX . $list_id, self::getDefaultStats() );
		delete_option( 'wlmi_rate_bucket_' . $list_id );
		update_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id, [
			'first_batch_scheduled' => true,
			'started_at'            => time(),
		] );

		$base_dir = self::getPluginFileBaseDir();
		FileHelper::ensureDir( $base_dir );

		$list_dir = self::getListDir( $list_id );
		FileHelper::ensureDir( $list_dir );

		$csv_path = self::getFailedUsersCsvPath( $list_id );
		if ( FileHelper::exists( $csv_path ) ) {
			FileHelper::deleteWithPerms( $csv_path );
		}

		self::scheduleNextBatch( $list_id, 0 );
	}

	/**
	 * Get default stats structure.
	 *
	 * @return array
	 */
	protected static function getDefaultStats(): array {
		return [
			'total_operations'    => 0,
			'finished_operations' => 0,
			'errored_operations'  => 0,
			'batches_submitted'   => 0,
			'batches_completed'   => 0,
			'batches_checking'    => 0,
			'has_errors'          => false,
			'last_updated_at'     => 0,
		];
	}

	/**
	 * Atomically increment accumulated stats for a list after a batch finishes.
	 *
	 * @param string $list_id
	 * @param int    $total
	 * @param int    $finished
	 * @param int    $errored
	 *
	 * @return void
	 */
	protected static function accumulateStats( string $list_id, int $total, int $finished, int $errored ): void {
		$stats = get_option( self::STATS_OPTION_PREFIX . $list_id, self::getDefaultStats() );
		if ( ! is_array( $stats ) ) {
			$stats = self::getDefaultStats();
		}

		$stats['total_operations']    += $total;
		$stats['finished_operations'] += $finished;
		$stats['errored_operations']  += $errored;
		$stats['batches_completed']   ++;
		$stats['batches_checking']    = max( 0, (int) ( $stats['batches_checking'] ?? 0 ) - 1 );
		$stats['last_updated_at']     = time();
		if ( $errored > 0 ) {
			$stats['has_errors'] = true;
		}

		update_option( self::STATS_OPTION_PREFIX . $list_id, $stats );
	}

	/**
	 * Record that a new batch has been submitted to Mailchimp (increment counters).
	 *
	 * @param string $list_id
	 *
	 * @return void
	 */
	protected static function recordBatchSubmitted( string $list_id ): void {
		$stats = get_option( self::STATS_OPTION_PREFIX . $list_id, self::getDefaultStats() );
		if ( ! is_array( $stats ) ) {
			$stats = self::getDefaultStats();
		}

		$stats['batches_submitted'] ++;
		$stats['batches_checking']  = ( $stats['batches_checking'] ?? 0 ) + 1;

		update_option( self::STATS_OPTION_PREFIX . $list_id, $stats );
	}

	/**
	 * Build the transient key used to lock migration run starts for a list.
	 *
	 * @param string $list_id
	 *
	 * @return string
	 */
	protected static function getSchedulingLockKey( string $list_id ): string {
		return self::MIGRATION_SCHEDULING_LOCK_PREFIX . $list_id;
	}

	/**
	 * Acquire the transient lock used to serialize migration run starts.
	 *
	 * @param string $list_id
	 *
	 * @return bool
	 */
	protected static function acquireSchedulingLock( string $list_id ): bool {
		$lock_key = self::getSchedulingLockKey( $list_id );
		if ( get_transient( $lock_key ) ) {
			return false;
		}

		return set_transient( $lock_key, 1, 300 );
	}

	/**
	 * Release the transient lock used to serialize migration run starts.
	 *
	 * @param string $list_id
	 *
	 * @return void
	 */
	protected static function releaseSchedulingLock( string $list_id ): void {
		delete_transient( self::getSchedulingLockKey( $list_id ) );
	}

	/**
	 * Try to consume one rate-limit token for batch submission.
	 *
	 * Uses a per-list token bucket stored in an option. Tokens refill continuously
	 * at the configured rate (default 5 per 60 seconds). Returns true if a token
	 * was consumed, false if the bucket is empty (caller should back off).
	 *
	 * @param string $list_id
	 *
	 * @return bool True if a token was consumed and submission may proceed.
	 */
	protected static function consumeRateToken( string $list_id ): bool {
		$capacity    = (int) apply_filters( 'wlmi_batch_rate_limit', 5 );
		$refill_rate = $capacity / 60.0;
		$option_key  = 'wlmi_rate_bucket_' . $list_id;
		$bucket      = get_option( $option_key );

		if ( ! is_array( $bucket ) || ! isset( $bucket['tokens'], $bucket['last_refill'] ) ) {
			$bucket = [
				'tokens'      => max( 0, $capacity - 1 ),
				'last_refill' => microtime( true ),
			];
			update_option( $option_key, $bucket, false );

			return true;
		}

		$now     = microtime( true );
		$elapsed = max( 0, $now - (float) $bucket['last_refill'] );
		$tokens  = min( (float) $capacity, (float) $bucket['tokens'] + $elapsed * $refill_rate );

		if ( $tokens < 1.0 ) {
			return false;
		}

		$bucket['tokens']      = $tokens - 1.0;
		$bucket['last_refill'] = $now;
		update_option( $option_key, $bucket, false );

		return true;
	}

	/**
	 * Check whether the number of in-flight batch checks exceeds the cap.
	 *
	 * @param string $list_id
	 *
	 * @return bool True if the cap is reached and submission should wait.
	 */
	protected static function isInFlightCapReached( string $list_id ): bool {
		$max_in_flight = (int) apply_filters( 'wlmi_max_in_flight_batches', 5 );
		$stats         = get_option( self::STATS_OPTION_PREFIX . $list_id, self::getDefaultStats() );

		if ( ! is_array( $stats ) ) {
			return false;
		}

		return ( (int) ( $stats['batches_checking'] ?? 0 ) ) >= $max_in_flight;
	}

	/**
	 * Decrement the batches_checking counter when a batch check is abandoned.
	 *
	 * @param string $list_id
	 *
	 * @return void
	 */
	protected static function markBatchAbandoned( string $list_id ): void {
		$stats = get_option( self::STATS_OPTION_PREFIX . $list_id, self::getDefaultStats() );
		if ( ! is_array( $stats ) ) {
			$stats = self::getDefaultStats();
		}

		$stats['batches_checking'] = max( 0, (int) ( $stats['batches_checking'] ?? 0 ) - 1 );
		$stats['last_updated_at']  = time();

		update_option( self::STATS_OPTION_PREFIX . $list_id, $stats );
	}

	/**
	 * Complete a migration run and trigger a queued follow-up sync if requested.
	 *
	 * @param string $list_id
	 * @param array  $settings
	 *
	 * @return void
	 */
	protected static function completeMigrationRun( string $list_id, array $settings ): void {
		$flag_key = 'wlmi_sync_after_migration_' . $list_id;
		if ( ! get_option( $flag_key ) ) {
			return;
		}

		$result = self::scheduleBatchesForList( $list_id, $settings );
		if ( in_array( $result, [ 'scheduled', 'already_pending' ], true ) ) {
			delete_option( $flag_key );
		}
	}

	/**
	 * Start a migration run for a given list by scheduling the first batch only.
	 *
	 * @param   string  $list_id
	 * @param   array   $settings
	 *
	 * @return string One of: scheduled, already_pending, locked, unavailable, invalid
	 */
	public static function scheduleBatchesForList( string $list_id, array $settings ): string {
		if ( empty( $list_id ) ) {
			return 'invalid';
		}
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return 'unavailable';
		}
		if ( ! self::acquireSchedulingLock( $list_id ) ) {
			return 'locked';
		}

		try {
			if ( self::isFirstBatchPending( $list_id ) ) {
				return 'already_pending';
			}

			self::startMigrationRun( $list_id );

			return 'scheduled';
		} finally {
			self::releaseSchedulingLock( $list_id );
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
		WC::setTimeLimit( 120 );
		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			return;
		}

		$limit          = apply_filters( 'wlmi_default_operations_per_batch_size', 500 );
		$include_banned = (bool) apply_filters( 'wlmi_migration_include_banned_users', false );
		$include_unsub  = (bool) apply_filters( 'wlmi_migration_include_unsubscribed_users', false );
		$points_column  = SettingsHelper::getPointsSyncColumn();

		$base_url   = site_url() . '?wlr_ref=';
		$operations = [];
		$last_id    = 0;
		$count      = 0;

		foreach ( self::streamUsers( $start_id, $limit, $include_banned, $include_unsub ) as $user ) {
			$last_id    = isset( $user->id ) ? (int) $user->id : $last_id;
			$user_email = isset( $user->user_email ) ? sanitize_email( $user->user_email ) : '';
			if ( empty( $user_email ) ) {
				continue;
			}

			$ref_code = isset( $user->refer_code ) ? (string) $user->refer_code : '';
			$ref_url  = ! empty( $ref_code ) ? $base_url . $ref_code : '';
			$points   = isset( $user->{$points_column} ) ? (int) $user->{$points_column} : 0;

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
			$count++;
		}

		if ( empty( $operations ) ) {
			delete_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id );
			self::completeMigrationRun( $list_id, $settings );
			return;
		}

		if ( self::isInFlightCapReached( $list_id ) || ! self::consumeRateToken( $list_id ) ) {
			self::scheduleNextBatch( $list_id, $start_id, 30 );
			return;
		}

		$response = MailchimpHelper::startBatch( $settings, $operations );
		if ( empty( $response ) ) {
			return;
		}

		$batch_id = isset( $response->id ) ? (string) $response->id : '';

		if ( ! empty( $batch_id ) ) {
			$option_key = 'wlmi_migration_batches_' . $list_id;
			$batch_ids  = get_option( $option_key, [] );
			if ( ! in_array( $batch_id, $batch_ids, true ) ) {
				$batch_ids[] = $batch_id;
				update_option( $option_key, $batch_ids );
			}

			self::recordBatchSubmitted( $list_id );
			self::scheduleBatchCheck( $list_id, $batch_id, 0 );
		}

		$has_more = $count >= $limit && $last_id > 0;
		if ( $has_more ) {
			self::scheduleNextBatch( $list_id, $last_id );
		} else {
			$flag = get_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id );
			if ( is_array( $flag ) ) {
				$flag['first_batch_scheduled'] = false;
				$flag['all_batches_submitted'] = true;
				update_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id, $flag );
			}
		}
	}

	/**
	 * Generator that yields user rows one at a time using a cursor-based approach,
	 * so the full result set is never held in memory simultaneously.
	 *
	 * @param int  $start_id
	 * @param int  $limit
	 * @param bool $include_banned
	 * @param bool $include_unsub
	 *
	 * @return \Generator
	 */
	private static function streamUsers(
		int $start_id,
		int $limit,
		bool $include_banned,
		bool $include_unsub
	): \Generator {
		global $wpdb;

		$user_model  = new Users();
		$table       = $user_model->getTableName();
		$chunk_size  = apply_filters( 'wlmi_stream_chunk_size', 100 ); // rows fetched per DB round-trip
		$fetched     = 0;
		$cursor_id   = $start_id;

		while ( $fetched < $limit ) {
			$remaining   = $limit - $fetched;
			$current_chunk = min( $chunk_size, $remaining );

			$where_parts = [ $wpdb->prepare( 'id > %d', $cursor_id ) ];
			if ( ! $include_unsub ) {
				$where_parts[] = $wpdb->prepare( 'is_allow_send_email = %d', 1 );
			}
			if ( ! $include_banned ) {
				$where_parts[] = "(is_banned_user IS NULL OR is_banned_user = 0 OR is_banned_user = '')";
			}

			$where = implode( ' AND ', $where_parts );
			$where .= ' ORDER BY id ASC';
			$where .= $wpdb->prepare( ' LIMIT %d', $current_chunk );

			$query = "SELECT id, user_email, refer_code, " . SettingsHelper::getPointsSyncColumn() . " FROM {$table} WHERE {$where}";
			$rows  = $user_model->rawQuery( $query, false );

			if ( empty( $rows ) ) {
				return; // No more rows — stop the generator.
			}

			foreach ( $rows as $row ) {
				yield $row;
				$cursor_id = isset( $row->id ) ? (int) $row->id : $cursor_id;
				$fetched++;
			}

			// If we got fewer rows than requested, we've exhausted the table.
			if ( count( $rows ) < $current_chunk ) {
				return;
			}
		}
	}

	/**
	 * Schedule a batch result check with exponential backoff.
	 *
	 * @param string $list_id
	 * @param string $batch_id
	 * @param int    $attempt
	 *
	 * @return void
	 */
	protected static function scheduleBatchCheck( string $list_id, string $batch_id, int $attempt ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$delay    = min( 60 * pow( 2, $attempt ), 600 );
		$job_args = [ [
			'list_id'  => $list_id,
			'batch_id' => $batch_id,
			'attempt'  => $attempt,
		] ];

		as_schedule_single_action( time() + $delay, self::BATCH_CHECK_HOOK, $job_args, self::BATCH_CHECK_GROUP );
	}

	/**
	 * Action Scheduler callback: check one Mailchimp batch result,
	 * accumulate stats, and process errors incrementally.
	 *
	 * @param array $job_data
	 *
	 * @return void
	 */
	public static function checkBatchResult( $job_data ) {
		if ( empty( $job_data ) || ! is_array( $job_data ) ) {
			return;
		}

		$list_id  = isset( $job_data['list_id'] ) ? (string) $job_data['list_id'] : '';
		$batch_id = isset( $job_data['batch_id'] ) ? (string) $job_data['batch_id'] : '';
		$attempt  = isset( $job_data['attempt'] ) ? (int) $job_data['attempt'] : 0;

		if ( empty( $list_id ) || empty( $batch_id ) ) {
			return;
		}

		WC::setTimeLimit( 120 );

		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			return;
		}

		$max_attempts = (int) apply_filters( 'wlmi_max_batch_check_attempts', 15 );
		$status       = MailchimpHelper::getBatchStatus( $settings, $batch_id );

		if ( empty( $status ) ) {
			if ( $attempt < $max_attempts ) {
				self::scheduleBatchCheck( $list_id, $batch_id, $attempt + 1 );
			} else {
				self::markBatchAbandoned( $list_id );
				self::maybeFinalizeMigration( $list_id, $settings );
			}
			return;
		}

		$batch_status = isset( $status->status ) ? strtolower( (string) $status->status ) : '';
		$in_progress  = in_array( $batch_status, [ 'pending', 'started', 'running', 'finalizing', 'pre-processing' ], true );

		if ( $in_progress ) {
			if ( $attempt < $max_attempts ) {
				self::scheduleBatchCheck( $list_id, $batch_id, $attempt + 1 );
			} else {
				self::markBatchAbandoned( $list_id );
				self::maybeFinalizeMigration( $list_id, $settings );
			}
			return;
		}

		$total    = isset( $status->total_operations ) ? (int) $status->total_operations : 0;
		$finished = isset( $status->finished_operations ) ? (int) $status->finished_operations : 0;
		$errored  = isset( $status->errored_operations ) ? (int) $status->errored_operations : 0;

		self::accumulateStats( $list_id, $total, $finished, $errored );

		if ( $errored > 0 && ! empty( $status->response_body_url ) ) {
			self::processErrorsForBatch( $list_id, $batch_id, (string) $status->response_body_url );
		}

		self::maybeFinalizeMigration( $list_id, $settings );
	}

	/**
	 * Check if the migration run is fully complete and clean up if so.
	 *
	 * @param string $list_id
	 * @param array  $settings
	 *
	 * @return void
	 */
	protected static function maybeFinalizeMigration( string $list_id, array $settings ): void {
		$flag = get_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id );
		if ( empty( $flag ) || ! is_array( $flag ) ) {
			return;
		}

		$all_submitted = ! empty( $flag['all_batches_submitted'] );
		if ( ! $all_submitted ) {
			return;
		}

		$stats = get_option( self::STATS_OPTION_PREFIX . $list_id, self::getDefaultStats() );
		if ( ! is_array( $stats ) ) {
			$stats = self::getDefaultStats();
		}

		$still_checking = (int) ( $stats['batches_checking'] ?? 0 );
		if ( $still_checking > 0 ) {
			return;
		}

		delete_option( self::MIGRATION_RUN_FLAG_PREFIX . $list_id );
		delete_option( 'wlmi_rate_bucket_' . $list_id );

		self::completeMigrationRun( $list_id, $settings );
	}

	/**
	 * Get consolidated migration status for a list (pure local read — no API calls).
	 *
	 * @param string $list_id  The Mailchimp list ID.
	 * @param array  $settings Plugin settings (unused, kept for backward compat).
	 *
	 * @return array Consolidated status data.
	 */
	public static function getConsolidatedStatus( string $list_id, array $settings ): array {
		$last_checked_at = Util::getCurrentTimeFormatted();

		$default = [
			'state'                 => 'no_runs',
			'total_operations'      => 0,
			'finished_operations'   => 0,
			'success_operations'    => 0,
			'errored_operations'    => 0,
			'batch_count'           => 0,
			'has_any_pending'       => false,
			'has_first_pending'     => false,
			'first_error_file_url'  => null,
			'failed_users_csv_url'  => null,
			'csv_processing_status' => 'not_started',
			'last_checked_at'       => $last_checked_at,
		];

		if ( empty( $list_id ) ) {
			return $default;
		}

		$pending_state = self::getPendingMigrationState( $list_id );
		$stats         = get_option( self::STATS_OPTION_PREFIX . $list_id );
		$has_stats     = is_array( $stats ) && ( (int) ( $stats['batches_submitted'] ?? 0 ) ) > 0;

		if ( ! $has_stats && ! $pending_state['has_any_batch_pending'] ) {
			return $default;
		}

		if ( ! $has_stats ) {
			$stats = self::getDefaultStats();
		}

		$total_operations    = (int) ( $stats['total_operations'] ?? 0 );
		$finished_operations = (int) ( $stats['finished_operations'] ?? 0 );
		$errored_operations  = (int) ( $stats['errored_operations'] ?? 0 );
		$success_operations  = max( 0, $finished_operations - $errored_operations );
		$batches_completed   = (int) ( $stats['batches_completed'] ?? 0 );

		$is_running = $pending_state['has_any_batch_pending'] || ( (int) ( $stats['batches_checking'] ?? 0 ) ) > 0;

		if ( $is_running ) {
			$state = 'in_progress';
		} elseif ( $has_stats ) {
			$state = 'completed';
		} else {
			$state = 'no_runs';
		}

		$csv_url    = null;
		$csv_status = 'not_started';

		if ( $state === 'completed' && $errored_operations > 0 ) {
			$expected_csv_path = self::getFailedUsersCsvPath( $list_id );

			if ( FileHelper::exists( $expected_csv_path ) && FileHelper::isReadable( $expected_csv_path ) ) {
				$csv_url    = self::getFailedUsersCsvUrl( $list_id );
				$csv_status = 'completed';
			} else {
				$csv_status = 'not_started';
			}
		}

		return [
			'state'                 => $state,
			'total_operations'      => $total_operations,
			'finished_operations'   => $finished_operations,
			'success_operations'    => $success_operations,
			'errored_operations'    => $errored_operations,
			'batch_count'           => $batches_completed,
			'has_any_pending'       => $pending_state['has_any_batch_pending'],
			'has_first_pending'     => $pending_state['has_first_batch_pending'],
			'first_error_file_url'  => null,
			'failed_users_csv_url'  => $csv_url,
			'csv_processing_status' => $csv_status,
			'last_checked_at'       => $last_checked_at,
		];
	}

	/**
	 * Download and process errors for a single Mailchimp batch, appending results to CSV.
	 *
	 * @param string $list_id
	 * @param string $batch_id
	 * @param string $response_body_url
	 *
	 * @return void
	 */
	protected static function processErrorsForBatch( string $list_id, string $batch_id, string $response_body_url ): void {
		$batch_log_dir = self::getBatchLogDir( $list_id, $batch_id );
		FileHelper::ensureDir( $batch_log_dir );

		$response = wp_remote_get( $response_body_url, [
			'timeout'   => 120,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$file_content  = wp_remote_retrieve_body( $response );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code !== 200 || empty( $file_content ) ) {
			return;
		}

		$tar_gz_path = $batch_log_dir . $batch_id . '-response.tar.gz';
		if ( ! FileHelper::putContent( $tar_gz_path, $file_content ) ) {
			return;
		}
		FileHelper::setPermissions( $tar_gz_path );
		unset( $file_content );

		$errors = self::extractErrorsFromTarGz( $tar_gz_path );

		self::cleanupDirectory( $batch_log_dir );
		self::cleanupEmptyBatchParents( $list_id );

		if ( empty( $errors ) ) {
			return;
		}

		self::appendErrorsToCSV( $list_id, $errors );
	}

	/**
	 * Extract error entries from a Mailchimp response tar.gz file.
	 *
	 * @param string $tar_gz_path
	 *
	 * @return array Associative array of email => reason.
	 */
	protected static function extractErrorsFromTarGz( string $tar_gz_path ): array {
		$errors      = [];
		$extract_dir = dirname( $tar_gz_path ) . DIRECTORY_SEPARATOR . 'extracted_' . basename( $tar_gz_path, '.tar.gz' );

		try {
			self::cleanupDirectory( $extract_dir );

			if ( ! FileHelper::ensureDir( $extract_dir ) ) {
				return $errors;
			}

			$real_path = realpath( $tar_gz_path );
			if ( $real_path === false ) {
				return $errors;
			}

			$tar = new Tar();
			$tar->open( $real_path );
			$tar->extract( $extract_dir );
			$tar->close();
		} catch ( \Exception $e ) {
			self::cleanupDirectory( $extract_dir );
			return $errors;
		}

		if ( ! FileHelper::isDir( $extract_dir ) ) {
			return $errors;
		}

		$dir_iter  = new \RecursiveDirectoryIterator( $extract_dir, \RecursiveDirectoryIterator::SKIP_DOTS );
		$flat_iter = new \RecursiveIteratorIterator( $dir_iter );

		foreach ( $flat_iter as $file ) {
			if ( ! $file->isFile() || strtolower( $file->getExtension() ) !== 'json' ) {
				continue;
			}

			$json_content = FileHelper::getContent( $file->getPathname() );
			if ( empty( $json_content ) ) {
				continue;
			}

			$operations = json_decode( $json_content, true );
			if ( ! is_array( $operations ) ) {
				continue;
			}

			if ( isset( $operations['status_code'] ) || isset( $operations['operation_id'] ) ) {
				$operations = [ $operations ];
			}

			foreach ( $operations as $operation ) {
				if ( ! is_array( $operation ) ) {
					continue;
				}

				$status_code = isset( $operation['status_code'] ) ? (int) $operation['status_code'] : 0;
				if ( $status_code < 400 ) {
					continue;
				}

				$email = isset( $operation['operation_id'] ) ? (string) $operation['operation_id'] : '';
				if ( empty( $email ) ) {
					continue;
				}

				$reason = 'Unknown error';
				if ( isset( $operation['response'] ) && ! empty( $operation['response'] ) ) {
					$response_obj = json_decode( $operation['response'], true );
					if ( is_array( $response_obj ) && isset( $response_obj['detail'] ) ) {
						$reason = (string) $response_obj['detail'];
					}
				}

				if ( ! isset( $errors[ $email ] ) ) {
					$errors[ $email ] = $reason;
				}
			}
		}

		self::cleanupDirectory( $extract_dir );

		return $errors;
	}

	/**
	 * Append error rows to the failed-users CSV for a list.
	 *
	 * @param string $list_id
	 * @param array  $errors Associative array of email => reason.
	 *
	 * @return void
	 */
	protected static function appendErrorsToCSV( string $list_id, array $errors ): void {
		$list_dir = self::getListDir( $list_id );
		if ( ! FileHelper::ensureDir( $list_dir ) ) {
			return;
		}
		if ( ! FileHelper::isWritable( $list_dir ) ) {
			return;
		}

		$csv_path = self::getFailedUsersCsvPath( $list_id );

		$is_new = ! file_exists( $csv_path );

		//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $csv_path, 'a' );
		if ( $handle === false ) {
			return;
		}

		if ( $is_new ) {
			fputcsv( $handle, [ 'email_address', 'reason' ], ';' );
		}

		foreach ( $errors as $email => $reason ) {
			fputcsv( $handle, [ $email, $reason ], ';' );
		}

		//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		FileHelper::setPermissions( $csv_path );
	}

	/**
	 * Recursively remove a directory and all its contents.
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
		}
	}
}
