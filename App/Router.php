<?php

namespace WLMI\App;

use WLMI\App\Controller\Admin\Labels;
use WLMI\App\Controller\Admin\Settings;
use WLMI\App\Controller\Admin\Api;
use WLMI\App\Controller\Common;
use WLMI\App\Controller\MigrationBatch;
use WLMI\App\Controller\Sync;

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
			add_action( 'wp_ajax_wlmi_admin_local_data', [ Labels::class, 'getLocalData' ] );
			add_action( 'wp_ajax_wlmi_get_labels', [ Labels::class, 'getLabels' ] );
			// setting
			add_action( 'wp_ajax_wlmi_admin_settings', [ Settings::class, 'getSettings' ] );
			//save settings
			add_action( 'wp_ajax_wlmi_save_settings', [ Settings::class, 'saveSettings' ] );
			add_action( 'wp_ajax_wlmi_connect_mailchimp', [ Api::class, 'connectMailchimp' ] );
			add_action( 'wp_ajax_wlmi_disconnect_mailchimp', [ Api::class, 'disconnectMailchimp' ] );
			add_action( 'wp_ajax_wlmi_get_lists', [ Api::class, 'getLists' ] );
			add_action( 'wp_ajax_wlmi_get_migration_status', [ Api::class, 'getMigrationStatus' ] );
			add_action( 'wp_ajax_wlmi_download_failed_users_csv', [ Api::class, 'downloadFailedUsersCSV' ] );
			add_action( 'wp_ajax_wlmi_perform_sync', [ Api::class, 'performSync' ] );
		}
		add_filter( 'wlr_internal_addons_list', [ Common::class, 'addInternalAddons' ] );
		add_action( 'wlr_customer_points_balance_changed', [ Sync::class, 'syncMember' ], 10, 6 );
		add_filter( 'wlr_delete_customer', [ Sync::class, 'onDeleteCustomer' ], 10, 2 );
		add_action( Sync::SYNC_ACTION_HOOK, [ Sync::class, 'processQueuedMemberSync' ], 10, 1 );
		add_action( Sync::DELETE_ACTION_HOOK, [ Sync::class, 'processQueuedMemberDelete' ], 10, 1 );
		add_action( 'wlr_import_completed', [ MigrationBatch::class, 'onImportCompleted' ], 10, 0 );
		add_action( 'wlmi_process_mailchimp_migration_batch', [ MigrationBatch::class, 'processBatch' ], 10, 1 );
		add_action( 'wlmi_check_batch_result', [ MigrationBatch::class, 'checkBatchResult' ], 10, 1 );
	}
}
