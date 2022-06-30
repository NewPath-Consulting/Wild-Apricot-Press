<?php
/**
 * helpers.php
 * The purpose of this class is to hold common constants and functions used across files 
 * in the WAWP namespace.
 */

namespace WAWP;

const CORE_SLUG = 'wawp';
const CORE_NAME = 'NewPath Wild Apricot Press (WAP)';

const TYPE_ARRAY = 'array';
const TYPE_STRING = 'string';

/**
 * @return bool true if the current page is the licensing settings page, false if not
 */
function is_licensing_submenu() {
    $current_url = get_current_url();
    return $current_url == 'admin.php?page=wawp-licensing';
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
 * @return string url of the current page relative to the base url
 */
function get_current_url() {
    return basename(home_url($_SERVER['REQUEST_URI']));
}

/**
 * @return bool true if the current page is the plugins admin page, false if not
 */
function is_plugin_page() {
    $current_url = get_current_url();

    return str_contains($current_url, 'plugins.php');
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
 * Returns the type of an object.
 * @param mixed $object
 * @return string type of the object
 */
function get_object_type($obj) {}

/**
 * Sanitizes, escapes, and validates input. Specific procedues vary based on the type of
 * the variable.
 * @see WordPress sanitization function docs 
 * https://developer.wordpress.org/reference/functions/sanitize_text_field/ 
 * https://developer.wordpress.org/reference/functions/sanitize_textarea_field/ for specifics.
 * Validation: making sure the variable is the right type and is set. Some of the 
 * validation is specific to the type of variable and must be done outside 
 * this generic function.
 * @param string|array $input the user input to sanitize and validate.
 * @param string $expected_type the expected type of the input variable.
 * @param bool $is_textarea flags whether the input is a text area or not. This changes
 * which Wordpress sanitization function is called. 
 * @return string|array the sanitized input.
 */
function sanitize_and_validate($raw_value, $expected_type, $is_textarea) {
    // Sanitization
    // if type is array, check if array and loop through and sanitize each input.
    $obj_type = get_object_type($raw_value);
    
    // verify the expected type
    if ($obj_type != $expected_type || !isset($raw_value)) return;

    $sanitized = '';
    if ($obj_type == TYPE_ARRAY) {
        foreach ($raw_value as $key => $value) {
            $value = sanitize($value);
        }
        return $raw_value;
    } else if ($is_textarea) {
        $sanitized = sanitize_textarea_field($raw_value);
    }
    else if ($obj_type == TYPE_STRING) {
        $sanitized = sanitize($raw_value);
    }

    return $sanitized;


    // if it's a license, sanitize, remove non alphanumeric and lowercase chars, preserve hyphens
    // if it's a textarea, call wordpress textarea sanitize

    // Validation
    // 
}

/**
 * Removes non-alphanumeric characters from the input string.
 * @param string $input
 * @return string Sanitized input.
 */
function sanitize($input) {
    $sanitized = sanitize_text_field($input);
    $sanitized = preg_replace(
        '/[^A-Za-z0-9]+/',
        '',
        $sanitized
    );

    return $sanitized;
}

/**
 * Escapes HTML output.
 *
 * @param string $output
 * @return string escaped
 */
function escape_output($output) {}

?>