<?php

namespace WAWP;

require_once __DIR__ . '/class-license-settings.php';
require_once __DIR__ . '/class-plugin-settings.php';
require_once __DIR__ . '/class-support-page.php';
require_once __DIR__ . '/class-wa-auth-settings.php';

/**
 * Manages and renders plugin settings on the admin screen.
 *
 * @since 1.1
 * @author Spencer Gable-Cook and Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class Settings_Controller
{
    public const SETTINGS_URL = 'wawp-wal-admin';

    private $admin_settings;
    private $wa_auth_settings;
    private $license_settings;
    private $support_page;

    /**
     * Adds actions and includes files
     */
    public function __construct()
    {
        add_action('admin_menu', array( $this, 'add_settings_page' ));
        add_action('admin_init', array( $this, 'page_init' ));

        $this->admin_settings   = new Admin_Settings();
        $this->wa_auth_settings = new WA_Auth_Settings();
        $this->license_settings = new License_Settings();
        $this->support_page     = new Support_Page();

        // Activate option in table if it does not exist yet
        // Currently, there is a WordPress bug that calls the 'sanitize' function twice if the option is not already in the database
        // See: https://core.trac.wordpress.org/ticket/21989
        if (!get_option(WA_Auth_Settings::WA_CREDENTIALS_KEY)) { // does not exist
            // Create option
            add_option(WA_Auth_Settings::WA_CREDENTIALS_KEY);
        }
        // Set default global page restriction message
        if (!get_option(WA_Restricted_Posts::GLOBAL_RESTRICTION_MESSAGE)) {
            add_option(WA_Restricted_Posts::GLOBAL_RESTRICTION_MESSAGE, '<h2>Restricted Content</h2> <p>This post is restricted to specific WildApricot users. Log into your WildApricot account or ask your administrator to add you to the post.</p>');
        }

    }

    /**
     * Add WAP settings page.
     *
     * @return void.
     */
    public function add_settings_page()
    {
        // Create WAWP admin page
        $this->admin_settings->add_menu_pages();

        $this->wa_auth_settings->add_submenu_page();
        $this->license_settings->add_submenu_page();
        $this->support_page->add_submenu_page();

    }

    /**
     * Register and add settings fields.
     *
     * @return void
     */
    public function page_init()
    {
        $this->wa_auth_settings->register_setting_add_fields();
        $this->license_settings->register_setting_add_fields();
        $this->admin_settings->register_setting_add_fields();
    }

}