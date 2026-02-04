<?php

namespace WLMI\App\Controller;

use WLMI\App\Helper\Mailchimp as MailchimpHelper;
use WLMI\App\Helper\Settings as SettingsHelper;
use Wlr\App\Models\Users;

defined( 'ABSPATH' ) || exit;

class MigrationBatch {
	/**
	 * Schedule migration batches when migration choice is enabled.
	 *
	 * @param array $settings
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
		$group   = 'wlmi_migration_queue';
		$args    = [ [ 'start_id' => 0, 'list_id' => $list_id ] ];

		if ( false !== as_next_scheduled_action( 'wlmi_process_mailchimp_migration_batch', $args, $group ) ) {
			return;
		}

		global $wpdb;
		$user_model = new Users();
		$table      = $user_model->getTableName();

		$include_banned = (bool) apply_filters( 'wlmi_migration_include_banned_users', false );
		$include_unsub  = (bool) apply_filters( 'wlmi_migration_include_unsubscribed_users', false );

		$batch_size  = 1000;
		$batch_index = 0;
		$last_id     = 0;

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

			$time    = time() + ( $batch_index * 120 );
			$job_args = [ [ 'start_id' => $last_id, 'list_id' => $list_id ] ];
			if ( false === as_next_scheduled_action( 'wlmi_process_mailchimp_migration_batch', $job_args, $group ) ) {
				as_schedule_single_action( $time, 'wlmi_process_mailchimp_migration_batch', $job_args, $group );
			}

			$last_row = end( $rows );
			$last_id  = isset( $last_row->id ) ? (int) $last_row->id : $last_id;
			$batch_index++;

			if ( count( $rows ) < $batch_size ) {
				break;
			}
		}
	}

	/**
	 * Process a single migration batch.
	 *
	 * @param array $job_data
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
		$user_model = new Users();
		$table      = $user_model->getTableName();
		$limit      = 1000;
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

		$query      = "SELECT * FROM {$table} WHERE {$where}";
		$users = $user_model->rawQuery( $query, false );
		if ( empty( $users ) ) {
			return;
		}

		$base_url   = site_url() . '?wlr_ref=';
		$operations = [];
		$last_id    = 0;
		foreach ( $users as $user ) {
			$last_id = isset( $user->id ) ? (int) $user->id : $last_id;
			$user_email = isset( $user->user_email ) ? sanitize_email( $user->user_email ) : '';
			if ( empty( $user_email ) ) {
				continue;
			}

			$ref_code = isset( $user->refer_code ) ? (string) $user->refer_code : '';
			$ref_url  = '';
			if ( ! empty( $ref_code ) ) {
				$ref_url = $base_url . $ref_code;
			}

			$points = isset( $user->points ) ? (int) $user->points : 0;

			$operations[] = [
				'method'       => 'POST',
				'path'         => "/lists/{$list_id}/members",
				'operation_id' => isset( $user->id ) ? (string) $user->id : $user_email,
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
	}
}
