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
	 * Wrapper for set_time_limit to see if it is enabled.
	 *
	 * @param int $limit Time limit.
	 */
	public static function setTimeLimit( $limit = 0 ) {
		if ( function_exists( 'set_time_limit' ) && false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
			@set_time_limit( $limit ); // @codingStandardsIgnoreLine
		}
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
			$offset    = (float) get_option( 'gmt_offset' );
			$hours     = (int) $offset;
			$minutes   = ( $offset - $hours );
			$sign      = ( $offset < 0 ) ? '-' : '+';
			$abs_hour  = abs( $hours );
			$abs_mins  = abs( $minutes * 60 );
			$tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

			return $tz_offset;
		}

		return wp_timezone_string();
	}
}
