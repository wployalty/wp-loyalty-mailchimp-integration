<?php

namespace WLMI\App\Helper;

defined( 'ABSPATH' ) || exit;

class Settings {
	public static function get( $key, $value ) {
		$settings = self::gets();

		return $settings[ $key ] ?? $value;
	}

	public static function gets() {
		return get_option( 'wlmi_settings', self::getDefaultSettings() );
	}

	public static function getDefaultSettings(): array {
		return [
			'api_key' => '',
			'server'  => ''
		];
	}
}