<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Input;
use WLMI\App\Helper\License as LicenseHelper;
use WLMI\App\Helper\WC;

defined( 'ABSPATH' ) or die;

/**
 * Admin controller for license management.
 *
 * Mirrors the WPLoyalty license controller behaviour, adapted for this add-on.
 */
class License {

	/**
	 * Common security validation for license AJAX actions.
	 *
	 * @return bool
	 */
	protected static function isSecurityValid(): bool {
		// Mirror WPLoyalty pattern but use this plugin's nonce name.
		return WC::isSecurityValid( 'common_nonce' );
	}

	/**
	 * Activate license via AJAX.
	 *
	 * Action: wp_ajax_wlmi_activate_license
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! self::isSecurityValid() ) {
			wp_send_json_error(
				[
					'status'  => 'inactive',
					'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		$license_key = (string) Input::get( 'license_key' );
		if ( $license_key === '' ) {
			wp_send_json_error(
				[
					'status'  => 'inactive',
					'message' => __( 'License key is required.', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		$response = LicenseHelper::activate( $license_key );

		if ( empty( $response ) || ( isset( $response['status'] ) && $response['status'] === 'failed' ) ) {
			$message = isset( $response['error'] ) ? $response['error'] : __( 'License activation failed.', 'wp-loyalty-mailchimp-integration' );

			wp_send_json_error(
				[
					'status'  => 'inactive',
					'message' => $message,
				]
			);
		}

		$license_status = LicenseHelper::getLicenseStatus();

		if ( $license_status === 'active' ) {
			wp_send_json_success(
				[
					'status'  => 'active',
					'message' => __( 'License activated. Thank you!', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		if ( $license_status === 'expired' ) {
			wp_send_json_success(
				[
					'status'  => 'inactive',
					'message' => __( 'License is expired.', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		wp_send_json_error(
			[
				'status'  => 'inactive',
				'message' => __( 'Invalid license key.', 'wp-loyalty-mailchimp-integration' ),
			]
		);
	}

	/**
	 * Deactivate license via AJAX.
	 *
	 * Action: wp_ajax_wlmi_deactivate_license
	 *
	 * @return void
	 */
	public static function deActivate() {
		if ( ! self::isSecurityValid() ) {
			wp_send_json_error(
				[
					'status'  => 'inactive',
					'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		$response = LicenseHelper::deactivate();

		if ( empty( $response ) || ( isset( $response['status'] ) && $response['status'] === 'failed' ) ) {
			$message = isset( $response['error'] ) ? $response['error'] : __( 'License deactivation failed.', 'wp-loyalty-mailchimp-integration' );

			wp_send_json_error(
				[
					'status'  => 'inactive',
					'message' => $message,
				]
			);
		}

		$status = LicenseHelper::getLicenseStatus();

		if ( $status === 'inactive' ) {
			wp_send_json_success(
				[
					'status'  => 'inactive',
					'message' => __( 'License deactivated.', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		wp_send_json_success(
			[
				'status'  => 'inactive',
				'message' => __( 'License deactivated successfully.', 'wp-loyalty-mailchimp-integration' ),
			]
		);
	}

	/**
	 * Check license status via AJAX.
	 *
	 * Action: wp_ajax_wlmi_check_license_status
	 *
	 * @return void
	 */
	public static function checkStatus() {
		if ( ! self::isSecurityValid() ) {
			wp_send_json_error(
				[
					'status'  => 'inactive',
					'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		$license_key = (string) Input::get( 'license_key' );
		if ( $license_key === '' ) {
			wp_send_json_error(
				[
					'status'  => 'inactive',
					'message' => __( 'License key is required.', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		$response = LicenseHelper::checkStatus( $license_key );

		if ( empty( $response ) || ( isset( $response['status'] ) && $response['status'] === 'failed' ) ) {
			$message = isset( $response['error'] ) ? $response['error'] : __( 'License status check failed.', 'wp-loyalty-mailchimp-integration' );

			wp_send_json_error(
				[
					'status'  => 'inactive',
					'message' => $message,
				]
			);
		}

		$status = isset( $response['status'] ) ? (string) $response['status'] : 'inactive';

		if ( $status === 'active' || $status === 'valid' ) {
			wp_send_json_success(
				[
					'status'  => 'active',
					'message' => __( 'License is active.', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		if ( $status === 'expired' ) {
			wp_send_json_success(
				[
					'status'  => 'inactive',
					'message' => __( 'License is expired.', 'wp-loyalty-mailchimp-integration' ),
				]
			);
		}

		wp_send_json_success(
			[
				'status'  => 'inactive',
				'message' => __( 'License is inactive.', 'wp-loyalty-mailchimp-integration' ),
			]
		);
	}
}

