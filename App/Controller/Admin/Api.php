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
	 * Get Mailchimp lists with pagination and server-side search
	 *
	 * @return void
	 */
	public static function getLists() {
		if ( ! WC::isSecurityValid( 'wlmi_launcher_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		$offset       = (int) Input::get( 'offset', 0 );
		$count        = (int) Input::get( 'count', 100 );
		$search_term  = trim( Input::get( 'search_term', '' ) );
		$max_batches  = 3;
		$batch_size   = 100;

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

			$results       = [];
			$current_offset = $offset;
			$total_items    = 0;
			$batches_fetched = 0;
			$has_search     = ! empty( $search_term );

			//fetch multiple batches if searching and no matches found
			while ( $batches_fetched < $max_batches ) {
				set_time_limit( 30 );

				$response = $mailchimp->lists->getAllLists( null, null, (string) $batch_size, (string) $current_offset );

				if ( ! isset( $response->lists ) || ! is_array( $response->lists ) ) {
					break;
				}

				$total_items = isset( $response->total_items ) ? (int) $response->total_items : 0;
				$batch_lists = $response->lists;

				foreach ( $batch_lists as $list ) {
					$list_data = [
						'value' => $list->id,
						'label' => $list->name,
						'stats' => isset( $list->stats ) ? $list->stats : null
					];

					if ( $has_search ) {
						if ( stripos( $list->name, $search_term ) !== false || stripos( $list->id, $search_term ) !== false ) {
							$results[] = $list_data;
						}
					} else {
						$results[] = $list_data;
					}
				}

				$batches_fetched++;
				$current_offset += count( $batch_lists );

				// Stop conditions:
				// 1. If we have results (matches found)
				// 2. If we've reached the end of all lists
				// 3. If not searching (only fetch one batch)
				if ( count( $results ) > 0 || $current_offset >= $total_items || ! $has_search ) {
					break;
				}
			}

			$next_offset = $current_offset;
			$has_more    = $next_offset < $total_items;

			// Limit results to the requested count
			if ( count( $results ) > $count ) {
				$results = array_slice( $results, 0, $count );
			}

			wp_send_json_success( [
				'results'      => $results,
				'next_offset'  => $next_offset,
				'total_items'  => $total_items,
				'has_more'     => $has_more,
				'search_term'  => $search_term,
				'batches_fetched' => $batches_fetched
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => __( 'Failed to fetch lists', 'wp-loyalty-mailchimp-integration' ) . ': ' . $e->getMessage() ] );
		}
	}

}