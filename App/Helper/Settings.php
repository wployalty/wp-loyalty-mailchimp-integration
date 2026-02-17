<?php

namespace WLMI\App\Helper;

defined( 'ABSPATH' ) || exit;

class Settings {
	public static function get( $key, $value ) {
		$settings = self::gets();

		return $settings[ $key ] ?? $value;
	}

	public static function gets() {
		$settings = get_option( 'wlmi_settings', self::getOptionDefault( 'wlmi_settings' ) );

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
			'migration_choice' => ''
		];
	}

	/**
	 * Get default value for a specific static option key.
	 *
	 * @param string $option_key The option key to get default for.
	 * @param mixed  $fallback   Fallback value if key not found. Defaults to null.
	 *
	 * @return mixed Default value for the option key, or fallback if not found.
	 */
	public static function getOptionDefault( string $option_key, $fallback = null ) {
		$defaults = self::getAllOptionDefaults();

		return $defaults[ $option_key ] ?? $fallback;
	}

	/**
	 * Get all default values for static option keys.
	 *
	 * @return array Associative array of option keys and their default values.
	 */
	public static function getAllOptionDefaults(): array {
		return [
			'wlmi_settings'                      => self::getDefaultSettings(),
			'wlmi_license'                       => [],
			'wlmi_is_launcher_plugin_activated' => false,
		];
	}
}
