<?php
/**
 * helpers.php
 * The purpose of this file is to hold common constants and one-off
 * functions used across files in the WAWP namespace.
 * 
 * @since 1.0b3
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @copyright 2022 NewPath Consulting
 */

namespace WAWP;

require_once __DIR__ . '/class-addon.php';
require_once __DIR__ . '/class-wa-api.php';

const CORE_SLUG = 'wawp';
const CORE_NAME = 'WildApricot Press (WAP)';


/**
 * @return bool true if the current page is the licensing settings page, false if not
 */
function is_licensing_submenu() {
    $current_url = get_current_url();
    return str_contains($current_url, 'admin.php?page=wawp-licensing');
}

/**
 * @return bool true if the current page is any wawp settings page, false if not
 */
function is_wawp_settings() {
    $current_url = get_current_url();
    return str_contains($current_url, CORE_SLUG);
}

/**
 * @return bool true if the license has just been submitted, false if not
 */
function license_submitted() {
    $current_url = get_current_url();
    return $current_url == 'admin.php?page=wawp-licensing&settings-updated=true';
}

/**
 * @return bool true if the current page is the wawp login page, false if not
 */
function is_wa_login_menu() {
    $current_url = get_current_url();
    return $current_url == 'admin.php?page=wawp-login';
}

/**
 * @return bool true if the current page is the WA user login page, false if not
 */
function is_user_login_page() {
    $current_url = get_current_url();
    return str_contains($current_url, 'wawp-wild-apricot-login');
}

/**
 * @return string url of the current page relative to the base url
 */
function get_current_url() {
    return basename(home_url($_SERVER['REQUEST_URI']));
}

/**
 * Returns the current tab. Used for finding which tab of the admin settings page
 * the user is on.
 *
 * @return string|null the current tab, or null if it's the main tab.
 */
function get_current_tab() {
    $current_url = get_current_url();
    $url_components = parse_url($current_url);
    parse_str($url_components['query'], $params);
    if (array_key_exists('tab', $params)) {
        return $params['tab'];
    }
    return null;
}

/**
 * @return bool true if the current page is the plugins admin page, false if not
 */
function is_plugin_page() {
    $current_url = get_current_url();

    return str_contains($current_url, 'plugins.php');
}

/**
 * @return bool true if the current page is the post editor, false if not
 */
function is_post_edit_page() {
    $current_url = get_current_url();
    return str_contains($current_url, 'post=') &&
           str_contains($current_url, 'action=edit');
}

/**
 * @param string $slug plugin referred to by a slug string
 * @return bool true if the plugin is the core WAP plugin, false if not
 */
function is_core($slug) {
    return $slug == CORE_SLUG;
}

/**
 * @param string $slug plugin referred to by a slug string
 * @return bool true if the plugin is an addon, false if not
 */
function is_addon($slug) {
    return !is_core($slug);
}

/**
 * Returns an array containing the keys of $arr all mapped to empty strings.
 *
 * @param array $arr
 * @return array
 */
function empty_string_array($arr) {
    $keys = array_keys($arr);
    $arr = array_fill_keys($keys, '');
    return $arr;
}

/**
 * Displays invalid nonce admin notice.
 *
 * @return void
 */
function invalid_nonce_error_message() {
    echo "<div class='notice notice-warning is-dismissable'><p>";
    echo "Invalid nonce error. Please try again.";
    echo "</p></div>";
    remove_action('admin_notices', 'WAWP\invalid_nonce_error_message');
}

/**
 * Disables the core plugin and resets the license keys.
 *
 * @return void
 */
function disable_core() {
    do_action('disable_plugin', CORE_SLUG, Addon::LICENSE_STATUS_NOT_ENTERED);
}

/**
 * Checks to see if the current entered credentials are still valid.
 * 
 * @return string|null|bool false if there's an exception or status of current license (current license, "empty" or null)
 */
function refresh_credentials() {
    try {
        WA_API::verify_valid_access_token();
    } catch (Exception $e) {
        Log::wap_log_error($e->getMessage(), true);
        return false;
    }
    // if we're here the WA creds are still valid

    $current_license_key = Addon::get_license(CORE_SLUG);
    try {
        // check for correct license properties
        $new_license = Addon::instance()::validate_license_key($current_license_key, CORE_SLUG);
    } catch (Exception $e) {
        Log::wap_log_error($e->getMessage(), true);
        return false;
    }

    // if validate_license_key returns the stored license then it's still valid
    Addon::update_license_check_option(CORE_SLUG, Addon::LICENSE_STATUS_VALID);
    return $new_license;
}


