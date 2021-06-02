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

// Activation hook
register_activation_hook(__FILE__, function() {
	// do_action('qm/debug', 'activation in plugin.php!');
	require_once plugin_dir_path(__FILE__) . 'src/Activator.php';
	Activator::activate();
} );

// Enqueue stylesheet
add_action('admin_enqueue_scripts', 'wawp_enqueue_admin_script');
function wawp_enqueue_admin_script($hook) {
    wp_enqueue_style('wawp-styles-admin', plugin_dir_url(__FILE__) . 'css/wawp-styles-admin.css', array(), '1.0');
}

// Include other classes required for each page
include 'src/WAWPLoginPage.php';
// $wawp_login_obj = new WAWPLoginPage();
// $wawp_login_obj->wawp_wal_init();

// Add menu
add_action( 'admin_menu', 'wawp_create_menu' );
function wawp_create_menu() {
    // // Include other classes required for each page
    // require_once("src/WAWPLoginPage.php");

    //create custom top-level menu
    add_menu_page( 'WA4WP Settings Page', 'WA4WP',
        'manage_options', 'wawp_options', 'wawp_settings_page',
        'dashicons-businesswoman', 6 );

    //create submenu items
    //add_submenu_page( 'wawp-options', 'Wild Apricot Login', 'Login', 'manage_options',
        // 'wawp_wal', 'wawp_login_page' );
    add_submenu_page( 'wawp_options', 'Wild Apricot Login', 'Login', 'manage_options',
       'wawp_wal', ['WAWPLoginPage', 'wawp_construct_page'] );


    // add_submenu_page( 'pdev-options', 'Help With The PDEV Plugin', 'Help', 'manage_options',
    //     'pdev-help', 'pdev_help_page' );
    // add_submenu_page( 'pdev-options', 'Uninstall The PDEV Plugin', 'Uninstall', 'manage_options',
    //     'pdev-uninstall', 'pdev_uninstall_page' );

}

// Settings page
function wawp_settings_page() {
	// Display WA4WP Settings page
    ?>
    <div class="wrap">
        <h1>Wild Apricot for WordPress (WA4WP)</h1>
    </div>
    <?php
}

// Register and define settings
add_action('admin_init', ['WAWPLoginPage', 'wawp_wal_admin_init']);

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
