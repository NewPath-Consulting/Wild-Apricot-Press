<?php

namespace WAWP;

class Activator {

	/**
	 * Activates the WA4WP plugin.
	 *
	 * Write the full details of what happens here.
	 */
	public static function activate() {
		// Activation code

		// Add the Login/Logout button to the menu
		// Filter for adding the Wild Apricot login to navigation menu
		add_filter('wp_nav_menu_items', array('Activator', 'create_wa_login_logout'), 10, 2); // 2 arguments
	}

	// see: https://developer.wordpress.org/reference/functions/wp_create_nav_menu/
	// Also: https://www.wpbeginner.com/wp-themes/how-to-add-custom-items-to-specific-wordpress-menus/
	// https://wordpress.stackexchange.com/questions/86868/remove-a-menu-item-in-menu
	public function create_wa_login_logout($items, $args) {
		do_action('qm/debug', 'Adding login in menu!');
		// Get login url based on user's Wild Apricot site
		$login_url = $this->get_base_api();
		$logout_url = '';
		// Check if user is logged in or logged out
		if (is_user_logged_in() && $args->theme_location == 'primary') { // Logout
			$items .= '<li><a href="'. wp_logout_url() .'">Log Out</a></li>';
		} elseif (!is_user_logged_in() && $args->theme_location == 'primary') { // Login
			$items .= '<li><a href="'. $login_url .'">Log In</a></li>';
		}

		// Printing out
		$menu_name = 'primary'; // will change this based on what user selects
		$menu_items = wp_get_nav_menu_items($menu_name);
		do_action('qm/debug', 'menu items: ' . $menu_items);

		$this->log_menu_items = $items;
		return $items;
	}
}
?>
