<?php
namespace WAWP;

class WAIntegration {
	private $credentials;
	private $access_token;
	private $refresh_token;
	private $base_wa_url;
	private $log_menu_items; // holds list of elements in header that Login/Logout is added to

	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action('wawp_wal_credentials_obtained', array($this, 'load_user_credentials'));
		// Filter for adding to menu
		add_filter('wp_nav_menu_items', array($this, 'create_wa_login_logout'), 10, 2); // 2 arguments
		// Include any required files
		require_once('DataEncryption.php');
	}

	// Creates login page that allows user to enter their email and password credentials for Wild Apricot
	// See: https://stackoverflow.com/questions/32314278/how-to-create-a-new-wordpress-page-programmatically
	// https://stackoverflow.com/questions/13848052/create-a-new-page-with-wp-insert-post
	private function create_login_page() {
		do_action('qm/debug', 'creating login page!');
		// Create page guid
		// $page_guid = site_url() . '/wawp-wa-login'; // check if this exists
		// Create metadata for hiding page from header
		// Create details of page
		$post_details = array(
			'post_title' => 'WAWP Wild Apricot Login',
			'post_content' => 'Login!',
			'post_status' => 'publish',
			'post_type' => 'page',
		);
		$page_id = wp_insert_post($post_details, FALSE);
		// Remove from header if it is automatically added
		$menu_with_button = 'primary'; // get this from settings
	}

	public function load_user_credentials() {
		// Load encrypted credentials from database
		$this->credentials = get_option('wawp_wal_name');
		// print_r($this->credentials);
		// do_action('qm/debug', 'api key: ' . $this->credentials['wawp_wal_api_key']);
		// do_action('qm/debug', 'client id: ' . $this->credentials['wawp_wal_client_id']);
		// do_action('qm/debug', 'client secret: ' . $this->credentials['wawp_wal_client_secret']);
		// Decrypt credentials
		$decrypted_credentials = array();
		$dataEncryption = new DataEncryption();
		$decrypted_credentials['wawp_wal_api_key'] = $dataEncryption->decrypt($this->credentials['wawp_wal_api_key']);
		$decrypted_credentials['wawp_wal_client_id'] = $dataEncryption->decrypt($this->credentials['wawp_wal_client_id']);
		$decrypted_credentials['wawp_wal_client_secret'] = $dataEncryption->decrypt($this->credentials['wawp_wal_client_secret']);
		// Echo values for testing
		// print_r($decrypted_credentials);
		// do_action('qm/debug', 'decrypt api key: ' . $decrypted_credentials['wawp_wal_api_key']);
		// do_action('qm/debug', 'decrypt client id: ' . $decrypted_credentials['wawp_wal_client_id']);
		// do_action('qm/debug', 'decrypt client secret: ' . $decrypted_credentials['wawp_wal_client_secret']);
		// Encode API key
		$api_string = 'APIKEY:' . $decrypted_credentials['wawp_wal_api_key'];
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
		do_action('qm/debug', $response);
		// Check that api response is valid -> return false if it is invalid
		if (is_wp_error($response)) {
			return false;
		}
		// Response is valid -> get body from response
		$body = wp_remote_retrieve_body($response);
		// Decode JSON string to array with 'true' parameter
		$data = json_decode($body, true);
		do_action('qm/debug', $data);
		$this->access_token = $data['access_token'];
		$this->refresh_token = $data['refresh_token'];

		// Add new login page
		$this->create_login_page();
	}

	public function get_base_api() {
		$api_args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Accept' => 'application/json',
				'User-Agent' => 'WildApricotForWordPress'
			),
		);
		$api_response = wp_remote_post('https://api.wildapricot.org/', $api_args);
		do_action('qm/debug', 'new api response!!!!' . $api_response);
		return $api_response;
	}

	// Returns list of elements in menu
	public function get_log_menu_items() {
		return $this->log_menu_items;
	}

	// see: https://developer.wordpress.org/reference/functions/wp_create_nav_menu/
	// Also: https://www.wpbeginner.com/wp-themes/how-to-add-custom-items-to-specific-wordpress-menus/
	// https://wordpress.stackexchange.com/questions/86868/remove-a-menu-item-in-menu
	public function create_wa_login_logout($items, $args) {
		do_action('qm/debug', 'Adding login in menu!');
		// Get login url based on user's Wild Apricot site
		$login_url = '';
		$logout_url = '';
		do_action('qm/debug', 'theme location = ' . $args->theme_location);
		// Check if user is logged in or logged out
		$menu_to_add_button = 'primary'; // get this from settings
		if (is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Logout
			$items .= '<li id="wawp_login_logout_button"><a href="'. wp_logout_url() .'">Log Out</a></li>';
		} elseif (!is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Login
			$items .= '<li id="wawp_login_logout_button"><a href="'. $login_url .'">Log In</a></li>';
		}

		// Printing out
		// $menu_name = 'primary'; // will change this based on what user selects
		// $menu_items = wp_get_nav_menu_items($menu_name);
		// do_action('qm/debug', 'menu items: ' . $menu_items);

		// Save to database
		update_option('wawp_wa-integration_login_menu_items', $items);

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
