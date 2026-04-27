<?php

namespace WLMI\App\Helper;

use WLMI\App\Controller\Sync;
use WLMI\App\Helper\Settings as SettingsHelper;

defined( 'ABSPATH' ) || exit;

class Mailchimp {
	/**
	 * Build Mailchimp client from settings.
	 *
	 * @param array $settings
	 *
	 * @return \MailchimpMarketing\ApiClient|null
	 */
	public static function getClientFromSettings( array $settings ) {
		if ( empty( $settings['api_key'] ) || empty( $settings['server'] ) ) {
			return null;
		}

		$mailchimp = new \MailchimpMarketing\ApiClient();
		$mailchimp->setConfig( [
			'apiKey' => $settings['api_key'],
			'server' => $settings['server']
		] );

		return $mailchimp;
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
	 * Get connection status with caching.
	 *
	 * @param string $api_key
	 * @param string $server
	 * @param bool $force_refresh
	 *
	 * @return bool|null
	 */
	public static function getConnectionStatus( $api_key, $server = '', $force_refresh = false ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		$transient_key = 'wlmi_connection_status';

		if ( ! $force_refresh ) {
			$cached = get_transient( $transient_key );
			if ( $cached !== false ) {
				return (bool) $cached;
			}
		}

		$status = self::checkConnection( $api_key, $server );

		if ( $status ) {
			$cache_time = (int) apply_filters( 'wlmi_connection_cache_duration', 5 * MINUTE_IN_SECONDS );
			set_transient( $transient_key, $status, $cache_time );
		}

		return $status;
	}

	/**
	 * Clear connection status cache.
	 *
	 * @return void
	 */
	public static function clearConnectionCache() {
		delete_transient( 'wlmi_connection_status' );
	}

	/**
	 * Fetch list batch.
	 *
	 * @param array $settings
	 * @param int $count
	 * @param int $offset
	 *
	 * @return object|null
	 */
	public static function getListBatch( array $settings, $count, $offset ) {
		$mailchimp = self::getClientFromSettings( $settings );
		if ( empty( $mailchimp ) ) {
			return null;
		}

		return $mailchimp->lists->getAllLists( null, null, (string) $count, (string) $offset );
	}

	/**
	 * Start a Mailchimp batch request.
	 *
	 * @param array $settings
	 * @param array $operations
	 *
	 * @return object|null
	 */
	public static function startBatch( array $settings, array $operations ) {
		if ( empty( $operations ) ) {
			return null;
		}

		$mailchimp = self::getClientFromSettings( $settings );
		if ( empty( $mailchimp ) ) {
			return null;
		}

		try {
			return $mailchimp->batches->start( [ 'operations' => $operations ] );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Check batch status.
	 *
	 * @param array $settings
	 * @param string $batch_id
	 *
	 * @return object|null
	 */
	public static function getBatchStatus( array $settings, string $batch_id ) {
		if ( empty( $batch_id ) ) {
			return null;
		}

		$mailchimp = self::getClientFromSettings( $settings );
		if ( empty( $mailchimp ) ) {
			return null;
		}

		try {
			return $mailchimp->batches->status( $batch_id );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Ensure required merge fields exist for list.
	 *
	 * @param string $list_id
	 * @param array|null $settings
	 *
	 * @return bool
	 */
	public static function ensureMergeFields( $list_id, $settings = null ): bool {
		$list_id = (string) $list_id;
		if ( empty( $list_id ) ) {
			return false;
		}

		$settings  = is_array( $settings ) ? $settings : SettingsHelper::gets();
		$mailchimp = self::getClientFromSettings( $settings );
		if ( empty( $mailchimp ) ) {
			return false;
		}

		try {
			$existing_tags = [];
			$response      = $mailchimp->lists->getListMergeFields( $list_id, null, null, 1000 );
			if ( isset( $response->merge_fields ) && is_array( $response->merge_fields ) ) {
				foreach ( $response->merge_fields as $field ) {
					if ( isset( $field->tag ) ) {
						$existing_tags[] = strtoupper( $field->tag );
					}
				}
			}

			$required = self::getRequiredMergeFields();
			foreach ( $required as $tag => $field_data ) {
				if ( in_array( $tag, $existing_tags, true ) ) {
					continue;
				}
				$payload = array_merge( [
					'tag'  => $tag,
					'name' => $field_data['name'],
					'type' => $field_data['type'],
				], isset( $field_data['default_value'] ) ? [ 'default_value' => $field_data['default_value'] ] : [] );

				$mailchimp->lists->addListMergeField( $list_id, $payload );
			}

			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Upsert list member with merge fields.
	 *
	 * @param string $list_id
	 * @param string $email
	 * @param array $merge_fields
	 *
	 * @return bool
	 */
	public static function upsertMember( $list_id, $email, array $merge_fields = [] ): bool {
		$list_id = (string) $list_id;
		$email   = sanitize_email( $email );
		if ( empty( $list_id ) || empty( $email ) ) {
			return false;
		}

		$mailchimp = self::getClientFromSettings( SettingsHelper::gets() );
		if ( empty( $mailchimp ) ) {
			return false;
		}

		try {
			$subscriber_hash = md5( strtolower( trim( $email ) ) );
			$mailchimp->lists->setListMember( $list_id, $subscriber_hash, [
				'email_address' => $email,
				'status_if_new' => 'subscribed',
				'status'        => 'subscribed',
				'merge_fields'  => $merge_fields,
			] );

			return true;
		} catch ( \Exception $e ) {
			$log_message = 'Mailchimp member added failed: ' . $e->getMessage();
			if ( method_exists( $e, 'getResponse' ) && $e->getResponse() !== null ) {
				$body = $e->getResponse()->getBody();
				if ( $body !== null && method_exists( $body, 'getContents' ) ) {
					$full_body = $body->getContents();
					if ( $full_body !== '' ) {
						$log_message .= ' Full response: ' . $full_body;
					}
				}
			}
			Sync::log( $log_message );
			return false;
		}
	}

	/**
	 * Delete a list member by email.
	 *
	 * @param string $list_id Mailchimp list ID.
	 * @param string $email   Member email address.
	 *
	 * @return bool True on success, false on failure or missing config.
	 */
	public static function deleteListMember( $list_id, $email ): bool {
		$list_id = (string) $list_id;
		$email   = sanitize_email( $email );
		if ( empty( $list_id ) || empty( $email ) ) {
			return false;
		}

		$mailchimp = self::getClientFromSettings( SettingsHelper::gets() );
		if ( empty( $mailchimp ) ) {
			return false;
		}

		try {
			$subscriber_hash = md5( strtolower( trim( $email ) ) );
			$mailchimp->lists->deleteListMember( $list_id, $subscriber_hash );
			return true;
		} catch ( \Exception $e ) {
			$log_message = 'Mailchimp member delete failed: ' . $e->getMessage();
			if ( method_exists( $e, 'getResponse' ) && $e->getResponse() !== null ) {
				$body = $e->getResponse()->getBody();
				if ( $body !== null && method_exists( $body, 'getContents' ) ) {
					$full_body = $body->getContents();
					if ( $full_body !== '' ) {
						$log_message .= ' Full response: ' . $full_body;
					}
				}
			}
			Sync::log( $log_message );
			return false;
		}
	}

	/**
	 * Required merge fields list.
	 *
	 * @return array
	 */
	protected static function getRequiredMergeFields(): array {
		return [
			'REF_CODE' => [
				'name' => 'Referral Code',
				'type' => 'text'
			],
			'REF_URL'  => [
				'name' => 'Referral URL',
				'type' => 'text'
			],
			'POINTS'   => [
				'name'          => 'Points',
				'type'          => 'number',
				'default_value' => '0'
			],
		];
	}
}
