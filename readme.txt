=== WPLoyalty - Mailchimp Integration ===
Contributors: wployalty
Donate link: https://wployalty.net
Tags: wployalty, mailchimp, woocommerce, loyalty, points
Requires at least: 6.0
Tested up to: 6.9
WC requires at least: 10.0
WC tested up to: 10.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connect WPLoyalty with Mailchimp to sync customer loyalty points and profile data to your selected audience list.

== Description ==

WPLoyalty - Mailchimp Integration helps you keep your Mailchimp audience aligned with customer loyalty activity in WooCommerce.

Key capabilities:
- Connect and validate your Mailchimp API key.
- Select and save the Mailchimp audience/list used for sync.
- Keep merge fields ready for loyalty data sync.
- Sync member data when customer points are updated in WPLoyalty.
- Remove members from the selected list when customer loyalty records are deleted.
- Run migration sync jobs for existing loyalty customers.
- Manage license activation/deactivation from the plugin admin page.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate **WPLoyalty - Mailchimp Integration** from the Plugins page.
3. Ensure these plugins are active:
   - WooCommerce
   - WPLoyalty (`wp-loyalty-rules`), version 1.4.4 or higher
4. Open the WPLoyalty Mailchimp Integration settings page.
5. Enter your Mailchimp API key and connect.
6. Select your Mailchimp audience/list and save settings.

== Frequently Asked Questions ==

= Why is the plugin not loading after activation? =
The add-on requires WooCommerce and WPLoyalty (minimum version 1.4.4). If dependencies are missing, the add-on will not initialize.

= Which PHP version is required? =
PHP 7.4 or higher.

= Does this plugin create or update Mailchimp merge fields? =
Yes. It ensures required merge fields exist for the selected audience before saving settings.

= Can I sync existing loyalty customers? =
Yes. The plugin includes migration sync support for existing customer records.

== Changelog ==

= 1.0.0 =
- Initial stable release.

== Upgrade Notice ==