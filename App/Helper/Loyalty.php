<?php

namespace WLMI\App\Helper;

use Wlr\App\Models\EarnCampaignTransactions;
use Wlr\App\Models\Users;

defined( 'ABSPATH' ) || exit;

class Loyalty {

	/**
	 * Determines if the plugin is in the Pro version based on filters.
	 *
	 * @return bool True if the plugin is in the Pro version, false otherwise.
	 */
	public static function isPro() {
		return apply_filters( 'wlr_is_pro', false );
	}

	/**
	 * Checks if a user is banned based on the user email.
	 *
	 * @param string $user_email The email of the user to check if banned. Defaults to an empty string.
	 *
	 * @return bool Whether the user is banned (true) or not (false).
	 */
	public static function isBannedUser( $user_email = "" ) {
		if ( empty( $user_email ) ) {
			$user_email = WC::getLoginUserEmail();
			if ( empty( $user_email ) ) {
				return false;
			}
		}
		$user    = get_user_by( 'email', $user_email );
		$user_id = ! empty( $user->ID ) ? $user->ID : 0;
		if ( ! apply_filters( 'wlr_before_add_to_loyalty_customer', true, $user_id, $user_email ) ) {
			return true;
		}

		$user_modal = new Users();
		global $wpdb;
		$where = $wpdb->prepare( "user_email = %s AND is_banned_user = %d ", array( $user_email, 1 ) );
		$user  = $user_modal->getWhere( $where, "*", true );

		return ( ! empty( $user ) && is_object( $user ) && isset( $user->is_banned_user ) );
	}
}