<?php
namespace WAWP;

class WAIntegration {
	private $credentials;

	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action('wawp_wal_credentials_obtained', array($this, 'load_user_credentials'));
		// Filter for adding the Wild Apricot login to navigation menu
		add_filter('wp_nav_menu_items', array($this, 'create_wa_login_logout'), 10, 2); // 2 arguments
		// Include any required files
		require_once('DataEncryption.php');
	}

	private function load_user_credentials() {
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
		do_action('qm/debug', 'decrypt api key: ' . $decrypted_credentials['wawp_wal_api_key']);
		do_action('qm/debug', 'decrypt client id: ' . $decrypted_credentials['wawp_wal_client_id']);
		do_action('qm/debug', 'decrypt client secret: ' . $decrypted_credentials['wawp_wal_client_secret']);
		// Encode API key
		$api_string = 'APIKEY:' . $decrypted_credentials['wawp_wal_api_key'];
		$encoded_api_string = base64_encode($api_string);
		// Perform API request
		$api_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $encoded_api_string,
				'Content-type' => 'application/x-www-form-urlencoded'
			),
			'body' => 'grant_type=client_credentials&scope=auto&obtain_refresh_token=true'
		);
		$api_response = wp_remote_post('https://oauth.wildapricot.org/auth/token', $api_args);
		do_action('qm/debug', $api_response);

		// Add navigation menu
	}

	// see: https://developer.wordpress.org/reference/functions/wp_create_nav_menu/
	// Also: https://www.wpbeginner.com/wp-themes/how-to-add-custom-items-to-specific-wordpress-menus/
	private function create_wa_login_logout($items, $args) {
		do_action('qm/debug', 'Adding login in menu!');
		// Check if user is logged in or logged out
		if (is_user_logged_in() && $args->theme_location == 'primary') {
			$items .= '<li><a href="'. wp_logout_url() .'">Log Out</a></li>';
		} elseif (!is_user_logged_in() && $args->theme_location == 'primary') {
			$items .= '<li><a href="'. site_url('wp-login.php') .'">Log In</a></li>';
		}
		return $items;
	}
}
?>
