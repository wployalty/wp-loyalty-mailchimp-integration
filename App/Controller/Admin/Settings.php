<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Mailchimp as MailchimpHelper;
use WLMI\App\Helper\Input;
use WLMI\App\Helper\Validation;
use WLMI\App\Helper\WC;
use WLMI\App\Helper\Settings as SettingsHelper;
use WLMI\App\Controller\MigrationBatch;

defined( 'ABSPATH' ) or die;

class Settings {
	/**
	 * Get settings.
	 *
	 * @return void
	 */
	public static function getSettings() {
		if ( ! WC::isSecurityValid( 'wlmi_admin_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$settings = SettingsHelper::gets();

		$settings['connected'] = MailchimpHelper::getConnectionStatus( $settings['api_key'] ?? '', $settings['server'] ?? '' );

		wp_send_json_success( $settings );
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function saveSettings() {
		if ( ! WC::isSecurityValid( 'wlmi_admin_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$settings = (string) Input::get( 'settings' );
		if ( empty( $settings ) ) {
			wp_send_json_error( [ 'message' => __( 'Settings not saved!', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$settings = json_decode( stripslashes( base64_decode( $settings ) ), true );
		if ( empty( $settings ) || ! is_array( $settings ) ) {
			wp_send_json_error( [ 'message' => __( 'Settings not saved!', 'wp-loyalty-mailchimp-integration' ) ] );
		}

		// Get existing settings so we can support partial updates (e.g. license tab only).
		$existing_settings = SettingsHelper::gets();
		if ( ! is_array( $existing_settings ) ) {
			$existing_settings = [];
		}

		// Merge incoming settings on top of existing, but PRESERVE the verified API key and server.
		// These should only be managed via connectMailchimp/disconnectMailchimp.
		$api_key = isset($existing_settings['api_key']) ? (string)$existing_settings['api_key'] : '';
		$server = isset($existing_settings['server']) ? (string)$existing_settings['server'] : '';
		
		$settings = array_merge( $existing_settings, $settings );
		$settings['api_key'] = $api_key;
		$settings['server'] = $server;

		$validate_data = Validation::validateSettingsTab( [ 'settings' => $settings ] );

		$list_id           = isset( $settings['list_id'] ) ? (string) $settings['list_id'] : '';
		$old_list_id       = isset( $existing_settings['list_id'] ) ? (string) $existing_settings['list_id'] : '';
		$list_transition = ! empty( $list_id ) && $list_id !== $old_list_id;

		$migration_choice = isset( $settings['migration_choice'] ) ? (string) $settings['migration_choice'] : '';
		if ( $list_transition && ! in_array( $migration_choice, [ 'yes', 'no' ], true ) ) {
			wp_send_json_error( [
				'message'     => __( 'Settings not saved!', 'wp-loyalty-mailchimp-integration' ),
				'field_error' => [
					'settings.migration_choice' => [ __( 'This field is required', 'wp-loyalty-mailchimp-integration' ) ],
				],
			] );
		}

		if ( $list_transition ) {
			$merge_fields_ready = MailchimpHelper::ensureMergeFields( $list_id, $settings );
			if ( ! $merge_fields_ready ) {
				wp_send_json_error( [ 'message' => __( 'Unable to setup Mailchimp merge fields for the selected list.', 'wp-loyalty-mailchimp-integration' ) ] );
			}
		}
		if ( empty( $list_id ) ) {
			$settings['migration_choice'] = '';
		}
		update_option( 'wlmi_settings', $settings );
		SettingsHelper::clearCache();

		if ( $list_transition ) {
			MigrationBatch::scheduleBatches( $settings );
		}

		wp_send_json_success( [
			'message' => __( 'Settings saved!', 'wp-loyalty-mailchimp-integration' ),
		] );
	}
}
