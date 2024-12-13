<?php

namespace WAWP;

// For iterating through menu HTML
use DOMDocument;

require_once __DIR__ . '/class-addon.php';
require_once __DIR__ . '/util/class-data-encryption.php';
require_once __DIR__ . '/util/class-log.php';
require_once __DIR__ . '/class-wa-api.php';
require_once __DIR__ . '/util/helpers.php';
require_once __DIR__ . '/class-restricted-posts.php';
require_once __DIR__ . '/class-wa-login.php';
require_once __DIR__ . '/class-wa-user.php';

/**
 * Class for managing WildApricot user accounts and post restriction.
 *
 * @since 1.0b1
 * @author Spencer Gable-Cook and Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class WA_Integration
{
    // Keys for data stored in WP databases.

    /**
     * Stores the total number of WildApricot contacts.
     */
    public const WA_CONTACTS_COUNT_KEY					= 'wawp_contacts_count';

    /**
     * Stores user's WA user ID in the user meta data.
     *
     * @var string
     */
    public const WA_USER_ID_KEY 						= 'wawp_wa_user_id';

    /**
     * Stores user's WA membership level(s) in the user meta data.
     *
     * @var string
     */
    public const WA_MEMBERSHIP_LEVEL_KEY 				= 'wawp_membership_level_key';

    /**
     * Stores user's WA membership ID(s) in the user meta data.
     *
     * @var string
     */
    public const WA_MEMBERSHIP_LEVEL_ID_KEY			= 'wawp_membership_level_id_key';

    /**
     * Stores user's WA status in the user meta data.
     *
     * @var string
     */
    public const WA_USER_STATUS_KEY 					= 'wawp_user_status_key';

    /**
     * Stores user's WA organization(s) in the user meta data.
     *
     * @var string
     */
    public const WA_ORGANIZATION_KEY 					= 'wawp_organization_key';

    /**
     * Stores user's WA group(s) in the user meta data.
     *
     * @var string
     */
    public const WA_MEMBER_GROUPS_KEY 					= 'wawp_list_of_groups_key';

    /**
     * Stores all existing membership levels in the linked WA admin account
     * in the options table.
     *
     * @var string
     */
    public const WA_ALL_MEMBERSHIPS_KEY 				= 'wawp_all_levels_key';

    /**
     * Stores all existing membership groups in the linked WA admin account
     * in the options table.
     *
     * @var string
     */
    public const WA_ALL_GROUPS_KEY						= 'wawp_all_groups_key';

    /**
     * Stores transient for the WA admin account ID. Deleted after 30 minutes.
     *
     * @var string
     */
    public const ADMIN_ACCOUNT_ID_TRANSIENT 			= 'wawp_admin_account_id';

    /**
     * Stores transient for the encrypted WA admin access token.
     * Deleted after 30 minutes.
     *
     * @var string
     */
    public const ADMIN_ACCESS_TOKEN_TRANSIENT 			= 'wawp_admin_access_token';

    /**
     * Stores transient for the encrypted WA admin refresh token.
     * Deleted after 30 minutes.
     *
     * @var string
     */
    public const ADMIN_REFRESH_TOKEN_OPTION 			= 'wawp_admin_refresh_token';

    /**
     * Stores all WA fields. Displayed in admin settings.
     *
     * @var string
     */
    public const LIST_OF_CUSTOM_FIELDS 				= 'wawp_list_of_custom_fields';

    /**
     * Stores WA fields that are WA admin only for display in admin settings.
     *
     * @var string
     */
    public const LIST_OF_ADMIN_FIELDS               = 'wawp_list_of_admin_fields';

    /**
     * Stores WA fields selected by user to sync with WordPress. Controlled in
     * admin settings.
     *
     * @var string
     */
    public const LIST_OF_CHECKED_FIELDS 				= 'wawp_fields_name';

    /**
     * Stores whether the user is a WA user added by the plugin in the user meta
     * data. Called when a user logs in with the WAP login shortcode.
     *
     * @var string
     */
    public const USER_ADDED_BY_PLUGIN 					= 'wawp_user_added_by_plugin';

    /**
     * Stores menu IDs on which the WAP login/logout button will appear.
     * Controlled in admin settings.
     *
     * @var string
     */
    public const MENU_LOCATIONS_KEY 					= 'wawp_menu_location_name';

    /**
     * Stores the encrypted WA URL to which the WP site is connected to.
     * Corresponds to the WA authorization credentials.
     *
     * @var string
     */
    public const WA_URL_KEY 							= 'wawp_wa_url_key';

    /**
     * Stores the flag indicating whether all WildApricot information should be
     * deleted when the plugin is deleted. Controlled in admin settings.
     *
     * @var string
     */
    public const WA_DELETE_OPTION 						= 'wawp_delete_setting';

    /**
     * Stores the page ID of the WAP login page created by the plugin in the
     * options table.
     *
     * @var string
     */
    public const LOGIN_PAGE_ID_OPT						= 'wawp_wal_page_id';

    public const CRON_FREQUENCY_OPT                     = 'wawp_cron_frequency';

    // Custom hook names

    public const CRON_HOOK = 'wawp_cron_refresh_memberships_hook';

    /**
     * License data refresh hook. Scheduled to run daily.
     *
     * @var string
     */
    public const CREDENTIALS_CHECK_HOOK 			    = 'wawp_cron_check_credentials';

    public $wa_login;
    public $wa_user;
    public $wa_post_restriction;

    /**
     * Constructs an instance of the WA_Integration class.
     *
     * Adds the actions and filters required.
     *
     * @return WA_Integration new WA_Integration instance
     */
    public function __construct()
    {
        $wa_login = new WA_Login();
        // $wa_user = new WA_User();
        $wa_post_restriction = new WA_Restricted_Posts();

        // Custon action for restricting access to login page
        add_action('remove_wa_integration', array($this, 'remove_wild_apricot_integration'));

        // Fires when displaying or editing user profile, adds WA user data
        add_action('show_user_profile', array($this, 'show_membership_level_on_profile'));
        add_action('edit_user_profile', array($this, 'show_membership_level_on_profile'));

        // Add actions for cron update
        add_action(self::CRON_HOOK, array($this, 'cron_update_wa_memberships'));

        // Action for hiding admin bar for non-admin users, fires after the theme is loaded
        add_action('after_setup_theme', array($this, 'hide_admin_bar'));

        // Fires on every page, checks credentials and disables plugin if necessary
        // add_action('init', array($this, 'check_updated_credentials'));

        // Action for Cron job that refreshes the license check
        add_action(self::CREDENTIALS_CHECK_HOOK, 'WAWP\WA_Integration::check_updated_credentials');

        // Fires when access to the admin page is denied, displays message prompting user to log out of their WA account
        add_action('admin_page_access_denied', array($this, 'tell_user_to_logout'));

        // filter to add custom cron recurrences
        add_filter('cron_schedules', array($this, 'add_custom_cron_recurrence'));
    }

    public function add_custom_cron_recurrence($schedules)
    {
        $schedules['wawp_interval_3x_daily'] = array(
            'interval' => 8 * HOUR_IN_SECONDS,
            'display'  => __('Three times daily (every 8 hours)', 'newpath-wildapricot-press')
        );

        $schedules['wawp_interval_4x_daily'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __('Four times daily (every 6 hours)', 'newpath-wildapricot-press')
        );

        $schedules['wawp_interval_every_other_day'] = array(
            'interval' => 48 * HOUR_IN_SECONDS,
            'display'  => __('Every other day', 'newpath-wildapricot-press')
        );
        return $schedules;
    }

    public static function get_cron_frequency()
    {
        $option = get_option(self::CRON_FREQUENCY_OPT);
        return $option ? $option : 'daily';
    }

    public static function schedule_cron_jobs()
    {
        $cron_freq = self::get_cron_frequency();
        if (!wp_next_scheduled(self::CREDENTIALS_CHECK_HOOK)) {
            wp_schedule_event(current_time('timestamp'), $cron_freq, self::CREDENTIALS_CHECK_HOOK);
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(current_time('timestamp'), $cron_freq, self::CRON_HOOK);
        }

        WA_User::create_cron_for_user_refresh($cron_freq);
    }

    public static function reschedule_cron_jobs(?string $new_freq = '')
    {
        self::unschedule_all_cron_jobs();
        $cron_freq = $new_freq ? $new_freq : self::get_cron_frequency();
        wp_schedule_event(current_time('timestamp'), $cron_freq, self::CREDENTIALS_CHECK_HOOK);
        wp_schedule_event(current_time('timestamp'), $cron_freq, self::CRON_HOOK);
        WA_User::create_cron_for_user_refresh($cron_freq);
    }

    public static function unschedule_all_cron_jobs()
    {
        self::unschedule_cron_job(self::CREDENTIALS_CHECK_HOOK);
        self::unschedule_cron_job(self::CRON_HOOK);
        self::unschedule_cron_job(WA_User::USER_REFRESH_HOOK);
    }

    public static function refresh_all_data()
    {
        // TODO: see where disable_plugin is called, add if needed
        try {
            $credentials = WA_API::load_user_credentials();
            $is_valid = WA_API::is_application_valid($credentials[WA_Auth_Settings::WA_API_KEY_OPT]);
            $access = WA_API::verify_valid_access_token();
            $access_token = $access['access_token'];
            $admin_account_id = $access['wa_account_id'];
            $wa_api = new WA_API($access_token, $admin_account_id);
        } catch (Exception $e) {
            Log::wap_log_error($e->getMessage());
        }

        Addon::update_licenses();

        self::cron_update_wa_memberships($wa_api);
        WA_User::refresh_user_wa_info($wa_api);
    }


    /**
    * Updates the membership levels and groups from WildApricot into WordPress upon each CRON job.
    *
    * @return void
    */
    public function cron_update_wa_memberships()
    {
        // Ensure that access token is valid
        try {
            $valid_access_credentials = WA_API::verify_valid_access_token();
        } catch (Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
            return;
        }


        $access_token = $valid_access_credentials['access_token'];
        $wa_account_id = $valid_access_credentials['wa_account_id'];

        // Ensure that access token and account id exist
        if (!empty($access_token) && !empty($wa_account_id)) {

            try {
                // Create WAWP Api instance
                $wawp_api = new WA_API($access_token, $wa_account_id);

                // Get membership levels
                $updated_levels = $wawp_api->get_membership_levels();

                // Get membership groups
                $updated_groups = $wawp_api->get_membership_levels(true);
            } catch (API_Exception $e) {
                Log::wap_log_error($e->getMessage(), true);
                return;
            }


            // TODO: instead of only checking for deleted groups/levels, also check for new ones
            // If the number of updated groups/levels is less than the number of old groups/levels, then this means that one or more group/level has been deleted
            // So, we must find the deleted group/level and remove it from the restriction post meta data of a post, if applicable
            $old_levels = get_option(WA_Integration::WA_ALL_MEMBERSHIPS_KEY);
            $old_groups = get_option(WA_Integration::WA_ALL_GROUPS_KEY);
            $restricted_posts = get_option(WA_Restricted_Posts::ARRAY_OF_RESTRICTED_POSTS);
            if (!empty($restricted_posts)) {
                if (!empty($old_levels) && !empty($updated_levels) && (count($updated_levels) < count($old_levels))) {
                    $this->remove_invalid_groups_levels($updated_levels, $old_levels, WA_Restricted_Posts::RESTRICTED_LEVELS);
                }
                if (!empty($old_groups) && !empty($updated_groups) && (count($updated_groups) < count($old_groups))) {
                    $this->remove_invalid_groups_levels($updated_groups, $old_groups, WA_Restricted_Posts::RESTRICTED_GROUPS);
                }
            }
            // Also, removed deleted roles if one or more membership levels are removed
            if (!empty($old_levels) && !empty($updated_levels) && (count($updated_levels) < count($old_levels))) {
                $this->remove_invalid_roles($updated_levels, $old_levels);
            }

            // Save updated levels to options table
            update_option(WA_Integration::WA_ALL_MEMBERSHIPS_KEY, $updated_levels);
            // Save updated groups to options table
            update_option(WA_Integration::WA_ALL_GROUPS_KEY, $updated_groups);
        }
    }

    /**
     * Deletes access token and account ID transients.
     *
     * @return void
     */
    public static function delete_transients()
    {
        delete_transient(self::ADMIN_ACCESS_TOKEN_TRANSIENT);
        delete_transient(self::ADMIN_ACCOUNT_ID_TRANSIENT);
    }

    /**
     * Checks for valid WildApricot credentials.
     *
     * @return bool true if valid authorization creds, false if not
     */
    public static function valid_wa_credentials()
    {
        $wa_credentials = get_option(WA_Auth_Settings::WA_CREDENTIALS_KEY);

        // wa_credentials will be false if the option doesn't exist
        // return here so we don't get invalid index in the lines below
        if (!$wa_credentials || empty($wa_credentials)) {
            return false;
        }

        $api_key = $wa_credentials[WA_Auth_Settings::WA_API_KEY_OPT];
        $client_id = $wa_credentials[WA_Auth_Settings::WA_CLIENT_ID_OPT];
        $client_secret = $wa_credentials[WA_Auth_Settings::WA_CLIENT_SECRET_OPT];

        // check first that creds exist
        return !empty($api_key) && !empty($client_id) && !empty($client_secret);
    }


    /**
     * Checks that updated WildApricot credentials match the registered site on the license key and that the credentials are still valid.
     *
     * @return void
     */
    public function check_updated_credentials()
    {
        // Ensure that credentials have been already entered
        $has_valid_wa_credentials = self::valid_wa_credentials();

        // see if credentials have gone invalid since last check
        if ($has_valid_wa_credentials) {
            try {
                WA_API::verify_valid_access_token();
            } catch (Exception $e) {
                $has_valid_wa_credentials = false;
            }
        }

        $license_status = Addon::get_license_check_option(CORE_SLUG);

        // re-validate license only if the plugin has been disabled and if the authorization credentials have not changed
        if (Addon::is_plugin_disabled() &&
            !Exception::fatal_error() &&
            $license_status != Addon::LICENSE_STATUS_AUTH_CHANGED &&
            $has_valid_wa_credentials) {
            Addon::update_licenses();
            // obtain new status
            $license_status = Addon::get_license_check_option(CORE_SLUG);
        }

        // if api creds aren't valid, remove licenses
        if (!$has_valid_wa_credentials && !Addon::is_plugin_disabled()) {
            delete_option(Addon::WAWP_LICENSE_KEYS_OPTION);
        }

        $has_valid_license = Addon::has_valid_license(CORE_SLUG);

        // if there's been a fatal error or there are invalid creds then disable
        if (Exception::fatal_error() ||
            !$has_valid_license ||
            !$has_valid_wa_credentials) {
            do_action('disable_plugin', CORE_SLUG, $license_status);
        } else {
            // if neither of the creds are invalid, do creds obtained action
            // also update plugin disabled option to be false and delete exception option
            update_option(Addon::WAWP_DISABLED_OPTION, false);
            delete_option(Exception::EXCEPTION_OPTION);
            do_action('wawp_wal_credentials_obtained');
        }
    }

    /**
     * Checks if the license key is registered for the WA url and account ID
     * corresponding to the entered API credentials
     *
     * @param array $response
     * @return bool
     */
    public static function check_licensed_wa_urls_ids($response)
    {
        $licensed_wa_urls = self::get_licensed_wa_urls($response);
        $licensed_wa_ids = self::get_licensed_wa_ids($response);
        if (is_null($licensed_wa_urls) || is_null($licensed_wa_ids)) {
            return false;
        }

        try {
            // Get access token and account id
            $access_and_account = WA_API::verify_valid_access_token();
            $access_token = $access_and_account['access_token'];
            $wa_account_id = $access_and_account['wa_account_id'];
            // Get account url from API
            $wawp_api = new WA_API($access_token, $wa_account_id);
            $wild_apricot_info = $wawp_api->get_account_url_and_id();
        } catch (Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
            return false;
        }

        // Compare license key information with current site
        if (in_array($wild_apricot_info['Id'], $licensed_wa_ids) && check_licensed_wa_urls($licensed_wa_urls, $wild_apricot_info['Url'])) {
            return true;
        }

        return false;

    }

    /**
     * Hides the WordPress admin bar for non-admin users
     *
     * @return void
     */
    public function hide_admin_bar()
    {
        if (!current_user_can('administrator') && !is_admin()) {
            show_admin_bar(false);
        }
    }

    /**
     * Tell user to logout of WildApricot if they are trying to access the admin menu
     *
     * @return void
     */
    public function tell_user_to_logout()
    {
        // Check if user is logged into WildApricot
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            // Check if user has WildApricot ID
            $wild_apricot_id = get_user_meta($user_id, self::WA_USER_ID_KEY);
            if (!empty($wild_apricot_id)) {
                // User is still logged into WildApricot
                $logout_link = wp_logout_url(esc_url(site_url()));
                Log::wap_log_warning('Please log out of your WildApricot account before accessing the Wordpress admin menu.');
                echo 'Are you trying to access the WordPress administrator menu while still logged into your WildApricot account?';
                echo 'If so, ensure that you are logged out of your WildApricot account by clicking <a href="' . esc_url($logout_link). '">Log Out</a>.';
            }
        }
    }

    /**
     * Add query vars to WordPress
     *
     * @param array  $vars Current, incoming query vars
     * @return array $vars Updated vars array with added query var
     * @see https://stackoverflow.com/questions/20379543/wordpress-get-query-var
     */
    public function add_custom_query_vars($vars)
    {
        // Add redirectId to query vars
        $vars[] = 'redirectId';
        return $vars;
    }

    /**
     * Creates a daily CRON job to check that the license matches
     *
     * @return void
     */
    public static function setup_license_check_cron()
    {
        $license_hook_name = self::LICENSE_CHECK_HOOK;
        if (!wp_next_scheduled($license_hook_name)) {
            wp_schedule_event(time(), 'daily', $license_hook_name);
        }
    }

    /**
     * Replaces login form with the appropriate error message.
     *
     * @return void
     */
    public function remove_wild_apricot_integration()
    {

        // then change content
        $content = '';
        if (Exception::fatal_error()) {
            $content = Exception::get_user_facing_error_message();
        } elseif (Addon::is_plugin_disabled()) {
            $content = "<p>You do not have access to this page. Please contact your site administrator.</p>";
        }

        // Check if login page exists
        $login_page_id = get_option(self::LOGIN_PAGE_ID_OPT);
        if (isset($login_page_id) && $login_page_id != '') {
            // Make login page private
            $login_page = get_post($login_page_id, 'ARRAY_A');
            $login_page['post_content'] = $content;
            $login_page['post_title'] = 'Access Denied';
            wp_update_post($login_page);
        }
    }

    /**
     * Display membership levels on user profile.
     *
     * @param WP_User $user is the user of the current profile
     * @return void
     */
    public function show_membership_level_on_profile($user)
    {
        // don't display WA data if the plugin is disabled
        if (Addon::is_plugin_disabled()) {
            return;
        }

        // Load in parameters from user's meta data
        $membership_level = get_user_meta($user->ID, self::WA_MEMBERSHIP_LEVEL_KEY, true);
        $user_status = get_user_meta($user->ID, self::WA_USER_STATUS_KEY, true);
        $wa_account_id = get_user_meta($user->ID, self::WA_USER_ID_KEY, true);
        $organization = get_user_meta($user->ID, self::WA_ORGANIZATION_KEY, true);
        $user_groups = get_user_meta($user->ID, self::WA_MEMBER_GROUPS_KEY);
        // Create list of user groups, if applicable
        $group_list = '';
        if (!empty($user_groups)) {
            $user_groups = maybe_unserialize($user_groups[0]);
            // Add comma after group only if it is NOT the last group
            $i = 0;
            $len = count($user_groups);
            foreach ($user_groups as $key => $value) {
                // Check if index is NOT the last index
                if (!($i == $len - 1)) { // NOT last
                    $group_list .= $value . ', ';
                } else {
                    $group_list .= $value;
                }
                // Increment counter
                $i++;
            }
        } else {
            // Set user groups to empty array
            $user_groups = array();
        }
        // Check if user has valid WildApricot credentials, and if so, display them
        if (isset($membership_level) && isset($user_status) && isset($wa_account_id) && isset($organization) && isset($user_groups)) { // valid
            // Get custom fields
            $checked_custom_fields = get_option(self::LIST_OF_CHECKED_FIELDS);
            $all_custom_fields = get_option(self::LIST_OF_CUSTOM_FIELDS);
            // Display WildApricot parameters
            ?>
<h2>WildApricot Membership Details</h2>
<table class="form-table">
    <!-- WildApricot Account ID -->
    <tr>
        <th><label>Account ID</label></th>
        <td>
            <?php
                        echo '<label>' . esc_html($wa_account_id) . '</label>';
            ?>
        </td>
    </tr>
    <!-- Membership Level -->
    <tr>
        <th><label>Membership Level</label></th>
        <td>
            <?php
                echo '<label>' . esc_html($membership_level) . '</label>';
            ?>
        </td>
    </tr>
    <!-- User Status -->
    <tr>
        <th><label>User Status</label></th>
        <td>
            <?php
                echo '<label>' . esc_html($user_status) . '</label>';
            ?>
        </td>
    </tr>
    <!-- Organization -->
    <tr>
        <th><label>Organization</label></th>
        <td>
            <?php
                echo '<label>' . esc_html($organization) . '</label>';
            ?>
        </td>
    </tr>
    <!-- Groups -->
    <tr>
        <th><label>Groups</label></th>
        <td>
            <?php
                echo '<label>' . esc_html($group_list) . '</label>';
            ?>
        </td>
    </tr>
    <?php
                // Display extra custom fields here
                if (!empty($checked_custom_fields)) {
                    foreach ($checked_custom_fields as $custom_key => $custom_field) {
                        // Load in field from user's meta data
                        $field_meta_key = 'wawp_' . str_replace(' ', '', $custom_field);
                        $field_saved_value = get_user_meta($user->ID, $field_meta_key);
                        if (!empty($field_saved_value)) {
                            $field_saved_value = $field_saved_value[0];
                        }
                        // Check if value is an array
                        if (is_array($field_saved_value)) {
                            // Convert array to string
                            $field_saved_value = self::convert_array_values_to_string($field_saved_value);
                            $field_saved_value = rtrim($field_saved_value, ', ');
                        }
                        ?>
    <tr>
        <th><label><?php echo esc_html($all_custom_fields[$custom_field]); ?></label>
        </th>
        <td>
            <?php
                                echo '<label>' . esc_html($field_saved_value) . '</label>';
                        ?>
        </td>
    </tr>
    <?php
                    }
                }
            ?>
</table>
<?php
        }
    }

    /**
     * Updates the user's WildApricot information in WordPress.
     *
     * @param int $current_user_id The user's WordPress ID
     * @return void
     */
    public function refresh_user_wa_info()
    {
        try {
            // Create WA_API with valid credentials
            $verified_data = WA_API::verify_valid_access_token();
            $admin_access_token = $verified_data['access_token'];
            $admin_account_id = $verified_data['wa_account_id'];
            $wawp_api = new WA_API($admin_access_token, $admin_account_id);
            // Refresh custom fields first
            $wawp_api->retrieve_custom_fields();
            // Get info for all WildApricot users
            $wawp_api->get_all_user_info();
        } catch (Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
        }

    }

    /**
     * Schedules the hourly event to update the user's WildApricot information
     * in their WordPress profile.
     *
     * @param int $user_id  User's WordPress ID
     * @return void
     */
    public static function create_cron_for_user_refresh()
    {
        // Schedule event if it is not already scheduled
        if (!wp_next_scheduled(self::USER_REFRESH_HOOK)) {
            wp_schedule_event(time(), 'daily', self::USER_REFRESH_HOOK);
        }
    }

    /**
     * Syncs WildApricot logged in user with WordPress user database.
     *
     * @param string $login_data  The login response from the API
     * @param string $login_email The email that the user has logged in with
     * @return void
     */
    public function add_user_to_wp_database($login_data, $login_email, $remember_user = true)
    {
        // Get access token and refresh token
        $access_token = $login_data['access_token'];
        $refresh_token = $login_data['refresh_token'];
        // Get time that token is valid
        $time_remaining_to_refresh = $login_data['expires_in'];
        // Get user's permissions
        $member_permissions = $login_data['Permissions'][0];
        // Get email of current WA user
        // https://gethelp.wildapricot.com/en/articles/391-user-id-aka-member-id
        $wa_user_id = $member_permissions['AccountId'];
        // Get user's contact information
        $wawp_api = new WA_API($access_token, $wa_user_id);
        $contact_info = array();
        $contact_info = $wawp_api->get_info_on_current_user();

        // Get membership level
        $membership_level = '';
        $membership_level_id = '';
        // Check that these are valid indicies in the array
        if (array_key_exists('MembershipLevel', $contact_info)) {
            $membership_level_array = $contact_info['MembershipLevel'];
            if (!empty($membership_level_array)) {
                if (array_key_exists('Name', $membership_level_array)) {
                    $membership_level = $membership_level_array['Name'];
                }
                if (array_key_exists('Id', $membership_level_array)) {
                    $membership_level_id = $membership_level_array['Id'];
                }
            }
        }
        // Get user status
        $user_status = $contact_info['Status'];
        if (!isset($user_status)) {
            $user_status = ''; // changed to blank
        }
        // Get first and last name
        $first_name = $contact_info['FirstName'];
        $last_name = $contact_info['LastName'];
        // Get organization
        $organization = $contact_info['Organization'];
        // Get field values
        $field_values = $contact_info['FieldValues'];
        // Get user ID
        $wild_apricot_user_id = $contact_info['Id'];

        // TODO: roles not synced

        // Check if WA email exists in the WP user database
        $current_wp_user_id = 0;
        if (email_exists($login_email)) { // email exists; we will update user
            // Get user
            $current_wp_user = get_user_by('email', $login_email); // returns WP_User
            $current_wp_user_id = $current_wp_user->ID;
            // Update user's first and last name if they are not set yet
            $current_first_name = get_user_meta($current_wp_user_id, 'first_name', true);
            $current_last_name = get_user_meta($current_wp_user_id, 'last_name', true);
            if (!isset($current_first_name) || $current_first_name == '') {
                wp_update_user([
                    'ID' => $current_wp_user_id,
                    'first_name' => $first_name
                ]);
            }
            if (!isset($current_last_name) || $current_last_name == '') {
                wp_update_user([
                    'ID' => $current_wp_user_id,
                    'last_name' => $last_name
                ]);
            }
            // Add user's WildApricot membership level as another role
            $another_role = 'wawp_' . str_replace(' ', '', $membership_level);
            $current_wp_user->add_role($another_role);
            // Set user's status of being added by the plugin to FALSE
            update_user_meta($current_wp_user_id, self::USER_ADDED_BY_PLUGIN, false);
        } else { // email does not exist; we will create a new user
            // Set user data
            // Generated username is 'firstName . lastName' with a random number on the end, if necessary
            $generated_username = $first_name . $last_name;
            // Check if generated username has been taken. If so, append a random number to the end of the user-id until a unique username is set
            while (username_exists($generated_username)) {
                // Generate random number
                $random_user_num = wp_rand(0, 9);
                $generated_username .= $random_user_num;
            }
            // Get role
            $user_role = 'subscriber';
            if (!empty($membership_level) && $membership_level != '') {
                $user_role = 'wawp_' . str_replace(' ', '', $membership_level);
            }
            $user_data = array(
                'user_email' => $login_email,
                'user_pass' => wp_generate_password(),
                'user_login' => $generated_username,
                'role' => $user_role,
                'display_name' => $first_name . ' ' . $last_name,
                'first_name' => $first_name,
                'last_name' => $last_name
            );
            // Insert user
            $current_wp_user_id = wp_insert_user($user_data); // returns user ID
            // Show error if necessary
            if (is_wp_error($current_wp_user_id)) {
                echo esc_html($current_wp_user_id->get_error_message());
            }
            // Set user's status of being added by the plugin to true
            update_user_meta($current_wp_user_id, self::USER_ADDED_BY_PLUGIN, true);
        }

        // Add WildApricot membership level to user's metadata
        update_user_meta($current_wp_user_id, self::WA_MEMBERSHIP_LEVEL_ID_KEY, $membership_level_id);
        update_user_meta($current_wp_user_id, self::WA_MEMBERSHIP_LEVEL_KEY, $membership_level);
        // Add WildApricot user status to user's metadata
        update_user_meta($current_wp_user_id, self::WA_USER_STATUS_KEY, $user_status);
        // Add WildApricot organization to user's metadata
        update_user_meta($current_wp_user_id, self::WA_ORGANIZATION_KEY, $organization);
        // Add WildApricot User ID to user's metadata
        update_user_meta($current_wp_user_id, self::WA_USER_ID_KEY, $wild_apricot_user_id);

        // Get list of custom fields that user should import
        $extra_custom_fields = get_option(self::LIST_OF_CHECKED_FIELDS);

        // Get groups
        // Loop through each field value until 'Group participation' is found
        $user_groups_array = array();
        foreach ($field_values as $field_value) {
            $field_name = $field_value['FieldName'];
            $system_code = $field_value['SystemCode'];
            if ($field_name == 'Group participation') { // Found
                $group_array = $field_value['Value'];
                // Loop through each group
                foreach ($group_array as $group) {
                    $user_groups_array[$group['Id']] = $group['Label'];
                }
            }
            // Get extra custom fields, if any
            if (!empty($extra_custom_fields)) {
                // Check if the current field value is in the extra custom fields
                if (in_array($system_code, $extra_custom_fields)) {
                    // This field is in the custom fields array and thus should be added to the user's meta data
                    $custom_meta_key = 'wawp_' . str_replace(' ', '', $system_code);
                    $custom_field_value = $field_value['Value'];
                    update_user_meta($current_wp_user_id, $custom_meta_key, $custom_field_value);
                }
            }
        }
        // Serialize the user groups array so that it can be added as user meta data
        $user_groups_array = maybe_serialize($user_groups_array);
        // Save to user's meta data
        update_user_meta($current_wp_user_id, self::WA_MEMBER_GROUPS_KEY, $user_groups_array);

        // Log user into WP account
        wp_set_auth_cookie($current_wp_user_id, $remember_user, is_ssl());
    }

    /**
     * Removes all users with WildApricot data added by the plugin.
     *
     * @return void
     */
    public static function remove_wa_users()
    {
        // get users added by the plugin
        $wap_users_added_by_plugin = get_users(
            array(
                'meta_key' => self::USER_ADDED_BY_PLUGIN,
                'meta_value' => true
            )
        );

        // delete all users added by the plugin
        foreach ($wap_users_added_by_plugin as $user) {
            $user_id = $user->ID;
            // if user is admin, don't delete
            if (in_array('administrator', $user->roles)) {
                continue;
            }
            wp_delete_user($user_id);
        }

        // get preexisting users with meta/roles added by the plugin
        $users_with_wap_data = get_users(
            array(
                'meta_key' => self::USER_ADDED_BY_PLUGIN,
                'meta_value' => false
            )
        );

        // merge admin users added by plugin with these users
        $users_with_wap_data = array_merge(
            $users_with_wap_data,
            $wap_users_added_by_plugin
        );

        // get wap roles
        $all_roles = (array) wp_roles();
        if (!empty($all_roles) && array_key_exists('role_names', $all_roles)) {
            $role_names = $all_roles['role_names'];
            // filter out non-WAP roles from list of all roles
            $wap_roles = array_filter(
                $role_names,
                function ($key) {
                    // anonymous function; returns true if WA or WAP role
                    return str_contains($key, 'wa_level') ||
                           str_contains($key, CORE_SLUG);
                },
                ARRAY_FILTER_USE_KEY
            );
        }

        // remove wap data from preexisting users
        foreach ($users_with_wap_data as $user) {
            $user_id = $user->ID;

            // delete wap meta from this user
            $user_meta = get_user_meta($user_id);
            foreach ($user_meta as $key => $value) {
                if (str_contains($key, CORE_SLUG)) {
                    delete_user_meta($user_id, $key);
                }
            }

            // delete wap roles from this user
            $user_roles = $user->roles;
            foreach ($user_roles as $role) {
                if (array_key_exists($role, $wap_roles)) {
                    $user->remove_role($role);
                }
            }
        }

        // remove wap roles
        foreach ($wap_roles as $role => $name) {
            remove_role($role);
        }

    }


    // **** private functions ****
    private static function unschedule_cron_job($cron_hook)
    {
        // Get the timestamp for the next event.
        $timestamp = wp_next_scheduled($cron_hook);
        // Check that event is already scheduled
        if ($timestamp) {
            wp_unschedule_event($timestamp, $cron_hook);
        }
    }

    /**
     * Returns the licensed WA urls from the hook response.
     *
     * @param array $response
     * @return array|null returns null if URLs don't exist
     */
    private static function get_licensed_wa_urls($response)
    {
        $licensed_wa_urls = array();

        if (!array_key_exists('Licensed Wild Apricot URLs', $response)) {
            Log::wap_log_warning('Licensed WildApricot URLs missing from hook response.');
            return null;
        }

        $licensed_wa_urls = $response['Licensed Wild Apricot URLs'];
        if (empty($licensed_wa_urls) || empty($licensed_wa_urls[0])) {
            return null;
        }

        // Sanitize urls, if necessary
        foreach ($licensed_wa_urls as $url_key => $url_value) {
            // Lowercase and remove https://, http://, and/or www. from url
            $licensed_wa_urls[$url_key] = WA_API::create_consistent_url($url_value);
        }

        return $licensed_wa_urls;
    }

    /**
     * Returns the licensed WA account IDs from the hook response.
     *
     * @param array $response
     * @return array|null returns null if account IDs don't exist
     */
    private static function get_licensed_wa_ids($response)
    {
        $licensed_wa_ids = array();

        if (!array_key_exists('Licensed Wild Apricot Account IDs', $response)) {
            Log::wap_log_warning('License WildApricot IDs missing from hook response');
            return null;
        }

        $licensed_wa_ids = $response['Licensed Wild Apricot Account IDs'];
        if (empty($licensed_wa_ids) || empty($licensed_wa_ids[0])) {
            return null;
        }

        foreach ($licensed_wa_ids as $id_key => $id_value) {
            // Ensure that only numbers are in the ID #
            $licensed_wa_ids[$id_key] = intval($id_value);
        }

        return $licensed_wa_ids;
    }

    /**
     * Returns whether the user is logged in with their WildApricot account.
     *
     * @return bool
     */
    private static function is_wa_user_logged_in()
    {
        if (!is_user_logged_in()) {
            return false;
        }
        $current_user_ID = wp_get_current_user()->ID;
        $user_wa_id = get_user_meta($current_user_ID, self::WA_USER_ID_KEY, true);
        return !empty($user_wa_id);
    }

    /**
     * Returns whether the post editor in use is the Gutenberg block editor or not.
     * This changes how the error message is displayed.
     *
     * @return bool
     * @see https://zerowp.com/detect-block-editor-gutenberg-php/?utm_source=rss&utm_medium=rss&utm_campaign=detect-block-editor-gutenberg-php
     */
    private static function is_block_editor()
    {
        $current_screen = get_current_screen();
        return method_exists($current_screen, 'is_block_editor') && $current_screen->is_block_editor();
    }

    /**
     * Converts an array of member values to a string for displaying on the user's profile
     *
     * @param  array  $array_values is the array of values to convert to a string
     * @return string $string_result is a string of each value separated by a comma
     */
    private static function convert_array_values_to_string($array_values)
    {
        $string_result = '';
        if (!empty($array_values)) {
            // Add comma after each value, unless it is the last value
            // $i = 0;
            // $len = count($array_values);
            foreach ($array_values as $key => $value) {
                // Check if there is another array
                if (!is_array($value)) {
                    if ($key != 'Id') {
                        $string_result .= $value . ', ';
                    }
                } else { // is another array
                    $string_result .= self::convert_array_values_to_string($value);
                }
            }
        }
        return $string_result;
    }

    public static function retrieve_custom_fields()
    {
        // Create WA_API with valid credentials
        $verified_data = WA_API::verify_valid_access_token();
        $admin_access_token = $verified_data['access_token'];
        $admin_account_id = $verified_data['wa_account_id'];
        $wawp_api = new WA_API($admin_access_token, $admin_account_id);
        // Refresh custom fields first
        $wawp_api->retrieve_custom_fields();
    }

}

?>