<?php
namespace WAWP;

/**
 * Class for managing the user's Wild Apricot account
 */
class WAIntegration {
	// Constants
	const ACCESS_TOKEN_META_KEY = 'wawp_wa_access_token';
	const REFRESH_TOKEN_META_KEY = 'wawp_wa_refresh_token';
	const WA_USER_ID_KEY = 'wawp_wa_user_id';
	const WA_MEMBERSHIP_LEVEL_KEY = 'wawp_membership_level_key';
	const WA_USER_STATUS_KEY = 'wawp_user_status_key';

	private $wa_credentials_entered; // boolean if user has entered their Wild Apricot credentials
	private $access_token;
	private $wa_api_instance;

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
		// Action for scheduling token refresh
		add_action('wawp_wal_token_refresh', array($this, 'refresh_wa_session'));
		// Actions for displaying membership levels on user profile
		add_action('show_user_profile', array($this, 'show_membership_level_on_profile'));
		add_action('edit_user_profile', array($this, 'show_membership_level_on_profile'));
		// Include any required files
		require_once('DataEncryption.php');
		require_once('WAWPApi.php');
		// Check if Wild Apricot credentials have been entered
		$this->wa_credentials_entered = false;
		$wa_credentials = get_option('wawp_wal_name');
		if (isset($wa_credentials) && $wa_credentials != '') {
			$this->wa_credentials_entered = true;
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
	 * Creates login page that allows user to enter their email and password credentials for Wild Apricot
	 *
	 * See: https://stackoverflow.com/questions/32314278/how-to-create-a-new-wordpress-page-programmatically
	 * https://stackoverflow.com/questions/13848052/create-a-new-page-with-wp-insert-post
	 *
	 */
	public function create_login_page() {
		// Check if Login page exists first
		$login_page_id = get_option('wawp_wal_page_id');
		if (isset($login_page_id) && $login_page_id != '') { // Login page already exists
			// Set existing login page to publish
			$login_page = get_post($login_page_id, 'ARRAY_A');
			$login_page['post_status'] = 'publish';
			wp_update_post($login_page);
		} else { // Login page does not exist
			// Create details of page
			// See: https://wordpress.stackexchange.com/questions/222810/add-a-do-action-to-post-content-of-wp-insert-post
			$post_details = array(
				'post_title' => 'WA4WP Wild Apricot Login',
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_content' => '[wawp_custom_login_form]' // shortcode
			);
			$page_id = wp_insert_post($post_details, FALSE);
			// Add page id to options so that it can be removed on deactivation
			update_option('wawp_wal_page_id', $page_id);
		}
		// Remove from header if it is automatically added
		$menu_with_button = get_option('wawp_wal_name')['wawp_wal_login_logout_button']; // get this from settings
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
	 * Show membership levels on user profile
	 *
	 * @param WP_User $user is the user of the current profile
	 */
	public function show_membership_level_on_profile($user) {
		// Get membership levels from API
		// Get access token
		$membership_level = get_user_meta($user->ID, WAIntegration::WA_MEMBERSHIP_LEVEL_KEY, true);
		$user_status = get_user_meta($user->ID, WAIntegration::WA_USER_STATUS_KEY, true);
		if ($membership_level && $user_status) { // valid
			// Display membership levels in dropdown menu
			?>
			<h2>Wild Apricot Membership Details</h2>
			<table class="form-table">
				<tr>
					<th><label>Membership Level</label></th>
					<td>
					<?php
						echo '<label>' . $membership_level . '</label>';
					?>
					</td>
				</tr>
				<tr>
					<th><label>User Status</label></th>
					<td>
					<?php
						echo '<label>' . $user_status . '</label>';
					?>
					</td>
				</tr>
			</table>
			<?php
		}
	}

	/**
	 * Gets refresh token after a scheduled CRON task
	 */
	public function refresh_wa_session() {
		// Refresh token
		// https://gethelp.wildapricot.com/en/articles/484#:~:text=for%20this%20access_token-,How%20to%20refresh%20tokens,-To%20refresh%20the
	}

	/**
	 * Schedules a single CRON event
	 */
	private function schedule_refresh_event($time_seconds, $refresh_token) {
		// Define arguments
		$args = [
			$refresh_token
		];
		// Check that event is not already scheduled
		if (!wp_next_scheduled('wawp_wal_token_refresh', $args)) {
			// Schedule single event
			wp_schedule_single_event(time() + $time_seconds, 'wawp_wal_token_refresh', $args);
		}
	}

	/**
	 * Syncs Wild Apricot logged in user with WordPress user database
	 */
	public function add_user_to_wp_database($login_data, $login_email) {
		// Get access token and refresh token
		$access_token = $login_data['access_token'];
		$this->access_token = $access_token;
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
		$contact_info = $wawp_api->get_info_on_current_user($wa_user_id);
		// Get membership level
		$membership_level = $contact_info['MembershipLevel']['Name'];
		if (!isset($membership_level) || $membership_level == '') {
			$membership_level = '**None found**';
		}
		// Get user status
		$user_status = $contact_info['Status'];
		if (!isset($user_status) || $user_status == '') {
			$user_status = '**None found**';
		}

		// Check if WA email exists in the WP user database
		$current_wp_user_id = 0;
		if (email_exists($login_email)) { // email exists; we will update user
			// Get user
			$current_wp_user = get_user_by('email', $login_email); // returns WP_User
			$current_wp_user_id = $current_wp_user->ID;
			// Get user's permissions and user's membership level in Wild Apricot
		} else { // email does not exist; we will create a new user
			// Get values from contact info
			$first_name = $contact_info['FirstName'];
			$last_name = $contact_info['LastName'];
			// Set user data
			// Generated username is 'firstName . lastName' with a random number on the end, if necessary
			$generated_username = $first_name . $last_name;
			// Check if generated username has been taken. If so, append a random number to the end of the user-id until a unique username is set
			while (username_exists($generated_username)) {
				// Generate random number
				$random_user_num = wp_rand(0, 9);
				$generated_username .= $random_user_num;
			}
			$user_data = array(
				'user_email' => $login_email,
				'user_pass' => wp_generate_password(),
				'user_login' => $generated_username,
				'role' => 'subscriber',
				'display_name' => $first_name . ' ' . $last_name
			);
			// Insert user
			$current_wp_user_id = wp_insert_user($user_data); // returns user ID
			// Show error if necessary
			if (is_wp_error($new_user_id)) {
				echo $new_user_id->get_error_message();
			}
		}
		// Now that we have the ID of the user, modify user with Wild Apricot information

		// Add access token and secret token to user's metadata
		$dataEncryption = new DataEncryption();
		add_user_meta($current_wp_user_id, ACCESS_TOKEN_META_KEY, $dataEncryption->encrypt($access_token), true); // directly insert
		add_user_meta($current_wp_user_id, REFRESH_TOKEN_META_KEY, $dataEncryption->encrypt($refresh_token), true); // directly insert
		// Add Wild Apricot id to user's metadata
		add_user_meta($current_wp_user_id, WA_USER_ID_KEY, $wa_user_id, true);
		// Add Wild Apricot membership level to user's metadata
		add_user_meta($current_wp_user_id, WAIntegration::WA_MEMBERSHIP_LEVEL_KEY, $membership_level, true);
		// Add Wild Apricot user status to user's metadata
		add_user_meta($current_wp_user_id, WAIntegration::WA_USER_STATUS_KEY, $user_status, true);

		// Log user into WP account
		wp_set_auth_cookie($current_wp_user_id, 1, is_ssl());
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
			if (isset($_POST['wawp_login_submit'])) {
				// Create array to hold the valid input
				$valid_login = array();

				// Check email form
				$email_input = $_POST['wawp_login_email'];
				if (!empty($email_input) && is_email($email_input)) { // email is well-formed
					// Sanitize email
					$valid_login['email'] = sanitize_email($email_input);
				} else { // email is NOT well-formed
					// Output error
					add_filter('the_content', array($this, 'add_login_error'));
					return;
				}

				// Check password form
				// Wild Apricot password requirements: https://gethelp.wildapricot.com/en/articles/22-passwords
				// Any combination of letters, numbers, and characters (except spaces)
				$password_input = $_POST['wawp_login_password'];
				// https://stackoverflow.com/questions/1384965/how-do-i-use-preg-match-to-test-for-spaces
				if (!empty($password_input) && sanitize_text_field($password_input) == $password_input) { // not empty and valid password
					// Sanitize password
					$valid_login['password'] = sanitize_text_field($password_input);
				} else { // password is NOT valid
					// Output error
					add_filter('the_content', array($this, 'add_login_error'));
					return;
				}
				// Check that nonce is valid
				if (!wp_verify_nonce($_POST['wawp_login_nonce_name'], 'wawp_login_nonce_action')) {
					wp_die('Your nonce could not be verified.');
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
				$this->add_user_to_wp_database($login_attempt, $valid_login['email']);

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
		// Create page content -> login form
		ob_start(); ?>
			<p>Log into your Wild Apricot account here:</p>
			<form method="post" action="">
				<?php wp_nonce_field("wawp_login_nonce_action", "wawp_login_nonce_name");?>
				<label for="wawp_login_email">Email:</label>
				<input type="text" id="wawp_login_email" name="wawp_login_email" placeholder="example@website.com">
				<br><label for="wawp_login_password">Password:</label>
				<input type="password" id="wawp_login_password" name="wawp_login_password" placeholder="***********">
				<br><input type="submit" name="wawp_login_submit" value="Submit">
			</form>
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
		// Get login url based on user's Wild Apricot site
		// First, check if Wild Apricot credentials are valid
		$wa_credentials_saved = get_option('wawp_wal_name');
		if (isset($wa_credentials_saved) && isset($wa_credentials_saved['wawp_wal_api_key']) && $wa_credentials_saved['wawp_wal_api_key'] != '') {
			// Create login url
			// https://wp-mix.com/wordpress-difference-between-home_url-site_url/
			$login_url = esc_url(site_url() . '/index.php?pagename=wa4wp-wild-apricot-login');
			// Get current page id
			// https://wordpress.stackexchange.com/questions/161711/how-to-get-current-page-id-outside-the-loop
			$current_page_id = get_queried_object_id();
			$login_url = esc_url(add_query_arg(array(
				'redirectId' => $current_page_id,
			), $login_url));
			// Check if user is logged in or logged out
			$menu_to_add_button = get_option('wawp_wal_name')['wawp_wal_login_logout_button'];
			if (is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Logout
				$items .= '<li id="wawp_login_logout_button"><a href="'. wp_logout_url(esc_url(get_permalink($current_page_id))) .'">Log Out</a></li>';
			} elseif (!is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Login
				$items .= '<li id="wawp_login_logout_button"><a href="'. $login_url .'">Log In</a></li>';
			}
		}
		return $items;
	}
}
?>
