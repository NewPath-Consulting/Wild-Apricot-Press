<?php
namespace WAWP;

require_once __DIR__ . '/class-addon.php';
require_once __DIR__ . '/class-log.php';
require_once __DIR__ . '/class-data-encryption.php';
require_once __DIR__ . '/class-wa-api.php';
require_once __DIR__ . '/class-wa-integration.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/wap-exception.php';

/**
 * Manages and renders plugin settings on the admin screen.
 *
 * @since 1.1
 * @author Spencer Gable-Cook and Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class Settings {
    const CRON_HOOK = 'wawp_cron_refresh_memberships_hook';
    const SETTINGS_URL = 'wawp-wal-admin';

    private $admin_settings;
    private $wa_auth_settings;
    private $license_settings;

    /**
     * Adds actions and includes files
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );

        $this->admin_settings   = new Admin_Settings();
        $this->wa_auth_settings = new WA_Auth_Settings();
        $this->license_settings = new License_Settings();

        // Activate option in table if it does not exist yet
        // Currently, there is a WordPress bug that calls the 'sanitize' function twice if the option is not already in the database
        // See: https://core.trac.wordpress.org/ticket/21989
        if (!get_option(WA_Integration::WA_CREDENTIALS_KEY)) { // does not exist
            // Create option
            add_option(WA_Integration::WA_CREDENTIALS_KEY);
        }
        // Set default global page restriction message
        if (!get_option(WA_Integration::GLOBAL_RESTRICTION_MESSAGE)) {
            add_option(WA_Integration::GLOBAL_RESTRICTION_MESSAGE, '<h2>Restricted Content</h2> <p>This post is restricted to specific WildApricot users. Log into your WildApricot account or ask your administrator to add you to the post.</p>');
        }

        // Add actions for cron update
        add_action(self::CRON_HOOK, array($this, 'cron_update_wa_memberships'));

    }

    /**
	 * Set-up CRON job for updating membership levels and groups.
     * 
     * @return void
	 */
	public static function setup_cron_job() {
        //If $timestamp === false schedule the event since it hasn't been done previously
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            //Schedule the event for right now, then to repeat daily using the hook
            wp_schedule_event(current_time('timestamp'), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Removes the invalid, now deleted groups and levels after the membership levels and groups are updated
     * Please note that, while this function refers to "levels", this function works for both levels and groups
     *
     * @param array $updated_levels         new levels obtained from refresh
     * @param array $old_levels             previous levels before refresh
     * @param string $restricted_levels_key key of the restricted levels to be saved
     * @return void
     */
    private function remove_invalid_groups_levels($updated_levels, $old_levels, $restricted_levels_key) {
        $restricted_posts = get_option(WA_Integration::ARRAY_OF_RESTRICTED_POSTS);

        // Convert levels arrays to its keys
        $updated_levels = array_keys($updated_levels);
        $old_levels = array_keys($old_levels);

        // Loop through each old level and check if it is in the updated levels
        foreach ($old_levels as $old_level) {
            if (!in_array($old_level, $updated_levels)) { // old level is NOT in the updated levels
                // This is a deleted level! ($old_level)
                $level_to_delete = $old_level;
                // Remove this level from restricted posts
                // Loop through each restricted post and check if its post meta data contains this level
                foreach ($restricted_posts as $restricted_post) {
                    // Get post's list of restricted levels
                    $post_restricted_levels = get_post_meta($restricted_post, $restricted_levels_key);
                    $post_restricted_levels = maybe_unserialize($post_restricted_levels[0]);
                    // See line 230 on class-wa-integration.php
                    if (in_array($level_to_delete, $post_restricted_levels)) {
                        // Remove this updated level from post restricted levels
                        $post_restricted_levels = array_diff($post_restricted_levels, array($level_to_delete));
                    }
                    // Check if post's restricted groups and levels are now empty
                    $other_membership_key = WA_Integration::RESTRICTED_GROUPS;
                    if ($restricted_levels_key == WA_Integration::RESTRICTED_GROUPS) {
                        $other_membership_key = WA_Integration::RESTRICTED_LEVELS;
                    }
                    $other_memberships = get_post_meta($restricted_post, $other_membership_key);
                    $other_memberships = maybe_unserialize($other_memberships[0]);
                    if (empty($other_memberships) && empty($post_restricted_levels)) {
                        // This post should NOT be restricted
                        update_post_meta($restricted_post, WA_Integration::IS_POST_RESTRICTED, false);
                        // Remove this post from the array of restricted posts
                        $updated_restricted_posts = array_diff($restricted_posts, array($restricted_post));
                        update_option(WA_Integration::ARRAY_OF_RESTRICTED_POSTS, $updated_restricted_posts);
                    }
                    // Save new restricted levels to post meta data
                    $post_restricted_levels = maybe_serialize($post_restricted_levels);
                    // Delete past value
                    // $old_post_levels = get_post_meta($restricted_post);
                    update_post_meta($restricted_post, $restricted_levels_key, $post_restricted_levels); // single value
                }
            }
        }
    }

    /**
     * Removes deleted roles after refreshing new membership levels
     *
     * @param array $updated_levels        the new levels obtained from refresh
     * @param array $old_levels            the previous levels before refresh
     * @return void
     */
    private function remove_invalid_roles($updated_levels, $old_levels) {
        // Convert levels arrays to its keys
        $updated_levels_keys = array_keys($updated_levels);
        $old_levels_keys = array_keys($old_levels);

        // Loop through each old level and check if it is in the updated levels
        foreach ($old_levels_keys as $old_level_key) {
            if (!in_array($old_level_key, $updated_levels_keys)) { // old level is NOT in the updated levels
                // This is a deleted level! ($old_level)
                $level_to_delete = $old_level_key;
                // Remove role
                $level_name = $old_levels[$level_to_delete];
                $role_to_remove = 'wawp_' . str_replace(' ', '', $level_name);
                remove_role($role_to_remove);
                // Remove users from this role now that it is deleted
                // CHECK THAT EDITOR/ADMIN IS NOT DOWNGRADED TO SUBSCRIBER
                $delete_args = array('role' => $role_to_remove);
                $users_with_deleted_roles = get_users($delete_args);
                // Loop through these users and set their roles to subscriber
                foreach ($users_with_deleted_roles as $user_to_modify) {
                    $user_to_modify->set_role('subscriber');
                }
            }
        }
    }

    /**
     * Updates the membership levels and groups from WildApricot into WordPress upon each CRON job.
     * 
     * @return void
     */
    public function cron_update_wa_memberships() {
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
            $restricted_posts = get_option(WA_Integration::ARRAY_OF_RESTRICTED_POSTS);
            if (!empty($restricted_posts)) {
                if (!empty($old_levels) && !empty($updated_levels) && (count($updated_levels) < count($old_levels))) {
                    $this->remove_invalid_groups_levels($updated_levels, $old_levels, WA_Integration::RESTRICTED_LEVELS);
                }
                if (!empty($old_groups) && !empty($updated_groups) && (count($updated_groups) < count($old_groups))) {
                    $this->remove_invalid_groups_levels($updated_groups, $old_groups, WA_Integration::RESTRICTED_GROUPS);
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
     * Add WAP settings page.
     * 
     * @return void.
     */
    public function add_settings_page() {
        // Create WAWP admin page
        $this->admin_settings->add_menu_pages();

        $this->wa_auth_settings->add_submenu_page();
        $this->license_settings->add_submenu_page();

    }

    /**
     * Register and add settings fields.
     * 
     * @return void
     */
    public function page_init() {
        $this->wa_auth_settings->register_setting_add_fields();
        $this->license_settings->register_setting_add_fields();
        $this->admin_settings->register_setting_add_fields();
    }

}

/**
 * Handles creating, rendering, and sanitizing WildApricot admin settings.
 * 
 * @since 1.1
 * @author Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class Admin_Settings {

    const LOGIN_BUTTON_LOCATION_SECTION = 'wap_menu_location_group';
    const LOGIN_BUTTON_LOCATION_PAGE = Settings::SETTINGS_URL . '-login-location';

    const DELETE_DB_DATA = 'delete_db_data';
    const DELETE_USER_DATA = 'delete_user_data';

    public function __construct() {
    }

    /**
     * Add main admin menu page and general submenu page.
     *
     * @return void
     */
    public function add_menu_pages() {
        // Sub-menu for settings
        add_menu_page(
            'WildApricot Press',
            'WildApricot Press',
            'manage_options',
            Settings::SETTINGS_URL,
            array( $this, 'create_admin_page' ),
			'dashicons-businesswoman',
			6
        );

        add_submenu_page(
            Settings::SETTINGS_URL,
            'Settings',
            'Settings',
            'manage_options',
            Settings::SETTINGS_URL
        );
    }

    /**
     * Registers settings and adds groups and fields for all admin settings.
     *
     * @return void
     */
    public function register_setting_add_fields() {
        // content restriction tab
        $this->register_login_button_location();
        $this->register_status_restriction();
        $this->register_global_restriction_msg();

        // sync options
        $this->register_custom_fields();

        // plugin options
        $this->register_deletion_option();
        $this->register_logfile_option();
    
    }

    /**
     * Create the admin page with main tab, fields,and plugin tab.
     *
     * @return void
     */
    public function create_admin_page() {
        $tab = get_current_tab();
        ?>
        <div class="wrap">
            <h2>Settings</h2>
            <?php
            if (Addon::is_plugin_disabled()) {
                ?> </div> <?php 
                return;
            }
            ?>
            <!-- navigation tabs -->
            <nav class="nav-tab-wrapper">
                <a href="?page=wawp-wal-admin" class="nav-tab <?php if($tab===null): ?>nav-tab-active<?php endif; ?>">Content Restriction Options</a>
                <a href="?page=wawp-wal-admin&tab=fields" class="nav-tab <?php if($tab==='fields'):?>nav-tab-active<?php endif; ?>">Synchronization Options</a>
                <a href="?page=wawp-wal-admin&tab=plugin" class="nav-tab <?php if($tab==='plugin'):?>nav-tab-active<?php endif; ?>">Plugin Options</a>
            </nav>
            <div class="tab-content">
                <?php
                switch($tab):
                    case 'fields': $this->create_sync_options_tab();
                        break;
                    case 'plugin': $this->create_plugin_options_tab();
                        break;
                    default:       $this->create_content_restriction_options_tab();
                    endswitch;
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Print the menu location description text
     * 
     * @return void
     */
    public function login_menu_location_print_section_info() {
        print 'Please specify the menu(s) that you would like the Login/Logout button to appear on. Users can then use this Login/Logout button to sign in and out of their WildApricot account on your WordPress site.';
    }

    /**
     * Inform user there are no menus so the login button location cannot be
     * chose.
     *
     * @return void
     */
    public function login_menu_location_no_menus_info() {
        print '<p class="wap-error"><strong>Please add at least one menu in <a href=' . 
                esc_url(admin_url('nav-menus.php')) . '>Appearance > Menus</a> in 
                order to display the Login/Logout button on your website.</strong></p>';
    }

    /**
     * Display the checkboxes corresponding to each visible menu on the site
     * for the user to choose from to place the WA user login button
     * 
     * @return void
     */
    public function login_menu_location_input_box() {
        // get saved menu for the login button
        $saved_login_menu = get_login_menu_location();

        // list of existing menus
        $menus = wp_get_nav_menus();
        // array of menu ids => assigned locations
        $menu_id_to_location = flipped_menu_location_array();

        // loop through list of menus
        foreach ($menus as $menu) {
            $menu_id = $menu->term_id;
            $display_name = $menu->name;

            // if menu is saved in options, check the input box
            $is_checked = in_array($menu_id, $saved_login_menu);
            $checked = '';
            if ($is_checked) {
                $checked = 'checked';
            }

            $menu_has_location = array_key_exists($menu_id, $menu_id_to_location);

            // if menu has a location, display it
            if ($menu_has_location) {
                $display_name = $display_name . ' (' . $menu_id_to_location[$menu_id] . ')';
            } else if (!empty($menu_id_to_location) && !$is_checked) {
                /**
                 * if menu does not have locations but there are other menus 
                 * registered to locations AND the menu is not currently 
                 * selected, don't display it
                 */
                continue;
            }
            // if no menus have locations, display all of them


            // output checkbox and label with the format menu name (location(s))
            echo '<div><input type="checkbox" id="wap_menu_option" 
                name="wawp_menu_location_name[]" value="' . 
                esc_attr($menu_id) . '" ' . esc_attr($checked) . '>';
            echo '<label for="' . esc_attr($menu_id) . '">' . 
                esc_html($display_name);
            if (!$menu_has_location && $is_checked) {
                echo '<span class="wap-error"> (Menu not assigned to a location)</span>';
            }
            echo '</label></div>';

        }
    }

    /**
     * Sanitize the login/logout menu location checkboxes
     *
     * @param array $input Contains all checkboxes in an array
     * @return array array of sanitized input
     */
    public function login_menu_location_sanitize($input) {
        // Verify nonce
        if (!wp_verify_nonce(
            $_POST['wawp_menu_location_nonce_name'], 
            'wawp_menu_location_nonce_action')
        ) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the menu location(s) could not be verified.');
            return empty_string_array($input);
        }

        // Create valid array that will hold valid inputs
        $valid = array();
        // Sanitize each element
        if (!empty($input)) {
            foreach ($input as $menu_key => $menu_value) {
                $valid[$menu_key] = sanitize_text_field($menu_value);
            }
        } else {
            // save primary menu location as default
            $valid[] = get_primary_menu();
        }
        return $valid;
    }

    /**
     * Print instructions on how to use the restriction status checkboxes
     * 
     * @return void
     */
    public function restriction_status_print_info() {
        print 'Please select the WildApricot member/contact status(es) that will be able to see the restricted posts.<br>';
        print 'If no statuses are selected, then all membership statuses can view the restricted posts.';
    }

    /**
     * Displays the checkboxes for selecting the membership statuses to restrict
     * from posts.
     * 
     * @return void
     */
    public function restriction_status_input_box() {
        // Display checkboxes for each WildApricot status
        // List of statuses here: https://gethelp.wildapricot.com/en/articles/137-member-and-contact-statuses
        $list_of_statuses = array(
            'Active' => 'Active',
            'Lapsed' => 'Lapsed',
            'PendingNew' => 'Pending - New',
            'PendingRenewal' => 'Pending - Renewal',
            'PendingLevel' => 'Pending - Level change'
        );
        // Should 'suspended' and 'archived' be included?

        // Load in the list of restricted statuses, if applicable
        $saved_statuses = get_option(WA_Integration::GLOBAL_RESTRICTED_STATUSES);
        // Check if saved statuses exists; if not, then create an empty array
        if (empty($saved_statuses)) { // create empty array
            $saved_statuses = array();
        }

        // Loop through the list of statuses and add each as a checkbox
        foreach ($list_of_statuses as $status_key => $status) {
            // Check if checkbox is already checked off
            $status_checked = '';
            if (in_array($status_key, $saved_statuses)) {
                $status_checked = 'checked';
            }
            ?>
            <input type="checkbox" name="wawp_restriction_status_name[]" class='wawp_class_status' value="<?php echo esc_attr($status_key); ?>" <?php echo esc_attr($status_checked); ?>/> <?php echo esc_html($status); ?> </input><br>
            <?php
        }
    }
    
    /**
     * Sanitize restriction status checkboxes.
     *
     * @param array $input Contains all settings fields as array keys
     * @return array array of sanitized inputs
     */
    public function restriction_status_sanitize($input) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['wawp_restriction_status_nonce_name'], 'wawp_restriction_status_nonce_action')) {
            // wp_die('Your nonce for the restriction status(es) could not be verified.');
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the restriction status could not be verified. Please try again.');
            return empty_string_array($input);
        }
        $valid = array();
        // Loop through each checkbox and sanitize
        if (!empty($input)) {
            foreach ($input as $key => $box) {
                $valid[$key] = htmlspecialchars($box);
            }
        }
        // Return sanitized value
        return $valid;
    }

    /**
     * Print the Global Restriction description
     * 
     * @return void
     */
    public function restriction_message_print_info() {
        print 'The "Global Restriction Message" is the message that is shown to users who are not members of the WildApricot membership level(s) or group(s) required to access a restricted post. ';
        print 'Try to make the message informative; for example, you can suggest what the user can do in order to be granted access to the post. '; 
        print 'You can also set a custom restriction message for each individual post by editing the "Individual Restriction Message" field under the post editor.';
    }
    
    /**
     * Displays the global restriction message text area.
     * 
     * @return void
     */
    public function restriction_message_input_box() {
        // Add wp editor
        // See: https://stackoverflow.com/questions/20331501/replacing-a-textarea-with-wordpress-tinymce-wp-editor
        // https://developer.wordpress.org/reference/functions/wp_editor/
        // Get default or saved restriction message
        $initial_content = get_option(WA_Integration::GLOBAL_RESTRICTION_MESSAGE);
        $editor_id = 'wawp_restricted_message_textarea';
        $editor_name = WA_Integration::GLOBAL_RESTRICTION_MESSAGE;
        $editor_settings = array('textarea_name' => $editor_name, 'tinymce' => true);
        // Create WP editor
        wp_editor($initial_content, $editor_id, $editor_settings);
    }

    /**
     * Sanitize restriction message.
     *
     * @param array $input Contains all settings fields as array keys
     * @return array array of sanitized inputs
     */
    public function restriction_message_sanitize($input) {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_restriction_nonce_name'], 'wawp_restriction_nonce_action')) {
            // wp_die('Your nonce for the restriction message could not be verified.');
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the restriction message could not be verified. Please try again.');
            return empty_string_array($input);
        }
		// Create valid variable that will hold the valid input
		$valid = wp_kses_post($input);
        // Return valid input
        return $valid;
    }

    /**
     * Print the Custom Fields introductory text
     * 
     * @return void
     */
    public function custom_fields_print_info() {
        print 'Please select the WildApricot Contact Fields that you would like to sync with your WordPress site.';
    }

    /**
     * Displays the checkboxes for the WildApricot custom fields.
     * 
     * @return void
     */
    public function custom_fields_input() {
        WA_Integration::retrieve_custom_fields();
        // Load in custom fields
        $custom_fields = get_option(WA_Integration::LIST_OF_CUSTOM_FIELDS);
        $checked_fields = get_option(WA_Integration::LIST_OF_CHECKED_FIELDS);
        // Display each custom field as a checkbox
        if (!empty($custom_fields)) {
            foreach ($custom_fields as $field_id => $field_name) {
                // Check if this field is in the list of checked fields
                $is_checked = '';
                if (!empty($checked_fields)) {
                    if (in_array($field_id, $checked_fields)) {
                        // This field should be checked
                        $is_checked = 'checked';
                    }
                }
                ?>
					<input type="checkbox" name="wawp_fields_name[]" class='wawp_case_field' value="<?php echo esc_attr($field_id); ?>" <?php echo esc_attr($is_checked); ?>/> <?php echo esc_html($field_name); ?> </input><br>
				<?php
            }
        } else { // no custom fields
            ?>
            <p>Your WildApricot site does not have any contact fields! Please ensure that you have correctly entered your WildApricot site's credentials under <a href="<?php echo esc_url(get_auth_menu_url()); ?>">WildApricot Press -> Authorization</a></p>
            <?php
        }
    }

    /**
     * Sanitize custom fields input.
     *
     * @param array $input Contains all settings fields as array keys
     * @return array array of sanitized inputs
     */
    public function custom_fields_sanitize($input) {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_field_nonce_name'], 'wawp_field_nonce_action')) {
            // wp_die('Your nonce could not be verified.');
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the custom fields selection could not be verified. Please try again.');
            return empty_string_array($input);
        }
        // Sanitize checkboxes
        $valid = array();
        if (!empty($input)) {
            foreach ($input as $key => $checkbox) {
                $valid[$key] = sanitize_text_field($checkbox);
            }
        }

        

        return $valid;
    }

    /**
     * Print description of the plugin options
     * 
     * @return void
     */
    public function deletion_option_print_info() {
        print 'By default, upon deletion of the <b>WildApricot Press</b> plugin, none of the data created and stored by WildApricot Press is deleted.<br><br>';
        
        print 'You can remove all <strong>database and post/page data</strong> created by WildApricot Press by checking <strong>Delete WordPress database data and post/page data</strong>.<br>';

        print 'You can remove all <strong>WildApricot users</strong> created by WildApricot Press by checking <strong>Delete users added by WildApricot Press</strong>.';
        
        return;
    }

    /**
     * Displays the options for deleting the plugin, including if the 
     * WildApricot synced users should be retained, etc.
     * 
     * @return void
     */
    public function deletion_option_input() {
        // Store each checkbox description in array
        $synced_info = array(
            self::DELETE_DB_DATA => 'Delete WordPress database data and post/page data',
            self::DELETE_USER_DATA => 'Delete users added by WildApricot Press'
        );
        
        // Load in saved checkboxes
        $saved_synced_info = get_option(WA_Integration::WA_DELETE_OPTION);
        // Display checkboxes
        foreach ($synced_info as $key => $attribute) {
            $checked = '';
            // Check if this attribute has already been checked
            if (!empty($saved_synced_info)) {
                if (in_array($key, $saved_synced_info)) {
                    $checked = 'checked';
                }
            }
            ?>
            <input type="checkbox" name="wawp_delete_setting[]" class='wawp_class_delete' value="<?php echo esc_attr($key); ?>" <?php echo esc_attr($checked); ?>/> <?php echo esc_html($attribute); ?> </input><br><br>
            <?php

        }
    }

    /**
     * Sanitize the plugin deletion option.
     * 
     * @param array $input contains settings input as array keys
     * @return array array of sanitized inputs
     */
    public function deletion_option_sanitize($input) {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_delete_nonce_name'], 'wawp_delete_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the deletion option could not be verified.');
            return empty_string_array($input);
        } 
        $valid = array();
        // Loop through input array and sanitize each value
        if (!empty($input)) {
            foreach ($input as $in_key => $in_value) {
                $valid[$in_key] = sanitize_text_field($in_value);
            }
        }
        // Return valid input
        return $valid;
    }

    /**
     * Print settings information for the logfile toggle.
     *
     * @return void
     */
    public function logfile_option_print_info() {
        print 'By checking this box, error and warning messages will be printed to a file accessible in <code>wp-content/wapdebug.log</code>.';
        print '<br>Note: log message timezone will be in UTC if timezone is not set in WordPress settings.';
    }

    /**
     * Renders the log file toggle checkbox.
     *
     * @return void
     */
    public function logfile_option_input() {
        $checked = Log::can_debug();
        ?>
        <input type="checkbox" name="<?php echo esc_attr(Log::LOG_OPTION); ?>" class="wawp_class_logfile" value="checked" <?php echo esc_html($checked); ?>></input>
        <?php
    }

    /**
     * Sanitize the logfile toggle input.
     *
     * @param string $input
     * @return string sanitized input
     */
    public function logfile_option_sanitize($input) {
        if (!wp_verify_nonce($_POST['wawp_logfile_flag_nonce_name'], 'wawp_logfile_flag_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the logfile option could not be verified.');
            return '';
        }
        
        $valid = sanitize_text_field($input);
        // if input is empty, box is not checked, return empty string
        if (!$valid) return '';
        return $valid;
    }

    // settings on the content restriction options tab
    /**
     * Register settings and add fields for the login button menu location.
     *
     * @return void
     */
    private function register_login_button_location() {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'login_menu_location_sanitize'),
            'default' => null
        );

        // Register setting
        register_setting(
            self::LOGIN_BUTTON_LOCATION_SECTION, // Option group
            WA_Integration::MENU_LOCATIONS_KEY, // Option name
            $register_args // Sanitize
        );

        // if menus are empty, display separate message and don't add fields
        $menus = wp_get_nav_menus();
        if (empty($menus)) {
            // Create settings section
            add_settings_section(
                'wawp_menu_location_id', // ID
                'Login/Logout Button Menu Location', // Title
                array( $this, 'login_menu_location_no_menus_info' ), // Callback
                self::LOGIN_BUTTON_LOCATION_PAGE // Page
            );
            return;
        }

        // Create settings section
        add_settings_section(
            'wawp_menu_location_id', // ID
            'Login/Logout Button Menu Location', // Title
            array( $this, 'login_menu_location_print_section_info' ), // Callback
            self::LOGIN_BUTTON_LOCATION_PAGE // Page
        );

        // Settings for Menu to add Login/Logout button
        add_settings_field(
            'wawp_wal_login_logout_button', // ID
            'Menu:', // Title
            array( $this, 'login_menu_location_input_box' ), // Callback
            self::LOGIN_BUTTON_LOCATION_PAGE, // Page 
            'wawp_menu_location_id' // Section
        );
    }
    
    /**
     * Register settings and add fields for the restricted statuses.
     *
     * @return void
     */
    private function register_status_restriction() {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'restriction_status_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_restriction_status_group', // group name for settings
            WA_Integration::GLOBAL_RESTRICTED_STATUSES, // name of option to sanitize and save
            $register_args
        );
        // Add settings section and field for restriction status
        add_settings_section(
            'wawp_restriction_status_id', // ID
            'WildApricot Status Restriction', // title
            array($this, 'restriction_status_print_info'), // callback
            Settings::SETTINGS_URL // page
        );
        // Field for membership statuses
        add_settings_field(
            'wawp_restriction_status_field_id', // ID
            'Membership Status(es):', // title
            array($this, 'restriction_status_input_box'), // callback
            Settings::SETTINGS_URL, // page
            'wawp_restriction_status_id' // section
        );
    }

    /**
     * Register settings and add fields for the global restriction message.
     *
     * @return void
     */
    private function register_global_restriction_msg() {
        // Register setting
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'restriction_message_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_restriction_group', // group name for settings
            WA_Integration::GLOBAL_RESTRICTION_MESSAGE, // name of option to sanitize and save
            $register_args
        );

        // Add settings section and field for restriction message
        add_settings_section(
            'wawp_restriction_id', // ID
            'Global Restriction Message', // title
            array($this, 'restriction_message_print_info'), // callback
            'wawp-wal-admin-message' // page
        );
        // Field for restriction message
        add_settings_field(
            'wawp_restriction_field_id', // ID
            'Restriction Message:', // title
            array($this, 'restriction_message_input_box'), // callback
            'wawp-wal-admin-message', // page
            'wawp_restriction_id' // section
        );
    }

    // settings on the sync options tab
    /**
     * Register settings and add fields for the custom fields.
     *
     * @return void
     */
    private function register_custom_fields() {
        // Register setting
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'custom_fields_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_fields_group', // group name for settings
            WA_Integration::LIST_OF_CHECKED_FIELDS, // name of option to sanitize and save
            $register_args
        );
        // Add settings section and field for selecting custom fields
        add_settings_section(
            'wawp_fields_id', // ID
            'Custom Fields', // title
            array($this, 'custom_fields_print_info'), // callback
            'wawp-wal-admin&tab=fields' // page
        );
        add_settings_field(
            'wawp_custom_field_id', // ID
            'Custom Fields to Include:', // title
            array($this, 'custom_fields_input'), // callback
            'wawp-wal-admin&tab=fields', // page
            'wawp_fields_id' // section
        );
    }
    
    // settings on the plugin options tab
    /**
     * Register settings add fields for the delete all content option.
     *
     * @return void
     */
    private function register_deletion_option() {
        // Register setting
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'deletion_option_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_delete_group', // group name for settings
            WA_Integration::WA_DELETE_OPTION, // name of option to sanitize and save
            $register_args
        );
        // Add settings section and field for selecting custom fields
        add_settings_section(
            'wawp_delete_id', // ID
            'Plugin Deletion Options', // title
            array($this, 'deletion_option_print_info'), // callback
            'wawp-wal-admin&tab=plugin' // page
        );
        add_settings_field(
            'wawp_delete_options_id', // ID
            'Data to Remove Upon Plugin Deletion:', // title
            array($this, 'deletion_option_input'), // callback
            'wawp-wal-admin&tab=plugin', // page
            'wawp_delete_id' // section
        );
        add_settings_field(
            'wawp_delete_db_options_id',
            'Delete DB info',
            array($this, 'deletion_option_input'),
            'wawp-wal-admin&tab=plugin',
            'wawp_delete_db_id'
        );
    }

    /**
     * Register settings and add fields for the logfile toggle.
     *
     * @return void
     */
    private function register_logfile_option() {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'logfile_option_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_logfile_group',
            Log::LOG_OPTION,
            $register_args
        );
        add_settings_section(
            'wawp_logfile_id',
            'Plugin Log Messages',
            array($this, 'logfile_option_print_info'),
            'wawp-wal-admin&tab=plugin#log'
        );
        add_settings_field(
            'wawp_logfile_flag_id',
            'Print log messages to log file',
            array($this, 'logfile_option_input'),
            'wawp-wal-admin&tab=plugin#log',
            'wawp_logfile_id',
        );
    }

    /**
     * Render content for the content restriction tab.
     *
     * @return void
     */
    private function create_content_restriction_options_tab() {

        ?>
        <!-- Menu Locations for Login/Logout button -->
        <form method="post" action="options.php">
        <?php
            // Nonce for verification
            wp_nonce_field('wawp_menu_location_nonce_action', 'wawp_menu_location_nonce_name');
            // This prints out all hidden setting fields
            settings_fields( self::LOGIN_BUTTON_LOCATION_SECTION );
            do_settings_sections( self::LOGIN_BUTTON_LOCATION_PAGE );
            $menus = wp_get_nav_menus();
            if (!empty($menus)) {
                // don't display submit button if there are no menus
                submit_button();
            }
        ?>
        </form>
        <!-- Form for Restriction Status(es) -->
        <form method="post" action="options.php">
        <?php
            // Nonce for verification
            wp_nonce_field('wawp_restriction_status_nonce_action', 'wawp_restriction_status_nonce_name');
            // This prints out all hidden setting fields
            settings_fields('wawp_restriction_status_group');
            do_settings_sections( Settings::SETTINGS_URL );
            submit_button();
        ?>
        </form>
        <!-- Form for global restriction message -->
        <form method="post" action="options.php">
        <?php
            // Nonce for verification
            wp_nonce_field('wawp_restriction_nonce_action', 'wawp_restriction_nonce_name');
            // This prints out all hidden setting fields
            settings_fields('wawp_restriction_group');
            do_settings_sections( 'wawp-wal-admin-message' );
            submit_button();
        ?>
        </form>
        <?php
    }

    /**
     * Render content for the synchronization tab.
     *
     * @return void
     */
    private function create_sync_options_tab() {
        ?>
        <form method="post" action="options.php">
        <?php
            // Nonce for verification
            wp_nonce_field('wawp_field_nonce_action', 'wawp_field_nonce_name');
            // This prints out all hidden setting fields
            settings_fields( 'wawp_fields_group' );
            do_settings_sections( 'wawp-wal-admin&tab=fields' );
            submit_button();
        ?>
        </form>
        <?php
    }

    /**
     * Render content for the plugin options tab.
     *
     * @return void
     */
    private function create_plugin_options_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            // Nonce for verification
            wp_nonce_field('wawp_delete_nonce_action', 'wawp_delete_nonce_name');
            // This prints out all hidden setting fields
            settings_fields( 'wawp_delete_group' );
            do_settings_sections( 'wawp-wal-admin&tab=plugin' );
            submit_button();
            ?>
        </form>
        <form method="post" action="options.php">
            <?php
            wp_nonce_field('wawp_logfile_flag_nonce_action', 'wawp_logfile_flag_nonce_name');
            settings_fields('wawp_logfile_group');
            do_settings_sections('wawp-wal-admin&tab=plugin#log');
            submit_button();
            ?>
        </form>
        <?php
    }

}

/**
 * Handles creating, rendering, and sanitizing WildApricot Authorization settings.
 * 
 * @since 1.1
 * @author Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class WA_Auth_Settings {
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    const OPTION_GROUP = 'wap_wa_auth_group';
    const SUBMENU_PAGE = 'wap-wa-auth-login';
    const SECTION = 'wap_wa_auth_section';

    public function __construct() {
    }

    /**
     * Adds the authorization submenu page to the admin menu.
     *
     * @return void
     */
    public function add_submenu_page() {
        add_submenu_page(
			Settings::SETTINGS_URL,
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
    public function register_setting_add_fields() {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'sanitize'),
            'default' => null
        );

		// Register setting
        register_setting(
            self::OPTION_GROUP, // Option group
            WA_Integration::WA_CREDENTIALS_KEY, // Option name
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
            WA_Integration::WA_API_KEY_OPT, // ID
            'API Key:', // Title
            array( $this, 'api_key_callback' ), // Callback
            self::SUBMENU_PAGE, // Page
            self::SECTION // Section
        );

		// Settings for Client ID
        add_settings_field(
            WA_Integration::WA_CLIENT_ID_OPT, // ID
            'Client ID:', // Title
            array( $this, 'client_id_callback' ), // Callback
            self::SUBMENU_PAGE, // Page
            self::SECTION // Section
        );

		// Settings for Client Secret
		add_settings_field(
            WA_Integration::WA_CLIENT_SECRET_OPT, // ID
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
    public function create_wa_auth_login_page() {
        $this->options = get_option( WA_Integration::WA_CREDENTIALS_KEY );
        
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
						settings_fields( self::OPTION_GROUP );
						do_settings_sections( self::SUBMENU_PAGE );
						submit_button();
					?>
					</form>
					<!-- Check if form is valid -->
					<?php
                        $wild_apricot_url = $this->check_wild_apricot_url();
                        // if there's a fatal error don't display anything after credentials form
                        if (Exception::fatal_error()) {
                            ?> </div> </div> </div> <?php
                            return;
                        }
						if (!WA_Integration::valid_wa_credentials()) { 
                            // not valid
							echo '<p class="wap-error">Missing valid WildApricot credentials. Please enter them above.</p>';
						} else if ($wild_apricot_url) { 
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
    public function sanitize($input) {
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

            $api_key = $valid[WA_Integration::WA_API_KEY_OPT];
            $valid_api = WA_API::is_application_valid($api_key); 
            // credentials invalid
            if (!$valid_api) {
                return empty_string_array($input);
            }
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
    public function wal_print_section_info() {
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
    public function api_key_callback() {
		echo '<input class="wap-wa-auth-creds" id="wawp_wal_api_key" name="wawp_wal_name[wawp_wal_api_key]"
			type="text" placeholder="*************" />';
		// Check if api key has been set; if so, echo that the client secret has been set!
		if (!empty($this->options[WA_Integration::WA_API_KEY_OPT]) && !Exception::fatal_error()) {
			echo '<p>API Key is set</p>';
		}
    }

    /**
     * Display text field for Client ID
     * 
     * @return void
     */
    public function client_id_callback() {
		echo '<input class="wap-wa-auth-creds" id="wawp_wal_client_id" name="wawp_wal_name[wawp_wal_client_id]"
			type="text" placeholder="*************" />';
		// Check if client id has been set; if so, echo that the client secret has been set!
		if (!empty($this->options[WA_Integration::WA_CLIENT_ID_OPT]) && !Exception::fatal_error()) {
			echo '<p>Client ID is set</p>';
		}
    }

	/**
     * Display text field for Client Secret
     * 
     * @return void
     */
    public function client_secret_callback() {
		echo '<input class="wap-wa-auth-creds" id="wawp_wal_client_secret" name="wawp_wal_name[wawp_wal_client_secret]"
			type="text" placeholder="*************" />';
		// Check if client secret has been set; if so, echo that the client secret has been set!
		if (!empty($this->options[WA_Integration::WA_CLIENT_SECRET_OPT]) && !Exception::fatal_error()) {
			echo '<p>Client Secret is set</p>';
		}
    }

    /**
     * Obtain WildApricot URL corresponding to the entered API credentials.
     *
     * @return string|bool WildApricot URL, false if it could not be obtained
     */
    private function check_wild_apricot_url() {
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
    private static function validate_and_sanitize_wa_input($input) {
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
    private static function obtain_and_save_wa_data_from_api($valid_api) {
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
            delete_option(WA_Integration::ARRAY_OF_RESTRICTED_POSTS);

            delete_option(WA_Integration::WA_CONTACTS_COUNT_KEY);
            // delete post meta added by the plugin
            delete_post_meta_by_key(WA_Integration::RESTRICTED_GROUPS);
            delete_post_meta_by_key(WA_Integration::RESTRICTED_LEVELS);
            delete_post_meta_by_key(WA_Integration::IS_POST_RESTRICTED);
            delete_post_meta_by_key(WA_Integration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY);
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

/**
 * Handles creating, rendering, and sanitizing license keys
 * 
 * @since 1.1
 * @author Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class License_Settings {
    const OPTION_GROUP = 'wap_licensing_group';
    const SUBMENU_PAGE = 'wap-licensing';
    const SECTION = 'wap_licensing_section';

    public function __construct() {}

    /**
     * Add submenu settings page for license settings.
     *
     * @return void
     */
    public function add_submenu_page() {
        // Create submenu for license key forms
        add_submenu_page(
            Settings::SETTINGS_URL,
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
    public function register_setting_add_fields() {
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
    public function create_license_form() {
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
    public function sanitize_and_validate($input) {
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

        foreach($input as $slug => $license) {
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

            } else if ($key == Addon::LICENSE_STATUS_ENTERED_EMPTY) {
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
    public function print_settings_info() {
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
    public function create_input_box(array $args) {
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