<?php
namespace WAWP;

/**
 * Addon class
 */
class Addon {
    const HOOK_URL = 'https://hook.integromat.com/mauo1z5yn88d94lfvc3wd4qulaqy1tko';

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

    // public static function init() {
    //     // if there are existing licenses in the options table, initialize the list with those
    //     $existing_licenses = get_option('wawp_license_keys');
    //     if ($existing_licenses !== false) {
    //         self::$addons_to_license = $existing_licenses;
    //     }
    // }
    
    /**
     * Returns the array of addons stored in the options table.
     */
    public static function get_addons() {
        return get_option('wawp_addons');
    }

    /**
     * Retuens the array of license keys stored in the options table.
     */
    public static function get_licenses() {
        return get_option('wawp_license_keys');
    }

    public static function has_license($slug) {
        $licenses = self::get_licenses();
        return array_key_exists($slug, $licenses);
    }

    // @param: addon is assoc. array of slug => display title
    /**
     * Adds a new add-on to the array of add-ons stored in the options table.
     * @param $addon Add-on to be added to the DB. 
     * Is an assoc. array of addon (slug => title)
     */
    public static function new_addon($addon) {
        $option = get_option('wawp_addons');
        if ($option == false) {
            $option = array();
        }
        if (in_array($addon, $option)) {
            return;
        }

        array_push($option, $addon);
        update_option('wawp_addons', $option);
        $slug = array_key_first($addon);
        if (in_array($slug, self::$instance::$addon_list)) {
            return;
        }
        self::$instance::$addon_list[$slug] = $addon[$slug];
    }

    /**
     * Called in uninstall.php. Deletes the data stored in the options table.
     */
    public static function delete() {
        delete_option('wawp_addons');
        delete_option('wawp_license_keys');

        // TODO: delete the other addons
    }

    /**
     * Validates the license key.
     * @param license_key_input license key from the input form.
     * @param addon_slug Respective add-on for the key.
     */
    public static function validate_license_key($license_key_input, $addon_slug) {
        // if license key is empty, do nothing
        if (empty($license_key_input)) return ;

        // remove non-alphanumeric, non-hyphen characters
        $license_key = preg_replace(
            '/[^A-Za-z0-9\-]/',
            '',
            $license_key_input
        );


        // construct array of data to send
        $data = array('key' => $license_key, 'json' => '1');

        // send request, receive response in $response
        $response = self::post_request($data);

        // if the license is invalid, return NULL
        // else return the valid license key
        if (array_key_exists('license-error', $response)) {
            return NULL;
        } else {
            // $plugin_name =__DIR__ . 
            // if (!is_plugin_active(__FILE__)) {
            //     activate_plugin(__FILE__);
            // }
            return $license_key;
        }
    }

    private static function show_error($slug) {
        $addons = self::get_addons();
        echo "<div class='error'><p>";
        echo "Invalid license key for " . $addons[$slug] . ". </p></div>";
    }

    /**
     * Sends a POST request to the license key validation hook, returns response data
     * @param data request data containing license key and JSON flag
     */
    private static function post_request($data) {
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

} // end of Addon class


?>