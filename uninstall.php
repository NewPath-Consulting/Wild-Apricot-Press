<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
	wp_die(sprintf(__('%s should only be called when uninstalling the plugin.', 'wawp'), __FILE__ ));
	exit;
}

// Delete entries in wp_options table
delete_option('wawp_wal_name');

delete_option('wawp_addons');
delete_option('wawp_license_keys');
?>
