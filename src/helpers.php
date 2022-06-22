<?php

namespace WAWP;

const CORE_SLUG = 'wawp';
const CORE_NAME = 'NewPath Wild Apricot Press (WAP)';

function is_licensing_submenu() {
    $current_url = get_current_url();
    return $current_url == 'admin.php?page=wawp-licensing';
}

function is_wawp_settings() {
    $current_url = get_current_url();
    return str_contains($current_url, CORE_SLUG);
}

function license_submitted() {
    $current_url = get_current_url();
    return $current_url == 'admin.php?page=wawp-licensing&settings-updated=true';
}

function is_wa_login_menu() {
    $current_url = get_current_url();
    return $current_url == 'admin.php?page=wawp-login';
}

function get_current_url() {
    return basename(home_url($_SERVER['REQUEST_URI']));
}

function is_plugin_page() {
    $current_url = get_current_url();

    return str_contains($current_url, 'plugins.php');
}

function is_core($slug) {
    return $slug == CORE_SLUG;
}

function is_addon($slug) {
    return !is_core($slug);
}

?>