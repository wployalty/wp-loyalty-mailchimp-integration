<?php

namespace WLMI\App\Controller\Admin;

use WLMI\App\Helper\Util;
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
		$short_codes     = [];

		$localize = [
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
			'admin_nonce'             => WC::createNonce( 'wlmi_admin_settings' ),
			'settings_nonce'          => WC::createNonce( 'wlmi_admin_settings' ),
		];
		$localize = apply_filters( 'wlmi_admin_local_data', $localize );
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
			'upgrade_text'                 => __( 'Activate the license.', 'wp-loyalty-mailchimp-integration' ),
			'buy_pro_button_text'          => __( 'Enter License key', 'wp-loyalty-mailchimp-integration' ),
			'license_required_description' => __('Activate your license to configure the Mailchimp integration settings.','wp-loyalty-mailchimp-integration'),
			'buy_pro_url'                  => Util::getSettingsPageUrl('license'),

			'back'                         => __( 'Back', 'wp-loyalty-mailchimp-integration' ),
			'back_to_apps'                 => __( 'Back to WPLoyalty', 'wp-loyalty-mailchimp-integration' ),
			'icon'                         => __( 'Icon', 'wp-loyalty-mailchimp-integration' ),
			'icon_buttons'                 => [

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
			'title'                      => __( 'Mailchimp Settings', 'wp-loyalty-mailchimp-integration' ),
			'api_key'                    => __( 'Mailchimp API Key', 'wp-loyalty-mailchimp-integration' ),
			'placeholder'                => __( 'Enter your Mailchimp API Key', 'wp-loyalty-mailchimp-integration' ),
			'description'                => __( 'You can find your API key in your Mailchimp account settings.', 'wp-loyalty-mailchimp-integration' ),
			'status'                     => __( 'Status', 'wp-loyalty-mailchimp-integration' ),
			'active'                     => __( 'Active', 'wp-loyalty-mailchimp-integration' ),
			'inactive'                   => __( 'Inactive', 'wp-loyalty-mailchimp-integration' ),
			'connect'                    => __( 'Connect', 'wp-loyalty-mailchimp-integration' ),
			'disconnect'                 => __( 'Disconnect', 'wp-loyalty-mailchimp-integration' ),
			'list_label'                 => __( 'Select Mailchimp List', 'wp-loyalty-mailchimp-integration' ),
			'list_placeholder'           => __( 'Search or select a list', 'wp-loyalty-mailchimp-integration' ),
			'list_description'           => __( 'Choose the Mailchimp list where customers will be added', 'wp-loyalty-mailchimp-integration' ),
			'connect_required'           => __( 'Connect Mailchimp before saving.', 'wp-loyalty-mailchimp-integration' ),
			'list_required'              => __( 'Please select a Mailchimp list.', 'wp-loyalty-mailchimp-integration' ),
			// Search-related labels
			'search_placeholder'         => __( 'Type to search lists...', 'wp-loyalty-mailchimp-integration' ),
			'loading_message'            => __( 'Loading...', 'wp-loyalty-mailchimp-integration' ),
			'searching_message'          => __( 'Searching through lists...', 'wp-loyalty-mailchimp-integration' ),
			'no_results_message'         => __( 'No lists found', 'wp-loyalty-mailchimp-integration' ),
			// translators: %s will be replaced with total count
			'searching_progress_message' => __( 'Searching through %s lists...', 'wp-loyalty-mailchimp-integration' ),
			'scroll_for_more_message'    => __( 'Scroll for more...', 'wp-loyalty-mailchimp-integration' ),
			// Migration choice labels
			'migration_label'            => __( 'Migration existing users?', 'wp-loyalty-mailchimp-integration' ),
			'migration_placeholder'      => __( 'Select migration choice', 'wp-loyalty-mailchimp-integration' ),
			'migration_description'      => __( 'Choose your migration option, by default users added when their point updated', 'wp-loyalty-mailchimp-integration' ),
			'migration_choice_required'  => __( 'Please choose whether to migrate existing users.', 'wp-loyalty-mailchimp-integration' ),
			'migration_options'          => [
				[ 'label' => __( 'Yes', 'wp-loyalty-mailchimp-integration' ), 'value' => 'yes' ],
				[ 'label' => __( 'No', 'wp-loyalty-mailchimp-integration' ), 'value' => 'no' ],
			],
			// Migration Status Labels
			'migration_status_title'     => __( 'Migration Status', 'wp-loyalty-mailchimp-integration' ),
			'migration_status_subtitle'  => __( 'Sync progress for the selected Mailchimp list', 'wp-loyalty-mailchimp-integration' ),
			'migration_state_no_runs'    => __( 'No runs yet', 'wp-loyalty-mailchimp-integration' ),
			'migration_state_in_progress' => __( 'In progress', 'wp-loyalty-mailchimp-integration' ),
			'migration_state_completed_errors' => __( 'Completed with errors', 'wp-loyalty-mailchimp-integration' ),
			'migration_state_completed'  => __( 'Completed', 'wp-loyalty-mailchimp-integration' ),
			'migration_refresh_status'   => __( 'Refresh status', 'wp-loyalty-mailchimp-integration' ),
			'migration_total_ops'        => __( 'Total Operations', 'wp-loyalty-mailchimp-integration' ),
			'migration_success'          => __( 'Success', 'wp-loyalty-mailchimp-integration' ),
			'migration_failures'         => __( 'Failures', 'wp-loyalty-mailchimp-integration' ),
			'migration_batches'          => __( 'Batches', 'wp-loyalty-mailchimp-integration' ),
			'migration_failed_ops'       => __( 'failed operations detected.', 'wp-loyalty-mailchimp-integration' ),
			'csv_processing_message'     => __( 'Processing failed users CSV...', 'wp-loyalty-mailchimp-integration' ),
			'check_csv_status'           => __( 'Check Status', 'wp-loyalty-mailchimp-integration' ),
			'csv_ready_message'          => __( 'CSV file ready for download.', 'wp-loyalty-mailchimp-integration' ),
			'migration_download_csv'     => __( 'Download failed users CSV', 'wp-loyalty-mailchimp-integration' ),
			'csv_processing_failed'      => __( 'CSV processing failed. Please try again.', 'wp-loyalty-mailchimp-integration' ),
			'migration_download_error_file' => __( 'Download error file', 'wp-loyalty-mailchimp-integration' ),
			'migration_no_errors'        => __( 'No migration errors detected.', 'wp-loyalty-mailchimp-integration' ),
			'migration_last_checked'     => __( 'Last checked:', 'wp-loyalty-mailchimp-integration' ),
			'migration_no_runs_message'  => __( 'No migrations have run for this list yet. Migrations will appear here once started.', 'wp-loyalty-mailchimp-integration' ),
		];
	}

}
