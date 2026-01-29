<?php
/**
 * Plugin Name: WPLoyalty - Mailchimp Integration
 * Plugin URI: https://www.wployalty.net
 * Description: Mailchimp integration for WPLoyalty.
 * Version: 1.0.0
 * Author: Wployalty
 * Slug: wp-loyalty-mailchimp-integration
 * Text Domain: wp-loyalty-mailchimp-integration
 * Domain Path: /i18n/languages/
 * Requires Plugins: woocommerce, wp-loyalty-rules
 * Requires at least: 4.9.0
 * WC requires at least: 6.5
 * WC tested up to: 9.8
 * Author URI: https://wployalty.net/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WLMI\App\Helper\Plugin;
use WLMI\App\Router;
use WLMI\App\Setup;

defined( 'ABSPATH' ) or die;

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( FeaturesUtil::class ) ) {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
	}
} );
if ( ! function_exists( 'getWLRPluginVersion' ) ) {
	function getWLRPluginVersion() {
		if ( defined( 'WLR_PLUGIN_VERSION' ) ) {
			return WLR_PLUGIN_VERSION;
		}
		$version = getWLMILoyaltyVersion(false);
		if ( $version == '1.0.0' ) {
			$version = getWLMILoyaltyVersion();
		}

		return $version;
	}
}
if ( ! function_exists( 'getWLMILoyaltyVersion' ) ) {
	function getWLMILoyaltyVersion( bool $force = true ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$folder = 'wp-loyalty-rules';
		$file   = 'wp-loyalty-rules.php';
		if ( $force ) {
			$folder = 'wployalty';
			$file   = 'wp-loyalty-rules-lite.php';
		}
		$plugin_file = $folder . '/' . $file;
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			return '1.0.0';
		}
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
		}
		if(!(in_array( $plugin_file, $active_plugins ) || array_key_exists( $plugin_file, $active_plugins ))){
			return '1.0.0';
		}
		$plugin_folder = get_plugins( '/' . $folder );

		return $plugin_folder[ $file ]['Version'] ?? '1.0.0';
	}
}
if ( ! function_exists( 'isWLMILoyaltyActive' ) ) {
	function isWLMILoyaltyActive() {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
		}

		return in_array( 'wp-loyalty-rules/wp-loyalty-rules.php', $active_plugins ) || array_key_exists( 'wp-loyalty-rules/wp-loyalty-rules.php', $active_plugins )
		       || in_array( 'wployalty/wp-loyalty-rules-lite.php', $active_plugins ) || array_key_exists( 'wployalty/wp-loyalty-rules-lite.php', $active_plugins );
	}
}

if ( ! isWLMILoyaltyActive() || ! ( (int) version_compare( getWLRPluginVersion(), '1.4.3', '>=' ) > 0 ) ) {
	return;
}

defined( 'WLMI_PLUGIN_NAME' ) or define( 'WLMI_PLUGIN_NAME', 'WPLoyalty - Mailchimp Integration' );
defined( 'WLMI_MINIMUM_PHP_VERSION' ) or define( 'WLMI_MINIMUM_PHP_VERSION', '7.4.0' );
defined( 'WLMI_MINIMUM_WP_VERSION' ) or define( 'WLMI_MINIMUM_WP_VERSION', '4.9' );
defined( 'WLMI_MINIMUM_WC_VERSION' ) or define( 'WLMI_MINIMUM_WC_VERSION', '6.0' );
defined( 'WLMI_MINIMUM_WLR_VERSION' ) or define( 'WLMI_MINIMUM_WLR_VERSION', '1.4.3' );
defined( 'WLMI_PLUGIN_VERSION' ) or define( 'WLMI_PLUGIN_VERSION', '1.0.0' );
defined( 'WLMI_PLUGIN_SLUG' ) or define( 'WLMI_PLUGIN_SLUG', 'wp-loyalty-mailchimp-integration' );
defined( 'WLMI_PLUGIN_FILE' ) or define( 'WLMI_PLUGIN_FILE', __FILE__ );
defined( 'WLMI_PLUGIN_DIR' ) or define( 'WLMI_PLUGIN_DIR', str_replace( '\\', '/', __DIR__ ) );
defined( 'WLMI_PLUGIN_PATH' ) or define( 'WLMI_PLUGIN_PATH', str_replace( '\\', '/', __DIR__ ) . '/' );
defined( 'WLMI_PLUGIN_URL' ) or define( 'WLMI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
if ( ! class_exists( Plugin::class ) ) {
	// Autoload the vendor
	require_once __DIR__ . '/vendor/autoload.php';
}
add_action( 'plugins_loaded', function () {
	if ( class_exists( Router::class ) && class_exists( \Wlr\App\Router::class ) ) {
		if ( Plugin::checkDependencies() ) {
			$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
				'https://github.com/wployalty/wp-loyalty-mailchimp-integration',
				__FILE__,
				'wp-loyalty-mailchimp-integration'
			);
			$myUpdateChecker->getVcsApi()->enableReleaseAssets();

			Router::init();
		}
	}
} );
if ( class_exists( Plugin::class ) ) {
	Setup::init();
}