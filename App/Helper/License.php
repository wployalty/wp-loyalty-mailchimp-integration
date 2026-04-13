<?php

namespace WLMI\App\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * License helper for WPLoyalty - Mailchimp Integration.
 *
 * Mirrors the WPLoyalty license helper behaviour, adapted for this add-on.
 */
class License {

	/**
	 * Option key used to store license data.
	 *
	 * @var string
	 */
	protected static string $option_key = 'wlmi_license';

	/**
	 * Cached license data.
	 *
	 * @var array|null
	 */
	protected static ?array $data = null;

	/**
	 * Remote API base URL.
	 *
	 * @var string
	 */
	protected static string $remote_url = 'https://wployalty.net/wp-json/products/v1';

	/**
	 * Initialize helper.
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Filter the Mailchimp add-on license API base URL.
		 *
		 * This allows overriding the remote URL in development or staging.
		 *
		 * @param string $remote_url Default remote URL.
		 *
		 * @return string
		 */
		self::$remote_url = apply_filters(
			'wlmi_license_remote_url',
			self::$remote_url
		);
	}

	/**
	 * Get raw license data or a specific key from the option.
	 *
	 * @param string $key     Optional array key to retrieve.
	 * @param mixed  $default Default value if key not found.
	 *
	 * @return mixed
	 */
	protected static function getData( string $key = '', $default = false ) {
		if ( is_null( self::$data ) ) {
			$data = get_option( self::$option_key, Settings::getOptionDefault( 'wlmi_license', [] ) );
			if ( ! is_array( $data ) ) {
				$data = [];
			}
			self::$data = $data;
		}

		if ( $key === '' ) {
			return self::$data;
		}

		return self::$data[ $key ] ?? $default;
	}

	/**
	 * Update license data and persist to the option.
	 *
	 * @param array $data Data to merge into existing license data.
	 *
	 * @return void
	 */
	protected static function updateData( array $data ) {
		$current = (array) self::getData();
		$current = array_merge( $current, $data );

		update_option( self::$option_key, $current );
		self::$data = null;
	}

	/**
	 * Delete stored license data.
	 *
	 * @return void
	 */
	protected static function deleteData() {
		delete_option( self::$option_key );
		self::$data = null;
	}

	/**
	 * Get stored license key.
	 *
	 * @return string
	 */
	public static function getLicenseKey(): string {
		$key = (string) self::getData( 'key', '' );

		return $key;
	}

	/**
	 * Get current license status.
	 *
	 * @param bool $format Whether to return human readable text.
	 *
	 * @return string
	 */
	public static function getLicenseStatus( bool $format = false ): string {
		$status = (string) self::getData( 'status', 'inactive' );

		// Normalize "valid" to "active" to match WPLoyalty behavior.
		if ( $status === 'valid' ) {
			$status = 'active';
		}

		if ( ! $format ) {
			return $status;
		}

		switch ( $status ) {
			case 'active':
				return __( 'Active', 'wp-loyalty-mailchimp-integration' );
			case 'expired':
				return __( 'Expired', 'wp-loyalty-mailchimp-integration' );
			default:
				return __( 'Inactive', 'wp-loyalty-mailchimp-integration' );
		}
	}

	/**
	 * Build full API URL with query parameters.
	 *
	 * @param string $endpoint Endpoint path (e.g. "license/activate").
	 * @param array  $params   Additional query parameters.
	 *
	 * @return string
	 */
	protected static function getApiUrl( string $endpoint, array $params = [] ): string {
		$key     = self::getLicenseKey();
		$version = defined( 'WLMI_PLUGIN_VERSION' ) ? WLMI_PLUGIN_VERSION : '1.0.0';
		$slug    = defined( 'WLMI_PLUGIN_SLUG' ) ? WLMI_PLUGIN_SLUG : 'wp-loyalty-mailchimp-integration';

		$defaults = [
			'key'     => $key,
			'slug'    => $slug,
			'version' => $version,
		];

		$all_params = array_merge( $defaults, $params );

		return trailingslashit( self::$remote_url ) . $endpoint . '?' . http_build_query( $all_params );
	}

	/**
	 * Perform HTTP request to license API.
	 *
	 * @param string $endpoint Endpoint path relative to base.
	 * @param array  $params   Query parameters.
	 *
	 * @return array|false
	 */
	protected static function apiRequest( string $endpoint, array $params = [] ) {
		$url      = self::getApiUrl( $endpoint, $params );

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( (int) $code !== 200 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return false;
		}

		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : false;
	}

	/**
	 * Activate license with given key.
	 *
	 * @param string $key License key.
	 *
	 * @return array
	 */
	public static function activate( string $key ): array {
		$key    = trim( $key );
		$result = [
			'status' => 'failed',
		];

		if ( $key === '' ) {
			$result['error'] = __( 'License key is required.', 'wp-loyalty-mailchimp-integration' );

			return $result;
		}

		$response = self::apiRequest(
			'license/activate',
			[
				'key' => $key,
			]
		);

		if ( empty( $response ) ) {
			$result['error'] = __( 'Unable to connect server. Try again later!', 'wp-loyalty-mailchimp-integration' );

			return $result;
		}

		if ( isset( $response['status'] ) && in_array( $response['status'], [ 'active', 'activated', 'valid' ], true ) ) {
			self::updateData(
				[
					'key'     => $key,
					'status'  => 'active',
					'expires' => $response['expires'] ?? '',
				]
			);
		} else {
			// Reset stored license data but keep the key for UX.
			delete_option( self::$option_key );
			self::$data = null;
			self::updateData(
				[
					'key' => $key,
				]
			);
		}

		return $response;
	}

	/**
	 * Deactivate license.
	 *
	 * @return array
	 */
	public static function deactivate(): array {
		$key = self::getLicenseKey();
		if ( $key === '' ) {
			return [
				'status' => 'failed',
				'error'  => __( 'License key is missing.', 'wp-loyalty-mailchimp-integration' ),
			];
		}

		$response = self::apiRequest( 'license/deactivate' );

		if ( empty( $response ) ) {
			return [
				'status' => 'failed',
				'error'  => __( 'Unable to connect server. Try again later!', 'wp-loyalty-mailchimp-integration' ),
			];
		}

		if ( isset( $response['status'] ) && in_array( $response['status'], [ 'inactive', 'deactivated' ], true ) ) {
			self::deleteData();
		}

		return $response;
	}

	/**
	 * Check license status for a given key.
	 *
	 * @param string $key License key.
	 *
	 * @return array
	 */
	public static function checkStatus( string $key ): array {
		$key = trim( $key );
		if ( $key === '' ) {
			return [
				'status' => 'failed',
				'error'  => __( 'License key is required.', 'wp-loyalty-mailchimp-integration' ),
			];
		}

		$response = self::apiRequest(
			'license/status',
			[
				'key' => $key,
			]
		);

		if ( empty( $response ) ) {
			return [
				'status' => 'failed',
				'error'  => __( 'Unable to connect server. Try again later!', 'wp-loyalty-mailchimp-integration' ),
			];
		}

		return $response;
	}

	/**
	 * Append license data into settings array.
	 *
	 * @param array $data Settings data.
	 *
	 * @return array
	 */
	public static function appendLicenseToSettings( array $data ): array {
		$data['license_status'] = self::getLicenseStatus();
		$data['license_key']    = self::getLicenseKey();

		return $data;
	}

	/**
	 * Show top-of-page banner when license is not active.
	 *
	 * Mirrors the WPLoyalty banner behaviour (custom bar at the top of the
	 * plugin screen), instead of using the default WordPress admin notice UI.
	 *
	 * @return void
	 */
	public static function showHeaderNotice() {
		// Only show on this plugin's admin page.
		if ( ! class_exists( Input::class ) ) {
			return;
		}

		$page  = Input::get( 'page', '' );

		if ( empty( $page ) || $page !== WLMI_PLUGIN_SLUG ) {
			return;
		}

		$status = self::getLicenseStatus();
		$key    = self::getLicenseKey();

		if ( $status === 'active' && $key !== '' ) {
			return;
		}

		$settings_url = Util::getSettingsPageUrl( 'license' );

		$html  = '<div id="wlmi-admin-notice" class="wlmi-admin-notice-top-of-page wlmi-pro-inactive">';
		$html .= esc_html__(
			'Make sure to activate your license to receive updates, support and security fixes!',
			'wp-loyalty-mailchimp-integration'
		);
		$html .= ' <a id="wlmi-activate-license-btn" href="' . esc_url( $settings_url ) . '">';
		$html .= esc_html__( 'Enter license key', 'wp-loyalty-mailchimp-integration' ) . '</a>';
		$html .= '</div>';
		$html .= '<style>
.wlmi-admin-notice-top-of-page {
    text-align: center;
    padding: 10px 46px 10px 22px;
    font-size: 15px;
    line-height: 1.4;
    color: #fff;
    margin-left: -20px;
}
.wlmi-admin-notice-top-of-page a {
    color: #fff;
    text-decoration: underline;
}
.wlmi-pro-inactive {
    background: #d63638;
}
</style>';

		echo wp_kses_post( $html );
	}
}

