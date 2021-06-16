<?php
namespace WAWP;

class WAIntegration {
	private $credentials;
	private $decrypted_credentials;
	private $access_token;
	private $refresh_token;
	private $base_wa_url;
	private $log_menu_items; // holds list of elements in header that Login/Logout is added to
	private $wa_credentials_entered; // boolean if user has entered their Wild Apricot credentials

	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action('wawp_wal_credentials_obtained', array($this, 'load_user_credentials'));
		// Filter for adding to menu
		add_filter('wp_nav_menu_items', array($this, 'create_wa_login_logout'), 10, 2); // 2 arguments
		// Shortcode for login form
		add_shortcode('wawp_custom_login_form', array($this, 'custom_login_form_shortcode'));
		// Include any required files
		require_once('DataEncryption.php');
		// Check if Wild Apricot credentials have been entered
		$this->wa_credentials_entered = false;
		$wa_credentials = get_option('wawp_wal_name');
		if (isset($wa_credentials) && $wa_credentials != '') {
			$this->wa_credentials_entered = true;
		}
	}

	// Static function that checks if application codes (API Key, Client ID, and Client Secret are valid)
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
		do_action('qm/debug', $data);
		// Check if there is an error in body
		if (isset($data['error'])) { // error in body
			// Update successful login as false
			// update_option('wawp_wal_success', false);
			return false;
		}
		// Valid response; return data
		return $data;
	}

	// Creates login page that allows user to enter their email and password credentials for Wild Apricot
	// See: https://stackoverflow.com/questions/32314278/how-to-create-a-new-wordpress-page-programmatically
	// https://stackoverflow.com/questions/13848052/create-a-new-page-with-wp-insert-post
	private function create_login_page() {
		do_action('qm/debug', 'creating login page!');
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

	// Connect user to Wild Apricot API after obtaining their email and password
	// https://gethelp.wildapricot.com/en/articles/484
	private function login_email_password($valid_login) {
		// Encode API key
		$authorization_string = $this->decrypted_credentials['wawp_wal_client_id'] . ':' . $this->decrypted_credentials['wawp_wal_client_secret'];
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
		do_action('qm/debug', $response);
	}

	public function custom_login_form_shortcode() {
		// Boolean to hold if user has entered valid input
		$input_is_valid = true;

		// Create page content -> login form
		$page_content = '<p>Log into your Wild Apricot account here:</p>
			<form method="post">';
		$email_content = '<label for="wawp_login_email">Email:</label>
				<input type="text" id="wawp_login_email" name="wawp_login_email" placeholder="example@website.com">';
		$password_content =	'<br><label for="wawp_login_password">Password:</label>
				<input type="password" id="wawp_login_password" name="wawp_login_password" placeholder="***********">';
		$submit_content = '<br><input type="submit" name="wawp_login_submit" value="Submit">
			</form>';

		if (isset($_POST['wawp_login_submit'])) { // login form has been submitted
			// Create array to hold the valid input
			$valid_login = array();

			// Check email form
			$email_input = $_POST['wawp_login_email'];
			if (!empty($email_input) && is_email($email_input)) { // email is well-formed
				// Sanitize email
				$valid_login['email'] = sanitize_email($email_input);
			} else { // email is NOT well-formed
				// Output error
				$email_content .= '<p style="color:red;">Invalid email!</p>';
				$input_is_valid = false;
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
				$password_content .= '<p style="color:red;">Invalid password!</p>';
				$input_is_valid = false;
			}

			// Send POST request to Wild Apricot API to log in if input is valid
			if ($input_is_valid) {
				$this->login_email_password($valid_login);
			}
		}

		// Combine all content together
		$page_content .= $email_content . $password_content . $submit_content;
		return $page_content;
	}

	// Sets API Key, Client ID, and Client Secret to '' to signal that the credentials are invalid
	private function clear_wa_credentials() {
		// Loop through credentials array
		foreach ($this->credentials as $index => $credential) {
			// Set credential and decrypted credential as ''
			$credential = '';
			$this->decrypted_credentials[$index] = '';
		}
	}

	// Load Wild Apricot credentials that user has input in the WA4WP settings
	public function load_user_credentials() {
		// Load encrypted credentials from database
		$this->credentials = get_option('wawp_wal_name');
		// Decrypt credentials
		$this->decrypted_credentials = array();
		$dataEncryption = new DataEncryption();
		$this->decrypted_credentials['wawp_wal_api_key'] = $dataEncryption->decrypt($this->credentials['wawp_wal_api_key']);
		$this->decrypted_credentials['wawp_wal_client_id'] = $dataEncryption->decrypt($this->credentials['wawp_wal_client_id']);
		$this->decrypted_credentials['wawp_wal_client_secret'] = $dataEncryption->decrypt($this->credentials['wawp_wal_client_secret']);
		// Encode API key
		// $api_string = 'APIKEY:' . $this->decrypted_credentials['wawp_wal_api_key'];
		// $encoded_api_string = base64_encode($api_string);
		// // Perform API request
		// $args = array(
		// 	'headers' => array(
		// 		'Authorization' => 'Basic ' . $encoded_api_string,
		// 		'Content-type' => 'application/x-www-form-urlencoded'
		// 	),
		// 	'body' => 'grant_type=client_credentials&scope=auto&obtain_refresh_token=true'
		// );
		// $response = wp_remote_post('https://oauth.wildapricot.org/auth/token', $args);
		// do_action('qm/debug', $response);
		// // Check that api response is valid -> return false if it is invalid
		// if (is_wp_error($response)) {
		// 	$this->clear_wa_credentials();
		// 	return false;
		// }
		// // Response is valid -> get body from response
		// $body = wp_remote_retrieve_body($response);
		// // Decode JSON string to array with 'true' parameter
		// $data = json_decode($body, true);
		// do_action('qm/debug', $data);
		// // Check if there is an error with connecting
		// if (array_key_exists('error', $data)) { // error in body
		// 	// Update successful login as false
		// 	update_option('wawp_wal_success', false);
		// 	// Clear the invalid Wild Apricot credentials
		// 	$this->clear_wa_credentials();
		// } else { // valid credentials
		// 	update_option('wawp_wal_success', true);
		// 	$this->access_token = $data['access_token'];
		// 	$this->refresh_token = $data['refresh_token'];
		// 	// Add new login page
		// 	$this->create_login_page();
		// }

		// Get data from application
		// $data = WAIntegration::is_application_valid($this->decrypted_credentials['wawp_wal_api_key']);
		// Get required information
	}

	// Returns list of elements in menu
	public function get_log_menu_items() {
		return $this->log_menu_items;
	}

	// see: https://developer.wordpress.org/reference/functions/wp_create_nav_menu/
	// Also: https://www.wpbeginner.com/wp-themes/how-to-add-custom-items-to-specific-wordpress-menus/
	public function create_wa_login_logout($items, $args) {
		do_action('qm/debug', 'Adding login in menu!');
		// Get login url based on user's Wild Apricot site
		if ($this->wa_credentials_entered) {
			$login_url = home_url() . '/wa4wp-wild-apricot-login';
			do_action('qm/debug', 'theme location = ' . $args->theme_location);
			// Check if user is logged in or logged out
			$menu_to_add_button = get_option('wawp_wal_name')['wawp_wal_login_logout_button'];
			if (is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Logout
				$items .= '<li id="wawp_login_logout_button"><a href="'. wp_logout_url() .'">Log Out</a></li>';
			} elseif (!is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Login
				$items .= '<li id="wawp_login_logout_button"><a href="'. $login_url .'">Log In</a></li>';
			}
		}

		// Printing out
		// $menu_name = 'primary'; // will change this based on what user selects
		// $menu_items = wp_get_nav_menu_items($menu_name);
		// do_action('qm/debug', 'menu items: ' . $menu_items);

		// Save to database
		// update_option('wawp_wa-integration_login_menu_items', $items);

		$this->log_menu_items = $items;
		return $items;
	}

	// Login actions
	public function login_user_to_wa() {

	}

	// Logout actions
	public function logout_user_to_wa() {

	}
}
?>
