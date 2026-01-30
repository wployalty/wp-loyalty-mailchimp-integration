<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Loyalty;

use WLMI\App\Helper\WC;

defined( 'ABSPATH' ) or die;

class Labels {
	/**
	 * Getting local data.
	 *
	 * @return void
	 */
	public static function getLocalData() {
		if ( ! WC::isSecurityValid( 'local_data' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$is_pro          = Loyalty::isPro();
		$short_codes     = [];

		$localize = [
			'is_pro'                  => $is_pro,
			'common'                  => [
				'back_to_apps_url' => admin_url( 'admin.php?' . http_build_query( [ 'page' => WLR_PLUGIN_SLUG ] ) ) . '#/apps',
			],
			'plugin_name'             => WLMI_PLUGIN_NAME,
			'version'                 => 'v' . WLMI_PLUGIN_VERSION,
			'short_code_lists'        => $short_codes,
			'render_admin_page_nonce' => WC::createNonce( 'render_page_nonce' ),
			'common_nonce'            => WC::createNonce( 'common_nonce' ),
			'design_nonce'            => WC::createNonce( 'wlmi_design_settings' ),
			'content_nonce'           => WC::createNonce( 'wlmi_content_settings' ),
			'launcher_nonce'          => WC::createNonce( 'wlmi_launcher_settings' ),
			'settings_nonce'          => WC::createNonce( 'wlmi_launcher_settings' ),
		];
		$localize = apply_filters( 'wlmi_launcher_local_data', $localize );
		wp_send_json_success( $localize );
	}

	/**
	 * Getting labels data.
	 *
	 * @return void
	 */
	public static function getLabels() {
		if ( ! WC::isSecurityValid( 'common_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Basic check failed', 'wp-loyalty-mailchimp-integration' ) ] );
		}
		$label_data = [
			'common'            => self::getCommonLabels(),
			'settings'          => self::getSettingsLabels(),
		];
		wp_send_json_success( $label_data );
	}

	/**
	 * Retrieves common labels used in the plugin.
	 *
	 * @return array Array containing various labels used in the plugin.
	 */
	public static function getCommonLabels() {
		return [
			'plugin_name'                  => WLMI_PLUGIN_NAME,
			'version'                      => 'v' . WLMI_PLUGIN_VERSION,
			'save'                         => __( 'Save Changes', 'wp-loyalty-mailchimp-integration' ),
			'upgrade_text'                 => __( 'Upgrade to Pro', 'wp-loyalty-mailchimp-integration' ),
			'buy_pro_url'                  => 'https://wployalty.net/pricing/?utm_campaign=wployalty-link&utm_medium=pro_url&utm_source=pricing',
			'reset'                        => __( 'Reset', 'wp-loyalty-mailchimp-integration' ),
			'back'                         => __( 'Back', 'wp-loyalty-mailchimp-integration' ),
			'back_to_apps'                 => __( 'Back to WPLoyalty', 'wp-loyalty-mailchimp-integration' ),
			'icon'                         => __( 'Icon', 'wp-loyalty-mailchimp-integration' ),
			'icon_buttons'                 => [
				'restore' => __( 'Restore Default', 'wp-loyalty-mailchimp-integration' ),
				'browse'  => __( 'Browse Image', 'wp-loyalty-mailchimp-integration' ),
			],
			'background'                   => __( 'Background', 'wp-loyalty-mailchimp-integration' ),
			'text'                         => __( 'Text', 'wp-loyalty-mailchimp-integration' ),
			'texts'                        => __( 'Texts', 'wp-loyalty-mailchimp-integration' ),
			'link'                         => __( 'Link', 'wp-loyalty-mailchimp-integration' ),
			'color'                        => __( 'Color', 'wp-loyalty-mailchimp-integration' ),
			'colors'                       => __( 'Colors', 'wp-loyalty-mailchimp-integration' ),
			'buttons'                      => __( 'Buttons', 'wp-loyalty-mailchimp-integration' ),
			'title'                        => __( 'Title', 'wp-loyalty-mailchimp-integration' ),
			'description'                  => __( 'Description', 'wp-loyalty-mailchimp-integration' ),
			'visibility'                   => __( 'Visibility', 'wp-loyalty-mailchimp-integration' ),
			'show'                         => __( 'Show', 'wp-loyalty-mailchimp-integration' ),
			'none'                         => __( 'Do not show', 'wp-loyalty-mailchimp-integration' ),
			'restore_default'              => __( 'Restore Default', 'wp-loyalty-mailchimp-integration' ),
			'browse_image'                 => __( 'Browse Image', 'wp-loyalty-mailchimp-integration' ),
			'left'                         => __( 'Left', 'wp-loyalty-mailchimp-integration' ),
			'right'                        => __( 'Right', 'wp-loyalty-mailchimp-integration' ),
			'mobile_only'                  => __( 'Mobile Only', 'wp-loyalty-mailchimp-integration' ),
			'desktop_only'                 => __( 'Desktop Only', 'wp-loyalty-mailchimp-integration' ),
			'mobile_and_desktop'           => __( 'Mobile and Desktop', 'wp-loyalty-mailchimp-integration' ),
			'display_none'                 => __( 'Do not show', 'wp-loyalty-mailchimp-integration' ),
			'image_description'            => __( 'Choose an image to preview.', 'wp-loyalty-mailchimp-integration' ),
			'logo_image'                   => __( 'Your logo', 'wp-loyalty-mailchimp-integration' ),
			'font_family'                  => __( 'Font Family', 'wp-loyalty-mailchimp-integration' ),
			'white'                        => __( 'White', 'wp-loyalty-mailchimp-integration' ),
			'black'                        => __( 'Black', 'wp-loyalty-mailchimp-integration' ),
			'primary'                      => __( 'Primary', 'wp-loyalty-mailchimp-integration' ),
			'secondary'                    => __( 'Secondary', 'wp-loyalty-mailchimp-integration' ),
			'back_to_loyalty'              => __( 'Back to WPLoyalty', 'wp-loyalty-mailchimp-integration' ),
			'reset_message'                => __( 'Reset Successfully', 'wp-loyalty-mailchimp-integration' ),
			'theme_color'                  => __( 'Color', 'wp-loyalty-mailchimp-integration' ),
			'no_result_found'              => __( 'No results found!', 'wp-loyalty-mailchimp-integration' ),
			'toggle'                       => [
				'activate'   => __( 'click to activate', 'wp-loyalty-mailchimp-integration' ),
				'deactivate' => __( 'click to de-activate', 'wp-loyalty-mailchimp-integration' ),
			],
			'visibility_list'              => [
				[ 'label' => __( 'Show', 'wp-loyalty-mailchimp-integration' ), 'value' => 'show' ],
				[ 'label' => __( 'None', 'wp-loyalty-mailchimp-integration' ), 'value' => 'none' ],
			],
			'banner'                       => __( 'Banner', 'wp-loyalty-mailchimp-integration' ),
			'enabled'                      => __( 'Enabled', 'wp-loyalty-mailchimp-integration' ),
			'disabled'                     => __( 'Disabled', 'wp-loyalty-mailchimp-integration' ),
			'ok_text'                      => __( 'Yes, Reset', 'wp-loyalty-mailchimp-integration' ),
			'cancel_text'                  => __( 'Cancel', 'wp-loyalty-mailchimp-integration' ),
			'confirm_title'                => __( 'Reset Settings?', 'wp-loyalty-mailchimp-integration' ),
			'confirm_description'          => __( 'Are you sure want to reset this settings?', 'wp-loyalty-mailchimp-integration' ),
			'apply_button_text'            => __( 'Apply', 'wp-loyalty-mailchimp-integration' ),
			'delete_text'                  => __( "delete", 'wp-loyalty-mailchimp-integration' ),
		];
	}

	/**
	 * Retrieve settings labels.
	 *
	 * @return array
	 */
	public static function getSettingsLabels() {
		return [
			'title'           => __( 'Mailchimp Settings', 'wp-loyalty-mailchimp-integration' ),
			'api_key'         => __( 'Mailchimp API Key', 'wp-loyalty-mailchimp-integration' ),
			'placeholder'     => __( 'Enter your Mailchimp API Key', 'wp-loyalty-mailchimp-integration' ),
			'description'     => __( 'You can find your API key in your Mailchimp account settings.', 'wp-loyalty-mailchimp-integration' ),
			'status'          => __( 'Status', 'wp-loyalty-mailchimp-integration' ),
			'active'          => __( 'Active', 'wp-loyalty-mailchimp-integration' ),
			'inactive'        => __( 'Inactive', 'wp-loyalty-mailchimp-integration' ),
			'test_connection' => __( 'Test Connection', 'wp-loyalty-mailchimp-integration' ),
		];
	}

}