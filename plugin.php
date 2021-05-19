<?php

// namespace WAWP;
/**
 * Plugin Name:       Wild Apricot for WordPress (WA4WP)
 * Plugin URI:        https://newpathconsulting.com/wild-apricot-for-wordpress
 * Description:       Integrates your Wild Apricot account with your WordPress website!
 * Version:           1.0.0
 * Requires at least: 5.3
 * Requires PHP:      5.6
 * Author:            NewPath Consulting
 * Author URI:        https://newpathconsulting.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pdev
 * Domain Path:       /public/lang
 */

/*
Copyright (C) 2021 NewPath Consulting

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

do_action('qm/debug', 'outside!');

// Activation hook
register_activation_hook(__FILE__, function() {
	// do_action('qm/debug', 'activation in plugin.php!');
	add_option('hello_table', 'hello_table');
	require_once plugin_dir_path(__FILE__) . 'src/Activator.php';
	Activator::activate();
} );

// Add menu
add_action( 'admin_menu', 'wawp_create_menu' );

function wawp_create_menu() {

    //create custom top-level menu
    add_menu_page( 'WA4WP Settings Page', 'WA4WP',
        'manage_options', 'wawp-options', 'wawp_settings_page',
        'dashicons-businesswoman', 6 );

    //create submenu items
    // add_submenu_page( 'pdev-options', 'About The PDEV Plugin', 'About', 'manage_options',
    //     'pdev-about', 'pdev_about_page' );
    // add_submenu_page( 'pdev-options', 'Help With The PDEV Plugin', 'Help', 'manage_options',
    //     'pdev-help', 'pdev_help_page' );
    // add_submenu_page( 'pdev-options', 'Uninstall The PDEV Plugin', 'Uninstall', 'manage_options',
    //     'pdev-uninstall', 'pdev_uninstall_page' );

}

//placerholder function for the settings page
function wawp_settings_page() {
	// Display WA4WP Settings page

}

// //placerholder function for the about page
// function pdev_about_page() {

// }

// //placerholder function for the help page
// function pdev_help_page() {

// }

// //placerholder function for the uninstall page
// function pdev_uninstall_page() {

// }

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
	require_once plugin_dir_path(__FILE__) . 'src/Deactivator.php';
	Deactivator::deactivate();
} );

?>
