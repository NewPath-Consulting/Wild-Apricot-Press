<?php
namespace WAWP;

require_once __DIR__ . '/class-data-encryption.php';
require_once __DIR__ . '/class-log.php';
require_once __DIR__ . '/class-wa-api.php';
require_once __DIR__ . '/class-wa-integration.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/wap-exception.php';

// for checking license key expiration dates
use \DateTime; 

/**
 * Addon class
 * For managing the Addon plugins for WAWP
 * 
 * @since 1.0b1
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @copyright 2022 NewPath Consulting
 */
class Addon {

    /**
     * Base hook url.
     * 
     * @var string
     */
    const HOOK_URL = 'https://newpathconsulting.com/check';

    /**
     * Array of free addons.
     * 
     * @var string[]
     */
    const FREE_ADDONS = array(0 => CORE_SLUG);

    /**
     * Option name for the license key list.
     * 
     * @var string
     */
    const WAWP_LICENSE_KEYS_OPTION      = 'wawp_license_keys';

    /**
     * Option name for the list of addons.
     * 
     * @var string
     */
    const WAWP_ADDON_LIST_OPTION        = 'wawp_addons';
    
    /**
	 * @var string prefix for the activation notice toggle option name
	 */
    const WAWP_ACTIVATION_NOTICE_OPTION = 'show_activation_notice';
    const WAWP_DISABLED_OPTION          = 'wawp_disabled';

    /**
     * Options used to keep track of license key status.
     * 
     * @var string
     */

    /**
     * License key entered.
     */
    const LICENSE_STATUS_VALID          = 'true';

    /**
     * License key invalid.
     * 
     * @var string
     */
    const LICENSE_STATUS_INVALID        = 'invalid';

    /**
     * Empty license key entered.
     * 
     * @var string
     */
    const LICENSE_STATUS_ENTERED_EMPTY  = 'empty';

    /**
     * License key hasn't been entered.
     * 
     * @var string
     */
    const LICENSE_STATUS_NOT_ENTERED    = 'false';

    /**
     * WildApricot authorization credentials have changed-- current license
     * is no longer valid.
     * 
     * @var string
     */
    const LICENSE_STATUS_AUTH_CHANGED   = 'auth_changed';


    private static $instance = null;


    private static $addon_list = array();
    private static $license_check_options = array();
    private static $data_encryption;

    private function __construct() {
        add_action('disable_plugin', 'WAWP\Addon::disable_plugin', 10, 2);

        if (!get_option(self::WAWP_LICENSE_KEYS_OPTION)) {
            add_option(self::WAWP_LICENSE_KEYS_OPTION);
        }

        try {
            self::$data_encryption = new Data_Encryption();
        } catch (Encryption_Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
            return;
        }
    }

    /**
     * Returns the instance of this class (singleton)
     * If the instance does not exist, creates one.
     * 
     * @return Addon
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Activates addon functionality. 
     *
     * @param string $slug slug of addon to activate.
     * @return bool true if addon has valid license and can be activated, 
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
     * 
     * @return void
     */
    public static function new_addon($addon) {
        $option = get_option('wawp_addons');
        if (!$option) {
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
        update_option(self::WAWP_ADDON_LIST_OPTION, $option);
    }

    /**
     * Prints appropriate admin notices displaying the license status.
     *
     * @return void
     */
    public static function license_admin_notices() {

        $is_licensing_page = is_licensing_submenu();
        $is_plugin_page = is_plugin_admin_page();

        // loop through all addons
        foreach(self::get_addons() as $slug => $data) {
            // grab the license status from options table
            $license_status = self::get_license_check_option($slug);

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
            } else if ($license_status == self::LICENSE_STATUS_AUTH_CHANGED) {
                self::license_wa_auth_changed_notice($slug, $is_licensing_page);
                return;
            }
        }
            

    }

    /**
     * Returns the license status from the options table.
     *
     * @param string $slug plugin slug for which to find the license status
     * @return string|bool license status, false if license status option
     * doesn't exist or is empty.
     */
    public static function get_license_check_option($slug) {
        return get_option(self::$license_check_options[$slug]);
    }

    /**
     * Updates the license status in the options table.
     *
     * @param string $slug plugin slug for which to update the license status
     * @param string $val new status
     * @return void
     */
    public static function update_license_check_option($slug, $val) {
        update_option(self::$license_check_options[$slug], $val);
    }

    /**
     * Returns the show activation notice toggle.
     *
     * @param string $slug plugin slug for which to return the toggle.
     * @return bool true if toggle is on, false if it's off or option doesn't
     * exist or is empty. 
     */
    public static function get_show_activation_notice_option($slug) {
        return get_option(self::get_addons()[$slug][self::WAWP_ACTIVATION_NOTICE_OPTION]);
    }

    /**
     * Updates the show notice activation toggle.
     * 
     * @param bool $slug plugin slug for which to update the toggle. true to 
     * enable the toggle, false to disable.
     * @return void
     */
    public static function update_show_activation_notice_option($slug, $val) {
        update_option(self::$addon_list[$slug][self::WAWP_ACTIVATION_NOTICE_OPTION], $val);
    }

    /**
     * Called when the WildApricot credentials have changed. Updates the core
     * plugin's status to `License::LICENSE_STATUS_AUTH_CHANGED` and all the
     * blocks' statuses to `License::LICENSE_STATUS_NOT_ENTERED`.
     *
     * @return void
     */
    public static function wa_auth_changed_update_status() {
        // update core status to auth changed
        self::update_license_check_option(CORE_SLUG, self::LICENSE_STATUS_AUTH_CHANGED);

        // update block status to not entered
        foreach (self::get_addons() as $slug => $addon) {
            if (is_core($slug)) continue;
            self::update_license_check_option($slug, self::LICENSE_STATUS_NOT_ENTERED);
        }
    }


    /**
     * Returns the array of addons stored in the options table.
     *
     * @return string[]|bool array of add-ons. returns false if option does not
     * exist or is empty.
     */
    public static function get_addons() {
        return get_option(self::WAWP_ADDON_LIST_OPTION);
    }

    /**
     * Returns the array of license keys stored in the options table.
     *
     * @return array|null array of license keys. returns null if option
     * doesn't exist, is empty, or couldn't be decrypted.
     */
    public static function get_licenses() {
        $licenses = get_option(self::WAWP_LICENSE_KEYS_OPTION);
        if (!$licenses) return null;
        foreach ($licenses as $slug => $license) {
            // decrypt will throw an error when trying to decrypt empty string
            if (empty($license)) { continue; }
            try {
                $licenses[$slug] = self::$data_encryption->decrypt($license);
            } catch(Decryption_Exception $e) {
                Log::wap_log_error($e->getMessage(), true);
                return null;
            }
            
        }

        return $licenses;
    }

    /**
     * Gets the license key based on the slug name.
     *
     * @param string $slug is the slug name of the add-on
     * @return string|null license key for the add-on, null if it doesn't exist
     */
    public static function get_license($slug) {
        $licenses = self::get_licenses();

        if (empty($licenses[$slug])) return null;

        return $licenses[$slug];
    }

    /**
     * Checks if plugin currently has a valid license. License must not be
     * empty and status must be valid.
     *
     * @param string $slug is the slug name of the add-on
     * @return bool true if license is valid, false if not
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
     * @return string filename of the addon
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
     * @return string title of the add-on
     */
    public static function get_title($slug) {
        $addons = self::get_addons();
        return $addons[$slug]['name'];

    }

    /**
     * Called in `uninstall.php`. Deletes the add-on data stored in the options
     * table.
     * 
     * @return void
     */
    public static function delete() {
        $addons = self::get_addons();

        // delete license status options, show activation notice flag
        foreach ($addons as $slug => $addon) {
            delete_option($addon['license_check_option']);
            delete_option($addon['show_activation_notice']);
        }

        delete_option(self::WAWP_ADDON_LIST_OPTION);
        delete_option(self::WAWP_LICENSE_KEYS_OPTION);
        delete_option(self::WAWP_DISABLED_OPTION);
    }

    /**
     * Removes non alphanumeric, non-hyphen characters from license key input.
     * 
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
     * Disables addon referred to by the slug parameter.
     * Updates the license status to be false.
     * Addon blocks are unregistered. 
     * 
     * @param string $slug slug string of the plugin to be disabled. 
     * @return void
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
     * Plugins will be disabled if the WildApricot authorization 
     * credentials are invalid, the license key is invalid, or if there's been
     * a fatal error.
     * If the slug is the core plugin, all NewPath addons will be disabled along
     * with the core plugin. If the slug is an addon, `disable_addon` will be
     * called. Make login page private and prevent uses from accessing the 
     * login form
     * 
     * @param string $slug slug string of the plugin to disable.
     * @param string $new_license_status new, accurate status for the license.
     * Will be invalid if license was found to be invalid or expired. Will be 
     * "not entered" if license has not been entered or WA credentials are invalid.
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

        WA_Integration::delete_transients();

        Addon::unschedule_all_cron_jobs();
        
        do_action('remove_wa_integration');

        if (Exception::fatal_error()) return;

        // remove membership levels, groups, and custom fields
        delete_option(WA_Integration::WA_ALL_MEMBERSHIPS_KEY);
        delete_option(WA_Integration::WA_ALL_GROUPS_KEY);
        delete_option(WA_Integration::LIST_OF_CUSTOM_FIELDS);

        // remove saved fields
        delete_option(WA_Integration::LIST_OF_CHECKED_FIELDS);

    }

    /**
     * Unschedules all CRON jobs scheduled by the plugin.
     *
     * @return void
     */
    public static function unschedule_all_cron_jobs() {
		Addon::unschedule_cron_job(Settings::CRON_HOOK);
		Addon::unschedule_cron_job(WA_Integration::USER_REFRESH_HOOK);
		Addon::unschedule_cron_job(WA_Integration::LICENSE_CHECK_HOOK);
    }

    /**
	 * Unschedules CRON job.
	 * 
	 * @param string $cron_hook_name cron job to remove
	 * @return void
	 */
	private static function unschedule_cron_job($cron_hook_name) {
		// Get the timestamp for the next event.
		$timestamp = wp_next_scheduled($cron_hook_name);
		// Check that event is already scheduled
		if ($timestamp) {
			wp_unschedule_event($timestamp, $cron_hook_name);
		}
    }

    /**
     * Returns whether the plugin is currently disabled.
     * Plugin will be disabled if any of the credentials are invalid/nonexistent
     * or if there's been an unresolved fatal error.
     *
     * @return bool
     */
    public static function is_plugin_disabled() {
        return get_option(self::WAWP_DISABLED_OPTION);
    }

    /**
     * Refresh license keys.
     * 
     * @return void
     */
    public static function update_licenses() {

        $licenses = self::get_licenses();
        if (is_null($licenses)) {
            do_action('disable_plugin', CORE_SLUG, Addon::LICENSE_STATUS_NOT_ENTERED);
            return;
        }

        // loop through all saved licenses
        foreach (self::get_licenses() as $slug => $license) {
            // if empty, don't send the request
            if (empty($license)) continue;
            
            // try validating license key
            try {
                $new_license = self::validate_license_key($license, $slug);    
            } catch (Exception $e) {
                // if there's a fatal error, clear the status
                Log::wap_log_error($e->getMessage(), true);
                $new_license = Addon::LICENSE_STATUS_ENTERED_EMPTY;
            }
            
            // update status
            if ($new_license == Addon::LICENSE_STATUS_ENTERED_EMPTY) {
                $new_license_status = Addon::LICENSE_STATUS_NOT_ENTERED;
            } else if (is_null($new_license)) {
                $new_license_status = Addon::LICENSE_STATUS_INVALID;
            } else {
                $new_license_status = Addon::LICENSE_STATUS_VALID;
            }
            self::update_license_check_option($slug, $new_license_status);
        }
    }  

    /**
     * Validates the license key. License key must not be expired, user must have
     * WAP licensed, correct URL and user ID for their WA account
     * 
     * @param string $license_key_input license key from the input form.
     * @param string $addon_slug Respective add-on for the key.
     * @return string|null the license key if the input is valid, "empty" 
     * if entered license is empty, and null if it's invalid. 
     */
    public static function validate_license_key($license_key_input, $addon_slug) {
        // if license key is empty, do nothing
        if (empty($license_key_input)) return self::LICENSE_STATUS_ENTERED_EMPTY;
        
        // escape input
        $license_key = self::escape_license($license_key_input);

        // if escaped key doesn't match input, it's invalid
        if ($license_key != $license_key_input) return null;

        // if license hasn't changed, return it to avoid making expensive request
        if (self::get_license($addon_slug) == $license_key && 
            self::has_valid_license($addon_slug)) 
        {
            return $license_key;
        } 

        // check key against integromat scenario
        $response = self::post_request($license_key);

        // check that key has the necessary properties to be valid
        $is_license_valid = self::check_license_properties($response, $addon_slug);
        if (!$is_license_valid) return null;

        return $license_key;
    }

    /**
     * Sends a POST request to the license key validation hook,
     * returns response data.
     * 
     * @param string $license_key key license key to check against the Make scenario
     * @return array JSON response data 
     */
    private static function post_request($license_key) {

        // check for dev flag, construct appropriate url
        $url = self::HOOK_URL;
        if (defined('WAP_LICENSE_CHECK_DEV') && WAP_LICENSE_CHECK_DEV) {
            $url = $url . 'dev';
        }

        // construct array of data to send
        $data = array('key' => $license_key, 'json' => 1);
        $args = array(
            'body'        => $data,
            'timeout'     => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(),
            'cookies'     => array(),
        );

        // make post request to hook and decode response data
        $response = wp_remote_post($url, $args);
        $response_data = $response['body'];

        return json_decode($response_data, true);

    }

    /**
     * Checks the Integromat hook response for the necessary conditions for a 
     * valid license.
     * Must have WAP in products.
     * Must have correct URL and user ID for their WA account associated with
     * the API credentials.
     * Must not be expired.
     * 
     * @return bool true if above conditions are valid, false if not.
     */
    public static function check_license_properties($response, $slug) {
        // if the license is invalid OR an invalid WildApricot URL is being used, return null
        // else return the valid license key
        if (array_key_exists('license-error', $response)) return false;

        if (!(
            array_key_exists('Products', $response) &&
            array_key_exists('Support Level', $response) && 
            array_key_exists('expiration date', $response)
        )) {
            return false;
        }

        // Get list of product(s) that this license is valid for
        $valid_products = $response['Products'];
        $exp_date = $response['expiration date'];
        
        // Check if the addon_slug in in the products list, has support access and is expired
        if (!in_array(CORE_SLUG, $valid_products) || self::is_expired($exp_date)) {
            return false;
        }

        $name = self::get_title($slug);
        if (self::is_expired($exp_date)) {
            Log::wap_log_warning('License key for ' . $name . ' has expired.');
            return false;
        }

        // Ensure that this license key is valid for the associated WildApricot ID and website
        $valid_urls_and_ids = WA_Integration::check_licensed_wa_urls_ids($response);

        if (!$valid_urls_and_ids) {
            Log::wap_log_warning('License key for ' . $name . ' invalid for your WildApricot account and/or website');
            Log::wap_log_warning('Products Licensed: ' . implode(',', $valid_products));
            Log::wap_log_warning('License Expiration Date: ' . $exp_date);
            Log::wap_log_warning('Support Level: ' . $response['Support Level']);
            Log::wap_log_warning('Licensed URLs: ' . implode(',',$response['Licensed URLs']) .'|');
            Log::wap_log_warning('Licensed WA URLs: ' . implode(',',$response['Licensed Wild Apricot URLs']) .'|');
            Log::wap_log_warning('Licensed WA IDs: ' . implode(',',$response['Licensed Wild Apricot Account IDs']).'|');
           
            return false;
        }
	
        Log::wap_log_debug('Valid Licensed URLs: ' . implode(',',$response['Licensed URLs']));
		Log::wap_log_debug('Valid Licensed WA URLs: ' . implode(',',$response['Licensed Wild Apricot URLs']));
		Log::wap_log_debug('Valid Licensed WA IDs: ' . implode(',',$response['Licensed Wild Apricot Account IDs']));
        return true;
    }

    /**
     * Returns whether the license key is expired or not.
     * 
     * @param string $exp_date_string the expiry date of the license entered
     * @return bool true if license is expired, false if not. 
     */
    private static function is_expired($exp_date_string) {
        $now = new DateTime();
        $exp_date = new DateTime($exp_date_string);

        $now_ts = $now->getTimestamp();
        $exp_date_ts = $exp_date->getTimestamp();
        $is_expired = !empty($exp_date_string) && $exp_date_ts < $now_ts;

        return $is_expired;
    }

    /**
     * Displays valid license notice.
     *
     * @param string $slug plugin slug for which the entered license is valid
     * @return void
     */
    public static function valid_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        echo "<div class='notice notice-success is-dismissible license'><p>";
		echo "Saved license key for <strong>" . esc_html($plugin_name) . "</strong>.</p>";
		echo "</div>";
    }

    /**
     * Displays valid license notice.
     *
     * @param string $slug plugin slug for which the license is invalid
     * @return void
     */
    public static function invalid_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        echo "<div class='notice notice-error is-dismissible license'><p>";
        echo "Your license key for <strong>" . esc_html($plugin_name);
        echo "</strong> is invalid or expired. To get a new key please visit the <a href='https://newpathconsulting.com/wild-apricot-for-wordpress/'>WildApricot for Wordpress website</a>.";
        echo "</div>";
    }

    /**
     * Displays valid license notice.
     *
     * @param string $slug plugin slug for which the license is empty
     * @return void
     */
    public static function empty_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        $filename = self::get_filename($slug);
        echo "<div class='notice notice-warning license'><p>";
        echo "Please enter a valid license key for <strong>" . esc_html($plugin_name) . "</strong>. </p></div>";
        // prevents printing "Plugin activated" message
        unset($_GET['activate']); 
    }

    /**
     * Prints out a message prompting the user to enter their license key.
     * Called when user has activated the plugin but has NOT YET entered their 
     * license key.
     * 
     * @param string $slug slug of the plugin for which to display this prompt
     * @param bool $is_licensing_page whether the user is currently on the
     * license settings page or not
     * @param void
     */
    public static function license_key_prompt($slug, $is_licensing_page) {
        $plugin_name = self::get_title($slug);

        echo "<div class='notice notice-warning is-dismissable license'><p>";
        echo "Please enter your license key";
        // if the user is not on the license settings, print the url
        if (!$is_licensing_page) {
            echo " in <a href=" . esc_url(get_licensing_menu_url()) . ">WildApricot Press > Licensing</a>"; 
        }
        
        echo " in order to use the <strong>" . esc_html($plugin_name) . "</strong> functionality.</p></div>";

        unset($_GET['activate']);
    }

    /**
     * Prints out a message informing the user that their license key(s) are now
     * invalid because there are new WA authorization credentials.
     *
     * @param string $slug plugin slug for which to output this message
     * @param bool $is_licensing_page whether the user is currently on the 
     * license settings page or not
     * @return void
     */
    public static function license_wa_auth_changed_notice($slug, $is_licensing_page) {
        $plugin_name = self::get_title($slug);

        echo '<div class="notice notice-warning license"><p>';
        echo 'Your WildApricot authorization credentials have changed. ';
        echo 'Please re-enter your license key(s)';
        // if the user is not on the license settings, print the url
        if (!$is_licensing_page) {
            echo ' in <a href=' . esc_url(get_licensing_menu_url()) . 
            '>WildApricot Press > Licensing</a>';
        }
        echo ' in order to continue using the <strong>' . esc_html($plugin_name)
        . '</strong> functionality.</p></div>';

    }

} // end of Addon class