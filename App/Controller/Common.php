<?php

namespace WLMI\App\Controller;

use WLMI\App\Helper\Input;
use WLMI\App\Helper\Util;
use WLMI\App\Helper\WC;

defined( 'ABSPATH' ) or die;

class Common {
	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public static function addMenu() {
		if ( WC::hasAdminPrivilege() ) {
			add_menu_page(
				WLMI_PLUGIN_NAME, WLMI_PLUGIN_NAME, 'manage_woocommerce', WLMI_PLUGIN_SLUG, [
				self::class,
				'displayMenuContent'
			], 'dashicons-megaphone', 57
			);
		}
	}

	/**
	 * Hide add-on menu.
	 *
	 * @return void
	 */
	public static function hideMenu() {
		?>
        <style>
            #toplevel_page_wp-loyalty-mailchimp-integration {
                display: none !important;
            }
        </style>
		<?php
	}

	/**
	 * Display menu content.
	 *
	 * @return void
	 */
	public static function displayMenuContent() {
		if ( ! WC::hasAdminPrivilege() ) {
			return;
		}
		$params = apply_filters( 'wlmi_before_mailchimp_admin_page', [] );
		wc_get_template( 'main.php', $params, WLMI_PLUGIN_SLUG, WLMI_PLUGIN_DIR . '/App/View/Admin/' );
	}

	/**
	 * Loads necessary admin scripts and styles.
	 *
	 * This method enqueues required scripts and styles for the admin area. It removes unwanted actions,
	 * enqueues specific stylesheets and scripts, loads JavaScript files, and localizes data for the scripts.
	 *
	 * @return void
	 */
	public static function adminScripts() {
		if ( Input::get( 'page' ) != WLMI_PLUGIN_SLUG ) {
			return;
		}
		$suffix = '.min';
		if ( defined( 'SCRIPT_DEBUG' ) ) {
			$suffix = SCRIPT_DEBUG ? '' : '.min';
		}
		// Remove other actions
		array_map( 'remove_all_actions', apply_filters( 'wlmi_remove_other_plugin_enqueue_actions', [
			'admin_head',
			'admin_enqueue_scripts'
		] ) );
		$cache_fix     = apply_filters( 'wlmi_load_admin_asset_with_time', true );
		$add_cache_fix = ( $cache_fix ) ? '&t=' . time() : '';
		// remove admin notice
		remove_all_actions( 'admin_notices' );

		wp_enqueue_style( WLMI_PLUGIN_SLUG . '-wlr-font', WLR_PLUGIN_URL . 'Assets/Site/Css/wlr-fonts' . $suffix . '.css', [], WLR_PLUGIN_VERSION . $add_cache_fix );
		wp_enqueue_style( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Css/alertify.css', [], WLR_PLUGIN_VERSION . $add_cache_fix );
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
		wp_enqueue_script( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Js/alertify.js', [ 'jquery' ], WLR_PLUGIN_VERSION . $add_cache_fix );
		$common_path   = WLMI_PLUGIN_DIR . '/assets/admin/js/dist';
		$js_files      = Util::getDirFileLists( $common_path );
		$localize_name = '';
		foreach ( $js_files as $file ) {
			$path         = str_replace( WLMI_PLUGIN_PATH, '', $file );
			$js_file_name = str_replace( $common_path . '/', '', $file );
			$js_name      = WLMI_PLUGIN_SLUG . '-react-ui-' . substr( $js_file_name, 0, - 3 );
			$js_file_url  = WLMI_PLUGIN_URL . $path;
			if ( $js_file_name == 'main.bundle.js' ) {
				$localize_name = $js_name;
                // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
				wp_register_script( $js_name, $js_file_url, [ 'jquery' ], WLMI_PLUGIN_VERSION . $add_cache_fix );
				wp_enqueue_script( $js_name );
			}
		}

		//register the scripts
		$localize_data = [
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			//nonce
			'local_data_nonce'    => wp_create_nonce( 'local_data' ),
		];
		wp_localize_script( $localize_name, 'wlmi_settings_form', $localize_data );
	}

	/**
	 * Adds internal addons to the provided list of addons.
	 *
	 * This method adds internal addons to the list of addons passed as a parameter. It checks for a specific addon,
	 * updates the addon list accordingly, and then adds a new internal addon to the list.
	 *
	 * @param array $add_ons An array containing the list of addons.
	 *
	 * @return array The updated list of addons with internal addons added.
	 */
	public static function addInternalAddons( $add_ons ) {
		if ( ! empty( $add_ons['wp-loyalty-launcher'] ) ) {
			unset( $add_ons['wp-loyalty-launcher'] );
		}
		update_option( 'wlmi_is_launcher_plugin_activated', true );
		$add_ons['wp-loyalty-mailchimp-integration'] = [
			'name'         => esc_html__( 'WPLoyalty - Mailchimp Integration', 'wp-loyalty-mailchimp-integration' ),
			'description'  => __( 'The add-on integrates WPLoyalty with your Mailchimp.', 'wp-loyalty-mailchimp-integration' ),
			'icon_url'     => \Wlr\App\Helpers\Util::getImageUrl( 'wp-loyalty-mailchimp-integration' ),
			'page_url'     => '{addon_page}',
			'document_url' => '',
			'is_external'  => true,
			'is_pro'       => false,
			'dependencies' => [],
			'plugin_file'  => 'wp-loyalty-mailchimp-integration/wp-loyalty-mailchimp-integration.php',
		];

		return $add_ons;
	}



}