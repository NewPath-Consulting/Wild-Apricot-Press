<?php
namespace WAWP;

class WAIntegration {
	private $credentials;

	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action( 'wawp_wal_credentials_obtained', array( $this, 'get_user_credentials') );
	}

	public function get_user_credentials() {
		// Load encrypted credentials from database
		$this->credentials =
	}
}
?>
