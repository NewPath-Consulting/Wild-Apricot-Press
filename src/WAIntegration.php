<?php
namespace WAWP;

class WAIntegration {
	private $credentials;

	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action( 'wawp_wal_credentials_obtained', array( $this, 'load_user_credentials') );
		// Include any required files
		require_once('DataEncryption.php');
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
	}
}
?>
