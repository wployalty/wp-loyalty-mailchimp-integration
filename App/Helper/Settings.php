<?php

namespace WLMI\App\Helper;

defined( 'ABSPATH' ) || exit;

class Settings {
	public static function get( $key, $value ) {
		$settings = self::gets();

		return $settings[ $key ] ?? $value;
	}

	public static function gets() {
		$settings = get_option( 'wlmi_settings', self::getDefaultSettings() );

		/**
		 * Filter Mailchimp add-on settings before they are returned.
		 *
		 * This allows helpers (like License) to append additional data such as
		 * license_status and license_key which are needed on the React side.
		 *
		 * @param array $settings Settings data.
		 *
		 * @return array
		 */
		return apply_filters( 'wlmi_get_settings_data', $settings );
	}

	public static function getDefaultSettings(): array {
		return [
			'api_key' => '',
			'server'  => '',
			'list_id' => '',
			'wlmi_request_migration_from_admin' => false,
			'migration_choice' => ''
		];
	}
}
