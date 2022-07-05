<?php
namespace WAWP;

// For iterating through menu HTML
use DOMDocument;
use DOMAttr;
use WAWP\Log;
use WAWP\Addon;
require_once __DIR__ . '/Log.php';
require_once __DIR__ . '/Addon.php';
require_once __DIR__ . '/helpers.php';


/**
 * Class for managing the user's Wild Apricot account
 */
class WAIntegration {
	// Constants for keys used for database management
	const WA_CREDENTIALS_KEY = 'wawp_wal_name';
	const WAWP_LICENSES_KEY = 'wawp_license_keys';
	const WA_API_KEY_OPT = 'wawp_wal_api_key';
	const WA_CLIENT_ID_OPT = 'wawp_wal_client_id';
	const WA_CLIENT_SECRET_OPT = 'wawp_wal_client_secret';
	const WA_USER_ID_KEY = 'wawp_wa_user_id';
	const WA_MEMBERSHIP_LEVEL_KEY = 'wawp_membership_level_key';
	const WA_MEMBERSHIP_LEVEL_ID_KEY = 'wawp_membership_level_id_key';
	const WA_USER_STATUS_KEY = 'wawp_user_status_key';
	const WA_ORGANIZATION_KEY = 'wawp_organization_key';
	const WA_MEMBER_GROUPS_KEY = 'wawp_list_of_groups_key';
	const WA_ALL_MEMBERSHIPS_KEY = 'wawp_all_levels_key';
	const RESTRICTED_GROUPS = 'wawp_restricted_groups';
	const RESTRICTED_LEVELS = 'wawp_restricted_levels';
	const IS_POST_RESTRICTED = 'wawp_is_post_restricted';
	const ARRAY_OF_RESTRICTED_POSTS = 'wawp_array_of_restricted_posts';
	const INDIVIDUAL_RESTRICTION_MESSAGE_KEY = 'wawp_individual_restriction_message_key';
	const ADMIN_ACCOUNT_ID_TRANSIENT = 'wawp_admin_account_id';
	const ADMIN_ACCESS_TOKEN_TRANSIENT = 'wawp_admin_access_token';
	const ADMIN_REFRESH_TOKEN_OPTION = 'wawp_admin_refresh_token';
	const LIST_OF_CUSTOM_FIELDS = 'wawp_list_of_custom_fields';
	const LIST_OF_CHECKED_FIELDS = 'wawp_fields_name';
	const USER_ADDED_BY_PLUGIN = 'wawp_user_added_by_plugin';
	const MENU_LOCATIONS_KEY = 'wawp_menu_location_name';
	const WA_URL_KEY = 'wawp_wa_url_key';
	// Custom hooks
	const USER_REFRESH_HOOK = 'wawp_cron_refresh_user_hook';
	const LICENSE_CHECK_HOOK = 'wawp_cron_refresh_license_check';

	/**
	 * Constructs an instance of the WAIntegration class
	 *
	 * Adds the actions and filters required. Includes other required files. Initializes class variables.
	 *
	 */
	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action('wawp_wal_credentials_obtained', array($this, 'create_login_page'));
		// Action for when login page is updated when submit button is pressed
		add_action('template_redirect', array($this, 'create_user_and_redirect'));
		// Filter for adding to menu
		add_filter('wp_nav_menu_items', array($this, 'create_wa_login_logout'), 10, 2); // 2 arguments
		// Shortcode for login form
		add_shortcode('wawp_custom_login_form', array($this, 'custom_login_form_shortcode'));
		// Add redirectId to query vars array
		add_filter('query_vars', array($this, 'add_custom_query_vars'));
		// Action for making profile page private
		add_action('wawp_wal_set_login_private', array($this, 'make_login_private'));
		// Actions for displaying membership levels on user profile
		add_action('show_user_profile', array($this, 'show_membership_level_on_profile'));
		add_action('edit_user_profile', array($this, 'show_membership_level_on_profile'));
		// Fire our meta box setup function on the post editor screen
		add_action('load-post.php', array($this, 'post_access_meta_boxes_setup'));
		add_action('load-post-new.php', array($this, 'post_access_meta_boxes_setup'));
		// Loads in restricted groups/levels when post is saved
		add_action('save_post', array($this, 'post_access_load_restrictions'), 10, 2); // changed to all types of posts
		// On post load check if the post can be accessed by the current user
		add_action('the_content', array($this, 'restrict_post_wa'));
		// Action for creating 'select all' checkboxes
		add_action('wawp_create_select_all_checkboxes', array($this, 'select_all_checkboxes_jquery'));
		// Action for user refresh cron hook
		add_action(self::USER_REFRESH_HOOK, array($this, 'refresh_user_wa_info'));
		// Action for hiding admin bar for non-admin users
		add_action('after_setup_theme', array($this, 'hide_admin_bar'));
		// Action when user views the settings page -> check that Wild Apricot credentials and license still match
		add_action('load-toplevel_page_wawp-wal-admin', array($this, 'check_updated_credentials'));
		// Action for Cron job that refreshes the license check
		add_action(self::LICENSE_CHECK_HOOK, array($this, 'check_updated_credentials'));
		// Action for when user tries to access admin page
		add_action('admin_page_access_denied', array($this, 'tell_user_to_logout'));
		// Include any required files
		require_once('DataEncryption.php');
		require_once('WAWPApi.php');
		require_once('Addon.php');
	}

	/**
	 * Checks for valid Wild Apricot credentials.
	 * @return boolean true if valid authorization creds, false if not
	 */
	public static function valid_wa_credentials() {
		$wa_credentials = get_option(self::WA_CREDENTIALS_KEY);

		// wa_credentials will be false if the option doesn't exist
		// return here so we don't get invalid index in the lines below
		if (!$wa_credentials || !isset($wa_credentials)) return false;
		if (empty($wa_credentials)) return false;

		$api_key = $wa_credentials[self::WA_API_KEY_OPT];
		$client_id = $wa_credentials[self::WA_CLIENT_ID_OPT];
		$client_secret = $wa_credentials[self::WA_CLIENT_SECRET_OPT];

		// check first that creds exist
		return !empty($api_key) && !empty($client_id) && !empty($client_secret);
	}

	/**
	 * Checks that updated Wild Apricot credentials match the registered site on the license key
	 */
	public function check_updated_credentials() {
		// Ensure that credentials have been already entered
		$has_valid_wa_credentials = self::valid_wa_credentials();
		$has_valid_license = Addon::instance()::has_valid_license(CORE_SLUG);
		$license_status = Addon::get_license_check_option(CORE_SLUG);

		

		// if the stored WA credentials and license key are valid, check the validity of each
		if ($has_valid_wa_credentials && $has_valid_license) {
			// Verify that the license still matches the Wild Apricot credentials
			$current_license_key = Addon::get_license(CORE_SLUG);

			// check for correct license properties
			$license = Addon::instance()::validate_license_key($current_license_key, CORE_SLUG);

			if ($license == Addon::LICENSE_STATUS_ENTERED_EMPTY || !$has_valid_wa_credentials) {
				$license_status = Addon::LICENSE_STATUS_NOT_ENTERED;
			} else if (is_null($license)) {
				$license_status = Addon::LICENSE_STATUS_INVALID;
			}

			// now check if WA creds invalid
			// $wa_credentials = get_option(self::WA_CREDENTIALS_KEY);
			// $has_valid_wa_credentials = WAWPApi::is_application_valid($wa_credentials[self::WA_API_KEY_OPT]);
		}

		// update new license status
		// invalid if license was found to be invalid
		// not entered if only WA creds are invalid


		// license status is subject to change based on the request made
		if ($license_status != Addon::LICENSE_STATUS_VALID || !$has_valid_license || !$has_valid_wa_credentials) {
			// disable plugin since one or both of the creds are invalid
			do_action('disable_plugin', CORE_SLUG, $license_status);
		} else {
			// if neither of the creds are invalid, do creds obtained action
			do_action('wawp_wal_credentials_obtained');
		}
	}

	public static function get_licensed_wa_urls($response) {
		$licensed_wa_urls = array();

		if (!array_key_exists('Licensed Wild Apricot URLs', $response)) {
			return null;
		}

		$licensed_wa_urls = $response['Licensed Wild Apricot URLs'];
		// Sanitize urls, if necessary

		if (empty($licensed_wa_urls)) return null;

		foreach ($licensed_wa_urls as $url_key => $url_value) {
			// Lowercase and remove https://, http://, and/or www. from url
			$licensed_wa_urls[$url_key] = WAWPApi::create_consistent_url($url_value);
		}

		return $licensed_wa_urls;
	}

	public static function get_licensed_wa_ids($response) {
		$licensed_wa_ids = array();

		if (!array_key_exists('Licensed Wild Apricot Account IDs', $response)) {
			return null;
		}

		$licensed_wa_ids = $response['Licensed Wild Apricot Account IDs'];

		if (empty($licensed_wa_ids)) return null;

		foreach ($licensed_wa_ids as $id_key => $id_value) {
			// Ensure that only numbers are in the ID #
			$licensed_wa_ids[$id_key] = intval($id_value);
		}

		return $licensed_wa_ids;
	}

	public static function check_licensed_wa_urls_ids($response) {
		$licensed_wa_urls = self::get_licensed_wa_urls($response);
		$licensed_wa_ids = self::get_licensed_wa_ids($response);
		if ($licensed_wa_urls == null || $licensed_wa_ids == null ) return false;

		// Get access token and account id
		$access_and_account = WAWPApi::verify_valid_access_token();
		$access_token = $access_and_account['access_token'];
		$wa_account_id = $access_and_account['wa_account_id'];
		// Get account url from API
		$wawp_api = new WAWPApi($access_token, $wa_account_id);
		$wild_apricot_info = $wawp_api->get_account_url_and_id();


		// Compare license key information with current site
		if (in_array($wild_apricot_info['Id'], $licensed_wa_ids) && in_array($wild_apricot_info['Url'], $licensed_wa_urls)) { 
			return true;
		}
		
		// do_action('wawp_wal_set_login_private');
		return false;

	}

	/**
	 * Hides the WordPress admin bar for non-admin users
	 */
	public function hide_admin_bar() {
		if (!current_user_can('administrator') && !is_admin()) {
			show_admin_bar(false);
		}
	}

	/**
	 * Tell user to logout of Wild Apricot if they are trying to access the admin menu
	 */
	public function tell_user_to_logout() {
		// Check if user is logged into Wild Apricot
		if (is_user_logged_in()) {
			$user_id = get_current_user_id();
			// Check if user has Wild Apricot ID
			$wild_apricot_id = get_user_meta($user_id, self::WA_USER_ID_KEY);
			if (!empty($wild_apricot_id)) {
				// User is still logged into Wild Apricot
				$logout_link = esc_url(wp_logout_url(esc_url(site_url())));
				echo 'Are you trying to access the WordPress administrator menu while still logged into your Wild Apricot account? If so, ensure that you are logged out of your Wild Apricot account by clicking <a href="'.$logout_link.'">Log Out</a>.';
			}
		}
	}

	/**
	 * Add query vars to WordPress
	 *
	 * See: https://stackoverflow.com/questions/20379543/wordpress-get-query-var
	 *
	 * @param array  $vars Current, incoming query vars
	 * @return array $vars Updated vars array with added query var
	 */
	public function add_custom_query_vars($vars) {
		// Add redirectId to query vars
		$vars[] = 'redirectId';
		return $vars;
	}

	/**
     * Creates a daily CRON job to check that the license matches
     */
    public static function setup_license_check_cron() {
        $license_hook_name = WAIntegration::LICENSE_CHECK_HOOK;
        if (!wp_next_scheduled($license_hook_name)) {
            wp_schedule_event(time(), 'daily', $license_hook_name);
        }
    }

	/**
	 * Creates login page that allows user to enter their email and password credentials for Wild Apricot
	 *
	 * See: https://stackoverflow.com/questions/32314278/how-to-create-a-new-wordpress-page-programmatically
	 * https://stackoverflow.com/questions/13848052/create-a-new-page-with-wp-insert-post
	 *
	 */
	public function create_login_page() {
		// Run action to create user refresh CRON event
		self::create_cron_for_user_refresh();
		// Create event for checking license
		self::setup_license_check_cron();

		// Check if Login page exists first
		$login_page_id = get_option('wawp_wal_page_id');
		if (isset($login_page_id) && $login_page_id != '') { // Login page already exists
			// Set existing login page to publish
			$login_page = get_post($login_page_id, 'ARRAY_A');
			$login_page['post_status'] = 'publish';
			wp_update_post($login_page);
			// Add user roles
			$saved_wa_roles = get_option('wawp_all_levels_key');
			// Loop through roles and add them as roles to WordPress
			if (!empty($saved_wa_roles)) {
				foreach ($saved_wa_roles as $role) {
					add_role('wawp_' . str_replace(' ', '', $role), $role);
				}
			}
		} else { // Login page does not exist
			// Create details of page
			// See: https://wordpress.stackexchange.com/questions/222810/add-a-do-action-to-post-content-of-wp-insert-post
			$post_details = array(
				'post_title' => 'Login with your Wild Apricot credentials',
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_content' => '[wawp_custom_login_form]', // shortcode
				'post_name' => 'wawp-wild-apricot-login'
			);
			$page_id = wp_insert_post($post_details, FALSE);
			// Add page id to options so that it can be removed on deactivation
			update_option('wawp_wal_page_id', $page_id);
		}
		// Remove new login page from menu
		// https://wordpress.stackexchange.com/questions/86868/remove-a-menu-item-in-menu
		// https://stackoverflow.com/questions/52511534/wordpress-wp-insert-post-adds-page-to-the-menu
		$page_id = get_option('wawp_wal_page_id');
		$menu_item_ids = wp_get_associated_nav_menu_items($page_id, 'post_type');
		// Loop through ids and remove
		foreach ($menu_item_ids as $menu_item_id) {
			wp_delete_post($menu_item_id, true);
		}
	}

	/**
	 * Sets the login page to private if the plugin is deactivated or invalid credentials are entered
	 */
	public function make_login_private() {
		// Check if login page exists
		$login_page_id = get_option('wawp_wal_page_id');
		if (isset($login_page_id) && $login_page_id != '') { // Login page already exists
			// Make login page private
			// Set existing login page to publish
			$login_page = get_post($login_page_id, 'ARRAY_A');
			$login_page['post_status'] = 'private';
			wp_update_post($login_page);
		}
	}

	/**
	 * Adds error to login shortcode page
	 *
	 * @param  string $content Holds the existing content on the page
	 * @return string $content Holds the new content on the page
	 */
	public function add_login_error($content) {
		// Only run on wa4wp page
		$login_page_id = get_option('wawp_wal_page_id');
		if (is_page($login_page_id)) {
			return $content . '<p style="color:red;">Invalid credentials! Please check that you have entered the correct email and password.
			If you are sure that you entered the correct email and password, please contact your administrator.</p>';
		}
		return $content;
	}

	/**
	 * Generates the URL to the WAWP Login page on the website
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
	 * Determines whether or not to restrict the post to the current user based on the user's levels/groups and the post's list of restricted levels/groups
	 *
	 * @param string $post_content holds the post content in HTML form
	 *
	 * @return string $post_content is the new post content - either the original post content if the post is not restricted, or the restriction message if otherwise
	 */
	public function restrict_post_wa($post_content) {
		// Get ID of current post
		$current_post_ID = get_queried_object_id();
		// Check if valid Wild Apricot credentials have been entered
		$valid_wa_credentials = get_option(WAIntegration::WA_CREDENTIALS_KEY);

		// Make sure a page/post is requested and the user has already entered their valid Wild Apricot credentials
		$wawp_licenses = get_option(self::WAWP_LICENSES_KEY);
		if (is_singular() && !empty($valid_wa_credentials) && !empty($wawp_licenses) && array_key_exists(CORE_SLUG, $wawp_licenses) && $wawp_licenses[CORE_SLUG] != '') {
			// Check that this current post is restricted
			$is_post_restricted = get_post_meta($current_post_ID, WAIntegration::IS_POST_RESTRICTED, true); // return single value
			if (isset($is_post_restricted) && $is_post_restricted) {
				// Load in restriction message from message set by user
				$restriction_message = wpautop(get_option('wawp_restriction_name'));
				// Check if current post has a custom restriction message
				$individual_restriction_message = wpautop(get_post_meta($current_post_ID, WAIntegration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, true));
				if (!empty($individual_restriction_message) && $individual_restriction_message != '') { // this post has an individual restriction message
					$restriction_message = $individual_restriction_message;
				}
				// Append 'Log In' button and the styling div to the restriction message
				$login_url = $this->get_login_link();
				$restriction_message = '<div class="wawp_restriction_content_div">' . $restriction_message;
				$restriction_message .= '<li id="wawp_restriction_login_button"><a href="'. $login_url .'">Log In</a></li>';
				$restriction_message .= '</div>';
				// Automatically restrict the post if user is not logged in
				if (!is_user_logged_in()) {
					return $restriction_message;
				}
				// Show a warning/notice on the restriction page if the user is logged into WordPress but is not synced with Wild Apricot
				// Get user's Wild Apricot ID -> if it does not exist, then the user is not synced with Wild Apricot
				$current_user_ID = wp_get_current_user()->ID;
				$user_wa_id = get_user_meta($current_user_ID, WAIntegration::WA_USER_ID_KEY, true);
				if (empty($user_wa_id)) {
					// Present notice that user is not synced with Wild Apricot
					$restriction_message .= '<p style="color:red;">Please note that while you are logged into WordPress, you have not synced your account with Wild Apricot. Please <a href="'. $login_url .'">Log In</a> into your Wild Apricot account to sync your data in your WordPress site.</p>';
					return $restriction_message;
				}

				// Get post meta data
				// Get post's restricted groups
				$post_restricted_groups = get_post_meta($current_post_ID, WAIntegration::RESTRICTED_GROUPS);
				// Unserialize
				$post_restricted_groups = maybe_unserialize($post_restricted_groups[0]);
				// Get post's restricted levels
				$post_restricted_levels = get_post_meta($current_post_ID, WAIntegration::RESTRICTED_LEVELS);
				// Unserialize
				$post_restricted_levels = maybe_unserialize($post_restricted_levels[0]);

				// If no options are selected, then the post is unrestricted, as there cannot be a post with no viewers
				if (empty($post_restricted_groups) && empty($post_restricted_levels)) {
					update_post_meta($current_post_ID, WAIntegration::IS_POST_RESTRICTED, false);
					return $post_content;
				}

				// Get user meta data
				$user_groups = get_user_meta($current_user_ID, WAIntegration::WA_MEMBER_GROUPS_KEY);
				$user_level = get_user_meta($current_user_ID, WAIntegration::WA_MEMBERSHIP_LEVEL_ID_KEY, true);
				$user_status = get_user_meta($current_user_ID, WAIntegration::WA_USER_STATUS_KEY, true);

				// Check if user's status is allowed to view restricted posts
				// Get restricted status(es) from options table
				$restricted_statuses = get_option('wawp_restriction_status_name');
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
			}
		}
		// Return original post content if no changes are made
		return $post_content;
	}

	/**
	 * Processes the restricted groups set in the post meta data and update these levels/groups to the current post's meta data. Called when a post is saved.
	 *
	 * @param int     $post_id holds the ID of the current post
	 * @param WP_Post $post holds the current post
	 */
	public function post_access_load_restrictions($post_id, $post) {
		// Verify the nonce before proceeding
		if (!isset($_POST['wawp_post_access_control']) || !wp_verify_nonce($_POST['wawp_post_access_control'], basename(__FILE__))) {
			// Invalid nonce
			return;
		}

		// Return if user does not have permission to edit the post
		if (!current_user_can('edit_post', $post_id)) {
			// User cannot edit the post
			return;
		}

		// actually need to use post if it's the first time getting post meta.
		$post_meta = get_post_meta($post_id);
		if (!array_key_exists(self::RESTRICTED_GROUPS, $post_meta)) {
			$post_meta = sanitize_post_meta($post_meta);
		}
		// Get levels and groups that the user checked off
		$wa_post_meta = self::get_wa_post_meta($post_meta);
		$checked_groups_ids = $wa_post_meta[self::RESTRICTED_GROUPS];
		$checked_levels_ids = $wa_post_meta[self::RESTRICTED_LEVELS];

		// Add the 'restricted' property to this post's meta data and check if page is indeed restricted
		$this_post_is_restricted = false;
		if (!empty($checked_groups_ids) || !empty($checked_levels_ids)) {
			$this_post_is_restricted = true;
			update_post_meta($post_id, WAIntegration::IS_POST_RESTRICTED, true);
		}
		// Set post's meta data to false if it is not restricted
		if (!$this_post_is_restricted) {
			update_post_meta($post_id, WAIntegration::IS_POST_RESTRICTED, false);
		}

		// Add this post to the 'restricted' posts in the options table so that its extra post meta data can be deleted upon uninstall
		// Get current array of restricted post, if applicable
		$site_restricted_posts = get_option(WAIntegration::ARRAY_OF_RESTRICTED_POSTS);
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
		update_post_meta($post_id, WAIntegration::RESTRICTED_GROUPS, $checked_groups_ids); // only add single value
		update_post_meta($post_id, WAIntegration::RESTRICTED_LEVELS, $checked_levels_ids); // only add single value

		// Save updated restricted posts to options table
		update_option(WAIntegration::ARRAY_OF_RESTRICTED_POSTS, $updated_restricted_posts);

		// Save individual restriction message to post meta data
		$individual_message = $wa_post_meta[self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY];
		if (!empty($individual_message)) {
			// Filter restriction message
			$individual_message = wp_kses_post($individual_message);
			// Save to post meta data
			update_post_meta($post_id, WAIntegration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, $individual_message);
		}
	}

	/**
	 * Allows for the 'select all' checkbox to select all boxes
	 */
	public function select_all_checkboxes_jquery() {
		?>
		<script language="javascript">
			// Check all levels
			jQuery('#wawp_check_all_levels').click(function () {
				jQuery('.wawp_case_level').prop('checked', true);
			});

			// Check all groups
			jQuery('#wawp_check_all_groups').click(function () {
				jQuery('.wawp_case_group').prop('checked', true);
			});

			// If all checkboxes are selected, check the select-all checkbox, and vice versa
			// Levels
			jQuery(".wawp_case_level").click(function() {
				if(jQuery(".wawp_case_level").length == jQuery(".wawp_case_level:checked").length) {
					jQuery("#wawp_check_all_levels").attr("checked", "checked");
				} else {
					jQuery("#wawp_check_all_levels").removeAttr("checked");
				}
			});
			// Groups
			jQuery(".wawp_case_group").click(function() {
				if(jQuery(".wawp_case_group").length == jQuery(".wawp_case_group:checked").length) {
					jQuery("#wawp_check_all_groups").attr("checked", "checked");
				} else {
					jQuery("#wawp_check_all_groups").removeAttr("checked");
				}
			});
    	</script>
		<?php
	}

	/**
	 * Displays the post meta box for the custom restriction message for the individual post
	 *
	 * @param WP_Post $post is the current post being edited
	 */
	public function individual_restriction_message_display($post) {
		// Get link to the global restriction page
		$global_restriction_link = site_url('/wp-admin/admin.php?page=wawp-wal-admin');
		?>
		<p>If you like, you can enter a restriction message that is custom to this individual post! If not, just leave this field blank - the global restriction message set under <a href="<?php echo $global_restriction_link ?>">Wild Apricot Press > Settings</a> will be displayed to restricted users.</p>
		<?php
		$current_post_id = $post->ID;
		// Get individual restriction message from post meta data
		$initial_message = get_post_meta($current_post_id, WAIntegration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, true); // return single value
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
	 * Displays the post meta data on each post to select which levels and groups can access the post
	 *
	 * @param WP_Post $post is the current post being edited
	 */
	public function post_access_display($post) {
		// INCLUDE A MESSAGE TO DESCRIBE IF ACCESS LEVELS ARE CHECKED OFF
		// INCLUDE CHECKBOX FOR 'ALL MEMBERS AND CONTACTS'
		// if no boxes are checked, then this post is available to everyone, including logged out users
		// Load in saved membership levels
		$all_membership_levels = get_option('wawp_all_levels_key');
		$all_membership_groups = get_option('wawp_all_groups_key');
		$current_post_id = $post->ID;
		// Add a nonce field to check on save
		wp_nonce_field(basename(__FILE__), 'wawp_post_access_control', 10, 2);
		?>
			<!-- Membership Levels -->
			<ul>
			<p>If you would like everyone (including non Wild Apricot users) to see the current post, then leave all the checkboxes blank! You can restrict this post to specific Wild Apricot groups and levels by selecting the checkboxes below!</p>
			<li style="margin:0;font-weight: 600;">
                <label for="wawp_check_all_levels"><input type="checkbox" value="wawp_check_all_levels" id='wawp_check_all_levels' name="wawp_check_all_levels" /> Select All Membership Levels</label>
            </li>
			<?php
			// Get checked levels from post meta data
			$already_checked_levels = get_post_meta($current_post_id, WAIntegration::RESTRICTED_LEVELS);
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
						<input type="checkbox" name="wawp_membership_levels[]" class='wawp_case_level' value="<?php echo htmlspecialchars($membership_key); ?>" <?php echo($level_checked); ?>/> <?php echo htmlspecialchars($membership_level); ?> </input>
					</li>
				<?php
			}
			?>
			</ul>
			<!-- Membership Groups -->
			<ul>
			<li style="margin:0;font-weight: 600;">
                <label for="wawp_check_all_groups"><input type="checkbox" value="wawp_check_all_groups" id='wawp_check_all_groups' name="wawp_check_all_groups" /> Select All Membership Groups</label>
            </li>
			<?php
			// Get checked groups from post meta data
			$already_checked_groups = get_post_meta($current_post_id, WAIntegration::RESTRICTED_GROUPS);
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
						<input type="checkbox" name="wawp_membership_groups[]" class="wawp_case_group" value="<?php echo htmlspecialchars($membership_key); ?>" <?php echo($group_checked); ?>/> <?php echo htmlspecialchars($membership_group); ?> </input>
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
	 * Adds post meta box when editing a post
	 */
	public function post_access_add_post_meta_boxes() {
		// Get post types to add the meta boxes to
		// Get all post types, including built-in WordPress post types and custom post types
		$post_types = get_post_types(array('public' => true));

		// Add meta box for post access
		add_meta_box(
			'wawp_post_access_meta_box_id', // ID
			'Wild Apricot Access Control', // title
			array($this, 'post_access_display'), // callback
			$post_types, // screen
			'side', // location of meta box
			'high' // priority in comparison to other meta boxes
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
	 * Sets up the post meta data for Wild Apricot access control if valid Wild Apricot credentials have already been entered
	 */
	public function post_access_meta_boxes_setup() {
		// Add meta boxes if and only if the Wild Apricot credentials have been entered and are valid
		// $valid_wa_credentials = get_option('wawp_wa_credentials_valid');
		$valid_wa_credentials = get_option(self::WA_CREDENTIALS_KEY);
		$valid_license = get_option(self::WAWP_LICENSES_KEY);
		if (!empty($valid_wa_credentials) && !empty($valid_license) && array_key_exists(CORE_SLUG, $valid_license) && $valid_license[CORE_SLUG] != '') {
			// Add meta boxes on the 'add_meta_boxes' hook
			add_action('add_meta_boxes', array($this, 'post_access_add_post_meta_boxes'));
		}
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
	 * Show membership levels on user profile
	 *
	 * @param WP_User $user is the user of the current profile
	 */
	public function show_membership_level_on_profile($user) {
		// Load in parameters from user's meta data
		$membership_level = get_user_meta($user->ID, WAIntegration::WA_MEMBERSHIP_LEVEL_KEY, true);
		$user_status = get_user_meta($user->ID, WAIntegration::WA_USER_STATUS_KEY, true);
		$wa_account_id = get_user_meta($user->ID, WAIntegration::WA_USER_ID_KEY, true);
		$organization = get_user_meta($user->ID, WAIntegration::WA_ORGANIZATION_KEY, true);
		$user_groups = get_user_meta($user->ID, WAIntegration::WA_MEMBER_GROUPS_KEY);
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
		// Check if user has valid Wild Apricot credentials, and if so, display them
		if (isset($membership_level) && isset($user_status) && isset($wa_account_id) && isset($organization) && isset($user_groups)) { // valid
			// Get custom fields
			$checked_custom_fields = get_option(self::LIST_OF_CHECKED_FIELDS);
			$all_custom_fields = get_option(self::LIST_OF_CUSTOM_FIELDS);
			// Display Wild Apricot parameters
			?>
			<h2>Wild Apricot Membership Details</h2>
			<table class="form-table">
				<!-- Wild Apricot Account ID -->
				<tr>
					<th><label>Account ID</label></th>
					<td>
					<?php
						echo '<label>' . $wa_account_id . '</label>';
					?>
					</td>
				</tr>
				<!-- Membership Level -->
				<tr>
					<th><label>Membership Level</label></th>
					<td>
					<?php
						echo '<label>' . $membership_level . '</label>';
					?>
					</td>
				</tr>
				<!-- User Status -->
				<tr>
					<th><label>User Status</label></th>
					<td>
					<?php
						echo '<label>' . $user_status . '</label>';
					?>
					</td>
				</tr>
				<!-- Organization -->
				<tr>
					<th><label>Organization</label></th>
					<td>
					<?php
						echo '<label>' . $organization . '</label>';
					?>
					</td>
				</tr>
				<!-- Groups -->
				<tr>
					<th><label>Groups</label></th>
					<td>
					<?php
						echo '<label>' . $group_list . '</label>';
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
							<th><label><?php echo($all_custom_fields[$custom_field]); ?></label></th>
							<td>
							<?php
								echo '<label>' . $field_saved_value . '</label>';
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
	 * Updates the user's Wild Apricot information in WordPress
	 *
	 * @param int $current_user_id The user's WordPress ID
	 */
	public function refresh_user_wa_info() {
		// Create WAWPApi with valid credentials
		$verified_data = WAWPApi::verify_valid_access_token();
		$admin_access_token = $verified_data['access_token'];
		$admin_account_id = $verified_data['wa_account_id'];
		$wawp_api = new WAWPApi($admin_access_token, $admin_account_id);
		// Refresh custom fields first
		$wawp_api->retrieve_custom_fields();
		// Get info for all Wild Apricot users
		$wawp_api->get_all_user_info();
	}

	/**
	 * Schedules the hourly event to update the user's Wild Apricot information in their WordPress profile
	 *
	 * @param int $user_id  User's WordPress ID
	 */
	public static function create_cron_for_user_refresh() {
		// Schedule event if it is not already scheduled
		if (!wp_next_scheduled(self::USER_REFRESH_HOOK)) {
			wp_schedule_event(time(), 'daily', self::USER_REFRESH_HOOK);
		}
	}

	/**
	 * Syncs Wild Apricot logged in user with WordPress user database
	 *
	 * @param string $login_data  The login response from the API
	 * @param string $login_email The email that the user has logged in with
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
		$wawp_api = new WAWPApi($access_token, $wa_user_id);
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
			// Add user's Wild Apricot membership level as another role
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
				echo $current_wp_user_id->get_error_message();
			}
			// Set user's status of being added by the plugin to true
			update_user_meta($current_wp_user_id, self::USER_ADDED_BY_PLUGIN, true);
		}

		// Add access token and secret token to user's metadata
		$dataEncryption = new DataEncryption();
		// Add Wild Apricot membership level to user's metadata
		update_user_meta($current_wp_user_id, WAIntegration::WA_MEMBERSHIP_LEVEL_ID_KEY, $membership_level_id);
		update_user_meta($current_wp_user_id, WAIntegration::WA_MEMBERSHIP_LEVEL_KEY, $membership_level);
		// Add Wild Apricot user status to user's metadata
		update_user_meta($current_wp_user_id, WAIntegration::WA_USER_STATUS_KEY, $user_status);
		// Add Wild Apricot organization to user's metadata
		update_user_meta($current_wp_user_id, WAIntegration::WA_ORGANIZATION_KEY, $organization);

		// Get list of custom fields that user should import
		$extra_custom_fields = get_option(self::LIST_OF_CHECKED_FIELDS);

		// Get groups
		// Loop through each field value until 'Group participation' is found
		$wild_apricot_user_id = '';
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
			// Find User ID
			if ($field_name == 'User ID') {
				$wild_apricot_user_id = $field_value['Value'];
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
		update_user_meta($current_wp_user_id, WAIntegration::WA_MEMBER_GROUPS_KEY, $user_groups_array);
		// Save user id
		update_user_meta($current_wp_user_id, WAIntegration::WA_USER_ID_KEY, $wild_apricot_user_id);

		// Log user into WP account
		wp_set_auth_cookie($current_wp_user_id, $remember_user, is_ssl());
	}

	/**
	 * Handles the redirect after the user is logged in
	 */
	public function create_user_and_redirect() {
		// Check that we are on the login page
		$login_page_id = get_option('wawp_wal_page_id');
		if (is_page($login_page_id)) {
			// Get id of last page from url
			// https://stackoverflow.com/questions/13652605/extracting-a-parameter-from-a-url-in-wordpress
			if (!empty($_POST['wawp_login_submit'])) {
				// Check that nonce is valid
				if (!wp_verify_nonce(wp_unslash($_POST['wawp_login_nonce_name']), 'wawp_login_nonce_action')) {
					// Redirect
					wp_die('Your login failed.');
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
				// Wild Apricot password requirements: https://gethelp.wildapricot.com/en/articles/22-passwords
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
				$remember_me_input = sanitize_text_field(wp_unslash($_POST['wawp_remember_me']));
				$remember_user = false;
				if ($remember_me_input == 'on') { // should remember user
					$remember_user = true;
				}

				// Send POST request to Wild Apricot API to log in if input is valid
				$login_attempt = WAWPApi::login_email_password($valid_login);
				// If login attempt is false, then the user could not log in
				if (!$login_attempt) {
					// Present user with log in error
					add_filter('the_content', array($this, 'add_login_error'));
					return;
				}
				// If we are here, then it means that we have not come across any errors, and the login is successful!
				$this->add_user_to_wp_database($login_attempt, $valid_login['email'], $remember_user);

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
		}
	}

	/**
	 * Creates the shortcode that holds the login form
	 *
	 * @return string Holds the HTML content of the form
	 */
	public function custom_login_form_shortcode() {
		// Get Wild Apricot URL
		$wild_apricot_url = get_option(self::WA_URL_KEY);
		if ($wild_apricot_url) {
			$dataEncryption = new DataEncryption();
			$wild_apricot_url =	esc_url($dataEncryption->decrypt($wild_apricot_url));
		}
		// Create page content -> login form
		ob_start(); ?>
			<div id="wawp_login-wrap">
				<p id="wawp_wa_login_direction">Log into your Wild Apricot account here to access content exclusive to Wild Apricot members!</p>
				<form method="post" action="">
					<?php wp_nonce_field("wawp_login_nonce_action", "wawp_login_nonce_name");?>
					<label for="wawp_login_email">Email:</label>
					<br><input type="text" id="wawp_login_email" name="wawp_login_email" placeholder="example@website.com">
					<br><label for="wawp_login_password">Password:</label>
					<br><input type="password" id="wawp_login_password" name="wawp_login_password" placeholder="***********" autocomplete="new-password">
					<!-- Remember Me -->
					<div id="wawp_remember_me_div">
						<br><label id="wawp_remember_me_label" for="wawp_remember_me">Remember me?</label>
						<input type="checkbox" id="wawp_remember_me" name="wawp_remember_me" checked>
					</div>
					<!-- Forgot password -->
					<br><label id="wawp_forgot_password"><a href="<?php echo esc_url($wild_apricot_url . '/Sys/ResetPasswordRequest'); ?>" target="_blank" rel="noopener noreferrer">Forgot Password?</a></label>
					<br><input type="submit" id="wawp_login_submit" name="wawp_login_submit" value="Submit">
				</form>
			</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Create login and logout buttons in the menu
	 *
	 * @param  string $items  HTML of menu items
	 * @param  array  $args   Arguments supplied to the filter
	 * @return string $items  The updated items with the login/logout button
	 */
	// see: https://developer.wordpress.org/reference/functions/wp_create_nav_menu/
	// Also: https://www.wpbeginner.com/wp-themes/how-to-add-custom-items-to-specific-wordpress-menus/
	public function create_wa_login_logout($items, $args) {
		// First, check if Wild Apricot credentials and the license is valid
		if (self::valid_wa_credentials() && Addon::has_valid_license(CORE_SLUG)) {
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
								// Check that user is synced with Wild Apricot
								$current_users_id = get_current_user_id();
								$users_wa_id = get_user_meta($current_users_id, self::WA_USER_ID_KEY);
								// Check if user ID actually exists
								if (!empty($users_wa_id) && $users_wa_id != '') { // User has been synced with Wild Apricot
									// Check if user's status is within the allowed status(es)
									$users_status = get_user_meta($current_users_id, self::WA_USER_STATUS_KEY);
									$users_status = $users_status[0];
									$allowed_statuses = get_option('wawp_restriction_status_name');
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
									$intersect_groups = array_intersect(array_keys($users_member_groups), $page_member_groups);
									$intersect_level = in_array($user_member_level, $page_member_levels);
									if ((empty($intersect_groups) && !$intersect_level) || !$valid_status) { // the user can't see this page!
										// Remove this element from the menu
										$user_can_see = false;
									}
								} else {
									// User has not been synced with Wild Apricot; they therefore cannot see this in the menu
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
			// Check if user is logged in or logged out, now an array
			$menus_to_add_button = get_option(self::MENU_LOCATIONS_KEY);

			// EDIT: Feb. 17, 2021
			// If the theme location is empty, then we will just add the login button by default
			if (empty($args->theme_location)) {
				if (is_user_logged_in()) {
					$items .= '<li id="wawp_login_logout_button" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="'. wp_logout_url(esc_url(get_permalink($current_page_id))) .'">Log Out</a></li>';
				} else {
					$items .= '<li id="wawp_login_logout_button" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="'. $login_url .'">Log In</a></li>';
				}
			}

			//class hardcoded in to match theme. in the future, give users text box so they could put this themselves?
			if(!empty($menus_to_add_button)) {
				foreach ($menus_to_add_button as $menu_to_add_button) {
					if (is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Logout
						$items .= '<li id="wawp_login_logout_button" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="'. wp_logout_url(esc_url(get_permalink($current_page_id))) .'">Log Out</a></li>';
					} elseif (!is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Login
						$items .= '<li id="wawp_login_logout_button" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="'. $login_url .'">Log In</a></li>';
					}
				}
			}
		}
		return $items;
	}
	/**
	 * Returns the post meta values pertaining to Wild Apricot.
	 * The list of restricted groups and levels, flag of whether the post is restricted or not, and the restriction message.
	 *
	 * @param array $meta metadata of a post.
	 * @return array array of the restricted groups and levels, each in their own
	 * respective element.
	 */
	public function get_wa_post_meta($meta) {
		$restricted_groups = array();
		$restricted_levels = array();
		$is_restricted = 0;
		$individual_restriction_msg = '';

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
}
?>
