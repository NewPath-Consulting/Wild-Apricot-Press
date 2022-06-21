<?php
namespace WAWP;

require_once __DIR__ . '/WAWPApi.php';
require_once __DIR__ . '/WAIntegration.php';
require_once __DIR__ . '/DataEncryption.php';
require_once __DIR__ . '/Log.php';
require_once __DIR__ . '/helpers.php';


use \DateTime; // for checking license key expiration dates
use WAWP\Log;

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
        self::$data_encryption = new DataEncryption();

        if (!get_option(self::WAWP_LICENSE_KEYS_OPTION)) {
            add_option(self::WAWP_LICENSE_KEYS_OPTION);
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
        if (in_array($addon, $option)) {
            return;
        }

        $slug = $addon['slug'];
        $option[$slug] = array(
            'name' => $addon['name'],
            'filename' => $addon['filename'],
            'license_check_option' => $addon['license_check_option']
        );
        self::$license_check_options[$slug] = $addon['license_check_option'];
        self::$addon_list[$slug] = $option[$slug];
        self::update_addons($option);


    }

    public static function update_addons($new_list) {
        update_option(self::WAWP_ADDON_LIST_OPTION, $new_list);
    }

    public static function license_admin_notices() {
        $is_licensing_page = is_licensing_submenu();
        Log::good_error_log('enter');
        foreach(self::$addon_list as $slug => $data) {
            $license_status = self::get_license_check_option($slug);

            if (license_submitted()) {
                if ($license_status == self::LICENSE_STATUS_VALID) {
                    self::valid_license_key_notice($slug);
                } else if ($license_status == self::LICENSE_STATUS_ENTERED_EMPTY) {
                    self::empty_license_key_notice($slug, $is_licensing_page);
                    self::update_license_check_option($slug, self::LICENSE_STATUS_NOT_ENTERED);
                }
            } else {
                if ($license_status == self::LICENSE_STATUS_NOT_ENTERED) {
                    self::license_key_prompt($slug, $is_licensing_page);
                }
            }

            if ($license_status == self::LICENSE_STATUS_INVALID) {
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


    /**
     * Returns the array of addons stored in the options table.
     *
     * @return array of add-ons
     */
    public static function get_addons() {
        return self::$addon_list;
    }

    /**
     * Returns the array of license keys stored in the options table.
     *
     * @return array of license keys
     */
    public static function get_licenses() {
        $licenses = get_option(self::WAWP_LICENSE_KEYS_OPTION);
        if (!$licenses) return NULL;
        foreach ($licenses as $slug => $license) {
            $licenses[$slug] = self::$data_encryption->decrypt($license);
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

        if (empty($licenses[$slug])) return NULL;

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
    

    //     $slug = array_key_first($addon);
    //     $option[$slug] = $addon[$slug];
    //     // array_push($option, $addon);
    //     update_option('wawp_addons', $option);

    // }

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

    public static function escape_license($license_key_input,) {
        // remove non-alphanumeric, non-hyphen characters
        $license_key = preg_replace(
            '/[^A-Za-z0-9\-]/',
            '',
            $license_key_input
        );

        return $license_key;
    }

    public static function check_license($license_key) {
        // construct array of data to send
        $data = array('key' => $license_key, 'json' => 1);

        // send request, receive response in $response
        $response = self::post_request($data);

        return $response;
    }

    /**
     * Validates the license key.
     * @param license_key_input license key from the input form.
     * @param addon_slug Respective add-on for the key.
     */
    public static function validate_license_key($license_key_input, $addon_slug) {
        // if license key is empty, do nothing
        if (empty($license_key_input)) return 'empty';

        $license_key = self::escape_license($license_key_input);

        if (self::get_license($addon_slug) == $license_key) return $license_key;

        $response = self::check_license($license_key);


        // if the license is invalid OR an invalid Wild Apricot URL is being used, return NULL
        // else return the valid license key
        $filename = self::get_filename($addon_slug);
        if (array_key_exists('license-error', $response)) return NULL;

        if (!(array_key_exists('Products', $response) && array_key_exists('Support Level', $response) && array_key_exists('expiration date', $response))) return NULL;

        // check that the license owner has access to the product and support
        // Get list of product(s) that this license is valid for
        $valid_products = $response['Products'];
        $support_level = $response['Support Level'];
        $exp_date = $response['expiration date'];

        
        // Check if the addon_slug in in the valid products
        if (!in_array(CORE_SLUG, $valid_products) || $support_level != 'support' || self::is_expired($exp_date)) {
            // Not Valid!
            return NULL;
        }

        // Ensure that this license key is valid for the associated Wild Apricot ID and website
        // Compare these licensed urls and ids with the current site's urls/ids
        // Check if Wild Apricot credentials have been entered.
        // If so, then we can check if the plugin will be activated.
        // If not, then the plugin cannot be activated
        $valid_urls_and_ids = WAIntegration::check_licensed_wa_urls_ids($response);


        if (!$valid_urls_and_ids) {
            return NULL;
        }

        return $license_key;
    }

    /**
     * Sends a POST request to the license key validation hook, returns response data
     * @param array $data request data containing license key and JSON flag
     * @return array JSON response data 
     */
    public static function post_request($data) {

        // get integromat url from redirect
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::HOOK_URL);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($curl);
        $url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

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

    public static function valid_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        $filename = self::get_filename($slug);
        echo "<div class='notice notice-success is-dismissible'><p>";
		echo "Saved license key for <strong>" . $plugin_name . "</strong>.</p>";
		if (!is_plugin_active($filename)) {
			activate_plugin($filename);
			echo "<p>Activating plugin.</p>";
		}
		echo "</div>";
    }

    public static function invalid_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        $filename = self::get_filename($slug);
        echo "<div class='notice notice-error is-dismissible'><p>";
        echo "Your license key is invalid or expired. To get a new key please visit the <a href='https://newpathconsulting.com/wild-apricot-for-wordpress/'>Wild Apricot for Wordpress website</a>.";
        echo "</div>";
    }

    public static function empty_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        $filename = self::get_filename($slug);
        echo "<div class='notice notice-warning'><p>";
        echo "Please enter a valid license key for <strong>" . $plugin_name . "</strong>. </p></div>";
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

        echo "<div class='notice notice-warning is-dismissable'><p>";
        echo "Please enter your license key";
        if (!$is_licensing_page) {
         echo " in <a href=" . admin_url('admin.php?page=wawp-licensing') . ">Wild Apricot Press > Licensing</a>"; 
        }
        
        echo " in order to use the " . $plugin_name . " functionality.</p></div>";

        unset($_GET['activate']);
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



} // end of Addon class

?>
