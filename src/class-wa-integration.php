<?php
namespace WAWP;

// For iterating through menu HTML
use DOMDocument;

require_once __DIR__ . '/class-addon.php';
require_once __DIR__ . '/class-data-encryption.php';
require_once __DIR__ . '/class-log.php';
require_once __DIR__ . '/class-wa-api.php';
require_once __DIR__ . '/helpers.php';

/**
 * Class for managing WildApricot user accounts and post restriction.
 * 
 * @since 1.0b1
 * @author Spencer Gable-Cook and Natalie Brotherton
 * @copyright 2022 NewPath Consulting
 */
class WA_Integration {
	// Keys for data stored in WP databases.

	// TODO: move constants to separate WA_Integration_Constants class
	/**
	 * Stores encrypted WA authorization credentials.
	 * 
	 * @var string
	 */
	const WA_CREDENTIALS_KEY 					= 'wawp_wal_name';

	/**
	 * Key in `WA_CREDENTIALS_KEY` option storing the encrypted API key.
	 * 
	 * @var string
	 */
	const WA_API_KEY_OPT 						= 'wawp_wal_api_key';

	/**
	 * Key in `WA_CREDENTIALS_KEY` option storing the encrypted client ID.
	 * 
	 * @var string
	 */
	const WA_CLIENT_ID_OPT 						= 'wawp_wal_client_id';

	/**
	 * Key in `WA_CREDENTIALS_KEY` option storing the encrypted client secret.
	 * 
	 * @var string
	 */
	const WA_CLIENT_SECRET_OPT 					= 'wawp_wal_client_secret';

	/**
	 * Stores the total number of Wild Apricot contacts.
	 */
	const WA_CONTACTS_COUNT_KEY					= 'wawp_contacts_count';

	/**
	 * Stores user's WA user ID in the user meta data.
	 * 
	 * @var string
	 */
	const WA_USER_ID_KEY 						= 'wawp_wa_user_id';

	/**
	 * Stores user's WA membership level(s) in the user meta data.
	 * 
	 * @var string
	 */
	const WA_MEMBERSHIP_LEVEL_KEY 				= 'wawp_membership_level_key';

	/**
	 * Stores user's WA membership ID(s) in the user meta data.
	 * 
	 * @var string
	 */
	const WA_MEMBERSHIP_LEVEL_ID_KEY			= 'wawp_membership_level_id_key';

	/**
	 * Stores user's WA status in the user meta data.
	 * 
	 * @var string
	 */
	const WA_USER_STATUS_KEY 					= 'wawp_user_status_key';

	/**
	 * Stores user's WA organization(s) in the user meta data.
	 * 
	 * @var string
	 */
	const WA_ORGANIZATION_KEY 					= 'wawp_organization_key';

	/**
	 * Stores user's WA group(s) in the user meta data.
	 * 
	 * @var string
	 */
	const WA_MEMBER_GROUPS_KEY 					= 'wawp_list_of_groups_key';

	/**
	 * Stores all existing membership levels in the linked WA admin account
	 * in the options table.
	 * 
	 * @var string
	 */
	const WA_ALL_MEMBERSHIPS_KEY 				= 'wawp_all_levels_key';

	/**
	 * Stores all existing membership groups in the linked WA admin account
	 * in the options table.
	 * 
	 * @var string
	 */
	const WA_ALL_GROUPS_KEY						= 'wawp_all_groups_key';

	/**
	 * Stores restricted groups in the post meta data.
	 * 
	 * @var string
	 */
	const RESTRICTED_GROUPS 					= 'wawp_restricted_groups';

	/**
	 * Stores restricted levels in the post meta data.
	 * 
	 * @var string
	 */
	const RESTRICTED_LEVELS 					= 'wawp_restricted_levels';

	/**
	 * Stores whether the post is restricted or not in the post meta data.
	 * 
	 * @var string
	 */
	const IS_POST_RESTRICTED 					= 'wawp_is_post_restricted';

	/**
	 * Stores custom restriction message in post meta data.
	 * 
	 * @var string
	 */
	const INDIVIDUAL_RESTRICTION_MESSAGE_KEY	= 'wawp_individual_restriction_message_key';

	/**
	 * Stores array of all restricted posts in the options table. Used for
	 * deleting custom post metadata upon plugin deletion.
	 * 
	 * @var string
	 */
	const ARRAY_OF_RESTRICTED_POSTS 			= 'wawp_array_of_restricted_posts';

	/**
	 * Stores global restriction message in options table.
	 * 
	 * @var string
	 */
	const GLOBAL_RESTRICTION_MESSAGE			= 'wawp_global_restriction_message';

	/**
	 * Stores WA statuses for which posts are not restricted. 
	 * Controlled in the admin settings.
	 * 
	 * @var string
	 */
	const GLOBAL_RESTRICTED_STATUSES					= 'wawp_restriction_status_name';

	/**
	 * Stores transient for the WA admin account ID. Deleted after 30 minutes.
	 * 
	 * @var string
	 */
	const ADMIN_ACCOUNT_ID_TRANSIENT 			= 'wawp_admin_account_id';

	/**
	 * Stores transient for the encrypted WA admin access token.
	 * Deleted after 30 minutes.
	 * 
	 * @var string
	 */
	const ADMIN_ACCESS_TOKEN_TRANSIENT 			= 'wawp_admin_access_token';

	/**
	 * Stores transient for the encrypted WA admin refresh token.
	 * Deleted after 30 minutes.
	 * 
	 * @var string
	 */
	const ADMIN_REFRESH_TOKEN_OPTION 			= 'wawp_admin_refresh_token';

	/**
	 * Stores all WA fields. Displayed in admin settings.
	 * 
	 * @var string
	 */
	const LIST_OF_CUSTOM_FIELDS 				= 'wawp_list_of_custom_fields';

	/**
	 * Stores WA fields selected by user to sync with WordPress. Controlled in
	 * admin settings.
	 * 
	 * @var string
	 */
	const LIST_OF_CHECKED_FIELDS 				= 'wawp_fields_name';

	/**
	 * Stores whether the user is a WA user added by the plugin in the user meta 
	 * data. Called when a user logs in with the WAP login shortcode.
	 * 
	 * @var string
	 */
	const USER_ADDED_BY_PLUGIN 					= 'wawp_user_added_by_plugin';

	/**
	 * Stores menu IDs on which the WAP login/logout button will appear. 
	 * Controlled in admin settings.
	 * 
	 * @var string
	 */
	const MENU_LOCATIONS_KEY 					= 'wawp_menu_location_name';

	/**
	 * Stores the encrypted WA URL to which the WP site is connected to. 
	 * Corresponds to the WA authorization credentials.
	 * 
	 * @var string
	 */
	const WA_URL_KEY 							= 'wawp_wa_url_key';

	/**
	 * Stores the flag indicating whether all WildApricot information should be 
	 * deleted when the plugin is deleted. Controlled in admin settings.
	 * 
	 * @var string
	 */
	const WA_DELETE_OPTION 						= 'wawp_delete_setting';

	/**
	 * Stores the page ID of the WAP login page created by the plugin in the
	 * options table.
	 *
	 * @var string
	 */
	const LOGIN_PAGE_ID_OPT						= 'wawp_wal_page_id';
	
	// Custom hook names
	/**
	 * User data refresh hook. Scheduled to run daily.
	 * 
	 * @var string
	 */
	const USER_REFRESH_HOOK 					= 'wawp_cron_refresh_user_hook';

	/**
	 * License data refresh hook. Scheduled to run daily.
	 * 
	 * @var string
	 */
	const LICENSE_CHECK_HOOK 					= 'wawp_cron_refresh_license_check';

	/**
	 * Constructs an instance of the WA_Integration class.
	 *
	 * Adds the actions and filters required.
	 *
	 * @return WA_Integration new WA_Integration instance
	 */
	public function __construct() {
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

		// Custon action for restricting access to login page
		add_action('remove_wa_integration', array($this, 'remove_wild_apricot_integration'));

		// Fires when displaying or editing user profile, adds WA user data
		add_action('show_user_profile', array($this, 'show_membership_level_on_profile'));
		add_action('edit_user_profile', array($this, 'show_membership_level_on_profile'));

		// Fires on the add meta boxes hook, adds custom WAP meta boxes
		add_action('add_meta_boxes', array($this, 'post_access_add_post_meta_boxes'));

		// Fires when post is saved, processes custom post metadata
		add_action('save_post', array($this, 'post_access_load_restrictions'), 10, 2);

		// Fires when post is loaded, restricts post content based on custom meta
		add_filter('the_content', array($this, 'restrict_post_wa'));

		// Action for user refresh cron hook
		add_action(self::USER_REFRESH_HOOK, array($this, 'refresh_user_wa_info'));

		// Action for hiding admin bar for non-admin users, fires after the theme is loaded
		add_action('after_setup_theme', array($this, 'hide_admin_bar'));

		// Fires on every page, checks credentials and disables plugin if necessary
		add_action('init', array($this, 'check_updated_credentials'));

		// Action for Cron job that refreshes the license check
		add_action(self::LICENSE_CHECK_HOOK, 'WAWP\Addon::update_licenses');

		// Fires when access to the admin page is denied, displays message prompting user to log out of their WA account
		add_action('admin_page_access_denied', array($this, 'tell_user_to_logout'));
	}

	/**
	 * Deletes access token and account ID transients.
	 * 
	 * @return void
	 */
	public static function delete_transients() {
		delete_transient(self::ADMIN_ACCESS_TOKEN_TRANSIENT);
		delete_transient(self::ADMIN_ACCOUNT_ID_TRANSIENT);
	}

	/**
	 * Checks for valid WildApricot credentials.
	 * 
	 * @return bool true if valid authorization creds, false if not
	 */
	public static function valid_wa_credentials() {
		$wa_credentials = get_option(self::WA_CREDENTIALS_KEY);

		// wa_credentials will be false if the option doesn't exist
		// return here so we don't get invalid index in the lines below
		if (!$wa_credentials || empty($wa_credentials)) return false;

		$api_key = $wa_credentials[self::WA_API_KEY_OPT];
		$client_id = $wa_credentials[self::WA_CLIENT_ID_OPT];
		$client_secret = $wa_credentials[self::WA_CLIENT_SECRET_OPT];

		// check first that creds exist
		return !empty($api_key) && !empty($client_id) && !empty($client_secret);
	}

	/**
	 * Checks that updated WildApricot credentials match the registered site on the license key and that the credentials are still valid.
	 * 
	 * @return void
	 */
	public function check_updated_credentials() {
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
			$has_valid_wa_credentials) 
		{
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
			!$has_valid_wa_credentials)
		{
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
	public static function check_licensed_wa_urls_ids($response) {
		$licensed_wa_urls = self::get_licensed_wa_urls($response);
		$licensed_wa_ids = self::get_licensed_wa_ids($response);
		if (is_null($licensed_wa_urls) || is_null($licensed_wa_ids)) return false;

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
		if (in_array($wild_apricot_info['Id'], $licensed_wa_ids) && in_array($wild_apricot_info['Url'], $licensed_wa_urls)) { 
			return true;
		}
		
		return false;

	}

	/**
	 * Hides the WordPress admin bar for non-admin users
	 * 
	 * @return void
	 */
	public function hide_admin_bar() {
		if (!current_user_can('administrator') && !is_admin()) {
			show_admin_bar(false);
		}
	}

	/**
	 * Tell user to logout of WildApricot if they are trying to access the admin menu
	 * 
	 * @return void
	 */
	public function tell_user_to_logout() {
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
	public function add_custom_query_vars($vars) {
		// Add redirectId to query vars
		$vars[] = 'redirectId';
		return $vars;
	}

	/**
     * Creates a daily CRON job to check that the license matches
	 * 
	 * @return void
     */
    public static function setup_license_check_cron() {
        $license_hook_name = self::LICENSE_CHECK_HOOK;
        if (!wp_next_scheduled($license_hook_name)) {
            wp_schedule_event(time(), 'daily', $license_hook_name);
        }
    }

	/**
	 * Creates user-facing WildApricot login page. Runs when both API key
	 * and license key are found to be valid.
	 *
	 * @see https://stackoverflow.com/questions/32314278/how-to-create-a-new-wordpress-page-programmatically
	 * @see https://stackoverflow.com/questions/13848052/create-a-new-page-with-wp-insert-post
	 * @return void
	 */
	public function create_login_page() {
		// Run action to create user refresh CRON event
		self::create_cron_for_user_refresh();
		// Create event for checking license
		self::setup_license_check_cron();
		// schedule cron update for updating the membership levels and groups
		Settings::setup_cron_job();

		$login_title = 'Login with your WildApricot credentials';
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
			$saved_wa_roles = get_option(self::WA_ALL_MEMBERSHIPS_KEY);
			// Loop through roles and add them as roles to WordPress
			if (!empty($saved_wa_roles)) {
				foreach ($saved_wa_roles as $role) {
					add_role('wawp_' . str_replace(' ', '', $role), $role);
				}
			}
		} else { 
			// insert the post
			$page_id = wp_insert_post($post_details, FALSE);
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
	 * Replaces login form with the appropriate error message.
	 * 
	 * @return void
	 */
	public function remove_wild_apricot_integration() {

		// then change content
		$content = '';
		if (Exception::fatal_error()) {
			$content = Exception::get_user_facing_error_message();
		} else if (Addon::is_plugin_disabled()) {
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
	 * Adds incorrect login error to login shortcode page
	 *
	 * @param string $content Holds the existing content on the page
	 * @return string $content Holds the new content on the page
	 */
	public function add_login_error($content) {
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
	public function add_login_server_error($content) {
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
	private function get_login_link() {
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

	/**
	 * Determines whether or not to restrict the post to the current user based
	 * on the user's levels/groups and the post's list of restricted levels/groups
	 *
	 * @param string $post_content holds the post content in HTML form
	 * @return string $post_content is the new post content. If the plugin is
	 * disabled or experiencing a fatal error, content will reflect that and
	 * display the appropriate message.
	 */
	public function restrict_post_wa($post_content) {
		// TODO: fix restriction message appearing in header and footer
		// Get ID of current post
		$current_post_ID = get_queried_object_id();
		
		// Check that this current post is restricted
		$is_post_restricted = get_post_meta($current_post_ID, self::IS_POST_RESTRICTED, true);
		if (!$is_post_restricted) return $post_content;

		if (Exception::fatal_error()) {
			// if there is an exception, display exception error
			return Exception::get_user_facing_error_message();
		} else if (Addon::is_plugin_disabled()) {
			// if plugin is disabled, display error message
			$message = "<div class='wawp-disabled'>
			<p>WildApricot Press is currently disabled. Please contact your site administrator.</p></div>";
			return $message;
		}

		// If post is not singular or it's the login page, don't restrict
		if (!is_singular() || is_user_login_page()) {
			return $post_content;
		}
		
		// Load in restriction message from message set by user
		$restriction_message = wpautop(get_option(self::GLOBAL_RESTRICTION_MESSAGE));
		// Check if current post has a custom restriction message
		$individual_restriction_message = wpautop(get_post_meta($current_post_ID, self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, true));
		if (!empty($individual_restriction_message)) { 
			$restriction_message = $individual_restriction_message;
		}
		
		// Append 'Log In' button and the styling div to the restriction message
		$login_url = $this->get_login_link();
		$restriction_message = '<div class="wawp_restriction_content_div">' . wp_kses_post($restriction_message);

		// Automatically restrict the post if user is not logged in
		if (!is_user_logged_in()) {
			$restriction_message .= '<a id="wawp_restriction_login_button" href="'. esc_url($login_url) .'">Log In</a>';
			$restriction_message .= '</div>';
			return $restriction_message;
		}
		
		// Show a warning/notice on the restriction page if the user is logged into WordPress but is not synced with WildApricot
		// Get user's WildApricot ID -> if it does not exist, then the user is not synced with WildApricot
		if (!self::is_wa_user_logged_in()) {
			// Present notice that user is not synced with WildApricot
			$restriction_message .= '<p style="color:red;">Please note that while you are logged into WordPress, you have not synced your account with WildApricot. ';
			$restriction_message .= 'Please <a href="'. esc_url($login_url) .'">Log In</a> into your WildApricot account to sync your data to your WordPress site.</p>';
			$restriction_message .= '</div>';
			return $restriction_message;
		}
		$restriction_message .= '</div>';

		// Get post meta data
		// Get post's restricted groups
		$post_restricted_groups = get_post_meta($current_post_ID, self::RESTRICTED_GROUPS);
		// Unserialize
		$post_restricted_groups = maybe_unserialize($post_restricted_groups[0]);
		// Get post's restricted levels
		$post_restricted_levels = get_post_meta($current_post_ID, self::RESTRICTED_LEVELS);
		// Unserialize
		$post_restricted_levels = maybe_unserialize($post_restricted_levels[0]);

		// If no options are selected, then the post is unrestricted, as there cannot be a post with no viewers
		if (empty($post_restricted_groups) && empty($post_restricted_levels)) {
			update_post_meta($current_post_ID, self::IS_POST_RESTRICTED, false);
			return $post_content;
		}

		$current_user_ID = wp_get_current_user()->ID;

		// Get user meta data
		$user_groups = get_user_meta($current_user_ID, self::WA_MEMBER_GROUPS_KEY);
		$user_level = get_user_meta($current_user_ID, self::WA_MEMBERSHIP_LEVEL_ID_KEY, true);
		$user_status = get_user_meta($current_user_ID, self::WA_USER_STATUS_KEY, true);

		// Check if user's status is allowed to view restricted posts
		// Get restricted status(es) from options table
		$restricted_statuses = get_option(self::GLOBAL_RESTRICTED_STATUSES);
		// If there are restricted statuses, then we must check them against the user's status
		if (!empty($restricted_statuses)) {
			// If user's status is not in the restricted statuses, then the user cannot see the post
			if (!in_array($user_status, $restricted_statuses)) {
				// User cannot access the post
				return $restriction_message;
			}
		}

		// Find common groups between the user and the post's restrictions
		// If user_groups is null, then the user is not part of any groups
		$common_groups = array();
		if (!empty($user_groups) && !empty($post_restricted_groups)) {
			$user_groups = maybe_unserialize($user_groups[0]);
			// Get keys of each group
			$user_groups = array_keys($user_groups);

			// Check if post groups and user groups overlap
			$common_groups = array_intersect($user_groups, $post_restricted_groups); // not empty if one or more of the user's groups are within the post's restricted groups
		}

		// Find common levels between the user and the post's restrictions
		$common_level = false;
		if (!empty($post_restricted_levels) && !empty($user_level)) {
			$common_level = in_array($user_level, $post_restricted_levels); // true if the user's level is one of the post's restricted levels
		}

		// Determine if post should be restricted
		if (empty($common_groups) && !$common_level) {
			// Page should be restricted
			return $restriction_message;
		}

		// Return original post content if no changes are made
		return $post_content;
	}


	/**
	 * Processes the restricted groups set in the post meta data and update
	 * these levels/groups to the current post's meta data.
	 * Called when a post is saved.
	 *
	 * @param int     $post_id holds the ID of the current post
	 * @param WP_Post $post holds the current post
	 * @return void 
	 */
	public function post_access_load_restrictions($post_id, $post) {
		if (Exception::fatal_error()) return;

		// if post isn't being saved *by the user*, return
		if (!isset($_POST['action']) || $_POST['action'] != 'editpost') return;

		// Verify the nonce before proceeding
		if (!isset($_POST['wawp_post_access_control']) || !wp_verify_nonce($_POST['wawp_post_access_control'], basename(__FILE__))) {
			// Invalid nonce
			Log::wap_log_error('Your nonce for the post access control input could not be verified');
			add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
			return;
		}

		// Return if user does not have permission to edit the post
		if (!current_user_can('edit_post', $post_id)) {
			// User cannot edit the post
			return;
		}

		// actually need to use post if it's the first time getting post meta.
		$wa_post_meta = self::get_wa_post_meta_from_post_data($_POST);
		// Get levels and groups that the user checked off
		$checked_groups_ids = $wa_post_meta[self::RESTRICTED_GROUPS];
		$checked_levels_ids = $wa_post_meta[self::RESTRICTED_LEVELS];

		// Add the 'restricted' property to this post's meta data and check if page is indeed restricted
		$this_post_is_restricted = false;
		if (!empty($checked_groups_ids) || !empty($checked_levels_ids)) {
			$this_post_is_restricted = true;
			update_post_meta($post_id, self::IS_POST_RESTRICTED, true);
		}
		// Set post's meta data to false if it is not restricted
		if (!$this_post_is_restricted) {
			update_post_meta($post_id, self::IS_POST_RESTRICTED, false);
		}

		// Add this post to the 'restricted' posts in the options table so that its extra post meta data can be deleted upon uninstall
		// Get current array of restricted post, if applicable
		$site_restricted_posts = get_option(self::ARRAY_OF_RESTRICTED_POSTS);
		$updated_restricted_posts = array();
		// Possible cases here:
		// If this post is NOT restricted and is already in $site_restricted_posts, then remove it
		// If the post is restricted and is NOT already in $site_restricted_posts, then add it
		// If the post is restricted and $site_restricted_posts is empty, then create the array and add the post to it
		if ($this_post_is_restricted) { // the post is to be restricted
			// Check if $site_restricted_posts is empty or not
			if (empty($site_restricted_posts)) {
				// Add post id to the new array
				$updated_restricted_posts[] = $post_id;
			} else { // There are already restricted posts
				// Check if the post id is already in the restricted posts -> if not, then add it
				if (!in_array($post_id, $site_restricted_posts)) {
					$site_restricted_posts[] = $post_id;
				}
				$updated_restricted_posts = $site_restricted_posts;
			}
		} else { // the post is NOT to be restricted
			// Check if this post is located in $site_restricted_posts -> if so, then remove it
			if (!empty($site_restricted_posts)) {
				if (in_array($post_id, $site_restricted_posts)) {
					$updated_restricted_posts = array_diff($site_restricted_posts, [$post_id]);
				} else {
					$updated_restricted_posts = $site_restricted_posts;
				}
			}
		}

		// Serialize results for storage
		$checked_groups_ids = maybe_serialize($checked_groups_ids);
		$checked_levels_ids = maybe_serialize($checked_levels_ids);

		// Store these levels and groups to this post's meta data
		update_post_meta($post_id, self::RESTRICTED_GROUPS, $checked_groups_ids); // only add single value
		update_post_meta($post_id, self::RESTRICTED_LEVELS, $checked_levels_ids); // only add single value

		// Save updated restricted posts to options table
		update_option(self::ARRAY_OF_RESTRICTED_POSTS, $updated_restricted_posts);

		// Save individual restriction message to post meta data
		$individual_message = $wa_post_meta[self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY];
		if (!empty($individual_message)) {
			// Filter restriction message
			$individual_message = wp_kses_post($individual_message);
			// Save to post meta data
			update_post_meta($post_id, self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, $individual_message);
		} else {
			delete_post_meta($post_id, self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY);
		}
	}

	/**
	 * Displays the post meta box for the custom restriction message
	 * for the individual post.
	 *
	 * @param WP_Post $post is the current post being edited
	 * @return void
	 */
	public function individual_restriction_message_display($post) {
		// Get link to the global restriction page
		$global_restriction_link = site_url('/wp-admin/admin.php?page=wawp-wal-admin');
		?>
<p>If you like, you can enter a restriction message that is custom to this individual post. If not, just leave this
    field blank - the global restriction message set under <a
        href="<?php echo esc_url($global_restriction_link) ?>">WildApricot Press > Settings</a> will be displayed to
    restricted users.</p>
<?php
		$current_post_id = $post->ID;
		// Get individual restriction message from post meta data
		$initial_message = get_post_meta($current_post_id, self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, true); // return single value
		// Set initial message to blank if there is no saved message
		if (empty($initial_message)) {
			$initial_message = '';
		}
		// Create wp editor
		$editor_id = 'wawp_individual_post_restricted_message_editor';
        $editor_name = 'wawp_individual_post_restricted_message_textarea';
        $editor_settings = array('textarea_name' => $editor_name, 'tinymce' => false);
		wp_editor($initial_message, $editor_id, $editor_settings);
	}

	/**
	 * Displays the WAP custom post meta data on each post to select which 
	 * levels and groups can access the post.
	 *
	 * @param WP_Post $post is the current post being edited
	 */
	public function post_access_display($post) {
		// INCLUDE A MESSAGE TO DESCRIBE IF ACCESS LEVELS ARE CHECKED OFF
		// INCLUDE CHECKBOX FOR 'ALL MEMBERS AND CONTACTS'
		// if no boxes are checked, then this post is available to everyone, including logged out users
		// Load in saved membership levels
		$all_membership_levels = get_option(self::WA_ALL_MEMBERSHIPS_KEY);
		$all_membership_groups = get_option(self::WA_ALL_GROUPS_KEY);
		$current_post_id = $post->ID;

		// Add a nonce field to check on save
		wp_nonce_field(basename(__FILE__), 'wawp_post_access_control', 10, 2);
		?>
<!-- Membership Levels -->
<ul>
    <p>If you would like everyone (including non WildApricot users) to see the current post, then leave all the
        checkboxes blank! You can restrict this post to specific WildApricot groups and levels by selecting the
        checkboxes below.</p>
    <li style="margin:0;font-weight: 600;">
        <label for="wawp_check_all_levels"><input type="checkbox" value="wawp_check_all_levels"
                id='wawp_check_all_levels' name="wawp_check_all_levels" /> Select All Membership Levels</label>
    </li>
    <?php
			// Get checked levels from post meta data
			$already_checked_levels = get_post_meta($current_post_id, self::RESTRICTED_LEVELS);
			if (isset($already_checked_levels) && !empty($already_checked_levels)) {
				$already_checked_levels = $already_checked_levels[0];
			}
			// Loop through each membership level and add it as a checkbox to the meta box
			foreach ($all_membership_levels as $membership_key => $membership_level) {
				// Check if level is already checked
				$level_checked = '';
				if (isset($already_checked_levels) && !empty($already_checked_levels)) {
					// Unserialize into array
					$already_checked_levels = maybe_unserialize($already_checked_levels);
					// Check if membership_key is in already_checked_levels
					if (is_array($already_checked_levels)) {
						if (in_array($membership_key, $already_checked_levels)) { // already checked
							$level_checked = 'checked';
						}
					} else {
						if ($membership_key == $already_checked_levels) {
							$level_checked = 'checked';
						}
					}
				}
				?>
    <li>
        <input type="checkbox" name="wawp_membership_levels[]" class='wawp_case_level'
            value="<?php echo esc_attr($membership_key); ?>" <?php echo esc_attr($level_checked); ?> />
        <?php echo esc_html($membership_level); ?> </input>
    </li>
    <?php
			}
			?>
</ul>
<!-- Membership Groups -->
<p>Group Restriction will only work if the Group Participation membership field is <em>not set to</em> "No access -
    Internal use".</p>
<ul>
    <li style="margin:0;font-weight: 600;">
        <label for="wawp_check_all_groups"><input type="checkbox" value="wawp_check_all_groups"
                id='wawp_check_all_groups' name="wawp_check_all_groups" /> Select All Membership Groups</label>
    </li>
    <?php
			// Get checked groups from post meta data
			$already_checked_groups = get_post_meta($current_post_id, self::RESTRICTED_GROUPS);
			if (isset($already_checked_groups) && !empty($already_checked_groups)) {
				$already_checked_groups = $already_checked_groups[0];
			}
			// Loop through each membership group and add it as a checkbox to the meta box
			foreach ($all_membership_groups as $membership_key => $membership_group) {
				// Check if group is already checked
				$group_checked = '';
				if (isset($already_checked_groups) && !empty($already_checked_groups)) {
					// Unserialize into array
					$already_checked_groups = maybe_unserialize($already_checked_groups);
					// Check if membership_key is in already_checked_levels
					if (is_array($already_checked_groups)) {
						if (in_array($membership_key, $already_checked_groups)) { // already checked
							$group_checked = 'checked';
						}
					} else {
						if ($membership_key == $already_checked_groups) {
							$group_checked = 'checked';
						}
					}
				}
				?>
    <li>
        <input type="checkbox" name="wawp_membership_groups[]" class="wawp_case_group"
            value="<?php echo esc_attr($membership_key); ?>" <?php echo esc_attr($group_checked); ?> />
        <?php echo esc_html($membership_group); ?> </input>
    </li>
    <?php
			}
			?>
</ul>
<?php
		// Fire action to allow "select all" checkboxes to select all options
		do_action('wawp_create_select_all_checkboxes');
	}

	/**
	 * Adds WAP custom post meta box when editing a post.
	 * 
	 * @return void
	 */
	public function post_access_add_post_meta_boxes() {
		// Get post types to add the meta boxes to
		// Get all post types, including built-in WordPress post types and custom post types
		$post_types = get_post_types(array('public' => true));

		if (Addon::is_plugin_disabled()) return;

		// Add meta box for post access
		add_meta_box(
			'wawp_post_access_meta_box_id', // ID
			'WildApricot Access Control', // title
			array($this, 'post_access_display'), // callback
			$post_types, // screen
			'side', // location of meta box
			'default' // priority in comparison to other meta boxes
		);

		// Add meta box for post's custom restriction message
		add_meta_box(
			'wawp_post_access_custom_message_id', // ID
			'Individual Restriction Message', // title
			array($this, 'individual_restriction_message_display'), // callback
			$post_types, // screen
			'normal', // location of meta box
			'high' // priority in comparison to other meta boxes
		);
	}

	/**
	 * Display membership levels on user profile.
	 *
	 * @param WP_User $user is the user of the current profile
	 * @return void
	 */
	public function show_membership_level_on_profile($user) {
		// don't display WA data if the plugin is disabled
		if (Addon::is_plugin_disabled()) return;
		
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
						$field_meta_key = 'wawp_' . str_replace(' ', '' , $custom_field);
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
        <th><label><?php echo esc_html($all_custom_fields[$custom_field]); ?></label></th>
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
	public function refresh_user_wa_info() {
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
	public static function create_cron_for_user_refresh() {
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
	public function add_user_to_wp_database($login_data, $login_email, $remember_user = true) {
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
	 * Handles the redirect after the user is logged in.
	 * 
	 * @return void
	 */
	public function create_user_and_redirect() {
		// Check that we are on the login page
		$login_page_id = get_option(self::LOGIN_PAGE_ID_OPT);
		if (!is_page($login_page_id)) return;

		// Get id of last page from url
		// https://stackoverflow.com/questions/13652605/extracting-a-parameter-from-a-url-in-wordpress
		if (empty($_POST['wawp_login_submit'])) return;

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
			$login_attempt = WA_API::login_email_password($valid_login);
			if (!$login_attempt) {
				add_filter('the_content', array($this, 'add_login_error'));
				return;
			}
			$this->add_user_to_wp_database($login_attempt, $valid_login['email'], $remember_user);
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
	 * Creates the shortcode that holds the login form.
	 *
	 * @return string Holds the HTML content of the form
	 */
	public function custom_login_form_shortcode() {
		// Get WildApricot URL
		$wild_apricot_url = get_option(self::WA_URL_KEY);
		try {
			$dataEncryption = new Data_Encryption();
			$wild_apricot_url =	esc_url($dataEncryption->decrypt($wild_apricot_url));
		} catch (Decryption_Exception $e) {
			Log::wap_log_error($e->getMessage(), true);
			return Exception::get_user_facing_error_message();
		}

		ob_start();
		// if WA user is not logged in, display login form
		if (!self::is_wa_user_logged_in()) {
			// Create page content -> login form
			?><div id="wawp_login-wrap">
    <p id="wawp_wa_login_direction">Log into your WildApricot account here to access content exclusive to WildApricot
        members!</p>
    <form method="post" action="">
        <?php wp_nonce_field("wawp_login_nonce_action", "wawp_login_nonce_name");?>
        <label for="wawp_login_email">Email:</label>
        <br><input type="text" id="wawp_login_email" name="wawp_login_email" placeholder="example@website.com">
        <br><label for="wawp_login_password">Password:</label>
        <br><input type="password" id="wawp_login_password" name="wawp_login_password" placeholder="***********"
            autocomplete="new-password">
        <!-- Remember Me -->
        <div id="wawp_remember_me_div">
            <br><label id="wawp_remember_me_label" for="wawp_remember_me">Remember me?</label>
            <input type="checkbox" id="wawp_remember_me" name="wawp_remember_me" checked>
        </div>
        <!-- Forgot password -->
        <br><label id="wawp_forgot_password"><a
                href="<?php echo esc_url($wild_apricot_url . '/Sys/ResetPasswordRequest'); ?>" target="_blank"
                rel="noopener noreferrer">Forgot Password?</a></label>
        <br><input type="submit" id="wawp_login_submit" name="wawp_login_submit" value="Submit">
    </form>
</div><?php
		} else {
			// display you are already logged in message and give option to logout
			$logout_link = wp_logout_url(esc_url(site_url()));
			?><div id="wawp_login-wrap">
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
	public function create_wa_login_logout($items, $args) {
		// First, check if WildApricot credentials and the license is valid
		if (Addon::is_plugin_disabled()) return $items;
		// Check the restrictions of each item in header IF the header is not blank
		if (!empty($items)) {
			// Get navigation items
			$args_menu = $args->menu;
			$nav_items = wp_get_nav_menu_items($args_menu);

			// Get li tags from menu
			$items = mb_convert_encoding($items, 'HTML-ENTITIES', 'UTF-8');;
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
					$nav_item_is_restricted = get_post_meta($nav_item_id, self::IS_POST_RESTRICTED);
					// If post is restricted, then check if the current has access to it
					if (!empty($nav_item_is_restricted) && $nav_item_is_restricted[0]) {
						if (is_user_logged_in()) { // user is logged in
							// Check that user is synced with WildApricot
							$current_users_id = get_current_user_id();
							$users_wa_id = get_user_meta($current_users_id, self::WA_USER_ID_KEY);
							// Check if user ID actually exists
							if (!empty($users_wa_id) && $users_wa_id != '') { // User has been synced with WildApricot
								// Check if user's status is within the allowed status(es)
								$users_status = get_user_meta($current_users_id, self::WA_USER_STATUS_KEY);
								$users_status = $users_status[0];
								$allowed_statuses = get_option(self::GLOBAL_RESTRICTED_STATUSES);
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
								$users_member_groups = get_user_meta($current_users_id, self::WA_MEMBER_GROUPS_KEY);
								$users_member_groups = maybe_unserialize($users_member_groups[0]);
								$user_member_level = get_user_meta($current_users_id, self::WA_MEMBERSHIP_LEVEL_ID_KEY);
								$user_member_level = $user_member_level[0];
								$wa_post_meta = self::get_wa_post_meta($nav_item_id);
								// Get page's groups and level
								$page_member_groups = $wa_post_meta[self::RESTRICTED_GROUPS];
								$page_member_levels = $wa_post_meta[self::RESTRICTED_LEVELS];
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


		if (empty($selected_login_button_locations)) return $items;

		foreach ($selected_login_button_locations as $menu) {
			if ($args->menu->term_id != $menu) continue;
			if (is_user_logged_in()) { 
				// Logout
				$url = $logout_url;
				$button_text = 'Log Out';
			} elseif (!is_user_logged_in()) { 
				// Login
				$url = $login_url;
				$button_text = 'Log In';
			}
			$items .= '<li id="wawp_login_logout_button" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="'. esc_url($url) .'">' . esc_html($button_text) . '</a></li>';
		}
		
		return $items;
	}

	/**
	 * Removes all users with WildApricot data added by the plugin.
	 *
	 * @return void
	 */
	public static function remove_wa_users() {
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
			if (in_array('administrator', $user->roles)) continue;
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
				function($key) {
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
	/**
	 * Returns the licensed WA urls from the hook response.
	 *
	 * @param array $response
	 * @return array|null returns null if URLs don't exist
	 */
	private static function get_licensed_wa_urls($response) {
		$licensed_wa_urls = array();

		if (!array_key_exists('Licensed Wild Apricot URLs', $response)) {
			Log::wap_log_warning('Licensed WildApricot URLs missing from hook response.');
			return null;
		}

		$licensed_wa_urls = $response['Licensed Wild Apricot URLs'];
		if (empty($licensed_wa_urls) || empty($licensed_wa_urls[0])) return null;

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
	private static function get_licensed_wa_ids($response) {
		$licensed_wa_ids = array();

		if (!array_key_exists('Licensed Wild Apricot Account IDs', $response)) {
			Log::wap_log_warning('License WildApricot IDs missing from hook response');
			return null;
		}

		$licensed_wa_ids = $response['Licensed Wild Apricot Account IDs'];
		if (empty($licensed_wa_ids) || empty($licensed_wa_ids[0])) return null;

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
	private static function is_wa_user_logged_in() {
		if (!is_user_logged_in()) return false;
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
	private static function is_block_editor() {
		$current_screen = get_current_screen();
		return method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor();
	}

	/**
	 * Converts an array of member values to a string for displaying on the user's profile
	 *
	 * @param  array  $array_values is the array of values to convert to a string
	 * @return string $string_result is a string of each value separated by a comma
	 */
	private static function convert_array_values_to_string($array_values) {
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

	/**
	 * Recursive function to sanitize post meta data.
	 *
	 * @param array $post_meta post meta data to sanitize.
	 * @return array sanitized array of post meta data.
	 */
	private static function sanitize_post_meta($post_meta) {
		// loop through all values in the array
		foreach ($post_meta as $key => &$value) {
			// if the value is a string, sanitize it
			if (gettype($value) == 'string') {
				if (str_contains($key, 'textarea')) {
					$value = sanitize_textarea_field($value);
				} else {
					$value = sanitize_text_field($value);
				}
			} elseif (gettype($value) == 'array') {
				/**
				 * if the value is an array, recursively call this function and
				 * obtain the sanitized inner array that it will return
				 */
				$value = self::sanitize_post_meta($value);
			}
			
		}

		return $post_meta;
	}

	/**
	 * Obtains the relevant WA post meta data from the $_POST response data and
	 * formats it similar to the post meta data structure obtained by using
	 * get_post_meta.
	 *
	 * @param string[] $post_data
	 * @return string[] formatted array of the post meta data.
	 */
	private static function get_wa_post_meta_from_post_data($post_data) {

		$memgroups = "";
		$memlevels = "";
		$restmsg = "";

		$post_data = self::sanitize_post_meta($post_data);
		if (array_key_exists('wawp_membership_groups', $post_data)) {
			$memgroups = $post_data['wawp_membership_groups'];
		}
		if (array_key_exists('wawp_membership_levels', $post_data)) {
			$memlevels = $post_data['wawp_membership_levels'];
		}
		if (array_key_exists('wawp_individual_post_restricted_message_textarea', $post_data)) {
			$restmsg = $post_data['wawp_individual_post_restricted_message_textarea'];
		}

		return array (
			self::RESTRICTED_GROUPS => $memgroups,
			self::RESTRICTED_LEVELS => $memlevels,
			self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY => $restmsg
		);
	}

	/**
	 * Returns the post meta values pertaining to WildApricot.
	 * The list of restricted groups and levels, flag of whether the post is 
	 * restricted or not, and the restriction message.
	 *
	 * @param array $meta metadata of a post.
	 * @return array array of the restricted groups and levels, each in their own
	 * respective element.
	 */
	private function get_wa_post_meta($nav_id) {
		$restricted_groups = array();
		$restricted_levels = array();
		$is_restricted = 0;
		$individual_restriction_msg = '';

		$meta = get_post_meta($nav_id);

		/**
		 * these will only be present in the post meta if there are restricted
		 * groups/levels. 
		 */
		if (array_key_exists(self::RESTRICTED_GROUPS, $meta)) {
			$restricted_groups = $meta[self::RESTRICTED_GROUPS][0];
		}
		if (array_key_exists(self::RESTRICTED_LEVELS, $meta)) {
			$restricted_levels = $meta[self::RESTRICTED_LEVELS][0];
		}

		// restriction flag will always be present
		if (array_key_exists(self::IS_POST_RESTRICTED, $meta)) {
			$is_restricted = $meta[self::IS_POST_RESTRICTED][0];

			$is_restricted = $meta[self::IS_POST_RESTRICTED][0] ? true : false;
		}

		// like groups and levels, restriction message will not always be in the meta
		if (array_key_exists(self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, $meta)) {
			$individual_restriction_msg = $meta[self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY][0];
		}

		/**
		 * need to call maybe_unserialize twice since the array of restricted 
		 * groups/levels is a serialized string containing a serialized array.
		 */
		$wa_meta = array(
			self::IS_POST_RESTRICTED => $is_restricted,
			self::RESTRICTED_GROUPS => maybe_unserialize(maybe_unserialize($restricted_groups)),
			self::RESTRICTED_LEVELS => maybe_unserialize(maybe_unserialize($restricted_levels)),
			self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY => $individual_restriction_msg
			
		);

		return $wa_meta;
	}

	public static function retrieve_custom_fields() {
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