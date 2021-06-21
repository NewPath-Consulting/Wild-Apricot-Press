<?php
namespace WAWP;

/**
 * Class for managing the user's Wild Apricot account
 */
class WAIntegration {
	private $wa_credentials_entered; // boolean if user has entered their Wild Apricot credentials

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
		// Include any required files
		require_once('DataEncryption.php');
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
	 * Static function that checks if application codes (API Key, Client ID, and Client Secret are valid)
	 *
	 * @param string         $entered_api_key The Wild Apricot API Key to check
	 * @return array|boolean $data	          An array of the response from the WA API if the key is valid; false otherwise
	 */
	public static function is_application_valid($entered_api_key) {
		// Encode API key
		$api_string = 'APIKEY:' . $entered_api_key;
		$encoded_api_string = base64_encode($api_string);
		// Perform API request
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $encoded_api_string,
				'Content-type' => 'application/x-www-form-urlencoded'
			),
			'body' => 'grant_type=client_credentials&scope=auto&obtain_refresh_token=true'
		);
		$response = wp_remote_post('https://oauth.wildapricot.org/auth/token', $args);

		if (is_wp_error($response)) {
			return false;
		}
		// Get body of response
		$body = wp_remote_retrieve_body($response);
		// Get data from json response
		$data = json_decode($body, true);
		// Check if there is an error in body
		if (isset($data['error'])) { // error in body
			// Update successful login as false
			return false;
		}
		// Valid response; return data
		return $data;
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
	 * Connect user to Wild Apricot API after obtaining their email and password
	 *
	 * https://gethelp.wildapricot.com/en/articles/484
	 *
	 * @param array          $valid_login Holds the email and password entered into the login screen
	 * @return array|boolean $data        Returns the response from the WA API if the credentials are valid; false otherwise
	 */
	private function login_email_password($valid_login) {
		// Get decrypted credentials
		$decrypted_credentials = $this->load_user_credentials();
		// Encode API key
		$authorization_string = $decrypted_credentials['wawp_wal_client_id'] . ':' . $decrypted_credentials['wawp_wal_client_secret'];
		$encoded_authorization_string = base64_encode($authorization_string);
		// Perform API request
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $encoded_authorization_string,
				'Content-type' => 'application/x-www-form-urlencoded'
			),
			'body' => 'grant_type=password&username=' . $valid_login['email'] . '&password=' . $valid_login['password'] . '&scope=auto'
		);
		$response = wp_remote_post('https://oauth.wildapricot.org/auth/token', $args);

		// Get body of response to obtain the success or error of the response
		// If an error is returned, return false to end the request
		if (is_wp_error($response)) {
			return false;
		}
		// No errors -> parse the data
		$body = wp_remote_retrieve_body($response);
		// Decode JSON string
		$data = json_decode($body, true); // returns an array

		// If error is NOT one of the keys, then we have successfully logged in!
		if (isset($data['error'])) { // error with logging in
			return false;
		}
		// Success with logging in
		// Return required information (access token, refresh token, etc.)
		return $data;
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

				// Send POST request to Wild Apricot API to log in if input is valid
				$login_attempt = $this->login_email_password($valid_login);
				// If login attempt is false, then the user could not log in
				if (!$login_attempt) {
					// Present user with log in error
					add_filter('the_content', array($this, 'add_login_error'));
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
		}
	}

	/**
	 * Creates the shortcode that holds the login form
	 *
	 * @return string Holds the HTML content of the form
	 */
	public function custom_login_form_shortcode() {
		// Create page content -> login form
		$page_content = '<p>Log into your Wild Apricot account here:</p>
			<form method="post">';
		$email_content = '<label for="wawp_login_email">Email:</label>
				<input type="text" id="wawp_login_email" name="wawp_login_email" placeholder="example@website.com">';
		$password_content =	'<br><label for="wawp_login_password">Password:</label>
				<input type="password" id="wawp_login_password" name="wawp_login_password" placeholder="***********">';
		$submit_content = '<br><input type="submit" name="wawp_login_submit" value="Submit">
			</form>';

		// Combine all content together
		$page_content .= $email_content . $password_content . $submit_content;
		return $page_content;
	}

	/**
	 * Load Wild Apricot credentials that user has input in the WA4WP settings
	 *
	 * @return array $decrypted_credentials	Decrypted Wild Apricot credentials
	 */
	public function load_user_credentials() {
		// Load encrypted credentials from database
		$credentials = get_option('wawp_wal_name');
		// Decrypt credentials
		$dataEncryption = new DataEncryption();
		$decrypted_credentials['wawp_wal_api_key'] = $dataEncryption->decrypt($credentials['wawp_wal_api_key']);
		$decrypted_credentials['wawp_wal_client_id'] = $dataEncryption->decrypt($credentials['wawp_wal_client_id']);
		$decrypted_credentials['wawp_wal_client_secret'] = $dataEncryption->decrypt($credentials['wawp_wal_client_secret']);

		return $decrypted_credentials;
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
				$items .= '<li id="wawp_login_logout_button"><a href="'. wp_logout_url() .'">Log Out</a></li>';
			} elseif (!is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Login
				$items .= '<li id="wawp_login_logout_button"><a href="'. $login_url .'">Log In</a></li>';
			}
		}
		return $items;
	}
}
?>
