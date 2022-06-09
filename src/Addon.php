<?php
namespace WAWP;

require_once __DIR__ . '/WAWPApi.php';
require_once __DIR__ . '/WAIntegration.php';
require_once __DIR__ . '/DataEncryption.php';
require_once __DIR__ . '/Log.php';


use \DateTime; // for checking license key expiration dates
use WAWP\Log;

/**
 * Addon class
 * For managing the Addon plugins for WAWP
 */
class Addon {
    const HOOK_URL = 'https://hook.integromat.com/mauo1z5yn88d94lfvc3wd4qulaqy1tko';
    // const HOOK_URL = 'https://newpathconsulting.com/checkdev';

    const FREE_ADDONS = array(0 => 'wawp');
    const PAID_ADDONS = array(0 => 'wawp-addon-wa-iframe');

    private static $instance = null;

    private function __construct() {}

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

    private static $addons_to_license = array();
    private static $addon_list = array();

    /**
     * Returns the array of addons stored in the options table.
     *
     * @return array of add-ons
     */
    public static function get_addons() {
        return get_option('wawp_addons');
    }

    /**
     * Returns the array of license keys stored in the options table.
     *
     * @return array of license keys
     */
    public static function get_licenses() {
        return get_option('wawp_license_keys');
    }

    /**
     * Gets the license key based on the slug name
     *
     * @param string $slug is the slug name of the add-on
     */
    public static function get_license($slug) {
        $licenses = self::get_licenses();
        if (!empty($licenses) && array_key_exists($slug, $licenses)) {
            return $licenses[$slug];
        }
        return '';
    }

    /**
     * Checks if slug name currently has a license
     *
     * @param string $slug is the slug name of the add-on
     */
    public static function has_license($slug) {
        // do_action('qm/debug', '{a} in has_license', ['a' => $slug]);
        $licenses = self::get_licenses();
        return $licenses && array_key_exists($slug, $licenses);
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

    /**
     * Gets title of add-on based on slug name
     *
     * @param string $slug is the slug name of the add-on
     */
    public static function get_title($slug) {
        $addons = self::get_addons();
        return $addons[$slug]['title'];
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

        $slug = array_key_first($addon);
        $option[$slug] = $addon[$slug];
        // array_push($option, $addon);
        update_option('wawp_addons', $option);

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
     * Validates the license key.
     * @param license_key_input license key from the input form.
     * @param addon_slug Respective add-on for the key.
     */
    public static function validate_license_key($license_key_input, $addon_slug) {
        // if license key is empty, do nothing
        if (empty($license_key_input)) return 'empty';

        // remove non-alphanumeric, non-hyphen characters
        $license_key = preg_replace(
            '/[^A-Za-z0-9\-]/',
            '',
            $license_key_input
        );

        Log::good_error_log('plugin ' . $addon_slug);
        if (self::get_license($addon_slug) == $license_key) return $license_key;

        // construct array of data to send
        $data = array('key' => $license_key, 'json' => '1');

        // send request, receive response in $response
        $response = self::post_request($data);
        Log::good_error_log('response ' . print_r($response, 1));
        

        // if the license is invalid OR an invalid Wild Apricot URL is being used, return NULL
        // else return the valid license key
        $filename = self::get_filename($addon_slug);
        if (array_key_exists('license-error', $response)) {
            // Ensure that we are not trying to deactivate the wawp plugin
            // if (is_plugin_active($filename) && $filename != 'wawp/plugin.php') {
            //     deactivate_plugins($filename);
            // }
            return NULL;
        } else { // no error
            // check that the license owner has access to the product and support
            if (array_key_exists('Products', $response) && array_key_exists('Support Level', $response) && array_key_exists('expiration date', $response)) {
                // Get list of product(s) that this license is valid for
                $valid_products = $response['Products'];
                $support_level = $response['Support Level'];
                $exp_date = $response['expiration date'];

                
                // Check if the addon_slug in in the valid products
                if (!in_array('wawp', $valid_products) || $support_level != 'support' || self::is_expired($exp_date)) {
                    // Not Valid!
                    return NULL;
                }
            } else {
                // No products; invalid key
                return NULL;
            }

            // Ensure that this license key is valid for the associated Wild Apricot ID and website
            // Get authorized Wild Apricot URL and ID
            $licensed_wa_urls = array();
            if (array_key_exists('Licensed Wild Apricot URLs', $response)) {
                $licensed_wa_urls = $response['Licensed Wild Apricot URLs'];
                // Sanitize urls, if necessary
                if (!empty($licensed_wa_urls)) {
                    foreach ($licensed_wa_urls as $url_key => $url_value) {
                        // Lowercase and remove https://, http://, and/or www. from url
                        $licensed_wa_urls[$url_key] = WAWPApi::create_consistent_url($url_value);
                    }
                }
            }
            $licensed_wa_ids = array();
            if (array_key_exists('Licensed Wild Apricot Account IDs', $response)) {
                $licensed_wa_ids = $response['Licensed Wild Apricot Account IDs'];
                // Sanitize ids, if necessary
                if (!empty($licensed_wa_ids)) {
                    foreach ($licensed_wa_ids as $id_key => $id_value) {
                        // Ensure that only numbers are in the ID #
                        // $licensed_wa_ids[$id_key] = preg_replace('/\d/', '', $id_value);
                        $licensed_wa_ids[$id_key] = intval($id_value);
                    }
                }
            }
            // Compare these licensed urls and ids with the current site's urls/ids
            // Check if Wild Apricot credentials have been entered.
            // If so, then we can check if the plugin will be activated.
            // If not, then the plugin cannot be activated
            $user_credentials = WAWPApi::load_user_credentials();
            if (!empty($user_credentials)) { // Credentials have been entered
                // Get access token and account id
                $access_and_account = WAWPApi::verify_valid_access_token();
                $access_token = $access_and_account['access_token'];
                $wa_account_id = $access_and_account['wa_account_id'];
                // Get account url from API
                $wawp_api = new WAWPApi($access_token, $wa_account_id);
                $wild_apricot_info = $wawp_api->get_account_url_and_id();

                // Compare license key information with current site
                if (in_array($wild_apricot_info['Id'], $licensed_wa_ids) && in_array($wild_apricot_info['Url'], $licensed_wa_urls)) { // valid
                    // This is valid! We can now 'activate' the WAWP functionality
                    do_action('wawp_wal_credentials_obtained');
                    return $license_key;
                } else { // This key is invalid!
                    do_action('wawp_wal_set_login_private');
                    return NULL;
                }


            } // Wild Apricot credentials are guaranteed to be added because the licensing page only appears when they have been entered!

            if (!is_plugin_active($filename)) {
                activate_plugin($filename);
            }
        }
    }

    /**
     * Sends a POST request to the license key validation hook, returns response data
     * @param data request data containing license key and JSON flag
     */
    public static function post_request($data) {
        $options = array(
                'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);

        $result = json_decode(file_get_contents(self::HOOK_URL, false, $context), 1);

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

    public static function empty_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        $filename = self::get_filename($slug);
        echo "<div class='notice notice-error is-dismissible'><p>";
        echo "Invalid key entered for <strong>" . $plugin_name . "</strong>.</p>";
        if (is_plugin_active($filename)) {
            echo "<p>Deactivating plugin.</p>";
        }
        echo "</div>";
        deactivate_plugins($filename); 
    }

    public static function invalid_license_key_notice($slug) {
        $plugin_name = self::get_title($slug);
        $filename = self::get_filename($slug);
        echo "<div class='notice notice-warning'><p>";
        echo "Please enter a valid license key for <strong>" . $plugin_name . "</strong>. </p></div>";
        unset($_GET['activate']); // prevents printing "Plugin activated" message
        deactivate_plugins($filename);
    }

    /**
     * Returns whether the license key is expired or not.
     * @param exp_date_string the expiry date of the license from the response data
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
