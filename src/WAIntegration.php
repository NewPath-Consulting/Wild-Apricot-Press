<?php
namespace WAWP;

class WAIntegration {
	private $credentials;

	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action( 'wawp_wal_credentials_obtained', array( $this, 'load_user_credentials') );
	}

	public function load_user_credentials() {
		// Load encrypted credentials from database
		$this->credentials = get_option( 'wawp_wal_name' );
		// Decrypt credentials

		// Perform API request
		$body = array(
			'grant_type' => 'grant_type=client_credentials&scope=auto&obtain_refresh_token=true'
		);
	}
}
?>
