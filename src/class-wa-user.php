<?php

namespace WAWP;

require_once __DIR__ . '/settings/class-settings-controller.php';
require_once __DIR__ . '/class-wa-api.php';

class WA_User
{
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
     * Stores whether the user is a WA user added by the plugin in the user meta
     * data. Called when a user logs in with the WAP login shortcode.
     *
     * @var string
     */
    public const USER_ADDED_BY_PLUGIN 					= 'wawp_user_added_by_plugin';


    // Custom hook names
    /**
     * User data refresh hook. Scheduled to run daily.
     *
     * @var string
     */
    public const USER_REFRESH_HOOK 					= 'wawp_cron_refresh_user_hook';

    public function __construct()
    {
        // Fires when displaying or editing user profile, adds WA user data
        add_action('show_user_profile', array($this, 'show_membership_level_on_profile'));
        add_action('edit_user_profile', array($this, 'show_membership_level_on_profile'));

        // Action for user refresh cron hook
        add_action(self::USER_REFRESH_HOOK, 'WAWP\WA_User::refresh_user_wa_info');
    }

    /**
     * Schedules the hourly event to update the user's WildApricot information
     * in their WordPress profile.
     *
     * @param int $user_id  User's WordPress ID
     * @return void
     */
    public static function create_cron_for_user_refresh($cron_freq)
    {
        // Schedule event if it is not already scheduled
        if (!wp_next_scheduled(self::USER_REFRESH_HOOK)) {
            wp_schedule_event(time(), $cron_freq, self::USER_REFRESH_HOOK);
        }
    }

    /**
     * Syncs WildApricot logged in user with WordPress user database.
     *
     * @param string $login_data  The login response from the API
     * @param string $login_email The email that the user has logged in with
     * @return void
     */
    public static function add_user_to_wp_database($login_data, $login_email, $remember_user = true)
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
        $extra_custom_fields = get_option(WA_Integration::LIST_OF_CHECKED_FIELDS);

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
            $checked_custom_fields = get_option(WA_Integration::LIST_OF_CHECKED_FIELDS);
            $all_custom_fields = get_option(WA_Integration::LIST_OF_CUSTOM_FIELDS);
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
            <?php echo '<label>' . esc_html($field_saved_value) . '</label>'; ?>
        </td>
    </tr>
    <?php
        }
    } ?>
</table>
<?php
        }
    }

    /**
     * Returns whether the user is logged in with their WildApricot account.
     *
     * @return bool
     */
    public static function is_wa_user_logged_in()
    {
        if (!is_user_logged_in()) {
            return false;
        }
        $current_user_ID = wp_get_current_user()->ID;
        $user_wa_id = get_user_meta($current_user_ID, self::WA_USER_ID_KEY, true);
        return !empty($user_wa_id);
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


}