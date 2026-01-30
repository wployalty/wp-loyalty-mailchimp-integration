<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Input;
use WLMI\App\Helper\WC;

defined( 'ABSPATH' ) or die;

class Api {
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

		$is_connected = self::checkConnection( $api_key );

		if ( $is_connected ) {
			wp_send_json_success( [ 'message' => __( 'Connected successfully!', 'wp-loyalty-mailchimp-integration' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Connection failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
	}

	/**
	 * Check connection.
	 *
	 * @param string $api_key
	 * @param string $server
	 *
	 * @return bool
	 */
	public static function checkConnection( $api_key, $server = '' ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		if ( empty( $server ) ) {
			if ( strpos( $api_key, '-' ) !== false ) {
				$server = substr( $api_key, strpos( $api_key, '-' ) + 1 );
			} else {
				$server = 'us1';
			}
		}

		try {
			$mailchimp = new \MailchimpMarketing\ApiClient();
			$mailchimp->setConfig( [
				'apiKey' => $api_key,
				'server' => $server
			] );
			$response = $mailchimp->ping->get();

			return ( isset( $response->health_status ) && $response->health_status == "Everything's Chimpy!" );
		} catch ( \Exception $e ) {
			return false;
		}
	}
}