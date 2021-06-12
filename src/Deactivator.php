<?php

namespace WAWP;

class Deactivator {

	public static function deactivate() {
		// Deactivation code

		// Remove Login/Logout from the navigation bar
		// check if menu_items is NULL or not
		$menu_items = get_option('wawp_wa-integration_login_menu_items'); // false if it does not exist
		if ($menu_items) { // NOT false, so menu can be accessed
			// Remove Login/Logout button
			wp_delete_post('wawp_login_logout_button');
		}
		// Delete entry from table
		delete_option('wawp_wa-integration_login_menu_items');
	}
}
?>
