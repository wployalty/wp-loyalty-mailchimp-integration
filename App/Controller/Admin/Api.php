<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Input;
use WLMI\App\Helper\WC;
use WLMI\App\Helper\Settings as SettingsHelper;

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

	/**
	 * Get Mailchimp lists with pagination
	 *
	 * @return void
	 */
	public static function getLists() {
		if ( ! WC::isSecurityValid( 'wlmi_launcher_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$offset = (int) Input::get( 'offset', 0 );
		$count  = (int) Input::get( 'count', 25 );

		$settings = SettingsHelper::gets();

		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			wp_send_json_error( [ 'message' => __( 'API connection not configured', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		try {
			$mailchimp = new \MailchimpMarketing\ApiClient();
			$mailchimp->setConfig( [
				'apiKey' => $settings['api_key'],
				'server' => $settings['server']
			] );

			$response = $mailchimp->lists->getAllLists( null, null, (string) $count, (string) $offset );

			$lists = [];
			if ( isset( $response->lists ) && is_array( $response->lists ) ) {
				foreach ( $response->lists as $list ) {
					$lists[] = [
						'value' => $list->id,
						'label' => $list->name,
						'stats' => isset( $list->stats ) ? $list->stats : null
					];
				}
			}

			wp_send_json_success( [
				'lists'       => $lists,
				'total_items' => isset( $response->total_items ) ? $response->total_items : 0,
				'offset'      => $offset,
				'count'       => $count
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => __( 'Failed to fetch lists', 'wp-loyalty-mailchimp-integration' ) . ': ' . $e->getMessage() ] );
		}
	}

}