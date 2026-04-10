<?php

namespace WLMI\App\Controller;

use WLMI\App\Helper\Mailchimp as MailchimpHelper;
use WLMI\App\Helper\Settings as SettingsHelper;
use Wlr\App\Helpers\Base as LoyaltyBase;
use Wlr\App\Models\Users;

defined( 'ABSPATH' ) || exit;

class Sync {
	/**
	 * Queue hook name for single member syncs.
	 */
	const SYNC_ACTION_HOOK = 'wlmi_sync_single_member';

	/**
	 * Queue hook name for single member deletes.
	 */
	const DELETE_ACTION_HOOK = 'wlmi_delete_single_member';

	/**
	 * Action Scheduler group for member syncs.
	 */
	const SYNC_ACTION_GROUP = 'wlmi_member_sync';

	/**
	 * Action Scheduler group for member deletes.
	 */
	const DELETE_ACTION_GROUP = 'wlmi_member_delete';

	/**
	 * Sync member data on points balance changes.
	 *
	 * @param   string  $user_email
	 * @param   int     $points
	 * @param   string  $transaction_type
	 * @param   string  $action_type
	 * @param   array   $hook_data
	 * @param   int     $point_balance
	 *
	 * @return void
	 */
	public static function syncMember(
		$user_email,
		$points,
		$transaction_type,
		$action_type,
		$hook_data,
		$point_balance
	) {

		if ( in_array( $action_type, [ 'import' ] ) ) {
			return;
		}
		$user_email = sanitize_email( $user_email );
		if ( empty( $user_email ) ) {
			return;
		}

		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) || empty( $settings['list_id'] ) ) {
			return;
		}

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			self::log( 'Action Scheduler not available for member sync queue.' );

			return;
		}

		$job_data = [
			'user_email' => $user_email,
			'list_id'    => (string) $settings['list_id'],
		];
		$job_args = [ $job_data ];
		$delay    = (int) apply_filters( 'wlmi_single_member_sync_delay', 60 );
		$run_at   = time() + max( 0, $delay );

		if ( false !== as_next_scheduled_action( self::SYNC_ACTION_HOOK, $job_args, self::SYNC_ACTION_GROUP ) ) {
			return;
		}

		as_schedule_single_action( $run_at, self::SYNC_ACTION_HOOK, $job_args, self::SYNC_ACTION_GROUP );
	}

	/**
	 * When a customer is deleted from WooCommerce Loyalty, remove them from the Mailchimp list.
	 *
	 * @param bool  $status    Result of the loyalty delete (true on success).
	 * @param array $condition Condition used for delete, must include 'user_email'.
	 *
	 * @return bool The original $status (unchanged).
	 */
	public static function onDeleteCustomer( $status, $condition ) {
		if ( ! $status || ! is_array( $condition ) || empty( $condition['user_email'] ) ) {
			return $status;
		}

		$user_email = sanitize_email( $condition['user_email'] );
		if ( empty( $user_email ) ) {
			return $status;
		}

		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) || empty( $settings['list_id'] ) ) {
			return $status;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			self::log( 'Action Scheduler not available for member delete queue.' );

			return $status;
		}

		$job_data = [
			'user_email' => $user_email,
			'list_id'    => (string) $settings['list_id'],
		];
		$job_args = [ $job_data ];

		self::unschedulePendingMemberSync( $job_args );

		if ( false === as_next_scheduled_action( self::DELETE_ACTION_HOOK, $job_args, self::DELETE_ACTION_GROUP ) ) {
			as_enqueue_async_action( self::DELETE_ACTION_HOOK, $job_args, self::DELETE_ACTION_GROUP );
		}

		return $status;
	}

	/**
	 * Process a queued member sync.
	 *
	 * @param array $job_data
	 *
	 * @return void
	 */
	public static function processQueuedMemberSync( $job_data ) {
		if ( empty( $job_data ) || ! is_array( $job_data ) ) {
			return;
		}

		$user_email = isset( $job_data['user_email'] ) ? sanitize_email( $job_data['user_email'] ) : '';
		$list_id    = isset( $job_data['list_id'] ) ? (string) $job_data['list_id'] : '';
		if ( empty( $user_email ) || empty( $list_id ) ) {
			return;
		}

		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) || empty( $settings['list_id'] ) ) {
			return;
		}
		if ( (string) $settings['list_id'] !== $list_id ) {
			return;
		}

		$user = self::getLoyaltyUserByEmail( $user_email );
		if ( empty( $user ) || ! is_object( $user ) ) {
			return;
		}
		$points_column = SettingsHelper::getPointsSyncColumn();

		$ref_code = isset( $user->refer_code ) ? (string) $user->refer_code : '';
		$ref_url  = '';
		if ( ! empty( $ref_code ) ) {
			$base    = new LoyaltyBase();
			$ref_url = $base->getReferralUrl( $ref_code );
		}

		$merge_fields = [
			'REF_CODE' => $ref_code,
			'REF_URL'  => $ref_url,
			'POINTS'   => isset( $user->{$points_column} ) ? (int) $user->{$points_column} : 0,
		];

		MailchimpHelper::upsertMember( $list_id, $user_email, $merge_fields );
	}

	/**
	 * Process a queued member delete.
	 *
	 * @param array $job_data
	 *
	 * @return void
	 */
	public static function processQueuedMemberDelete( $job_data ) {
		if ( empty( $job_data ) || ! is_array( $job_data ) ) {
			return;
		}

		$user_email = isset( $job_data['user_email'] ) ? sanitize_email( $job_data['user_email'] ) : '';
		$list_id    = isset( $job_data['list_id'] ) ? (string) $job_data['list_id'] : '';
		if ( empty( $user_email ) || empty( $list_id ) ) {
			return;
		}

		$settings = SettingsHelper::gets();
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) || empty( $settings['list_id'] ) ) {
			return;
		}
		if ( (string) $settings['list_id'] !== $list_id ) {
			return;
		}

		MailchimpHelper::deleteListMember( $list_id, $user_email );
	}

	/**
	 * Fetch the current loyalty user record by email.
	 *
	 * @param string $user_email
	 *
	 * @return object|null
	 */
	protected static function getLoyaltyUserByEmail( string $user_email ) {
		$user_model = new Users();
		global $wpdb;
		$where = $wpdb->prepare( 'user_email = %s', $user_email );
		$user  = $user_model->getWhere( $where, '*', true );

		return ( ! empty( $user ) && is_object( $user ) ) ? $user : null;
	}

	/**
	 * Remove pending sync actions for a member before queueing delete.
	 *
	 * @param array $job_args
	 *
	 * @return void
	 */
	protected static function unschedulePendingMemberSync( array $job_args ) {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::SYNC_ACTION_HOOK, $job_args, self::SYNC_ACTION_GROUP );

			return;
		}

		if ( ! function_exists( 'as_unschedule_action' ) ) {
			return;
		}

		while ( false !== as_next_scheduled_action( self::SYNC_ACTION_HOOK, $job_args, self::SYNC_ACTION_GROUP ) ) {
			as_unschedule_action( self::SYNC_ACTION_HOOK, $job_args, self::SYNC_ACTION_GROUP );
		}
	}

	/**
	 * Write a message to the WooCommerce logger when available.
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	protected static function log( string $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->add( 'wlmi', $message );
		}
	}
}
