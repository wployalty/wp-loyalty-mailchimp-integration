<?php

namespace WLMI\App;

use WLMI\App\Controller\Admin\Labels;
use WLMI\App\Controller\Admin\Settings;
use WLMI\App\Controller\Common;
use WLMI\App\Controller\Guest;
use WLMI\App\Controller\Member;

defined( 'ABSPATH' ) or die;

class Router {
	/**
	 * Initialize the necessary actions and filters based on the context.
	 *
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ Common::class, 'addMenu' ] );
			add_action( 'admin_footer', [ Common::class, 'hideMenu' ] );
			add_action( 'admin_enqueue_scripts', [ Common::class, 'adminScripts' ], 100 );

			//common
			add_action( 'wp_ajax_wlmi_launcher_local_data', [ Labels::class, 'getLocalData' ] );
			add_action( 'wp_ajax_wlmi_get_launcher_labels', [ Labels::class, 'getLabels' ] );
			// setting
			add_action( 'wp_ajax_wlmi_launcher_settings', [ Settings::class, 'getSettings' ] );
			//save settings
			add_action( 'wp_ajax_wlmi_launcher_save_settings', [ Settings::class, 'saveSettings' ] );
			add_action( 'wp_ajax_wlmi_test_connection', [ Settings::class, 'testConnection' ] );
		}
		add_filter( 'wlr_internal_addons_list', [ Common::class, 'addInternalAddons' ] );


	}
}