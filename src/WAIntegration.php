<?php
namespace WAWP;

class WAIntegration {
	private $credentials;

	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action( 'wawp_wal_credentials_obtained', array( $this, 'load_user_credentials') );
		// Include any required files
		include 'DataEncryption.php';
	}

	public function load_user_credentials() {
		// Load encrypted credentials from database
		$this->credentials = get_option( 'wawp_wal_name' );
		// Decrypt credentials
		$decrypted_credentials = array();
		$dataEncryption = new DataEncryption();
		$decrypted_credentials['wawp_wal_api_key'] = $dataEncryption->decrypt($this->credentials['wawp_wal_api_key']);
		$decrypted_credentials['wawp_wal_client_id'] = $dataEncryption->decrypt($this->credentials['wawp_wal_client_id']);
		$decrypted_credentials['wawp_wal_client_secret'] = $dataEncryption->decrypt($this->credentials['wawp_wal_client_secret']);
		// Perform API request
		$body = array(
			'grant_type' => 'grant_type=client_credentials&scope=auto&obtain_refresh_token=true'
		);
	}
}
?>
