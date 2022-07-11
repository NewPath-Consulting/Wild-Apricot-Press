<?php
namespace WAWP;

require_once __DIR__ . '/Addon.php';
require_once __DIR__ . '/Log.php';
require_once __DIR__ . '/helpers.php';


use WAWP\Addon;
use WAWP\Log;


use function PHPSTORM_META\map;

class MySettingsPage
{
    const CRON_HOOK = 'wawp_cron_refresh_memberships_hook';

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Adds actions and includes files
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );

        // Activate option in table if it does not exist yet
        // Currently, there is a WordPress bug that calls the 'sanitize' function twice if the option is not already in the database
        // See: https://core.trac.wordpress.org/ticket/21989
        if (!get_option('wawp_wal_name')) { // does not exist
            // Create option
            add_option('wawp_wal_name');
        }
        // Set default global page restriction message
        if (!get_option('wawp_restriction_name')) {
            add_option('wawp_restriction_name', '<h2>Restricted Content!</h2> <p>Oops! This post is restricted to specific Wild Apricot users. Log into your Wild Apricot account or ask your administrator to add you to the post!</p>');
        }

        // Add actions for cron update
        add_action(self::CRON_HOOK, array($this, 'cron_update_wa_memberships'));

        // Include files
        require_once('DataEncryption.php');
        require_once('WAWPApi.php');
        require_once('WAIntegration.php');
    }

    /**
	 * Set-up CRON job for updating membership levels and groups
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
     * @param $updated_levels        is an array of the new levels obtained from refresh
     * @param $old_levels            is an array of the previous levels before refresh
     * @param $restricted_levels_key is the key of the restricted levels to be saved
     */
    private function remove_invalid_groups_levels($updated_levels, $old_levels, $restricted_levels_key) {
        $restricted_posts = get_option('wawp_array_of_restricted_posts');

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
                    // See line 230 on WAIntegration.php
                    if (in_array($level_to_delete, $post_restricted_levels)) {
                        // Remove this updated level from post restricted levels
                        $post_restricted_levels = array_diff($post_restricted_levels, array($level_to_delete));
                    }
                    // Check if post's restricted groups and levels are now empty
                    $other_membership_key = 'wawp_restricted_groups';
                    if ($restricted_levels_key == 'wawp_restricted_groups') {
                        $other_membership_key = 'wawp_restricted_levels';
                    }
                    $other_memberships = get_post_meta($restricted_post, $other_membership_key);
                    $other_memberships = maybe_unserialize($other_memberships[0]);
                    if (empty($other_memberships) && empty($post_restricted_levels)) {
                        // This post should NOT be restricted
                        update_post_meta($restricted_post, 'wawp_is_post_restricted', false);
                        // Remove this post from the array of restricted posts
                        $updated_restricted_posts = array_diff($restricted_posts, array($restricted_post));
                        update_option('wawp_array_of_restricted_posts', $updated_restricted_posts);
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
     * @param $updated_levels        is an array of the new levels obtained from refresh
     * @param $old_levels            is an array of the previous levels before refresh
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
     * Updates the membership levels and groups from Wild Apricot into WordPress upon each CRON job
     */
    public function cron_update_wa_memberships() {
        // Ensure that access token is valid
        $valid_access_credentials = WAWPApi::verify_valid_access_token();
        $access_token = $valid_access_credentials['access_token'];
        $wa_account_id = $valid_access_credentials['wa_account_id'];

        // Ensure that access token and account id exist
        if (!empty($access_token) && !empty($wa_account_id)) {
            // Create WAWP Api instance
            $wawp_api = new WAWPApi($access_token, $wa_account_id);

            // Get membership levels
            $updated_levels = $wawp_api->get_membership_levels();

            // Get membership groups
            $updated_groups = $wawp_api->get_membership_levels(true);

            // If the number of updated groups/levels is less than the number of old groups/levels, then this means that one or more group/level has been deleted
            // So, we must find the deleted group/level and remove it from the restriction post meta data of a post, if applicable
            $old_levels = get_option('wawp_all_levels_key');
            $old_groups = get_option('wawp_all_groups_key');
            $restricted_posts = get_option('wawp_array_of_restricted_posts');
            if (!empty($restricted_posts)) {
                if (!empty($old_levels) && !empty($updated_levels) && (count($updated_levels) < count($old_levels))) {
                    $this->remove_invalid_groups_levels($updated_levels, $old_levels, 'wawp_restricted_levels');
                }
                if (!empty($old_groups) && !empty($updated_groups) && (count($updated_groups) < count($old_groups))) {
                    $this->remove_invalid_groups_levels($updated_groups, $old_groups, 'wawp_restricted_groups');
                }
            }
            // Also, removed deleted roles if one or more membership levels are removed
            if (!empty($old_levels) && !empty($updated_levels) && (count($updated_levels) < count($old_levels))) {
                $this->remove_invalid_roles($updated_levels, $old_levels);
            }

            // Save updated levels to options table
            update_option('wawp_all_levels_key', $updated_levels);
            // Save updated groups to options table
            update_option('wawp_all_groups_key', $updated_groups);
        }
    }

    /**
     * Add options page
     */
    public function add_settings_page()
    {
        // Create WAWP admin page
        add_menu_page(
            'Wild Apricot Press',
            'Wild Apricot Press',
            'manage_options',
            'wawp-wal-admin',
            array( $this, 'create_admin_page' ),
			'dashicons-businesswoman',
			6
        );

        // Sub-menu for settings
        add_submenu_page(
            'wawp-wal-admin',
            'Settings',
            'Settings',
            'manage_options',
            'wawp-wal-admin'
        );

		// Create Login sub-menu under WAWP
		add_submenu_page(
			'wawp-wal-admin',
			'Wild Apricot Authorization',
			'Authorization',
			'manage_options',
			'wawp-login',
			array($this, 'create_login_page')
		);

        // Create submenu for license key forms
        add_submenu_page(
            'wawp-wal-admin',
            'Licensing',
            'Licensing',
            'manage_options',
            'wawp-licensing',
            array($this, 'wawp_licensing_page')
        );
    }

        /**
     * Register and add settings
     */
    public function page_init() {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'wal_sanitize'),
            'default' => null
        );

		// Register setting
        register_setting(
            'wawp_wal_group', // Option group
            'wawp_wal_name', // Option name
            $register_args // Sanitize
        );

		// Create settings section
        add_settings_section(
            'wawp_wal_id', // ID
            'Wild Apricot Authorized Application Credentials', // Title
            array( $this, 'wal_print_section_info' ), // Callback
            'wawp-login' // Page
        );

		// Settings for API Key
        add_settings_field(
            'wawp_wal_api_key', // ID
            'API Key:', // Title
            array( $this, 'api_key_callback' ), // Callback
            'wawp-login', // Page
            'wawp_wal_id' // Section
        );

		// Settings for Client ID
        add_settings_field(
            'wawp_wal_client_id', // ID
            'Client ID:', // Title
            array( $this, 'client_id_callback' ), // Callback
            'wawp-login', // Page
            'wawp_wal_id' // Section
        );

		// Settings for Client Secret
		add_settings_field(
            'wawp_wal_client_secret', // ID
            'Client Secret:', // Title
            array( $this, 'client_secret_callback' ), // Callback
            'wawp-login', // Page
            'wawp_wal_id' // Section
        );

        // ---------------------------- Login/Logout button location ------------------------------
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'menu_location_sanitize'),
            'default' => null
        );

		// Register setting
        register_setting(
            'wawp_menu_location_group', // Option group
            'wawp_menu_location_name', // Option name
            $register_args // Sanitize
        );

		// Create settings section
        add_settings_section(
            'wawp_menu_location_id', // ID
            'Login/Logout Button Location', // Title
            array( $this, 'menu_location_print_section_info' ), // Callback
            'wawp-login-menu-location' // Page
        );

		// Settings for Menu to add Login/Logout button
        add_settings_field(
            'wawp_wal_login_logout_button', // ID
            'Menu Location(s):', // Title
            array( $this, 'login_logout_menu_callback' ), // Callback
            'wawp-login-menu-location', // Page // Possibly put somewhere else
            'wawp_menu_location_id' // Section
        );

        // -------------------------- License Keys -------------------------
        // Registering and adding settings for the license key forms
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'validate_license_form'),
            'default' => null
        );

        register_setting(
            'wawp_license_keys',
            'wawp_license_keys',
            $register_args
        );

        add_settings_section(
            'wawp_license',
            'License',
            array($this, 'license_print_info'),
            'wawp_licensing' // page
        );

        // Render the WAWP license form
        add_settings_field(
            'wawp_license_form', // ID
            'Wild Apricot Press', // title
            array($this, 'license_key_input'), // callback
            'wawp_licensing', // page
            'wawp_license', // section
            array('slug' => CORE_SLUG, 'name' => CORE_NAME) // args for callback
        );

        // For each addon installed, render a license key form
        $addons = Addon::instance()::get_addons();
        foreach ($addons as $slug => $addon) {
            if ($slug == CORE_SLUG) {continue;}
            $name = $addon['name'];
            add_settings_field(
                'wawp_license_form_' . $slug, // ID
                $name, // title
                array($this, 'license_key_input'), // callback
                'wawp_licensing', // page
                'wawp_license', // section
                array('slug' => $slug, 'name', $name) // args for callback
            );
        }

        // ------------------------ Restriction status ---------------------------
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'restriction_status_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_restriction_status_group', // group name for settings
            'wawp_restriction_status_name', // name of option to sanitize and save
            $register_args
        );
        // Add settings section and field for restriction status
        add_settings_section(
            'wawp_restriction_status_id', // ID
            'Wild Apricot Status Restriction', // title
            array($this, 'print_restriction_status_info'), // callback
            'wawp-wal-admin' // page
        );
        // Field for membership statuses
        add_settings_field(
            'wawp_restriction_status_field_id', // ID
            'Membership Status(es):', // title
            array($this, 'restriction_status_callback'), // callback
            'wawp-wal-admin', // page
            'wawp_restriction_status_id' // section
        );

        // ------------------------ Restriction page ---------------------------
        // Register setting
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'restriction_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_restriction_group', // group name for settings
            'wawp_restriction_name', // name of option to sanitize and save
            $register_args
        );

        // Add settings section and field for restriction message
        add_settings_section(
            'wawp_restriction_id', // ID
            'Global Restriction Message', // title
            array($this, 'print_restriction_info'), // callback
            'wawp-wal-admin-message' // page
        );
        // Field for restriction message
        add_settings_field(
            'wawp_restriction_field_id', // ID
            'Restriction Message:', // title
            array($this, 'restriction_message_callback'), // callback
            'wawp-wal-admin-message', // page
            'wawp_restriction_id' // section
        );

        // ------------------------- Custom fields ---------------------------
        // Register setting
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'custom_fields_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_fields_group', // group name for settings
            'wawp_fields_name', // name of option to sanitize and save
            $register_args
        );
        // Add settings section and field for selecting custom fields
        add_settings_section(
            'wawp_fields_id', // ID
            'Custom Fields', // title
            array($this, 'print_fields_info'), // callback
            'wawp-wal-admin&tab=fields' // page
        );
        add_settings_field(
            'wawp_custom_field_id', // ID
            'Custom Fields to Include:', // title
            array($this, 'field_message_callback'), // callback
            'wawp-wal-admin&tab=fields', // page
            'wawp_fields_id' // section
        );

        // ------------------------------ Deletion Options ---------------------------
        // Register setting
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'deletion_options_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_delete_group', // group name for settings
            'wawp_delete_name', // name of option to sanitize and save
            $register_args
        );
        // Add settings section and field for selecting custom fields
        add_settings_section(
            'wawp_delete_id', // ID
            'Plugin Deletion Options', // title
            array($this, 'print_delete_info'), // callback
            'wawp-wal-admin&tab=plugin' // page
        );
        add_settings_field(
            'wawp_delete_options_id', // ID
            'Attributes to Remove Upon Plugin Deletion:', // title
            array($this, 'plugin_delete_callback'), // callback
            'wawp-wal-admin&tab=plugin', // page
            'wawp_delete_id' // section
        );

        // ------------------------------ Log File Options ---------------------------
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'logfile_options_sanitize'),
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
            array($this, 'print_logfile_info'),
            'wawp-wal-admin&tab=plugin#log'
        );
        add_settings_field(
            'wawp_logfile_flag_id',
            'Print log messages to log file',
            array($this, 'wawp_logfile_flag_form'),
            'wawp-wal-admin&tab=plugin#log',
            'wawp_logfile_id',
        );

    }

    /**
     * Settings page callback
     */
    public function create_admin_page()
    {
        $tab = get_current_tab();
        ?>
        <div class="wrap">
            <h2>Wild Apricot Admin Settings</h2>
            <?php 
            // don't display settings if credentials and/or key are not valid
            if (!WAIntegration::valid_wa_credentials() || !Addon::instance()::has_valid_license(CORE_SLUG)) { ?> </div> <?php return; } ?>
            <!-- Tabs for navigation -->
            <nav class="nav-tab-wrapper">
                <a href="?page=wawp-wal-admin" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">Content Restriction Options</a>
                <a href="?page=wawp-wal-admin&tab=fields" class="nav-tab <?php if($tab==='fields'):?>nav-tab-active<?php endif; ?>">Synchronization Options</a>
                <a href="?page=wawp-wal-admin&tab=plugin" class="nav-tab <?php if($tab==='plugin'):?>nav-tab-active<?php endif; ?>">Plugin Options</a>
            </nav>
            <div class="tab-content">
                <?php switch($tab) :
                    case 'fields':
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
                        break;
                    case 'plugin':
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
                        break;
                    default:
                        ?>
                        <!-- Form for Restriction Status(es) -->
                        <form method="post" action="options.php">
                        <?php
                            // Nonce for verification
                            wp_nonce_field('wawp_restriction_status_nonce_action', 'wawp_restriction_status_nonce_name');
                            // This prints out all hidden setting fields
                            settings_fields('wawp_restriction_status_group');
                            do_settings_sections( 'wawp-wal-admin' );
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
                        break;
                    endswitch; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Displays the checkboxes for selecting the restricted status(es)
     */
    public function restriction_status_callback() {
        // Display checkboxes for each Wild Apricot status
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
        $saved_statuses = get_option('wawp_restriction_status_name');
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
            <input type="checkbox" name="wawp_restriction_status_name[]" class='wawp_class_status' value="<?php esc_html_e($status_key); ?>" <?php esc_html_e($status_checked); ?>/> <?php esc_html_e($status); ?> </input><br>
            <?php
        }
    }


    /**
     * Displays the restriction message text area
     */
    public function restriction_message_callback() {
        // Add wp editor
        // See: https://stackoverflow.com/questions/20331501/replacing-a-textarea-with-wordpress-tinymce-wp-editor
        // https://developer.wordpress.org/reference/functions/wp_editor/
        // Get default or saved restriction message
        $initial_content = get_option('wawp_restriction_name');
        $editor_id = 'wawp_restricted_message_textarea';
        $editor_name = 'wawp_restriction_name';
        $editor_settings = array('textarea_name' => $editor_name, 'tinymce' => true);
        // Create WP editor
        wp_editor($initial_content, $editor_id, $editor_settings);
    }

    /**
     * Displays the checkboxes for the Wild Apricot custom fields
     */
    public function field_message_callback() {
        // Load in custom fields
        $custom_fields = get_option(WAIntegration::LIST_OF_CUSTOM_FIELDS);
        $checked_fields = get_option('wawp_fields_name');
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
					<input type="checkbox" name="wawp_fields_name[]" class='wawp_case_field' value="<?php esc_html_e($field_id); ?>" <?php esc_html_e($is_checked); ?>/> <?php esc_html_e($field_name); ?> </input><br>
				<?php
            }
        } else { // no custom fields
            $authorization_link = esc_url(site_url() . '/wp-admin/admin.php?page=wawp-login');
            ?>
            <p>Your Wild Apricot site does not have any contact fields! Please ensure that you have correctly entered your Wild Apricot site's credentials under <a href="<?php esc_html_e($authorization_link); ?>">Wild Apricot Press -> Authorization</a></p>
            <?php
        }
    }


    /**
     * Displays the options for deleting the plugin, including if the Wild Apricot synced users should be retained, etc.
     */
    public function plugin_delete_callback() {
        // Store each checkbox description in array
        $synced_info = array('wawp_delete_checkbox' => 'Delete all Wild Apricot information from my WordPress site');
        // Load in saved checkboxes
        $saved_synced_info = get_option('wawp_delete_name');
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
            <input type="checkbox" name="wawp_delete_name[]" class='wawp_class_delete' value="<?php esc_html_e($key); ?>" <?php esc_html_e($checked); ?>/> <?php esc_html_e($attribute); ?> </input><br>
            <p><b><br>Please note that this information will never be deleted from your Wild Apricot site, only your WordPress site, so you can always recover the deleted information from your WordPress site by re-syncing your WordPress site with your Wild Apricot site.
            So, don't worry - you are not permanently deleting information that you cannot recover later!</b></p>
            <?php
        }
    }

    /**
     * Renders the log file toggle checkbox.
     *
     * @return void
     */
    public function wawp_logfile_flag_form() {
        $checked = Log::can_debug();
        Log::wap_log_debug('logfile flag ' . print_r($checked,1));
        // $checked = $logfile_flag ? 'checked' : '';
        ?>
        <input type="checkbox" name="<?php esc_html_e(Log::LOG_OPTION); ?>" class="wawp_class_logfile" value="checked" <?php esc_html_e($checked); ?>></input>
        <?php
    }

	/**
	 * Login page callback
     *
     * Creates the content for the Wild Apricot login page, including instructions
	 */
	public function create_login_page() {
		$this->options = get_option( 'wawp_wal_name' );
		?>
        <div class="wrap">
			<h1>Wild Apricot Authorization</h1>
			<div class="waSettings">
				
				<div class="loginChild">
                    <!-- Wild Apricot credentials form -->
					<form method="post" action="options.php">
					<?php
                        // Nonce for verification
                        wp_nonce_field('wawp_credentials_nonce_action', 'wawp_credentials_nonce_name');
						// This prints out all hidden setting fields
						settings_fields( 'wawp_wal_group' );
						do_settings_sections( 'wawp-login' );
						submit_button();
					?>
					</form>
					<!-- Check if form is valid -->
					<?php
                        // Delete the license keys, which would then need to be entered for the (potentially) new Wild Apricot site
						if (!isset($this->options['wawp_wal_api_key']) || !isset($this->options['wawp_wal_client_id']) || !isset($this->options['wawp_wal_client_secret']) || $this->options['wawp_wal_api_key'] == '' || $this->options['wawp_wal_client_id'] == '' || $this->options['wawp_wal_client_secret'] == '') { // not valid
							echo '<p style="color:red">Missing valid Wild Apricot credentials! Please enter them above!</p>';
						} else { // successful login
                            // Get Wild Apricot URL
                            $wild_apricot_url = get_option(WAIntegration::WA_URL_KEY);
                            if ($wild_apricot_url) {
                                $dataEncryption = new DataEncryption();
                                $wild_apricot_url = esc_url($dataEncryption->decrypt($wild_apricot_url));
                            }
							echo '<p style="color:green">Valid Wild Apricot credentials have been saved!</p>';
                            echo '<p style="color:green">Your WordPress site has been connected to <b>' . esc_url($wild_apricot_url) . '</b>!</p>';
						}
					?>
                    <!-- Menu Locations for Login/Logout button -->
                    <form method="post" action="options.php">
					<?php
                        // Nonce for verification
                        wp_nonce_field('wawp_menu_location_nonce_action', 'wawp_menu_location_nonce_name');
						// This prints out all hidden setting fields
						settings_fields( 'wawp_menu_location_group' );
						do_settings_sections( 'wawp-login-menu-location' );
						submit_button();
					?>
					</form>
                    <!-- Check if menu location(s) have been submitted -->
                    <?php
                        // Check menu locations in options table
                        $menu_location_saved = get_option('wawp_menu_location_name');
                        // If menu locations is not empty, then it has been saved
                        if (!empty($menu_location_saved)) {
                            // Display success statement
                            echo '<p style="color:green">Menu Location(s) for the Login/Logout button have been saved!</p>';
                        } else {
                            Log::wap_log_warning('No menu location for the login/logout button selected. Please select so the button will appear on your site.');
                            echo '<p style="color:red">Missing Menu Location(s) for the Login/Logout button! Please check off your desired menu locations above!</p>';
                        }
                    ?>
				</div>
                <div class="loginChild">
					<p>In order to connect your Wild Apricot with your WordPress website, <b>Wild Apricot Press</b> requires the following credentials from your Wild Apricot account:</p>
					<ul class="wawp_list">
					   <li>API Key</li>
					   <li>Client ID</li>
					   <li>Client Secret</li>
					</ul>
					<p>If you currently do not have these credentials, no problem! Please follow the steps below to obtain them.</p>
					<ol>
					   <li>In the admin view on your Wild Apricot site, in the left hand menu, select <b>Settings</b>. On the Global settings screen, select the <b>Authorized applications</b> option (under Integration). <br><br>
					      <img src="/wp-content/plugins/Wild-Apricot-Press/images/authorized-applications.png" alt="Settings > Integration > Authorized applications" class="wawp_authorization_img"> <br>
					   </li>
					   <li>On the Authorized applications screen, click the <b>Authorize application</b> button in the top left corner.
					      <br><br>
					      <img src="/wp-content/plugins/Wild-Apricot-Press/images/authorized-applications.png" alt="Authorized application button" class="wawp_authorization_img"> <br>
					   </li>
					   <li> On the Application authorization screen, click the <b>Server application</b> option then click the <b>Continue</b> button. <br><br>
					      <img src="/wp-content/plugins/Wild-Apricot-Press/images/authorized-application-type.png" alt="Authorized application server selection" class="wawp_authorization_img"><br>
					   </li>
					   <li>
					      On the Application details screen, the following options should be set:
					      <ul class="wawp_list">
						 <li>
						    <b>Application name</b>
						    <ul class="wawp_list">
						       <li>The name used to identify this application within the list of authorized applications. Select whatever name you like. For our example, it will be called "Our WordPress Site".
						       </li>
						    </ul>
						 </li>
						 <li>
						    <b>Access Level</b>
						    <ul class="wawp_list">
						       <li>Choose full access as the <b>Wild Apricot Press</b> plugin requires ability to read and write to your Wild Apricot database.
						       </li>
						    </ul>
						 </li>
						 <li>
						    <b>Client Secret</b>
						    <ul class="wawp_list">
						       <li>If there is no Client secret value displayed, click the green Generate client secret button. To delete the client secret, click the red X beside the value.
						       </li>
						    </ul>
					      </ul>
					   </li>
					   <li>
					      Click the <b>Save</b> button to save your changes.
					   </li>
					   <li>From the Application details screen, copy the <b>API key</b>, <b>Client ID</b>, and <b>Client secret</b> (the blue boxes). Input these values into their respective locations in WordPress, to the right of these instructions. <br><br>
					      <img src="/wp-content/plugins/Wild-Apricot-Press/images/application-detatails-api-keys.png" alt="Authorized application API keys" width="500">  <br>
					   </li>
					   <br>
					</ol>
				</div>
			</div>
        </div>
        <?php
	}



    /**
     * Sanitize restriction status checkboxes
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function restriction_status_sanitize($input) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['wawp_restriction_status_nonce_name'], 'wawp_restriction_status_nonce_action')) {
            // wp_die('Your nonce for the restriction status(es) could not be verified.');
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the restriction status could not be verified. Please try again.');
        }
        $valid = array();
        // Loop through each checkbox and sanitize
        if (!empty($input)) {
            foreach ($input as $key => $box) {
                $valid[$key] = filter_var($box, FILTER_SANITIZE_STRING);
            }
        }
        // Return sanitized value
        return $valid;
    }

    /**
     * Sanitize restriction message
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function restriction_sanitize($input) {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_restriction_nonce_name'], 'wawp_restriction_nonce_action')) {
            // wp_die('Your nonce for the restriction message could not be verified.');
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the restriction message could not be verified. Please try again.');
        }
		// Create valid variable that will hold the valid input
		$valid = sanitize_textarea_field($input);
        // Return valid input
        return $valid;
    }

    /**
     * Create licensing page content.
     *
     * @return void
     */
    public function wawp_licensing_page() {
        ?>
        <div class="wrap">
            <?php
            // Check if Wild Apricot credentials have been entered
            $wa_credentials = get_option(WAIntegration::WA_CREDENTIALS_KEY);
            // If credentials have been entered (not empty), then we can present the license page
            if (!empty($wa_credentials) && $wa_credentials['wawp_wal_api_key'] != '') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    // Nonce for verification
                    wp_nonce_field('wawp_license_nonce_action', 'wawp_license_nonce_name');
                    settings_fields('wawp_license_keys');
                    do_settings_sections('wawp_licensing');
                    submit_button('Save', 'primary');
                    ?>
                </form>
                <?php
            } else { // credentials have not been entered -> tell user to enter Wild Apricot credentials
                echo "<h2>License Keys</h2>";
                Log::wap_log_warning('Missing Wild Apricot API credentials -- cannot render license page');
            }
            ?>
        </div>
        <?php
    }

    /**
     * Create the license key input box for the form
     * @param array $args contains arguments with (slug, title) as keys.
     */
    public function license_key_input(array $args) {
        $slug = $args['slug'];
        $licenses = Addon::instance()::get_licenses();
        // Check that slug is valid
        $input_value = '';
        if (Addon::instance()::has_valid_license($slug)) {
            $input_value = Addon::instance()::get_license($slug);
        } else {
        }
        echo "<input id='license_key " . esc_html__($slug) . "' name='wawp_license_keys[" . esc_html__($slug) ."]' type='text' value='" . $input_value . "'  />" ;
    }

    /**
     * Sanitize custom fields input
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function custom_fields_sanitize($input) {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_field_nonce_name'], 'wawp_field_nonce_action')) {
            // wp_die('Your nonce could not be verified.');
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the custom fields selection could not be verified. Please try again.');
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
     * Sanitize the plugin options
     */
    public function deletion_options_sanitize($input) {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_delete_nonce_name'], 'wawp_delete_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the deletion option could not be verified.');
            // wp_die('Your plugin options could not be verified.');
        } 
        $valid = array();
        Log::wap_log_debug($input);
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
     * Sanitize the logfile toggle input.
     *
     * @param string $input
     * @return string sanitized input
     */
    public function logfile_options_sanitize($input) {
        if (!wp_verify_nonce($_POST['wawp_logfile_flag_nonce_name'], 'wawp_logfile_flag_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the logfile option could not be verified.');
        }
        
        $valid = sanitize_text_field($input);
        // if input is empty, box is not checked, return empty string
        if (!$valid) return '';
        return $valid;
    }

    /**
     * Sanitize the login/logout menu location checkboxes
     *
     * @param array $input Contains all checkboxes in an array
     */
    public function menu_location_sanitize($input) {
        // Verify nonce
        if (!wp_verify_nonce(
            $_POST['wawp_menu_location_nonce_name'], 'wawp_menu_location_nonce_action')
        ) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the menu location(s) could not be verified.');
        }

        // Create valid array that will hold valid inputs
        $valid = array();
        // Sanitize each element
        if (!empty($input)) {
            foreach ($input as $menu_key => $menu_value) {
                $valid[$menu_key] = sanitize_text_field($menu_value);
            }
        }
        return $valid;
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function wal_sanitize( $input ) {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_credentials_nonce_name'], 'wawp_credentials_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the Wild Apricot credentials could not be verified.');
            // wp_die('Your Wild Apricot credentials could not be verified.');
        }
		// Create valid array that will hold the valid input
		$valid = array();

        // TODO: loop through instead of doing this
        // Get valid api key
        $valid['wawp_wal_api_key'] = preg_replace(
            '/[^A-Za-z0-9]+/', // match only letters and numbers
            '',
            $input['wawp_wal_api_key']
        );
        // Get valid client id
        $valid['wawp_wal_client_id'] = preg_replace(
            '/[^A-Za-z0-9]+/', // match only letters and numbers
            '',
            $input['wawp_wal_client_id']
        );
        // Get valid client secret
        $valid['wawp_wal_client_secret'] = preg_replace(
            '/[^A-Za-z0-9]+/', // match only letters and numbers
            '',
            $input['wawp_wal_client_secret']
        );

        // Encrypt values if they are valid
        $entered_valid = true;
        $entered_api_key = '';
        // require_once('DataEncryption.php');
		$dataEncryption = new DataEncryption();
        // Check if inputs are valid
        if ($valid['wawp_wal_api_key'] !== $input['wawp_wal_api_key'] || $input['wawp_wal_api_key'] == '') { // incorrect api key
            $valid['wawp_wal_api_key'] = '';
            $entered_valid = false;
        } else { // valid
            $entered_api_key = $valid['wawp_wal_api_key'];
            $valid['wawp_wal_api_key'] = $dataEncryption->encrypt($valid['wawp_wal_api_key']);
        }
        if ($valid['wawp_wal_client_id'] !== $input['wawp_wal_client_id'] || $input['wawp_wal_client_id'] == '') { // incorrect client ID
            $valid['wawp_wal_client_id'] = '';
            $entered_valid = false;
        } else {
            $valid['wawp_wal_client_id'] = $dataEncryption->encrypt($valid['wawp_wal_client_id']);
        }
        if ($valid['wawp_wal_client_secret'] !== $input['wawp_wal_client_secret'] || $input['wawp_wal_client_secret'] == '') { // incorrect client secret
            $valid['wawp_wal_client_secret'] = '';
            $entered_valid = false;
        } else {
            $valid['wawp_wal_client_secret'] = $dataEncryption->encrypt($valid['wawp_wal_client_secret']);
        }

        // If input is valid, check if it can connect to the API
        $valid_api = '';
        if ($entered_valid) {
            $valid_api = WAWPApi::is_application_valid($entered_api_key);
        }
        // Set all elements to '' if api call is invalid or invalid input has been entered
        if ($valid_api == false || !$entered_valid) {
            // Set all inputs to ''
            $keys = array_keys($valid);
            $valid = array_fill_keys($keys, '');

            // Delete all licenses because they are invalid now and user must insert them again
            Addon::clear_licenses();
            return $valid;
        } 


        // Valid input and valid response
        // Extract access token and ID, as well as expiring time
        $access_token = $valid_api['access_token'];
        $account_id = $valid_api['Permissions'][0]['AccountId'];
        $expiring_time = $valid_api['expires_in'];
        $refresh_token = $valid_api['refresh_token'];
        // Store access token and account ID as transients
        set_transient('wawp_admin_access_token', $dataEncryption->encrypt($access_token), $expiring_time);
        set_transient('wawp_admin_account_id', $dataEncryption->encrypt($account_id), $expiring_time);
        // Store refresh token in database
        update_option('wawp_admin_refresh_token', $dataEncryption->encrypt($refresh_token));
        // Get all membership levels and groups
        $wawp_api_instance = new WAWPApi($access_token, $account_id);
        $all_membership_levels = $wawp_api_instance->get_membership_levels();
        // Create a new role for each membership level
        // Delete old roles if applicable
        $old_wa_roles = get_option('wawp_all_levels_key');
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
        $all_membership_groups = $wawp_api_instance->get_membership_levels(true);
        // Save membership levels and groups to options
        update_option('wawp_all_levels_key', $all_membership_levels);
        update_option('wawp_all_groups_key', $all_membership_groups);

        // Get Wild Apricot URL
        $wild_apricot_url_array = $wawp_api_instance->get_account_url_and_id();
        $wild_apricot_url = esc_url_raw($wild_apricot_url_array['Url']);
        // Save URL
        update_option(WAIntegration::WA_URL_KEY, $dataEncryption->encrypt($wild_apricot_url));

        // Schedule CRON update for updating the available membership levels and groups
        self::setup_cron_job();

        // Return array of valid inputs
        return $valid;

    }

        /**
     * License form callback.
     * For each license submitted, check if the license is valid.
     * If it is valid, it gets added to the array of valid license keys.
     * Otherwise, the user receives an error.
     * @param array $input settings form input array mapping addon slugs to license keys
     */
    public function validate_license_form($input) {
        $data_encryption = new DataEncryption();
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_license_nonce_name'], 'wawp_license_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the license key(s) could not be verified.');
        }

        $valid = array();

        foreach($input as $slug => $license) {
            $key = Addon::instance()::validate_license_key($license, $slug);
            if (is_null($key)) { 
                // invalid key
                Addon::update_license_check_option($slug, Addon::LICENSE_STATUS_INVALID);
                $valid[$slug] = '';

            } else if ($key == Addon::LICENSE_STATUS_ENTERED_EMPTY) {
                // key was not entered -- different message will be shown
                $valid[$slug] = '';

                Addon::update_license_check_option($slug, Addon::LICENSE_STATUS_ENTERED_EMPTY);
            } else { 
                // valid key
                Addon::update_license_check_option($slug, Addon::LICENSE_STATUS_VALID);
                $valid[$slug] = $data_encryption->encrypt($key);

            }


        }
        return $valid;
    }

    /**
     * Print instructions on how to use the restriction status checkboxes
     */
    public function print_restriction_status_info() {
        print 'Please select the Wild Apricot member/contact status(es) that will be able to see the restricted posts.';
        print '<br>If no statuses are selected, then all membership statuses can view the restricted posts!';
    }

    /**
     * Print the Custom Fields introductory text
     */
    public function print_fields_info() {
        print 'Please select the Wild Apricot Contact Fields that you would like to sync with your WordPress site.';
    }

    /**
     * Print description of the plugin options
     */
    public function print_delete_info() {
        print 'By default, upon deletion of the <b>Wild Apricot Press</b> plugin, the WordPress users and roles that you have synced from Wild Apricot are retained (not deleted). If you like, you can remove all Wild Apricot information from your WordPress site after deleting the <b>Wild Apricot Press</b> plugin by checking the checkbox below.<br><br>Then, all of the Wild Apricot information that you synced with your WordPress site will be deleted AFTER you delete the <b>Wild Apricot Press</b> plugin. If you would like to keep your Wild Apricot users and roles in your WordPress site upon deletion of the plugin, then you\'re all set - just leave the checkbox unchecked!';
    }

    public function print_logfile_info() {
        print 'By checking this box, error and warning messages will be printed to a log file accessible in wp-content.';
    }

    /**
     * Print the Global Restriction description
     */
    public function print_restriction_info() {
        print 'The "Global Restriction Message" is the message that is shown to users who are not members of the Wild Apricot membership level(s) or group(s) required to access a restricted post. Try to make the message informative; for example, you can suggest what the user can do in order to be granted access to the post. You can also set a custom restriction message for each individual post by editing the "Individual Restriction Message" field under the post editor.';
    }

    /**
     * Print the menu location description text
     */
    public function menu_location_print_section_info() {
        print 'Please specify the menu(s) that you would like the Login/Logout button to appear on. Users can then use this Login/Logout button to sign in and out of their Wild Apricot account on your WordPress site!';
    }

    /**
     * Print the instructions text for entering your Wild Apricot credentials
     */
    public function wal_print_section_info() {
        print 'Enter your Wild Apricot credentials here. Your data is encrypted for your safety!';
    }

    /**
     * Print the licensing settings section text
     */
    public function license_print_info() {
        $link_address = "https://newpathconsulting.com/wap/";
        print "Enter your license key(s) here. If you do not already have a license key, please visit our website <a href='".$link_address."' target='_blank' rel='noopener noreferrer'>here</a> to get a license key! The license key for <b>Wild Apricot Press</b> is 100% free, and we never share your information with any third party!";
    }

    /**
     * Display text field for API key
     */
    public function api_key_callback() {
		echo "<input id='wawp_wal_api_key' name='wawp_wal_name[wawp_wal_api_key]'
			type='text' placeholder='*************' />";
		// Check if api key has been set; if so, echo that the client secret has been set!
		if (isset($this->options['wawp_wal_api_key']) && $this->options['wawp_wal_api_key'] != '') {
			echo "<p>API Key is set!</p>";
		}
    }

    /**
     * Display text field for Client ID
     */
    public function client_id_callback() {
		echo "<input id='wawp_wal_client_id' name='wawp_wal_name[wawp_wal_client_id]'
			type='text' placeholder='*************' />";
		// Check if client id has been set; if so, echo that the client secret has been set!
		if (isset($this->options['wawp_wal_client_id']) && $this->options['wawp_wal_client_id'] != '') {
			echo "<p>Client ID is set!</p>";
		}
    }

	/**
     * Display text field for Client Secret
     */
    public function client_secret_callback() {
		echo "<input id='wawp_wal_client_secret' name='wawp_wal_name[wawp_wal_client_secret]'
			type='text' placeholder='*************' />";
		// Check if client secret has been set; if so, echo that the client secret has been set!
		if (isset($this->options['wawp_wal_client_secret']) && $this->options['wawp_wal_client_secret'] != '') {
			echo "<p>Client Secret is set!</p>";
		}
    }

    /**
     * Get the desired menu to add the login/logout button to
     */
    public function login_logout_menu_callback() {
        // Get menu items: https://wordpress.stackexchange.com/questions/111060/retrieving-a-list-of-menu-items-in-an-array
        $menu_locations = get_nav_menu_locations();
        $menu_items = array();
        // Save each menu name in menu_items
        foreach ($menu_locations as $key => $value) {
            // Append key to menu_items
            $menu_items[] = $key;
        }

        // See: https://wordpress.stackexchange.com/questions/328648/saving-multiple-checkboxes-with-wordpress-settings-api
        $wawp_wal_login_logout_button = get_option('wawp_menu_location_name',[]);

        foreach ($menu_items as $item) {
            echo "<div><input type='checkbox' id='wawp_selected_menu' name='wawp_menu_location_name[]' value='" . esc_html__($item) . "'" . (in_array( esc_html__($item), $wawp_wal_login_logout_button )?"checked='checked'":"") . ">";
            echo "<label for= '" . esc_html__($item) . "'>" . esc_html__($item) . "</label></div>";
        }
    }






}
