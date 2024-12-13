<?php

namespace WAWP;

use DOMDocument;

require_once __DIR__ . '/settings/class-settings-controller.php';
require_once __DIR__ . '/class-wa-api.php';
require_once __DIR__ . '/class-wa-user.php';
require_once __DIR__ . '/class-restricted-posts.php';

class WA_Login
{
    /**
    * Stores menu IDs on which the WAP login/logout button will appear.
    * Controlled in admin settings.
    *
    * @var string
    */
    public const MENU_LOCATIONS_KEY 					= 'wawp_menu_location_name';
    /**
     * Stores the page ID of the WAP login page created by the plugin in the
     * options table.
     *
     * @var string
     */
    public const LOGIN_PAGE_ID_OPT						= 'wawp_wal_page_id';

    // TODO: add comments
    public const LOGIN_SETTINGS                         = 'wap_login_settings';

    public const LOGIN_SETTINGS_TITLE                   = 'title';
    public const LOGIN_SETTINGS_INTRO                   = 'intro';
    public const LOGIN_SETTINGS_SUBMIT                  = 'submit';

    public const LOGIN_DEFAULT_TITLE                    = 'Login with your WildApricot credentials';
    public const LOGIN_DEFAULT_INTRO                    = 'Log into your WildApricot account here to access content exclusive to WildApricot members!';
    public const LOGIN_DEFAULT_SUBMIT                   = 'Submit';

    public function __construct()
    {
        // Custom hook that runs after WildApricot credentials are saved
        add_action('wawp_wal_credentials_obtained', array($this, 'create_login_page'));

        // Fires before WordPress loads the page; creates user using login information and redirects to the previous page
        add_action('template_redirect', array($this, 'create_user_and_redirect'));

        // Filters the navigation menu(s)
        add_filter('wp_nav_menu_items', array($this, 'create_wa_login_logout'), 10, 2);

        // Shortcode for login form
        add_shortcode('wawp_custom_login_form', array($this, 'custom_login_form_shortcode'));

        // Filter query variables to redirectId to query vars array
        add_filter('query_vars', array($this, 'add_custom_query_vars'));
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

    public static function get_login_settings(?string $idx = '')
    {
        $login = get_option(self::LOGIN_SETTINGS);

        $default = array(
            'title' => self::LOGIN_DEFAULT_TITLE,
            'intro' => self::LOGIN_DEFAULT_INTRO,
            'submit' => self::LOGIN_DEFAULT_SUBMIT
        );

        if (!$idx && !$login) {
            // return whole array and login option is not set yet --> default array
            return $default;
        } elseif ($idx && !$login) {
            return $default[$idx];
        } elseif ($idx && $login && (!array_key_exists($idx, $login) || empty($login[$idx]))) {
            // return option for idx but index option is not set --> default idx
            return $default[$idx];
        }

        // return login option for idx
        return $login[$idx];

    }

    /**
     * Creates user-facing WildApricot login page. Runs when both API key
     * and license key are found to be valid.
     *
     * @see https://stackoverflow.com/questions/32314278/how-to-create-a-new-wordpress-page-programmatically
     * @see https://stackoverflow.com/questions/13848052/create-a-new-page-with-wp-insert-post
     * @return void
     */
    public function create_login_page()
    {
        WA_Integration::schedule_cron_jobs();

        $login_title = self::get_login_settings('title');
        $login_content = '[wawp_custom_login_form]';

        $post_details = array(
            'post_title' => $login_title,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => $login_content, // shortcode
            'post_name' => 'wawp-wild-apricot-login'
        );

        // Check if Login page exists first
        $login_page_id = get_option(self::LOGIN_PAGE_ID_OPT);
        if (isset($login_page_id) && $login_page_id != '') { // Login page already exists
            $login_page = get_post($login_page_id, 'ARRAY_A');
            // restore the login content and title
            $login_page['post_title'] = $login_title;
            $login_page['post_content'] = $login_content;
            $login_page['post_status'] = 'publish';
            wp_update_post($login_page);
            // Add user roles
            $saved_wa_roles = get_option(WA_Integration::WA_ALL_MEMBERSHIPS_KEY);
            // Loop through roles and add them as roles to WordPress
            if (!empty($saved_wa_roles)) {
                foreach ($saved_wa_roles as $role) {
                    add_role('wawp_' . str_replace(' ', '', $role), $role);
                }
            }
        } else {
            // insert the post
            $page_id = wp_insert_post($post_details, false);
            // Add page id to options so that it can be removed on deactivation
            update_option(self::LOGIN_PAGE_ID_OPT, $page_id);
        }
        // Remove new login page from menu
        // https://wordpress.stackexchange.com/questions/86868/remove-a-menu-item-in-menu
        // https://stackoverflow.com/questions/52511534/wordpress-wp-insert-post-adds-page-to-the-menu
        $page_id = get_option(self::LOGIN_PAGE_ID_OPT);
        $menu_item_ids = wp_get_associated_nav_menu_items($page_id, 'post_type');
        // Loop through ids and remove
        foreach ($menu_item_ids as $menu_item_id) {
            wp_delete_post($menu_item_id, true);
        }
    }

    /**
     * Creates the shortcode that holds the login form.
     *
     * @return string Holds the HTML content of the form
     */
    public function custom_login_form_shortcode()
    {
        // Get WildApricot URL
        $wild_apricot_url = get_option(WA_Integration::WA_URL_KEY);
        try {
            $dataEncryption = new Data_Encryption();
            $wild_apricot_url =	esc_url($dataEncryption->decrypt($wild_apricot_url));
        } catch (Decryption_Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
            return Exception::get_user_facing_error_message();
        }

        ob_start();
        // if WA user is not logged in, display login form
        if (!WA_User::is_wa_user_logged_in()) {
            // Create page content -> login form
            $login_settings = self::get_login_settings();
            ?>
<div id="wawp_login-wrap">
    <p id="wawp_wa_login_direction">
        <?php echo esc_html($login_settings['intro']) ?>
    </p>
    <form method="post" action="">
        <?php wp_nonce_field("wawp_login_nonce_action", "wawp_login_nonce_name");?>
        <label for="wawp_login_email" style="margin-left: 0px;">Email:</label>
        <br><input type="text" id="wawp_login_email" style="width: 15em; margin-left: 0px;" name="wawp_login_email"
            placeholder="example@website.com">
        <br><label for="wawp_login_password" style=" margin-left: 0px;">Password:</label>
        <br><input type="password" id="wawp_login_password" name="wawp_login_password" placeholder="***********"
            autocomplete="new-password" style="width: 15em; margin-left: 0px;">
        <!-- Remember Me -->
        <div id="wawp_remember_me_div" style="margin-left: 0px;">
            <br><label id="wawp_remember_me_label" for="wawp_remember_me">Remember me?</label>
            <input type="checkbox" id="wawp_remember_me" name="wawp_remember_me" checked>

            <!-- Forgot password -->
            <br><label id="wawp_forgot_password"><a
                    href="<?php echo esc_url($wild_apricot_url . '/Sys/ResetPasswordRequest'); ?>" target="_blank"
                    rel="noopener noreferrer">Forgot Password?</a></label>

            <br><input type="submit" id="wawp_login_submit" name="wawp_login_submit" value="Submit"
                <?php echo esc_html($login_settings['submit']) ?> />
        </div>
    </form>
</div><?php
        } else {
            // display you are already logged in message and give option to logout
            $logout_link = wp_logout_url(esc_url(site_url()));
            ?>
<div id="wawp_login-wrap">
    <p>You are already logged in to your WildApricot account.</p>
    <p><a href="<?php echo esc_url($logout_link);?>">Log Out</a></p>
</div><?php
        }


        return ob_get_clean();
    }

    /**
     * Create login and logout buttons in the menu
     *
     * @param  string $items  HTML of menu items
     * @param  object  $args   Arguments supplied to the filter
     * @return string $items  The updated items with the login/logout button
     * @see https://developer.wordpress.org/reference/functions/wp_create_nav_menu/
     * @see https://www.wpbeginner.com/wp-themes/how-to-add-custom-items-to-specific-wordpress-menus/
     */
    public function create_wa_login_logout($items, $args)
    {
        // First, check if WildApricot credentials and the license is valid
        if (Addon::is_plugin_disabled()) {
            return $items;
        }
        $menu_item_class = '';
        // Check the restrictions of each item in header IF the header is not blank
        if (!empty($items)) {
            // Get navigation items as WP_Post objects
            $args_menu = $args->menu;
            $nav_items = wp_get_nav_menu_items($args_menu);

            // Get li tags from menu
            $items = mb_convert_encoding($items, 'HTML-ENTITIES', 'UTF-8');
            ;
            $doc_items = new DOMDocument('1.0', 'utf-8');
            libxml_use_internal_errors(true);
            $doc_items->loadHTML($items, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD); // DOMDocument
            libxml_clear_errors();
            $li_tags = $doc_items->getElementsByTagName('li'); // DOMNodeList

            $returned_html = '';
            // Loop through each nav item, get the ID, and check if the page is restricted
            if (!empty($nav_items)) {
                $nav_item_number = 0; // used for keeping track of which navigation item we are looking at
                foreach ($nav_items as $nav_item) {
                    $user_can_see = true;
                    // Get post id
                    $nav_item_id = $nav_item->object_id;
                    // Check if this post is restricted
                    $nav_item_is_restricted = get_post_meta($nav_item_id, WA_Restricted_Posts::IS_POST_RESTRICTED);
                    // If post is restricted, then check if the current has access to it
                    if (!empty($nav_item_is_restricted) && $nav_item_is_restricted[0]) {
                        if (is_user_logged_in()) { // user is logged in
                            // Check that user is synced with WildApricot
                            $current_users_id = get_current_user_id();
                            $users_wa_id = get_user_meta($current_users_id, WA_User::WA_USER_ID_KEY);
                            // Check if user ID actually exists
                            if (!empty($users_wa_id) && $users_wa_id != '') { // User has been synced with WildApricot
                                // Check if user's status is within the allowed status(es)
                                $users_status = get_user_meta($current_users_id, WA_User::WA_USER_STATUS_KEY);
                                $users_status = $users_status[0];
                                $allowed_statuses = get_option(WA_Restricted_Posts::GLOBAL_RESTRICTED_STATUSES);
                                // If some statuses have been checked off, then that means that some statuses are restricted
                                $valid_status = true;
                                if (!empty($allowed_statuses) && !empty($users_status)) {
                                    // check if user status is contained in the allowed statuses
                                    if (!in_array($users_status, $allowed_statuses)) {
                                        // user cannot see this restricted post because their status is not allowed to see restricted posts
                                        $valid_status = false;
                                    }
                                }
                                // Now, check if the current user is allowed to see this page
                                // Get user's groups and level
                                $users_member_groups = get_user_meta($current_users_id, WA_User::WA_MEMBER_GROUPS_KEY);
                                $users_member_groups = maybe_unserialize($users_member_groups[0]);
                                $user_member_level = get_user_meta($current_users_id, WA_User::WA_MEMBERSHIP_LEVEL_ID_KEY);
                                $user_member_level = $user_member_level[0];
                                $wa_post_meta = WA_Restricted_Posts::get_wa_post_meta($nav_item_id);
                                // Get page's groups and level
                                $page_member_groups = $wa_post_meta[WA_Restricted_Posts::RESTRICTED_GROUPS];
                                $page_member_levels = $wa_post_meta[WA_Restricted_Posts::RESTRICTED_LEVELS];
                                // Check if user's groups/level overlap with the page's groups/level
                                if (!is_array($users_member_groups)) {
                                    $users_member_groups = array($users_member_groups);
                                }
                                if (!is_array($user_member_level)) {
                                    $user_member_level = array($user_member_level);
                                }

                                // $page_member_groups contains an array of group ids
                                // $page_member_groups contains an array of group names, with the group ids as the keys
                                $intersect_groups = (!empty($page_member_groups) && array_intersect_key(array_flip($page_member_groups), $users_member_groups));
                                $intersect_level = (!empty($page_member_levels) && in_array($user_member_level[0], $page_member_levels));

                                if (!$intersect_groups && !$intersect_level || !$valid_status) { // the user can't see this page!
                                    // Remove this element from the menu
                                    $user_can_see = false;
                                }
                            } else {
                                // User has not been synced with WildApricot; they therefore cannot see this in the menu
                                $user_can_see = false;
                            }
                        } else {
                            // User is not logged in; page should definitely not be shown in menu
                            $user_can_see = false;
                        }
                    }

                    // Get associated HTML tag for this menu
                    $associated_html = $li_tags->item($nav_item_number);

                    // get menu item class
                    $item_class_name = $associated_html->className;
                    // set class variable ONLY if it hasn't been set yet AND the menu item isn't the currently viewed page (adds extra classes)
                    if (empty($menu_item_class) && !str_contains($item_class_name, 'current-menu-item')) {
                        $item_id = $associated_html->id;

                        // if menu item class contains item-specific id, remove it
                        if (!empty($item_id) && str_contains($item_class_name, $item_id)) {
                            $menu_item_class = str_replace($item_id, '', $item_class_name);
                        } else {
                            $menu_item_class = $item_class_name;
                        }
                    }
                    // Add or remove hidden style
                    if ($user_can_see) {
                        $associated_html->removeAttribute('style');
                    } else {
                        $associated_html->setAttribute('style', 'display: none;');
                    }

                    // Increment navigation item number
                    $nav_item_number++;
                }
            }
            // Get html to return
            $returned_html .= $doc_items->saveHTML();
            $items = $returned_html;
        }

        // https://wp-mix.com/wordpress-difference-between-home_url-site_url/
        // Get current page id
        // https://wordpress.stackexchange.com/questions/161711/how-to-get-current-page-id-outside-the-loop
        $current_page_id = get_queried_object_id();
        // Get login url
        $login_url = $this->get_login_link();
        $logout_url = wp_logout_url(esc_url(get_permalink($current_page_id)));
        // Check if user is logged in or logged out, now an array
        $selected_login_button_locations = get_login_menu_location();
        // $selected_login_button_locations = get_option(WA_Integration::MENU_LOCATIONS_KEY);


        if (empty($selected_login_button_locations)) {
            return $items;
        }

        foreach ($selected_login_button_locations as $menu) {
            if ($args->menu->term_id != $menu) {
                continue;
            }
            if (is_user_logged_in()) {
                // Logout
                $url = $logout_url;
                $button_text = 'Log Out';
            } elseif (!is_user_logged_in()) {
                // Login
                $url = $login_url;
                $button_text = 'Log In';
            }
            $items .= '<li id="wawp_login_logout_button" class="' . esc_html($menu_item_class) . '"><a href="'. esc_url($url) .'">' . esc_html($button_text) . '</a></li>';
        }

        return $items;
    }

    /**
     * Handles the redirect after the user is logged in.
     *
     * @return void
     */
    public function create_user_and_redirect()
    {
        // Check that we are on the login page
        $login_page_id = get_option(self::LOGIN_PAGE_ID_OPT);
        if (!is_page($login_page_id)) {
            return;
        }

        // Get id of last page from url
        // https://stackoverflow.com/questions/13652605/extracting-a-parameter-from-a-url-in-wordpress
        if (empty($_POST['wawp_login_submit'])) {
            return;
        }

        // Check that nonce is valid
        if (!wp_verify_nonce(wp_unslash($_POST['wawp_login_nonce_name']), 'wawp_login_nonce_action')) {
            // Redirect
            add_filter('the_content', array($this, 'add_login_error'));
            return;
        }

        // Create array to hold the valid input
        $valid_login = array();

        // Check email form
        $email_input = sanitize_text_field(wp_unslash($_POST['wawp_login_email']));
        if (!empty($email_input) && is_email($email_input)) { // email is well-formed
            // Sanitize email
            $valid_login['email'] = sanitize_email($email_input);
        } else { // email is NOT well-formed
            // Output error
            add_filter('the_content', array($this, 'add_login_error'));
            // DEBUG LOG
            return;
        }

        // Check password form
        // WildApricot password requirements: https://gethelp.wildapricot.com/en/articles/22-passwords
        // Any combination of letters, numbers, and characters (except spaces)
        $password_input = sanitize_text_field($_POST['wawp_login_password']);
        // https://stackoverflow.com/questions/1384965/how-do-i-use-preg-match-to-test-for-spaces
        if (!empty($password_input) && !preg_match("/\\s/", $password_input)) { // not empty and there are NOT spaces
            // Sanitize password
            $valid_login['password'] = sanitize_text_field($password_input);
        } else { // password is NOT valid
            // Output error
            add_filter('the_content', array($this, 'add_login_error'));
            return;
        }

        // Sanitize 'Remember Me?' checkbox
        $remember_user = false;
        if (array_key_exists('wawp_remember_me', $_POST)) {
            $remember_me_input = sanitize_text_field(wp_unslash($_POST['wawp_remember_me']));

            if ($remember_me_input == 'on') { // should remember user
                $remember_user = true;
            }
        }


        // Check if login is valid and add the user to wp database if it is
        try {
            $verified_data = WA_API::verify_valid_access_token();
            $admin_access_token = $verified_data['access_token'];
            $admin_account_id = $verified_data['wa_account_id'];
            $wawp_api = new WA_API($admin_access_token, $admin_account_id);
            $login_attempt = $wawp_api->login_email_password($valid_login);
            if (!$login_attempt) {
                add_filter('the_content', array($this, 'add_login_error'));
                return;
            }
            WA_User::add_user_to_wp_database($login_attempt, $valid_login['email'], $remember_user);
        } catch (Exception $e) {
            Log::wap_log_error($e->getMessage(), true);
            add_filter('the_content', array($this, 'add_login_server_error'));
            return;
        }

        // If we are here, then it means that we have not come across any errors, and the login is successful!

        // Redirect user to previous page, or home page if there is no previous page
        $last_page_id = get_query_var('redirectId', false);
        $redirect_code_exists = false;
        if ($last_page_id != false) { // get id of last page
            $redirect_code_exists = true;
        }
        // Redirect user to page they were previously on
        // https://wordpress.stackexchange.com/questions/179934/how-to-redirect-on-particular-page-in-wordpress/179939
        $redirect_after_login_url = '';
        if ($redirect_code_exists) {
            $redirect_after_login_url = esc_url(get_permalink($last_page_id));
        } else { // no redirect id; redirect to home page
            $redirect_after_login_url = esc_url(site_url());
        }
        wp_safe_redirect($redirect_after_login_url);
        exit();
    }


    /**
     * Adds incorrect login error to login shortcode page
     *
     * @param string $content Holds the existing content on the page
     * @return string $content Holds the new content on the page
     */
    public function add_login_error($content)
    {
        // Only run on wa4wp page
        $login_page_id = get_option(self::LOGIN_PAGE_ID_OPT);
        if (is_page($login_page_id)) {
            return $content . '<p style="color:red;">Invalid credentials! Please check that you have entered the correct email and password.
			If you are sure that you entered the correct email and password, please contact your administrator.</p>';
        }
        return $content;
    }

    /**
     * Adds fatal API error to login shortcode page
     *
     * @param  string $content Holds the existing content on the page
     * @return string $content Holds the new content with the error on the page
     */
    public function add_login_server_error($content)
    {
        // Only run on wa4wp page
        $login_page_id = get_option(self::LOGIN_PAGE_ID_OPT);
        if (is_page($login_page_id)) {
            // return Exception::get_user_facing_error_message();
            return "<div style='color:red;'><h3>Login Failed</h3><p>WildApricot Press has encountered an error and could not complete your request.</p></div>";
        }
        return $content;
    }

    /**
     * Generates the URL to the WAWP Login page on the website
     *
     * @return string
     */
    public static function get_login_link()
    {
        $login_url = esc_url(site_url() . '/index.php?pagename=wawp-wild-apricot-login');
        // Get current page id
        // https://wordpress.stackexchange.com/questions/161711/how-to-get-current-page-id-outside-the-loop
        $current_page_id = get_queried_object_id();
        $login_url = esc_url(add_query_arg(array(
            'redirectId' => $current_page_id,
        ), $login_url));
        // Return login url
        return $login_url;
    }

}
?>