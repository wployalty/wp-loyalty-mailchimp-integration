<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Mailchimp as MailchimpHelper;
use WLMI\App\Helper\Input;
use WLMI\App\Helper\Validation;
use WLMI\App\Helper\WC;
use WLMI\App\Helper\Settings as SettingsHelper;

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
		$settings = SettingsHelper::gets();

		$settings['connected'] = MailchimpHelper::checkConnection( $settings['api_key'] ?? '', $settings['server'] ?? '' );

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

		if ( ! empty( $settings['api_key'] ) && strpos( $settings['api_key'], '-' ) !== false ) {
			$settings['server'] = substr( $settings['api_key'], strpos( $settings['api_key'], '-' ) + 1 );
		} else {
			$settings['server'] = '';
		}

		$existing_settings = SettingsHelper::gets();
		$list_id           = isset( $settings['list_id'] ) ? (string) $settings['list_id'] : '';
		$old_list_id       = isset( $existing_settings['list_id'] ) ? (string) $existing_settings['list_id'] : '';
		$list_changed      = ! empty( $list_id ) && $list_id !== $old_list_id;
		$list_saved_first_time = ! empty( $list_id ) && empty( $old_list_id );

		if ( $list_changed ) {
			$merge_fields_ready = MailchimpHelper::ensureMergeFields( $list_id, $settings );
			if ( ! $merge_fields_ready ) {
				wp_send_json_error( [ 'message' => __( 'Unable to setup Mailchimp merge fields for the selected list.', 'wp-loyalty-mailchimp-integration' ) ] );
			}
		}

		// Set migration flag when list is saved for the first time or changed
		if ( $list_saved_first_time || $list_changed ) {
			$settings['wlmi_request_migration_from_admin'] = true;
		} else {
			// Preserve existing flag value if list hasn't changed
			$settings['wlmi_request_migration_from_admin'] = false;
		}

		update_option( 'wlmi_settings', $settings );
		wp_send_json_success( [ 'message' => __( 'Settings saved!', 'wp-loyalty-mailchimp-integration' ) ] );
	}
}
