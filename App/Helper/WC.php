<?php

namespace WLMI\App\Helper;
defined( 'ABSPATH' ) || exit;

class WC {
	/**
	 * Add admin notice.
	 *
	 * @param string $message Message.
	 * @param string $status Status.
	 *
	 * @return void
	 */
	public static function adminNotice( string $message, string $status = 'success' ) {
		add_action( 'admin_notices', function () use ( $message, $status ) {
			?>
            <div class="notice notice-<?php echo esc_attr( $status ); ?>">
                <p><?php echo wp_kses_post( $message ); ?></p>
            </div>
			<?php
		}, 1 );
	}

	/**
	 * Check the validity of a security nonce and the admin privilege.
	 *
	 * @param string $nonce_name The name of the nonce.
	 *
	 * @return bool
	 */
	public static function isSecurityValid( string $nonce_name = '' ): bool {
		$wdr_nonce = Input::get( 'wlmi_nonce' );
		if ( ! self::hasAdminPrivilege() || ! self::verifyNonce( $wdr_nonce, $nonce_name ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Has admin privilege.
	 *
	 * @return bool
	 */
	public static function hasAdminPrivilege(): bool {
		//return current_user_can( 'manage_woocommerce' );
		return current_user_can( 'manage_options' );
	}

	/**
	 * Verify nonce.
	 *
	 * @param string $nonce Nonce.
	 * @param string $action Action.
	 *
	 * @return bool
	 */
	public static function verifyNonce( string $nonce, string $action = '' ): bool {
		if ( empty( $nonce ) || empty( $action ) ) {
			return false;
		}

		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Check if the site security is valid.
	 *
	 * @param string $nonce_name The name of the nonce to be validated.
	 *
	 * @return bool True if site security is valid, false otherwise.
	 */
	public static function isSiteSecurityValid( string $nonce_name = '' ): bool {
		$wdr_nonce = Input::get( 'wlmi_nonce' );
		if ( ! self::verifyNonce( $wdr_nonce, $nonce_name ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create nonce for woocommerce.
	 *
	 * @param string $action
	 *
	 * @return false|string
	 */
	public static function createNonce( string $action = '' ) {
		if ( empty( $action ) ) {
			return false;
		}

		return wp_create_nonce( $action );
	}

	/**
	 * Get WordPress Timezone.
	 *
	 * This method retrieves the WordPress timezone string based on the available options.
	 * If the function wp_timezone_string exists, it directly returns the timezone string.
	 * If wp_timezone_string does not exist, it retrieves the timezone string from the option 'timezone_string'.
	 * If 'timezone_string' option is not set, it calculates the timezone based on 'gmt_offset' option.
	 *
	 * @return string The WordPress timezone string based on available options.
	 */
	public static function getWPZone() {
		if ( ! function_exists( 'wp_timezone_string' ) ) {
			$timezone_string = get_option( 'timezone_string' );
			if ( $timezone_string ) {
				return $timezone_string;
			}
			$offset   = (float) get_option( 'gmt_offset' );
			$hours    = (int) $offset;
			$minutes  = ( $offset - $hours );
			$sign     = ( $offset < 0 ) ? '-' : '+';
			$abs_hour = abs( $hours );
			$abs_mins = abs( $minutes * 60 );

			return sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
		}

		return wp_timezone_string();
	}

    /**
	 * Retrieves the email of the logged-in user.
	 *
	 * @return string The email of the logged-in user. If no user is logged in or the user email is empty, returns an empty string.
	 */
	public static function getLoginUserEmail() {
		$login_user = self::getLoginUser();

		return ! empty( $login_user ) ? $login_user->user_email : '';
	}

	/**
	 * Retrieves the current logged-in user.
	 *
	 * @return mixed Returns the current logged-in user object if function wp_get_current_user exists, otherwise returns false.
	 */
	public static function getLoginUser() {
		return function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : false;
	}
}