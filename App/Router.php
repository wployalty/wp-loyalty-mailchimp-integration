<?php

namespace WLMI\App;

use WLMI\App\Controller\Admin\Labels;
use WLMI\App\Controller\Admin\Settings;
use WLMI\App\Controller\Admin\License;
use WLMI\App\Controller\Admin\Api;
use WLMI\App\Controller\Common;
use WLMI\App\Controller\MigrationBatch;
use WLMI\App\Controller\Sync;
use WLMI\App\Helper\License as LicenseHelper;

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
			add_action( 'wp_ajax_wlmi_test_connection', [ Api::class, 'testConnection' ] );
			add_action( 'wp_ajax_wlmi_get_lists', [ Api::class, 'getLists' ] );
			add_action( 'wp_ajax_wlmi_get_migration_status', [ Api::class, 'getMigrationStatus' ] );

			// license
			LicenseHelper::init();
			add_action( 'wp_ajax_wlmi_activate_license', [ License::class, 'activate' ] );
			add_action( 'wp_ajax_wlmi_deactivate_license', [ License::class, 'deActivate' ] );
			add_action( 'wp_ajax_wlmi_check_license_status', [ License::class, 'checkStatus' ] );
			add_filter( 'wlmi_get_settings_data', [ LicenseHelper::class, 'appendLicenseToSettings' ] );
			add_action( 'in_admin_header', [ LicenseHelper::class, 'showHeaderNotice' ] );
		}
		add_filter( 'wlr_internal_addons_list', [ Common::class, 'addInternalAddons' ] );
		add_action( 'wlr_customer_points_balance_changed', [ Sync::class, 'syncMember' ], 10, 6 );
		add_filter( 'wlr_delete_customer', [ Sync::class, 'onDeleteCustomer' ], 10, 2 );
		add_action( 'wlr_import_completed', [ MigrationBatch::class, 'onImportCompleted' ], 10, 0 );
		add_action( 'wlmi_process_mailchimp_migration_batch', [ MigrationBatch::class, 'processBatch' ], 10, 1 );
		add_action( 'wlmi_check_migration_errors', [ MigrationBatch::class, 'checkMigrationErrors' ], 10, 1 );
	}
}
