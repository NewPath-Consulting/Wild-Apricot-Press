<?php
require_once plugin_dir_path(__FILE__) . 'src/Activator.php';
require_once plugin_dir_path(__FILE__) . 'src/Addon.php';

use WAWP\Activator;
use WAWP\Addon;

if (!defined('WP_UNINSTALL_PLUGIN')) {
	wp_die(sprintf(__('%s should only be called when uninstalling the plugin.', 'wawp'), __FILE__ ));
	exit;
}

// Remove WAWP Login/Logout page
$wawp_wal_page_id = get_option('wawp_wal_page_id');
if (isset($wawp_wal_page_id) && $wawp_wal_page_id != '') {
	wp_delete_post($wawp_wal_page_id, true); // delete page entirely
}
delete_option('wawp_wal_page_id');

// Delete entries in wp_options table
delete_option('wawp_wal_name');
delete_option('wawp_wal_page_id');
delete_option('wawp_license_form_nonce');
delete_option('wawp_all_memberships_key');
delete_option('wawp_all_groups_key');
delete_option('wawp_wa_credentials_valid');
delete_option('wawp_restriction_name');

// Delete the added post meta data to the restricted pages
// Get array of restricted pages
$restricted_pages = get_option('wawp_array_of_restricted_pages');
// Loop through each page and delete our extra post meta
if (!empty($restricted_pages)) {
	foreach ($restricted_pages as $restricted_page_id) {
		delete_post_meta($restricted_page_id, 'wawp_restricted_groups');
		delete_post_meta($restricted_page_id, 'wawp_restricted_levels');
		delete_post_meta($restricted_page_id, 'wawp_is_page_restricted');
	}
}
// delete_post_meta(2, 'wawp_groups');
// delete_post_meta(2, 'wawp_levels');
// Delete restricted pages option value
delete_option('wawp_array_of_restricted_pages');

Addon::instance()::delete();


?>
