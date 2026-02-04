<?php

namespace WLMI\App\Controller;

use WLMI\App\Helper\Mailchimp as MailchimpHelper;
use WLMI\App\Helper\Settings as SettingsHelper;
use Wlr\App\Helpers\Base as LoyaltyBase;
use Wlr\App\Models\Users;

defined( 'ABSPATH' ) || exit;

class Sync {
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

		$user_model = new Users();
		global $wpdb;
		$where = $wpdb->prepare( 'user_email = %s', [ $user_email ] );
		$user  = $user_model->getWhere( $where, '*', true );
		if ( empty( $user ) || ! is_object( $user ) ) {
			return;
		}

		$ref_code = isset( $user->refer_code ) ? (string) $user->refer_code : '';
		$ref_url  = '';
		if ( ! empty( $ref_code ) ) {
			$base = new LoyaltyBase();
			$ref_url = $base->getReferralUrl( $ref_code );
		}

		$merge_fields = [
			'REF_CODE' => $ref_code,
			'REF_URL'  => $ref_url,
			'POINTS'   => (int) $point_balance,
		];

		MailchimpHelper::upsertMember( $settings['list_id'], $user_email, $merge_fields );
	}
}
