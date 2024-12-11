<?php

namespace WAWP;

require_once __DIR__ . '/class-settings-controller.php';
require_once __DIR__ . '/../util/class-log.php';
require_once __DIR__ . '/../util/class-data-encryption.php';
require_once __DIR__ . '/../class-wa-api.php';
require_once __DIR__ . '/../class-wa-integration.php';
require_once __DIR__ . '/../util/helpers.php';
require_once __DIR__ . '/../util/wap-exception.php';

/**
 * Handles creating, rendering, and sanitizing WildApricot admin settings.
 *
 * @since 1.1
 * @author Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class Admin_Settings
{
    public const LOGIN_BUTTON_LOCATION_SECTION = 'wap_menu_location_group';
    public const LOGIN_BUTTON_LOCATION_PAGE = Settings_Controller::SETTINGS_URL . '-login-location';

    public const DELETE_DB_DATA = 'delete_db_data';
    public const DELETE_USER_DATA = 'delete_user_data';

    public const STYLE_OPTION_GROUP = 'wap_styles_group';
    public const STYLE_SUBMENU_PAGE = 'wap-styles-submenu';
    public const STYLE_SECTION = 'wap_styles_section';
    public const STYLE_OPTION_NAME = 'wawp_user_style';
    public const CSS_FILE_PATH = 'css/wawp-styles-user.css';

    public function __construct()
    {


    }

    /**
     * Add WAP admin settings pages.
     *
     * @return void.
     */
    public function add_settings_page()
    {

    }

    /**
     * Add main admin menu page and general submenu page.
     *
     * @return void
     */
    public function add_menu_pages()
    {
        // Sub-menu for settings
        add_menu_page(
            'WildApricot Press',
            'WildApricot Press',
            'manage_options',
            Settings_Controller::SETTINGS_URL,
            array( $this, 'create_admin_page' ),
            'dashicons-businesswoman',
            6
        );

        add_submenu_page(
            Settings_Controller::SETTINGS_URL,
            'Settings',
            'Settings',
            'manage_options',
            Settings_Controller::SETTINGS_URL
        );
    }


    /**
     * Registers settings and adds groups and fields for all admin settings.
     *
     * @return void
     */
    public function register_setting_add_fields()
    {
        // content restriction tab
        $this->register_login_button_location();
        $this->register_status_restriction();
        $this->register_global_restriction_msg();

        // sync options
        $this->register_cron_sync_option();
        $this->register_custom_fields();

        // plugin options
        $this->register_deletion_option();
        $this->register_logfile_option();

        // style options
        $this->register_user_style();
        $this->register_login_page_settings();

    }

    /**
     * Create the admin page with main tab, fields,and plugin tab.
     *
     * @return void
     */
    public function create_admin_page()
    {
        $tab = get_current_tab();
        ?>
<div class="wrap">
    <h2>Settings</h2>
    <?php
            if (Addon::is_plugin_disabled()) {
                ?>
</div> <?php
                return;
            }
        ?>
<!-- navigation tabs -->
<nav class="nav-tab-wrapper">
    <a href="?page=wawp-wal-admin" class="nav-tab <?php if($tab === null): ?>nav-tab-active<?php endif; ?>">Content
        Restriction Options</a>
    <a href="?page=wawp-wal-admin&tab=fields"
        class="nav-tab <?php if($tab === 'fields'):?>nav-tab-active<?php endif; ?>">Synchronization
        Options</a>
    <a href="?page=wawp-wal-admin&tab=plugin"
        class="nav-tab <?php if($tab === 'plugin'):?>nav-tab-active<?php endif; ?>">Plugin
        Options</a>
    <a href="?page=wawp-wal-admin&tab=style"
        class="nav-tab <?php if($tab === 'style'):?>nav-tab-active<?php  endif;?>">User
        Login Page Options</a>
</nav>
<div class="tab-content">
    <?php
            switch($tab):
                case 'fields': $this->create_sync_options_tab();
                    break;
                case 'plugin': $this->create_plugin_options_tab();
                    break;
                case 'style': $this->create_login_settings_tab();
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
    public function login_menu_location_print_section_info()
    {
        print 'Please specify the menu(s) that you would like the Login/Logout button to appear on. Users can then use this Login/Logout button to sign in and out of their WildApricot account on your WordPress site.';
    }

    /**
     * Inform user there are no menus so the login button location cannot be
     * chose.
     *
     * @return void
     */
    public function login_menu_location_no_menus_info()
    {
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
    public function login_menu_location_input_box()
    {
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
            } elseif (!empty($menu_id_to_location) && !$is_checked) {
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
    public function login_menu_location_sanitize($input)
    {
        // Verify nonce
        if (!wp_verify_nonce(
            $_POST['wawp_menu_location_nonce_name'],
            'wawp_menu_location_nonce_action'
        )
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
    public function restriction_status_print_info()
    {
        print 'Please select the WildApricot member/contact status(es) that will be able to see the restricted posts.<br>';
        print 'If no statuses are selected, then all membership statuses can view the restricted posts.';
    }

    /**
     * Displays the checkboxes for selecting the membership statuses to restrict
     * from posts.
     *
     * @return void
     */
    public function restriction_status_input_box()
    {
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
        $saved_statuses = get_option(WA_Restricted_Posts::GLOBAL_RESTRICTED_STATUSES);
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
<input type="checkbox" name="wawp_restriction_status_name[]" class="wawp_class_status"
    value="<?php echo esc_attr($status_key); ?>" <?php echo esc_attr($status_checked); ?> />
<?php echo esc_html($status); ?> </input><br>
<?php
        }
    }

    /**
     * Sanitize restriction status checkboxes.
     *
     * @param array $input Contains all settings fields as array keys
     * @return array array of sanitized inputs
     */
    public function restriction_status_sanitize($input)
    {
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
    public function restriction_message_print_info()
    {
        print 'The "Global Restriction Message" is the message that is shown to users who are not members of the WildApricot membership level(s) or group(s) required to access a restricted post. ';
        print 'Try to make the message informative; for example, you can suggest what the user can do in order to be granted access to the post. ';
        print 'You can also set a custom restriction message for each individual post by editing the "Individual Restriction Message" field under the post editor.';
    }

    /**
     * Displays the global restriction message text area.
     *
     * @return void
     */
    public function restriction_message_input_box()
    {
        // Add wp editor
        // See: https://stackoverflow.com/questions/20331501/replacing-a-textarea-with-wordpress-tinymce-wp-editor
        // https://developer.wordpress.org/reference/functions/wp_editor/
        // Get default or saved restriction message
        $initial_content = get_option(WA_Restricted_Posts::GLOBAL_RESTRICTION_MESSAGE);
        $editor_id = 'wawp_restricted_message_textarea';
        $editor_name = WA_Restricted_Posts::GLOBAL_RESTRICTION_MESSAGE;
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
    public function restriction_message_sanitize($input)
    {
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

    public function manual_sync_input()
    {
        ?> <input value="manual_sync" type="hidden" name="manual_sync" /> <?php
    }

    public function cron_freq_print_info()
    {
        print 'Please indicate how frequent you would like the plugin to resync data with your WildApricot site.';
    }

    public function cron_freq_input()
    {
        $cron_recurrence_array = wp_get_schedules();
        // Log::wap_log_debug($cron_recurrence_array);
        $cron_freq = WA_Integration::get_cron_frequency();
        ?> <select id="cron_recurrence" name="cron_recurrence"><?php
        foreach ($cron_recurrence_array as $id => $inner) {
            ?>
    <option value="<?php echo esc_attr($id); ?>" <?php echo strcmp($id, $cron_freq) == 0 ? "selected" : ""; ?>>
        <?php echo esc_html($inner['display']); ?>
    </option> <?php
        }
        ?>
</select> <?php
    }

    public function cron_freq_sanitize($input)
    {
        $cron_freq = WA_Integration::get_cron_frequency();
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_sync_nonce_name'], 'wawp_sync_nonce_action')) {
            // wp_die('Your nonce could not be verified.');
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the cron frequency selection could not be verified. Please try again.');
            return $cron_freq;
        }

        if (array_key_exists('cron_recurrence', $_POST)) {
            return sanitize_key($_POST['cron_recurrence']);
        }

        return $cron_freq;
    }

    /**
     * Print the Custom Fields introductory text
     *
     * @return void
     */
    public function custom_fields_print_info()
    {
        print 'Please select the WildApricot Contact Fields that you would like to sync with your WordPress site.<br>';
        print 'Admin-only contact fields are displayed below the list of contact fields but are not available to sync. If you wish to sync these fields with your site please change the field settings in WildApricot.';
    }

    /**
     * Displays the checkboxes for the WildApricot custom fields.
     *
     * @return void
     */
    public function custom_fields_input()
    {
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
<input type="checkbox" name="wawp_fields_name[]" class='wawp_case_field' value="<?php echo esc_attr($field_id); ?>"
    <?php echo esc_attr($is_checked); ?> />
<?php echo esc_html($field_name); ?> </input><br>
<?php
            }
        } else { // no custom fields
            ?>
<p>Your WildApricot site does not have any contact fields! Please ensure that you have correctly entered your
    WildApricot site's credentials under <a href="<?php echo esc_url(get_auth_menu_url()); ?>">WildApricot
        Press -> Authorization</a></p>
<?php
        }
    }

    /**
     * Displays list of WildApricot member fields that have admin only access.
     *
     * @return void
     */
    public function admin_fields_list()
    {
        $admin_fields = get_option(WA_Integration::LIST_OF_ADMIN_FIELDS);
        if (!empty($admin_fields)) {
            foreach ($admin_fields as $field_id => $field_name) {
                ?> <input type="checkbox" disabled style="color:gray">
<?php echo esc_html($field_name) ?> </input><br> <?php
            }
        }
    }

    /**
     * Sanitize custom fields input.
     *
     * @param array $input Contains all settings fields as array keys
     * @return array array of sanitized inputs
     */
    public function custom_fields_sanitize($input)
    {
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
    public function deletion_option_print_info()
    {
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
    public function deletion_option_input()
    {
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
<input type="checkbox" name="wawp_delete_setting[]" class='wawp_class_delete' value="<?php echo esc_attr($key); ?>"
    <?php echo esc_attr($checked); ?> />
<?php echo esc_html($attribute); ?> </input><br><br>
<?php

        }
    }

    /**
     * Sanitize the plugin deletion option.
     *
     * @param array $input contains settings input as array keys
     * @return array array of sanitized inputs
     */
    public function deletion_option_sanitize($input)
    {
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
    public function logfile_option_print_info()
    {
        print 'By checking this box, error and warning messages will be printed to a file accessible in <code>wp-content/wapdebug.log</code>.';
        print '<br>Note: log message timezone will be in UTC if timezone is not set in WordPress settings.';
    }

    /**
     * Renders the log file toggle checkbox.
     *
     * @return void
     */
    public function logfile_option_input()
    {
        $checked = Log::can_debug();
        ?>
<input type="checkbox" name="<?php echo esc_attr(Log::LOG_OPTION); ?>" class="wawp_class_logfile" value="checked"
    <?php echo $checked ? 'checked' : ''  ?>></input>
<?php
    }

    /**
     * Sanitize the logfile toggle input.
     *
     * @param string $input
     * @return string sanitized input
     */
    public function logfile_option_sanitize($input)
    {
        if (!wp_verify_nonce($_POST['wawp_logfile_flag_nonce_name'], 'wawp_logfile_flag_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the logfile option could not be verified.');
            return '';
        }

        update_option(Log::LOG_OPTION_UPDATED, 1);
        $valid = sanitize_text_field($input);
        // if input is empty, box is not checked, return empty string
        if (!$valid) {
            return 0;
        }
        return 1;
    }


    public function user_style_callback()
    {
        // get css file content
        $file_contents = file_get_contents(self::get_stylesheet_url());
        ?>
<textarea name="wawp_user_style[]" class="wawp_user_style_input"
    value="<?php echo esc_attr($file_contents)?>" /><?php echo esc_textarea($file_contents) ?></textarea><br>
<?php
    }

    public function user_style_sanitize($input)
    {
        if(!wp_verify_nonce($_POST['wawp_styles_nonce_name'], 'wawp_styles_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the restriction status could not be verified. Please try again.');
            return file_get_contents(self::get_stylesheet_url());
        }

        // write to file
        $sanitized_input = sanitize_textarea_field($input[0]);
        file_put_contents(self::get_stylesheet_url(), $sanitized_input);
        return $sanitized_input;
    }

    public function user_styles_print_section_info()
    {
        print 'Enter custom CSS for ' . esc_html(Addon::get_title(CORE_SLUG)) . ' elements here.';
    }



    public function login_title_callback()
    {
        $current = WA_Login::get_login_settings('title');
        ?>
<input class="wap-login-settings" id="login-title" name="wap_login_settings[title]" type="text"
    value="<?php echo esc_attr($current) ?>" <?php echo esc_html($current) ?> />
<?php
    }

    public function login_intro_callback()
    {
        $initial_content = WA_Login::LOGIN_DEFAULT_INTRO;
        $editor_id = 'login-intro';
        $editor_name = 'wap_login_settings[intro]';
        $editor_settings = array('textarea_name' => $editor_name, 'tinymce' => true);

        wp_editor($initial_content, $editor_id, $editor_settings);
    }

    public function login_submit_callback()
    {
        $current = WA_Login::get_login_settings('submit');
        ?>
<input class="wap-login-settings" id="login-submit" name="wap_login_settings[submit]" type="text"
    value="<?php echo esc_attr($current) ?>" <?php echo esc_html($current) ?> /> <?php
    }

    public function login_settings_sanitize($input)
    {
        if (!wp_verify_nonce($_POST['wawp_login_nonce_name'], 'wawp_login_nonce_action')) {
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            Log::wap_log_error('Your nonce for the WildApricot credentials could not be verified.');
            return empty_string_array($input);
        }

        if (array_key_exists('reset', $_POST)) {
            return array(
                'title' => WA_Login::LOGIN_DEFAULT_TITLE,
                'intro' => WA_Login::LOGIN_DEFAULT_INTRO,
                'submit' => WA_Login::LOGIN_DEFAULT_SUBMIT
            );
        }

        $input['title'] = sanitize_text_field($input['title']);
        $input['intro'] = sanitize_textarea_field($input['intro']);
        $input['submit'] = sanitize_text_field($input['submit']);

        return $input;

    }

    public function login_settings_print_info()
    {
        print 'Enter custom text for elements of the user login page here.';
    }



    // settings on the content restriction options tab
    /**
     * Register settings and add fields for the login button menu location.
     *
     * @return void
     */
    private function register_login_button_location()
    {
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
    private function register_status_restriction()
    {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'restriction_status_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_restriction_status_group', // group name for settings
            WA_Restricted_Posts::GLOBAL_RESTRICTED_STATUSES, // name of option to sanitize and save
            $register_args
        );
        // Add settings section and field for restriction status
        add_settings_section(
            'wawp_restriction_status_id', // ID
            'WildApricot Status Restriction', // title
            array($this, 'restriction_status_print_info'), // callback
            Settings_Controller::SETTINGS_URL // page
        );
        // Field for membership statuses
        add_settings_field(
            'wawp_restriction_status_field_id', // ID
            'Membership Status(es):', // title
            array($this, 'restriction_status_input_box'), // callback
            Settings_Controller::SETTINGS_URL, // page
            'wawp_restriction_status_id' // section
        );
    }

    /**
     * Register settings and add fields for the global restriction message.
     *
     * @return void
     */
    private function register_global_restriction_msg()
    {
        // Register setting
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'restriction_message_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_restriction_group', // group name for settings
            WA_Restricted_Posts::GLOBAL_RESTRICTION_MESSAGE, // name of option to sanitize and save
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

    private function register_cron_sync_option()
    {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'cron_freq_sanitize'),
            'default' => null
        );
        register_setting(
            'wawp_sync_group',
            WA_Integration::CRON_FREQUENCY_OPT,
            $register_args
        );
        add_settings_section(
            'wawp_cron_section',
            'WildApricot Data Sync',
            array($this, 'cron_freq_print_info'),
            'wawp-wal-admin-sync-freq'
        );
        add_settings_field(
            'wawp_cron_freq',
            'Sync Frequency',
            array($this, 'cron_freq_input'),
            'wawp-wal-admin-sync-freq',
            'wawp_cron_section'
        );
    }

    // settings on the sync options tab
    /**
     * Register settings and add fields for the custom fields.
     *
     * @return void
     */
    private function register_custom_fields()
    {
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
        add_settings_field(
            'wawp_admin_field_id',
            'Admin fields:',
            array($this, 'admin_fields_list'),
            'wawp-wal-admin&tab=fields',
            'wawp_fields_id'
        );
    }



    // settings on the plugin options tab
    /**
     * Register settings add fields for the delete all content option.
     *
     * @return void
     */
    private function register_deletion_option()
    {
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
    private function register_logfile_option()
    {
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

    private function register_user_style()
    {
        $register_args = array(
        'type' => 'string',
        'sanitize_callback' => array( $this, 'user_style_sanitize'),
        'default' => null
        );

        register_setting(
            self::STYLE_OPTION_GROUP,
            self::STYLE_OPTION_NAME,
            $register_args
        );

        add_settings_section(
            self::STYLE_SECTION,
            'Customize Look and Feel',
            array($this, 'user_styles_print_section_info'),
            self::STYLE_SUBMENU_PAGE
        );

        add_settings_field(
            self::STYLE_OPTION_NAME,
            'Custom CSS',
            array($this, 'user_style_callback'),
            self::STYLE_SUBMENU_PAGE,
            self::STYLE_SECTION
        );
    }

    private function register_login_page_settings()
    {
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'login_settings_sanitize'),
            'default' => null
            );

        register_setting(
            'wap_login_settings_group',
            WA_Login::LOGIN_SETTINGS,
            $register_args
        );

        add_settings_section(
            'wap_login_settings_section',
            'Customize Page Text',
            array($this, 'login_settings_print_info'),
            'login_settings_submenu'
        );

        add_settings_field(
            WA_Login::LOGIN_SETTINGS_TITLE,
            'Page Title',
            array($this, 'login_title_callback'),
            'login_settings_submenu',
            'wap_login_settings_section'
        );

        add_settings_field(
            WA_Login::LOGIN_SETTINGS_INTRO,
            'Introduction',
            array($this, 'login_intro_callback'),
            'login_settings_submenu',
            'wap_login_settings_section'
        );

        add_settings_field(
            WA_Login::LOGIN_SETTINGS_SUBMIT,
            'Submit button',
            array($this, 'login_submit_callback'),
            'login_settings_submenu',
            'wap_login_settings_section'
        );
    }

    /**
     * Render content for the content restriction tab.
     *
     * @return void
     */
    private function create_content_restriction_options_tab()
    {

        ?>
<!-- Menu Locations for Login/Logout button -->
<form method="post" action="options.php">
    <?php
            // Nonce for verification
            wp_nonce_field('wawp_menu_location_nonce_action', 'wawp_menu_location_nonce_name');
        // This prints out all hidden setting fields
        settings_fields(self::LOGIN_BUTTON_LOCATION_SECTION);
        do_settings_sections(self::LOGIN_BUTTON_LOCATION_PAGE);
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
        do_settings_sections(Settings_Controller::SETTINGS_URL);
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
        do_settings_sections('wawp-wal-admin-message');
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
    private function create_sync_options_tab()
    {
        ?>
<form method="post" action="options.php">
    <?php
        wp_nonce_field('wawp_sync_nonce_action', 'wawp_sync_nonce_name');
        // submit_button(__('Manually Sync WildApricot Data', 'newpath-wildapricot-press'), 'primary', 'sync', true);
        settings_fields('wawp_sync_group');
        do_settings_sections('wawp-wal-admin-sync-freq');
        submit_button();
        ?>
</form>
<form method="post" action="options.php">
    <?php
        // Nonce for verification
        wp_nonce_field('wawp_field_nonce_action', 'wawp_field_nonce_name');
        // This prints out all hidden setting fields
        settings_fields('wawp_fields_group');
        do_settings_sections('wawp-wal-admin&tab=fields');
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
    private function create_plugin_options_tab()
    {
        ?>
<form method="post" action="options.php">
    <?php
            // Nonce for verification
            wp_nonce_field('wawp_delete_nonce_action', 'wawp_delete_nonce_name');
        // This prints out all hidden setting fields
        settings_fields('wawp_delete_group');
        do_settings_sections('wawp-wal-admin&tab=plugin');
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

    private function create_login_settings_tab()
    {


        // create form w/ content

        ?>
<div class="wrap">
    <div class="wap-custom-css">
        <form method="post" action="options.php">
            <?php
        // Nonce for verification
        wp_nonce_field('wawp_styles_nonce_action', 'wawp_styles_nonce_name');
        // This prints out all hidden setting fields
        settings_fields(self::STYLE_OPTION_GROUP);
        do_settings_sections(self::STYLE_SUBMENU_PAGE);
        submit_button();
        ?>
        </form>
        <form method="post" action="options.php">
            <?php
        // Nonce for verification
        wp_nonce_field('wawp_login_nonce_action', 'wawp_login_nonce_name');
        // This prints out all hidden setting fields
        settings_fields('wap_login_settings_group');
        do_settings_sections('login_settings_submenu');
        submit_button(__('Save Changes', 'newpath-wildapricot-press'), 'primary', 'submit', false);
        submit_button(__('Reset to Defaults', 'newpath-wildapricot-press'), 'secondary', 'reset', false);
        ?>
        </form>
    </div>
</div><?php

    }


    private static function get_stylesheet_url()
    {
        return PLUGIN_PATH . self::CSS_FILE_PATH;
    }

}