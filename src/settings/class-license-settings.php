<?php

namespace WAWP;

require_once __DIR__ . '/../class-addon.php';
require_once __DIR__ . '/../util/class-log.php';
require_once __DIR__ . '/../util/class-data-encryption.php';
require_once __DIR__ . '/../class-wa-api.php';
require_once __DIR__ . '/../class-wa-integration.php';
require_once __DIR__ . '/../util/helpers.php';
require_once __DIR__ . '/../util/wap-exception.php';


/**
 * Handles creating, rendering, and sanitizing license keys
 *
 * @since 1.1
 * @author Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class License_Settings
{
    public const OPTION_GROUP = 'wap_licensing_group';
    public const SUBMENU_PAGE = 'wap-licensing';
    public const SECTION = 'wap_licensing_section';

    public function __construct()
    {
    }

    /**
     * Add submenu settings page for license settings.
     *
     * @return void
     */
    public function add_submenu_page()
    {
        // Create submenu for license key forms
        add_submenu_page(
            Settings_Controller::SETTINGS_URL,
            'WAP Licensing',
            'Licensing',
            'manage_options',
            self::SUBMENU_PAGE,
            array($this, 'create_license_form')
        );
    }

    /**
     * Register settings and add fields for all addons' licenses.
     *
     * @return void
     */
    public function register_setting_add_fields()
    {
        // Registering and adding settings for the license key forms
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize_and_validate'),
            'default' => null
        );

        register_setting(
            self::OPTION_GROUP, // option group
            Addon::WAWP_LICENSE_KEYS_OPTION, // option name
            $register_args
        );

        add_settings_section(
            self::SECTION, // section ID
            'Enable WAP with your license key', // title
            array($this, 'print_settings_info'), // callback
            self::SUBMENU_PAGE // page
        );

        // render fields for each plugin installed
        $addons = Addon::instance()::get_addons();
        foreach ($addons as $slug => $addon) {
            $name = $addon['name'];
            add_settings_field(
                'wap_license_form_' . $slug,
                $name,
                array($this, 'create_input_box'),
                self::SUBMENU_PAGE,
                self::SECTION,
                array('slug' => $slug, 'name' => $name)
            );
        }
    }

    /**
     * Render license form HTML.
     *
     * @return void
     */
    public function create_license_form()
    {
        ?>
<div class="wrap">
    <h1>Licensing</h1>
    <?php
            // Check if WildApricot credentials have been entered
            // If credentials have been entered (not empty) and plugin is not disabled, then we can present the license page
            if (!Exception::fatal_error() && WA_Integration::valid_wa_credentials()) {
                ?>
    <form method="post" action="options.php">
        <?php
                    // Nonce for verification
                    wp_nonce_field('wawp_license_nonce_action', 'wawp_license_nonce_name');
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::SUBMENU_PAGE);
                submit_button('Save', 'primary');
                ?>
    </form>
    <?php
            }
        ?>
</div>
<?php
    }

    /**
     * License form callback.
     * For each license submitted, check if the license is valid.
     * If it is valid, it gets added to the array of valid license keys.
     * Otherwise, the user receives an error.
     *
     * @param array $input settings form input array mapping addon slugs to license keys
     * @return array array of valid license keys, empty array if invalid or
     * fatal error
     */
    public function sanitize_and_validate($input)
    {
        $empty_input_array = empty_string_array($input);
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_license_nonce_name'], 'wawp_license_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the license key(s) could not be verified.');
            return $empty_input_array;
        }

        $valid = array();

        // return empty array if we can't encrypt data
        try {
            $data_encryption = new Data_Encryption();
        } catch (Encryption_Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
            return $empty_input_array;
        }

        foreach ($input as $slug => $license) {
            try {
                $key = Addon::instance()::validate_license_key($license, $slug);
            } catch (Exception $e) {
                Log::wap_log_error($e->getMessage(), true);
                Addon::update_license_check_option($slug, Addon::LICENSE_STATUS_NOT_ENTERED);
                return $empty_input_array;
            }
            if (is_null($key)) {
                // invalid key
                Addon::update_license_check_option($slug, Addon::LICENSE_STATUS_INVALID);
                $valid[$slug] = '';

            } elseif ($key == Addon::LICENSE_STATUS_ENTERED_EMPTY) {
                // key was not entered -- different message will be shown
                $valid[$slug] = '';

                Addon::update_license_check_option($slug, Addon::LICENSE_STATUS_ENTERED_EMPTY);
            } else {
                try {
                    $license_encrypted = $data_encryption->encrypt($key);
                } catch (Encryption_Exception $e) {
                    // if license could not be encrypted, just discard it
                    Log::wap_log_error($e->getMessage(), true);
                    return $empty_input_array;
                }
                if (is_core($slug)) {
                    update_option(Addon::WAWP_DISABLED_OPTION, false);
                }
                // valid key
                Addon::update_license_check_option($slug, Addon::LICENSE_STATUS_VALID);
                $valid[$slug] = $license_encrypted;

            }


        }
        return $valid;
    }

    /**
     * Print the licensing settings section text
     *
     * @return void
     */
    public function print_settings_info()
    {
        $link_address = "https://newpathconsulting.com/wap/";
        print "Enter your license key(s) here. If you do not already have a license key, please visit our website <a href='" . esc_url($link_address) . "' target='_blank' rel='noopener noreferrer'>here</a> to get a license key. ";
    }

    /**
     * Render license input box HTML.
     *
     * @param array $args the plugin for which to render the license field.
     * Contains slug and title. Passed in in `add_settings_field`.
     * @return void
     */
    public function create_input_box(array $args)
    {
        $slug = $args['slug'];
        // Check that slug is valid
        $input_value = '';
        $license_valid = Addon::instance()::has_valid_license($slug);
        if ($license_valid) {
            $input_value = Addon::instance()::get_license($slug);
        }

        echo '<input class="license_key" id="' . esc_attr($slug) . '" name="wawp_license_keys[' . esc_attr($slug) .']" type="text" value="' . esc_attr($input_value) . '"  />' ;
        if ($license_valid) {
            echo '<br><p class="wap-success"><span class="dashicons dashicons-saved"></span> License key valid</p>';
        }
    }
}
