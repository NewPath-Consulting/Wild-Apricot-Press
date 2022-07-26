<?php
namespace WAWP;

require_once __DIR__ . '/WAWPApi.php';
require_once __DIR__ . '/WAIntegration.php';
require_once __DIR__ . '/DataEncryption.php';
require_once __DIR__ . '/WAWPException.php';
require_once __DIR__ . '/Log.php';
require_once __DIR__ . '/helpers.php';


use \DateTime; // for checking license key expiration dates

/**
 * Addon class
 * For managing the Addon plugins for WAWP
 */
class Addon {
    // const HOOK_URL = 'https://hook.integromat.com/mauo1z5yn88d94lfvc3wd4qulaqy1tko';
    const HOOK_URL = 'https://newpathconsulting.com/checkdev';

    const FREE_ADDONS = array(0 => CORE_SLUG);
    const PAID_ADDONS = array(0 => 'wawp-addon-wa-iframe');

    // option used to keep track of license key status
    // possible values:
        // true: license key entered
        // false: default value, license key hasn't been entered yet
        // empty: license key entered (meaning form has been submitted) and the field was empty
        // invalid: invalid key entered
    const WAWP_LICENSE_KEYS_OPTION = 'wawp_license_keys';
    const WAWP_ADDON_LIST_OPTION = 'wawp_addons';
    const WAWP_ACTIVATION_NOTICE_OPTION = 'show_activation_notice';
    const WAWP_DISABLED_OPTION = 'wawp_disabled';

    const LICENSE_STATUS_VALID = 'true';
    const LICENSE_STATUS_INVALID = 'invalid';
    const LICENSE_STATUS_ENTERED_EMPTY = 'empty';
    const LICENSE_STATUS_NOT_ENTERED = 'false';


    private static $instance = null;


    private static $addons_to_license = array();
    private static $addon_list = array();
    private static $license_check_options = array();
    private static $data_encryption;

    private function __construct() {
        add_action('disable_plugin', 'WAWP\Addon::disable_plugin', 10, 2);

        if (!get_option(self::WAWP_LICENSE_KEYS_OPTION)) {
            add_option(self::WAWP_LICENSE_KEYS_OPTION);
        }

        try {
            self::$data_encryption = new DataEncryption();
        } catch (EncryptionException $e) {
            Log::wap_log_error($e->getMessage(), true);
            return;
        }
    }

    /**
     * Returns the instance of this class (singleton)
     * If the instance does not exist, creates one.
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Activates addon functionality. 
     *
     * @param string $slug slug of addon to activate.
     * @return boolean true if addon has valid license and can be activated, 
     * false if not.
     */
    public static function activate($slug) {
        $license_exists = self::instance()::has_valid_license($slug);
        if ($license_exists) return true;
        self::update_license_check_option($slug, self::LICENSE_STATUS_NOT_ENTERED);
        self::update_show_activation_notice_option($slug, 1);
        return false;
    }

    /**
     * Adds a new add-on to the array of add-ons stored in the options table.
     * @param $addon Add-on to be added to the DB.
     * Is an assoc. array of addon info in the following format:
     * slug = array(
     *      [title] => Display title,
     *      [filename] => Filename of main plugin file relative to plugin directory
     * )
     */
    public static function new_addon($addon) {
        $option = get_option('wawp_addons');
        if ($option == false) {
            $option = array();
        }

        $slug = $addon['slug'];

        if (in_array($slug, $option)) {
            return;
        }

        // populate 
        foreach ($addon as $key => $value) {
            if ($key == 'slug') continue;
            $option[$slug][$key] = $value;
        }

        self::$license_check_options[$slug] = $addon['license_check_option'];
        self::$addon_list[$slug] = $option[$slug];
        self::update_addons($option);
    }

    public static function update_addons($new_list) {
        update_option(self::WAWP_ADDON_LIST_OPTION, $new_list);
    }

    public static function license_admin_notices() {

        $is_licensing_page = is_licensing_submenu();
        $is_plugin_page = is_plugin_page();

        // Log::wap_log_debug('Addon::license_admin_notices');
        // loop through all addons
        foreach(self::get_addons() as $slug => $data) {
            // grab the license status from options table
            $license_status = self::get_license_check_option($slug);
            if (is_core($slug)) $core_license_status = $license_status;

            // some messages will only be shown as a feedback message when license is entered
            if (license_submitted()) {
                // if entered license is valid, show message
                if ($license_status == self::LICENSE_STATUS_VALID) {
                    self::valid_license_key_notice($slug);
                    continue; // skip rest of loop code because further operations will only be done on invalid licenses.
                } else if ($license_status == self::LICENSE_STATUS_ENTERED_EMPTY) {
                    // if entered license is empty, show message, then revert to not entered option
                    self::empty_license_key_notice($slug);
                    self::update_license_check_option($slug, self::LICENSE_STATUS_NOT_ENTERED);
                }
            }

            $should_show_activation_notice = self::get_show_activation_notice_option($slug);
            // if it's the plugin page, set show notice to false so it doesn't appear every time you see the plugins page
            // also prevent the activation message from showing
            if ($is_plugin_page) {
                self::update_show_activation_notice_option($slug, 0);
                unset($_GET['activate']);
            }

            // continue here only if it's wawp settings or it's the plugin page and the notice should be showed
            if ($is_plugin_page && !$should_show_activation_notice) continue;

            if ($license_status == self::LICENSE_STATUS_NOT_ENTERED) {
                // show generic license prompt on licensing page if license has not been entered
                self::license_key_prompt($slug, $is_licensing_page);
            } else if ($license_status == self::LICENSE_STATUS_INVALID) {
                // show invalid license message on any wawp settings page
                self::invalid_license_key_notice($slug);
            }
        }
            

    }



    public static function get_license_check_option($slug) {
        return get_option(self::$license_check_options[$slug]);
    }

    public static function update_license_check_option($slug, $val) {
        update_option(self::$license_check_options[$slug], $val);
    }

    public static function get_show_activation_notice_option($slug) {
        return get_option(self::get_addons()[$slug][self::WAWP_ACTIVATION_NOTICE_OPTION]);
    }

    public static function update_show_activation_notice_option($slug, $val) {
        update_option(self::$addon_list[$slug][self::WAWP_ACTIVATION_NOTICE_OPTION], $val);
    }


    /**
     * Returns the array of addons stored in the options table.
     *
     * @return array of add-ons
     */
    public static function get_addons() {
        return get_option(self::WAWP_ADDON_LIST_OPTION);
    }

    /**
     * Returns the array of license keys stored in the options table.
     *
     * @return array of license keys
     */
    public static function get_licenses() {
        $licenses = get_option(self::WAWP_LICENSE_KEYS_OPTION);
        if (!$licenses) return null;
        foreach ($licenses as $slug => $license) {
            // decrypt will throw an error when trying to decrypt empty string
            if (empty($license)) { continue; }
            try {
                $licenses[$slug] = self::$data_encryption->decrypt($license);
            } catch(EncryptionException $e) {
                Log::wap_log_error($e->getMessage(), true);
                $licenses[$slug] = '';
            }
            
        }

        return $licenses;
    }

    /**
     * Gets the license key based on the slug name
     *
     * @param string $slug is the slug name of the add-on
     */
    public static function get_license($slug) {
        $licenses = self::get_licenses();

        if (empty($licenses[$slug])) return null;

        return $licenses[$slug];
    }

    /**
     * Checks if plugin currently has a license. License status must also be valid.
     *
     * @param string $slug is the slug name of the add-on
     * @return boolean true if license is valid, false if not
     */
    public static function has_valid_license($slug) {
        $license = self::get_license($slug);
        
        $license_status = self::get_license_check_option($slug);
        return !empty($license) && $license_status == self::LICENSE_STATUS_VALID;
    }

    /**
     * Gets filename of add-on based on slug name
     *
     * @param string $slug is the slug name of the add-on
     */
    public static function get_filename($slug) {
        $addons = self::get_addons();
        return $addons[$slug]['filename'];
    }

    public static function clear_licenses() {
        $license_options = self::$license_check_options;

        foreach($license_options as $slug) {
            self::update_license_check_option($slug, self::LICENSE_STATUS_NOT_ENTERED);
        }
    }

    /**
     * Gets title of add-on based on slug name
     *
     * @param string $slug is the slug name of the add-on
     */
    public static function get_title($slug) {
        $addons = self::get_addons();
        return $addons[$slug]['name'];

    }

    /**
     * Called in uninstall.php. Deletes the data stored in the options table.
     */
    public static function delete() {
        $addons = self::get_addons();

        foreach ($addons as $slug => $addon) {
            $filename = $addon['filename'];
            delete_plugins(array(0 => $filename));
            delete_option('license-check-' . $slug);
        }

        delete_option('wawp_addons');
        delete_option('wawp_license_keys');
    }

    /**
     * Removes non alphanumeric, non-hyphen characters from license key input.
     * @param string $license_key_input input from license key form to escape.
     * @return string escaped license key.
     */
    public static function escape_license($license_key_input) {
        // remove non-alphanumeric, non-hyphen characters
        $license_key = preg_replace(
            '/[^A-Z0-9\-]/',
            '',
            $license_key_input
        );

        return $license_key;
    }

    /**
     * Constructs array of license key data to send to the Integromat license key scenario. Sends request to the Integromat hook URL.
     * @param string $license_key license key to check against the hook.
     * @return string[] response from the scenario.
     */
    public static function request_integromat_hook($license_key) {
        // construct array of data to send
        $data = array('key' => $license_key, 'json' => 1);

        // send request, receive response in $response
        $response = self::post_request($data);

        return $response;
    }

    /**
     * Disables addon referred to by the slug parameter.
     * Updates the license status to be false.
     * Addon blocks are unregistered. 
     * @param string $slug slug string of the plugin to be disabled. 
     */
    public static function disable_addon($slug) {
        if (self::instance()::get_license_check_option($slug) != self::LICENSE_STATUS_NOT_ENTERED) {
            self::instance()::update_license_check_option($slug, self::LICENSE_STATUS_NOT_ENTERED);
        }

        $blocks = self::instance()::get_addons()[$slug]['blocks'];

        foreach ($blocks as $block) {
            unregister_block_type($block);
        }
    }

    /**
     * Disables plugins. 
     * If the slug is the core plugin, all NewPath addons will be disabled along with the core plugin.
     * If the slug is an addon, disable_addon will be called.
     * Make login page private.
     * @param string $slug slug string of the plugin to disable.
     * @param string $new_license_status new, accurate status for the license. Will be invalid if license was found to be invalid or expired. Will be "not entered" if license has not been entered or WA credentials are invalid.
     */
    public static function disable_plugin($slug, $new_license_status) {
        // if slug == core
        // loop through addon list
        // if plugin is an addon, call disable addon
        // for all of them, change license status to false
        // do action make login private


        if (is_addon($slug)) {
            self::instance()::disable_addon($slug);
            return;
        }

        foreach (self::instance()::get_addons() as $slug_iter => $addon) {
            if (is_addon($slug_iter)) {
                self::instance()::disable_addon($slug_iter);
            } else { // disable_addon already updates license status since it can be called independent of this function

                // change license status only if it is currently valid
                // this will happen when this function is called during a cron job, which means this license has expired or otherwise become invalid since it had been entered.
                if (self::instance()::get_license_check_option($slug) != $new_license_status) {
                    self::instance()::update_license_check_option($slug, $new_license_status);
                }
            }
        }

        if (!Addon::is_plugin_disabled()) {
            update_option(self::WAWP_DISABLED_OPTION, true);
        }

        WAIntegration::delete_transients();
        
        do_action('remove_wa_integration');

    }

    public static function is_plugin_disabled() {
        return get_option(self::WAWP_DISABLED_OPTION);
    }

    /**
     * Validates the license key.
     * @param string $license_key_input license key from the input form.
     * @param string $addon_slug Respective add-on for the key.
     * @return string|null the license key if the input is valid, null if not. 
     */
    public static function validate_license_key($license_key_input, $addon_slug) {
        // if license key is empty, do nothing
        if (empty($license_key_input)) return self::LICENSE_STATUS_ENTERED_EMPTY;
        // escape input
        $license_key = self::escape_license($license_key_input);

        // if license hasn't changed, return it
        // avoid making expensive request
        if (self::get_license($addon_slug) == $license_key) return $license_key;

        // check key against integromat scenario
        $response = self::request_integromat_hook($license_key);

        // check that key has the necessary properties to be valid
        $is_license_valid = self::check_license_properties($response, $addon_slug);

        if (!$is_license_valid) return null;


        return $license_key;
    }

    /**
     * Sends a POST request to the license key validation hook, returns response data
     * @param array $data request data containing license key and JSON flag
     * @return array JSON response data 
     */
    public static function post_request($data) {

        // get integromat hook url from redirect
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::HOOK_URL);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($curl);
        $url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

        // send request to hook url
        $options = array(
                'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);

        $result = json_decode(file_get_contents($url, false, $context), 1);

        return $result;
    }

    /**
     * Checks the Integromat hook response for the necessary conditions for a valid license.
     * Must have WAWP in products.
     * Must have support.
     * Must not be expired.
     * @return bool true if above conditions are valid, false if not.
     */
    public static function check_license_properties($response, $slug) {
        // if the license is invalid OR an invalid Wild Apricot URL is being used, return null
        // else return the valid license key
        if (array_key_exists('license-error', $response)) return false;

        if (!(array_key_exists('Products', $response) && array_key_exists('Support Level', $response) && array_key_exists('expiration date', $response))) return false;

        // Get list of product(s) that this license is valid for
        $valid_products = $response['Products'];
        // $support_level = $response['Support Level'];
        $exp_date = $response['expiration date'];

        
        // Check if the addon_slug in in the products list, has support access and is expired
        if (!in_array(CORE_SLUG, $valid_products) || self::is_expired($exp_date)) {
            return false;
        }

        $name = self::get_title($slug);

        if (self::is_expired($exp_date)) {
            Log::wap_log_warning('License key for ' . $name . ' has expired.');
        }

        // Ensure that this license key is valid for the associated Wild Apricot ID and website
        $valid_urls_and_ids = WAIntegration::check_licensed_wa_urls_ids($response);

        if (!$valid_urls_and_ids) {
            Log::wap_log_warning('License key for' . $name . 'invalid for your Wild Apricot account and/or website');
            return false;
        }

        return true;
    }

    /**
     * Returns whether the license key is expired or not.
     * @param string $exp_date_string the expiry date of the license entered
     * @return boolean true if license is expired, false if not. 
     */
    private static function is_expired($exp_date_string) {
        $now = new DateTime();
        $exp_date = new DateTime($exp_date_string);

        $now_ts = $now->getTimestamp();
        $exp_date_ts = $exp_date->getTimestamp();
        $is_expired = !empty($exp_date_string) && $exp_date_ts < $now_ts;

        return $is_expired;
    }

    public static function valid_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        echo "<div class='notice notice-success is-dismissible license'><p>";
		echo "Saved license key for <strong>" . esc_html__($plugin_name) . "</strong>.</p>";
		echo "</div>";
    }

    public static function invalid_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        echo "<div class='notice notice-error is-dismissible license'><p>";
        echo "Your license key for <strong>" . esc_html__($plugin_name);
        echo "</strong> is invalid or expired. To get a new key please visit the <a href='https://newpathconsulting.com/wild-apricot-for-wordpress/'>Wild Apricot for Wordpress website</a>.";
        echo "</div>";
    }

    public static function empty_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        $filename = self::get_filename($slug);
        echo "<div class='notice notice-warning license'><p>";
        echo "Please enter a valid license key for <strong>" . esc_html__($plugin_name) . "</strong>. </p></div>";
        unset($_GET['activate']); // prevents printing "Plugin activated" message
        // deactivate_plugins($filename);
    }

    /**
     * Prints out a message prompting the user to enter their license key.
     * Called when user has activated the plugin but has NOT YET entered their license key.
     * @param string $slug slug of the plugin for which to display this prompt
     * @param boolean $is_licensing_page indicating whether or not the current page is the licensing form page. if it isn't, print a link to the licensing form page. 
     */
    public static function license_key_prompt($slug, $is_licensing_page) {
        $plugin_name = self::get_title($slug);

        echo "<div class='notice notice-warning is-dismissable license'><p>";
        echo "Please enter your license key";
        if (!$is_licensing_page) {
         echo " in <a href=" . esc_url(admin_url('admin.php?page=wawp-licensing')) . ">Wild Apricot Press > Licensing</a>"; 
        }
        
        echo " in order to use the <strong>" . esc_html__($plugin_name) . "</strong> functionality.</p></div>";

        unset($_GET['activate']);
    }





} // end of Addon class

?>
