<?php

namespace WAWP;

class Deactivator {
	public static function deactivate() {
		// Deactivation code

		// Remove Login/Logout from the navigation bar
		// First, get menu items of navigation menu
		$menu_name = 'primary'; // will change this based on what user selects
		$menu_items = wp_get_nav_menu_items($menu_name);
		do_action('qm/debug', $menu_items);
	}
}
?>
