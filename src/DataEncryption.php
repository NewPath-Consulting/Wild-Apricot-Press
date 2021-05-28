<?php
class DataEncryption {
	// Holds key and salt values
	private $key;
	private $salt;

	// Constructor for class
	public function __construct() {
		$this->key = $this->get_default_key();
		$this->salt = $this->get_default_salt();
	}

	// Encrypts value
	public function encrypt( $value ) {
		// Check that openssl library exists
		if (!extension_loaded('openssl')) {
			return $value;
		}

		// Load encryption parameters
		$method = 'aes-256-ctr';
		$ivlen = openssl_cipher_iv_length($method);
		$iv = openssl_random_pseudo_bytes($ivlen);
	}

	// Decrypts value
	public function decrypt( $value ) {

	}

	private function get_default_key() {
		if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
			return LOGGED_IN_KEY;
		}
		// Error if we are down here
		throw new Exception('No "logged in key" value set. Please set your LOGGED_IN_KEY in the "wp-config.php" file in your WordPress folder.');
	}

	private function get_default_salt() {
		if ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ) {
			return LOGGED_IN_SALT;
		}
		// Error if we are down here
		throw new Exception('No "logged in salt" value set. Please set your LOGGED_IN_SALT in the "wp-config.php" file in your WordPress folder.');
	}
}
?>
