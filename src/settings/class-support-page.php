<?php

namespace WAWP;

class Support_Page
{
    public function __construct()
    {
    }

    public function add_submenu_page()
    {
        add_submenu_page(
            Settings_Controller::SETTINGS_URL,
            'WAP Support',
            'Support',
            'manage_options',
            'wap-support',
            array($this, 'create_support_page')
        );
    }

    public function create_support_page()
    {
        echo '<div class="wrap"><h1>Support</h1>Support for this plugin is available in two ways:<br><br>Community support: <a href="https://talk.newpathconsulting.com" target="_blank">https://talk.newpathconsulting.com</a><br>In-person real-time support: <a href="https://newpathconsulting.com/hero" target="_blank">https://newpathconsulting.com/hero</a></div>';
    }
}