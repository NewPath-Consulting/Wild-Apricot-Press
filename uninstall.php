<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
	wp_die(sprintf(__('%s should only be called when uninstalling the plugin.', 'wawp'), __FILE__ ));
	exit;
}

// Delete entries in wp_options table
delete_option('wawp_wal_name');

delete_option('wawp_addons');
delete_option('wawp_license_keys');

// Remove Login/Logout from the navigation bar
// First, get menu items of navigation menu
$menu_name = 'primary'; // will change this based on what user selects
$menu_items = wp_get_nav_menu_items($menu_name);
?>
