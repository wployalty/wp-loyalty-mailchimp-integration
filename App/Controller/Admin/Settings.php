<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Input;
use WLMI\App\Helper\Validation;
use WLMI\App\Helper\WC;

defined( 'ABSPATH' ) or die;

class Settings {
	/**
	 * Get settings.
	 *
	 * @return void
	 */
	public static function getSettings() {
		if ( ! WC::isSecurityValid( 'wlmi_launcher_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$settings = get_option( 'wlmi_mailchimp_settings', [] );

		$isConnected = false;
		if ( ! empty( $settings['api_key'] ) ) {
			try {
				$mailchimp = new \MailchimpMarketing\ApiClient();
				$server    = 'us1';
				if ( strpos( $settings['api_key'], '-' ) !== false ) {
					$server = substr( $settings['api_key'], strpos( $settings['api_key'], '-' ) + 1 );
				}
				$mailchimp->setConfig( [
					'apiKey' => $settings['api_key'],
					'server' => $server
				] );
				$response = $mailchimp->ping->get();
				if ( isset( $response->health_status ) && $response->health_status == "Everything's Chimpy!" ) {
					$isConnected = true;
				}
			} catch ( \Exception $e ) {
				$isConnected = false;
			}
		}
		$settings['connected'] = $isConnected;

		wp_send_json_success( $settings );
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function saveSettings() {
		if ( ! WC::isSecurityValid( 'wlmi_launcher_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$settings = (string) Input::get( 'settings' );
		if ( empty( $settings ) ) {
			wp_send_json_error( [ 'message' => __( 'Settings not saved!', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$settings = json_decode( stripslashes( base64_decode( $settings ) ), true );
		if ( empty( $settings ) ) {
			wp_send_json_error( [ 'message' => __( 'Settings not saved!', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$validate_data = Validation::validateSettingsTab( [ 'settings' => $settings ] );
		if ( is_array( $validate_data ) ) {
			foreach ( $validate_data as $key => $validate ) {
				$validate_data[ $key ] = [ current( $validate ) ];
			}
			wp_send_json_error( [
				'message'     => __( 'Settings not saved!', 'wp-loyalty-mailchimp-integration' ),
				'field_error' => $validate_data
			] );
		}

		update_option( 'wlmi_mailchimp_settings', $settings );
		wp_send_json_success( [ 'message' => __( 'Settings saved!', 'wp-loyalty-mailchimp-integration' ) ] );
	}

	/**
	 * Test connection.
	 *
	 * @return void
	 */
	public static function testConnection() {
		if ( ! WC::isSecurityValid( 'wlmi_launcher_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$api_key = Input::get( 'api_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'API Key is required', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		try {
			$mailchimp = new \MailchimpMarketing\ApiClient();
			$server    = 'us1';
			if ( strpos( $api_key, '-' ) !== false ) {
				$server = substr( $api_key, strpos( $api_key, '-' ) + 1 );
			}
			$mailchimp->setConfig( [
				'apiKey' => $api_key,
				'server' => $server
			] );
			$response = $mailchimp->ping->get();
			if ( isset( $response->health_status ) && $response->health_status == "Everything's Chimpy!" ) {
				wp_send_json_success( [ 'message' => __( 'Connected successfully!', 'wp-loyalty-mailchimp-integration' ) ] );
			} else {
				wp_send_json_error( [ 'message' => __( 'Connection failed', 'wp-loyalty-mailchimp-integration' ) ] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => __( 'Connection failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
	}
}