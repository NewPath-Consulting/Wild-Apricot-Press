<?php

namespace WAWP;

require_once __DIR__ . '/class-settings-controller.php';
require_once __DIR__ . '/../class-addon.php';
require_once __DIR__ . '/../util/class-log.php';
require_once __DIR__ . '/../util/class-data-encryption.php';
require_once __DIR__ . '/../class-wa-api.php';
require_once __DIR__ . '/../class-wa-integration.php';
require_once __DIR__ . '/../util/helpers.php';
require_once __DIR__ . '/../util/wap-exception.php';

/**
 * Handles creating, rendering, and sanitizing WildApricot Authorization settings.
 *
 * @since 1.1
 * @author Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class WA_Auth_Settings
{
    /**
     * Stores encrypted WA authorization credentials.
     *
     * @var string
     */
    public const WA_CREDENTIALS_KEY 					= 'wawp_wal_name';

    /**
     * Key in `WA_CREDENTIALS_KEY` option storing the encrypted API key.
     *
     * @var string
     */
    public const WA_API_KEY_OPT 						= 'wawp_wal_api_key';

    /**
     * Key in `WA_CREDENTIALS_KEY` option storing the encrypted client ID.
     *
     * @var string
     */
    public const WA_CLIENT_ID_OPT 						= 'wawp_wal_client_id';

    /**
     * Key in `WA_CREDENTIALS_KEY` option storing the encrypted client secret.
     *
     * @var string
     */
    public const WA_CLIENT_SECRET_OPT 					= 'wawp_wal_client_secret';

    /**
     * Stores the validity status of the WildApricot API credentials.
     *
     * @var string
     */
    public const API_STATUS_OPTION = 'wawp_api_status';

    /**
     * Valid WA API credentials.
     *
     * @var string
     */
    public const API_STATUS_VALID = 'valid';

    /**
     * Invalid WA API credentials.
     *
     * @var string
     */
    public const API_STATUS_INVALID = 'invalid';

    /**
     * WA API credentials have the incorrect scope - read only or
     * WordPress credentials.
     *
     * @var string
     */
    public const API_STATUS_INCORRECT_SCOPE = 'incorrect_scope';
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    public const OPTION_GROUP = 'wap_wa_auth_group';
    public const SUBMENU_PAGE = 'wap-wa-auth-login';
    public const SECTION = 'wap_wa_auth_section';

    public function __construct()
    {
    }

    public static function get_api_input_status()
    {
        return get_option(self::API_STATUS_OPTION);
    }

    public static function set_api_status_invalid()
    {
        update_option(self::API_STATUS_OPTION, self::API_STATUS_INVALID);
    }

    public static function set_api_status_incorrect_scope()
    {
        update_option(self::API_STATUS_OPTION, self::API_STATUS_INCORRECT_SCOPE);
    }

    public static function set_api_status_valid()
    {
        update_option(self::API_STATUS_OPTION, self::API_STATUS_VALID);
    }

    /**
     * Adds the authorization submenu page to the admin menu.
     *
     * @return void
     */
    public function add_submenu_page()
    {
        add_submenu_page(
            Settings_Controller::SETTINGS_URL,
            'WildApricot Authorization',
            'Authorization',
            'manage_options',
            self::SUBMENU_PAGE,
            array($this, 'create_wa_auth_login_page')
        );
    }

    /**
     * Registers the login settings and creates the respective settings sections
     * and fields for the API key, client ID, and client secret.
     *
     * @return void
     */
    public function register_setting_add_fields()
    {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize'),
            'default' => null
        );

        // Register setting
        register_setting(
            self::OPTION_GROUP, // Option group
            self::WA_CREDENTIALS_KEY, // Option name
            $register_args // Sanitize
        );

        // Create settings section
        add_settings_section(
            self::SECTION, // ID
            'WildApricot Authorized Application Credentials', // Title
            array( $this, 'wal_print_section_info' ), // Callback
            self::SUBMENU_PAGE // Page
        );

        // Settings for API Key
        add_settings_field(
            self::WA_API_KEY_OPT, // ID
            'API Key:', // Title
            array( $this, 'api_key_callback' ), // Callback
            self::SUBMENU_PAGE, // Page
            self::SECTION // Section
        );

        // Settings for Client ID
        add_settings_field(
            self::WA_CLIENT_ID_OPT, // ID
            'Client ID:', // Title
            array( $this, 'client_id_callback' ), // Callback
            self::SUBMENU_PAGE, // Page
            self::SECTION // Section
        );

        // Settings for Client Secret
        add_settings_field(
            self::WA_CLIENT_SECRET_OPT, // ID
            'Client Secret:', // Title
            array( $this, 'client_secret_callback' ), // Callback
            self::SUBMENU_PAGE, // Page
            self::SECTION // Section
        );
    }

    /**
     * API credentials settings page callback. Renders form for API credentials,
     * checkbox for login button location on the website menu, and tutorial
     * for obtaining WildApricot API credentials.
     *
     * @return void
     */
    public function create_wa_auth_login_page()
    {
        $this->options = get_option(self::WA_CREDENTIALS_KEY);

        ?>
<div class="wrap">
    <h1>Authorization</h1>
    <div class="waSettings">
        <div class="loginChild">
            <!-- WildApricot credentials form -->
            <form method="post" action="options.php">
                <?php
        // Nonce for verification
        wp_nonce_field('wawp_credentials_nonce_action', 'wawp_credentials_nonce_name');
        // This prints out all hidden setting fields
        settings_fields(self::OPTION_GROUP);
        do_settings_sections(self::SUBMENU_PAGE);
        submit_button();
        ?>
            </form>
            <!-- Check if form is valid -->
            <?php
            $wild_apricot_url = $this->check_wild_apricot_url();
        // if there's a fatal error don't display anything after credentials form
        if (Exception::fatal_error()) {
            ?>
        </div>
    </div>
</div> <?php
            return;
        }
        $api_status = self::get_api_input_status();
        if ($api_status == self::API_STATUS_INVALID) {
            // not valid
            echo '<p class="wap-error">Missing valid WildApricot credentials. Please enter them above.</p>';
        } elseif ($api_status == self::API_STATUS_INCORRECT_SCOPE) {
            // incorrect scope selected
            echo '<p class="wap-error">Incorrect application scope. Please create a <strong>full-access Server Application</strong> on WildApricot.</p>';
        } elseif ($api_status == self::API_STATUS_VALID && $wild_apricot_url) {
            // successful login
            echo '<p class="wap-success">Valid WildApricot credentials have been saved.</p>';
            echo '<p class="wap-success">Your WordPress site has been connected to <b>' . esc_url($wild_apricot_url) . '</b>.</p>';
        }
        return;
        ?>
</div>
</div>
</div>
<?php
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     * @return array array of sanitized input, empty array if invalid or fatal error
     */
    public function sanitize($input)
    {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_credentials_nonce_name'], 'wawp_credentials_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the WildApricot credentials could not be verified.');
            return empty_string_array($input);
        }

        $valid = array();
        // try to validate and encrypt input, and connect to api
        // any of the functions in this try block could throw an exception, catcn will handle all of them.
        try {
            $valid = self::validate_and_sanitize_wa_input($input);
            if (!$valid) {
                return empty_string_array($input);
            }

            $api_key = $valid[self::WA_API_KEY_OPT];
            $valid_api = WA_API::is_application_valid($api_key);

            // empty response from WA API --> credentials invalid
            if (!$valid_api) {
                self::set_api_status_invalid();
                return empty_string_array($input);
            }

            // application w/ full access has 15 accessible endpoints
            $scope = count($valid_api['Permissions'][0]['AvailableScopes']);
            if ($scope < 15) {
                self::set_api_status_incorrect_scope();
                return empty_string_array($input);
            }

            self::set_api_status_valid();
            self::obtain_and_save_wa_data_from_api($valid_api);

            // encrypt valid credentials
            $data_encryption = new Data_Encryption();
            foreach ($valid as $key => $value) {
                $valid[$key] = $data_encryption->encrypt($value);
            }
        } catch (Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
            return empty_string_array($input);
        }

        // Return array of valid inputs
        return $valid;
    }

    /**
     * Print the instructions text for entering your WildApricot credentials
     *
     * @return void
     */
    public function wal_print_section_info()
    {
        print 'Please enter your WildApricot authorization credentials here. Your data is encrypted for your safety.<br>';
        print 'To obtain your WildApricot authorization credentials, please create a full-access <strong>Server application</strong>.<br><br>';

        print 'Refer to <a href="https://gethelp.wildapricot.com/en/articles/180-authorizing-external-applications" target="_blank">WildApricot support</a> for more details on creating an authorized application.<br>';
        print '<strong>IMPORTANT:</strong> Do NOT create a WordPress authorized application for authorizing WildApricot Press.';
    }

    /**
     * Display text field for API key
     *
     * @return void
     */
    public function api_key_callback()
    {
        echo '<input class="wap-wa-auth-creds" id="wawp_wal_api_key" name="wawp_wal_name[wawp_wal_api_key]"
			type="text" placeholder="*************" />';
        // Check if api key has been set; if so, echo that the client secret has been set!
        if (!empty($this->options[self::WA_API_KEY_OPT]) && !Exception::fatal_error()) {
            echo '<p>API Key is set</p>';
        }
    }

    /**
     * Display text field for Client ID
     *
     * @return void
     */
    public function client_id_callback()
    {
        echo '<input class="wap-wa-auth-creds" id="wawp_wal_client_id" name="wawp_wal_name[wawp_wal_client_id]"
			type="text" placeholder="*************" />';
        // Check if client id has been set; if so, echo that the client secret has been set!
        if (!empty($this->options[self::WA_CLIENT_ID_OPT]) && !Exception::fatal_error()) {
            echo '<p>Client ID is set</p>';
        }
    }

    /**
     * Display text field for Client Secret
     *
     * @return void
     */
    public function client_secret_callback()
    {
        echo '<input class="wap-wa-auth-creds" id="wawp_wal_client_secret" name="wawp_wal_name[wawp_wal_client_secret]"
			type="text" placeholder="*************" />';
        // Check if client secret has been set; if so, echo that the client secret has been set!
        if (!empty($this->options[self::WA_CLIENT_SECRET_OPT]) && !Exception::fatal_error()) {
            echo '<p>Client Secret is set</p>';
        }
    }

    /**
     * Obtain WildApricot URL corresponding to the entered API credentials.
     *
     * @return string|bool WildApricot URL, false if it could not be obtained
     */
    private function check_wild_apricot_url()
    {
        $wild_apricot_url = get_option(WA_Integration::WA_URL_KEY);
        try {
            if ($wild_apricot_url) {
                $dataEncryption = new Data_Encryption();
                $wild_apricot_url = esc_url($dataEncryption->decrypt($wild_apricot_url));
            }
        } catch (Decryption_Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
            return false;
        }
        return $wild_apricot_url;
    }

    /**
     * Sanitize and validate each input value for the WildApricot API
     * Credentials.
     *
     * @param string[] $input
     * @return string[]|false returns array of valid inputs and false if inputs are not valid
     */
    private static function validate_and_sanitize_wa_input($input)
    {
        $valid = array();
        foreach ($input as $key => $value) {
            // remove non-alphanumeric chars
            $valid[$key] = preg_replace(
                '/[^A-Za-z0-9]+/',
                '',
                $value
            );


            if ($valid[$key] != $value || empty($value)) {
                return false;
            }

        }

        return $valid;
    }

    /**
     * Gets WildApricot membership levels and groups, account URL and ID and
     * sets transients and updates options. Data encryption and API calls could
     * throw exceptions which are caught in the caller function.
     *
     * @param string[] $valid_api response from initial connection to WildApricot API
     * @return void
     */
    private static function obtain_and_save_wa_data_from_api($valid_api)
    {
        $data_encryption = new Data_Encryption();
        // Extract access token and ID, as well as expiring time
        $access_token = $valid_api['access_token'];
        $account_id = $valid_api['Permissions'][0]['AccountId'];
        $expiring_time = $valid_api['expires_in'];
        $refresh_token = $valid_api['refresh_token'];

        $access_token_enc = $data_encryption->encrypt($access_token);
        $account_id_enc = $data_encryption->encrypt($account_id);
        $refresh_token_enc = $data_encryption->encrypt($refresh_token);

        // Get all membership levels and groups
        $wawp_api_instance = new WA_API($access_token, $account_id);
        $all_membership_levels = $wawp_api_instance->get_membership_levels();

        $all_membership_groups = $wawp_api_instance->get_membership_levels(true);

        // Get WildApricot URL
        $wild_apricot_url_array = $wawp_api_instance->get_account_url_and_id();
        $wild_apricot_url = esc_url_raw($wild_apricot_url_array['Url']);

        // if wild apricot site changes, remove saved custom fields and license and WAP user data
        $old_wa_url = get_option(WA_Integration::WA_URL_KEY);
        $old_wa_url = $data_encryption->decrypt($old_wa_url);

        if ($old_wa_url != $wild_apricot_url) {
            Addon::wa_auth_changed_update_status();
            WA_Integration::remove_wa_users();
            delete_option(WA_Integration::LIST_OF_CHECKED_FIELDS);
            delete_option(WA_Restricted_Posts::ARRAY_OF_RESTRICTED_POSTS);

            delete_option(WA_Integration::WA_CONTACTS_COUNT_KEY);
            // delete post meta added by the plugin
            delete_post_meta_by_key(WA_Restricted_Posts::RESTRICTED_GROUPS);
            delete_post_meta_by_key(WA_Restricted_Posts::RESTRICTED_LEVELS);
            delete_post_meta_by_key(WA_Restricted_Posts::IS_POST_RESTRICTED);
            delete_post_meta_by_key(WA_Restricted_Posts::INDIVIDUAL_RESTRICTION_MESSAGE_KEY);
        }

        $wild_apricot_url_enc = $data_encryption->encrypt($wild_apricot_url);

        // Save transients and options all at once; by this point all values should be valid.
        // Store access token and account ID as transients
        set_transient(WA_Integration::ADMIN_ACCESS_TOKEN_TRANSIENT, $access_token_enc, $expiring_time);
        set_transient(WA_Integration::ADMIN_ACCOUNT_ID_TRANSIENT, $account_id_enc, $expiring_time);
        // Store refresh token in database
        update_option(WA_Integration::ADMIN_REFRESH_TOKEN_OPTION, $refresh_token_enc);
        // Save membership levels and groups to options
        update_option(WA_Integration::WA_ALL_MEMBERSHIPS_KEY, $all_membership_levels);
        update_option(WA_Integration::WA_ALL_GROUPS_KEY, $all_membership_groups);
        update_option(WA_Integration::WA_URL_KEY, $wild_apricot_url_enc);
        // Create a new role for each membership level
        // Delete old roles if applicable
        $old_wa_roles = get_option(WA_Integration::WA_ALL_MEMBERSHIPS_KEY);
        if (isset($old_wa_roles) && !empty($old_wa_roles)) {
            // Loop through each role and delete it
            foreach ($old_wa_roles as $old_role) {
                remove_role('wawp_' . str_replace(' ', '', $old_role));
            }
        }
        foreach ($all_membership_levels as $level) {
            // In identifier, remove spaces so that the role can become a single word
            add_role('wawp_' . str_replace(' ', '', $level), $level);
        }
    }

}