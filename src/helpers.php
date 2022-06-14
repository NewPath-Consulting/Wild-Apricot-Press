<?php

namespace WAWP;

const CORE_SLUG = 'wawp';
const CORE_NAME = 'NewPath Wild Apricot Press (WAP)';

function is_licensing_submenu() {
    $current_url = basename(home_url($_SERVER['REQUEST_URI']));
    return $current_url == 'admin.php?page=wawp-licensing';
}

function license_submitted() {
    $current_url = basename(home_url($_SERVER['REQUEST_URI']));
    return $current_url == 'admin.php?page=wawp-licensing&settings-updated=true';
}

?>