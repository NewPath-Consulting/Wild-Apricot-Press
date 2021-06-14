<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
	wp_die(sprintf(__('%s should only be called when uninstalling the plugin.', 'wawp'), __FILE__ ));
	exit;
}

// Remove WAWP Login/Logout page
$wawp_wal_page_id = get_option('wawp_wal_page_id');
if (isset($wawp_wal_page_id) && $wawp_wal_page_id != '') {
	wp_delete_post($wawp_wal_page_id);
}
delete_option('wawp_wal_page_id');

// Delete entries in wp_options table
delete_option('wawp_wal_name');

delete_option('wawp_addons');
delete_option('wawp_license_keys');


?>
