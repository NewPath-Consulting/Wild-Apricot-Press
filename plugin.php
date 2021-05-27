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
	add_option('hello_table', 'hello_table');
	require_once plugin_dir_path(__FILE__) . 'src/Activator.php';
	Activator::activate();
} );

// Enqueue stylesheet
add_action('admin_enqueue_scripts', 'wawp_enqueue_admin_script');
function wawp_enqueue_admin_script($hook) {
    wp_enqueue_style('wawp-styles-admin', plugin_dir_url(__FILE__) . 'css/wawp-styles-admin.css', array(), '1.0');
}

// Add menu
add_action( 'admin_menu', 'wawp_create_menu' );
function wawp_create_menu() {

    //create custom top-level menu
    add_menu_page( 'WA4WP Settings Page', 'WA4WP',
        'manage_options', 'wawp-options', 'wawp_settings_page',
        'dashicons-businesswoman', 6 );

    //create submenu items
    add_submenu_page( 'wawp-options', 'Wild Apricot Login', 'Login', 'manage_options',
        'wawp_wal', 'wawp_login_page' );
    // add_submenu_page( 'pdev-options', 'Help With The PDEV Plugin', 'Help', 'manage_options',
    //     'pdev-help', 'pdev_help_page' );
    // add_submenu_page( 'pdev-options', 'Uninstall The PDEV Plugin', 'Uninstall', 'manage_options',
    //     'pdev-uninstall', 'pdev_uninstall_page' );

}

//placerholder function for the settings page
function wawp_settings_page() {
	// Display WA4WP Settings page
    ?>
    <div class="wrap">
        <h1>Wild Apricot for WordPress (WA4WP)</h1>
    </div>
    <?php
}

//placerholder function for the login page
function wawp_login_page() {
    // echo '<p>Hello and welcome!</p>';
    ?>
    <div class="wrap">
        <h1>Connect Wild Apricot with WordPress!</h1>
        <div class="waSettings">
            <div class="loginChild">
                <p>In order to connect your Wild Apricot with your WordPress website, WA4WP requires the following credentials from your Wild Apricot account:</p>
                <ul>
                    <li>API key</li>
                    <li>Client ID</li>
                    <li>Client secret</li>
                </ul>
                <p>If you currently do not have these credentials, no problem! Please follow the steps below to obtain them.</p>
            </div>
            <div class="loginChild">
                <!-- <h3>Please enter your credentials here:</h3> -->
                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'wawp_wal_options' );
                    do_settings_sections( 'wawp_wal' );
                    submit_button( 'Save', 'primary' );
                    ?>
                </form>
                <!-- Check if form is valid -->
                <?php
                $user_options = get_option( 'wawp_wal_options' );
                if (!isset($user_options['api_key']) || !isset($user_options['client_ID']) || !isset($user_options['client_secret']) || $user_options['api_key'] == '' || $user_options['client_ID'] == '' || $user_options['client_secret'] == '') { // not valid
                    echo '<p style="color:red">Invalid credentials!</p>';
                } else {
                    echo '<p style="color:green">Success! Credentials saved!</p>';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

// Register and define settings
add_action('admin_init', 'wawp_wal_admin_init');

function wawp_wal_admin_init() {
    // define the setting arguments
    $args = array(
        'type' => 'string',
        'sanitize_callback' => 'wawp_wal_validate_options',
        'default' => NULL
    );

    // Register settings
    register_setting( 'wawp_wal_options', 'wawp_wal_options', $args );

    // Add settings section
    add_settings_section(
        'wawp_wal_main',
        'Wild Apricot Login',
        'wawp_wal_section_text',
        'wawp_wal'
    );

    // Create settings field for api-key (originally name)
    add_settings_field(
        'wawp_wal_api_key',
        'API Key:',
        'wawp_wal_setting_api_key',
        'wawp_wal',
        'wawp_wal_main'
    );

    // Create settings field for client ID
    add_settings_field(
        'wawp_wal_client_ID',
        'Client ID:',
        'wawp_wal_setting_client_ID',
        'wawp_wal',
        'wawp_wal_main'
    );

    // Create settings field for client secret
    add_settings_field(
        'wawp_wal_client_secret',
        'Client Secret:',
        'wawp_wal_setting_client_secret',
        'wawp_wal',
        'wawp_wal_main'
    );
}

// Draw section header
function wawp_wal_section_text() {
    echo '<p>Enter your Wild Apricot credentials here.</p>';
}

// Display and fill the Name form field
function wawp_wal_setting_api_key() {
    // get option 'text_string' value from the database
    $options = get_option( 'wawp_wal_options' );
    // Check that api key exists first
    $api_key = '';
    if (isset($options['api_key'])) { // api key set or is empty
        $api_key = $options['api_key'];
    }
    // do_action( 'qm/debug', $api_key );
    // echo the field
    // echo "<input id='name' name='wawp_wal_options['name']' type='text' value='" . esc_attr( $name ) . "'/>";
    echo "<input id='api_key' name='wawp_wal_options[api_key]'
        type='text' value='" . esc_attr( $api_key ) . "' />";
}

// Display and fill the Client ID field
function wawp_wal_setting_client_ID() {
    // get option 'text_string' value from the database
    $options = get_option( 'wawp_wal_options' );
    $client_ID = '';
    if (isset($options['client_ID'])) {
        $client_ID = $options['client_ID'];
    }
    // do_action( 'qm/debug', $client_ID );
    // echo the field
    // echo "<input id='name' name='wawp_wal_options['name']' type='text' value='" . esc_attr( $name ) . "'/>";
    echo "<input id='client_ID' name='wawp_wal_options[client_ID]'
        type='text' value='" . esc_attr( $client_ID ) . "' />";
}

// Display and fill the Client Secret field
function wawp_wal_setting_client_secret() {
    // get option 'text_string' value from the database
    $options = get_option( 'wawp_wal_options' );
    $client_secret = '';
    if (isset($options['client_secret'])) {
        $client_secret = $options['client_secret'];
    }
    // do_action( 'qm/debug', $client_ID );
    // echo the field
    // echo "<input id='name' name='wawp_wal_options['name']' type='text' value='" . esc_attr( $name ) . "'/>";
    echo "<input id='client_secret' name='wawp_wal_options[client_secret]'
        type='text' value='" . esc_attr( $client_secret ) . "' />";
}

// Validate user input (text only)
function wawp_wal_validate_options( $input ) {
    // do_action('qm/debug', 'validate input is ' . $input);
    $valid = array();
    // if (isset($_POST['wawp_wal_options[name]'])) {
    //     echo '<p>Invalid!</p>';
    //     do_action('qm/debug', 'invalind input!');
    // }
    // $valid['name'] = preg_replace('/^[\w]+$/', '', $input['name']);
    $valid_api_key = preg_match('/^[\w]+$/', $input['api_key']);
    if (!$valid_api_key) { // alert user that they have invalid input!
        $valid['api_key'] = '';
    } else { // valid input
        $valid['api_key'] = $input['api_key'];
    }
    // Client ID
    $valid_client_ID = preg_match('/^[\w]+$/', $input['client_ID']);
    if (!$valid_client_ID) { // alert user that they have invalid input!
        $valid['client_ID'] = '';
    } else { // valid input
        $valid['client_ID'] = $input['client_ID'];
    }
    // Client secret
    $valid_client_secret = preg_match('/^[\w]+$/', $input['client_secret']);
    if (!$valid_client_secret) { // alert user that they have invalid input!
        $valid['client_secret'] = '';
    } else { // valid input
        $valid['client_secret'] = $input['client_secret'];
    }

    return $valid;
}

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
