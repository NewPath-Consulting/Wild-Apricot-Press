<?php

// namespace WAWP;

class Activator {
	function wawp_create_menu() {
		add_option('wawp_create_menu_key', 'wawp_create_menu_yo');
		add_menu_page('WA4WP Settings Page', 'WA4WP', 'manage_options', 'wawp-options', 'wawp_settings_page', 'dashicons-businesswoman', 99);
	}

	/**
	 * Activates the WA4WP plugin.
	 *
	 * Write the full details of what happens here.
	 */
	public static function activate() {
		// Activation code
		// Set up menu
		add_option('we_activate', 'we_activate');
		// wawp_create_menu();
		// add_action('admin_menu', 'wawp_create_menu');
	}
}
?>
