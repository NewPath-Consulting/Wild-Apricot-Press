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

		// Define and get encryption parameters
		$encryption_method = 'aes-256-ctr';
		$cipher_iv_len = openssl_cipher_iv_length($encryption_method);
		$random_string = openssl_random_pseudo_bytes($cipher_iv_len);

		// Encrypt raw value
		$raw_value = openssl_encrypt($value . $this->salt, $encryption_method, $this->key, 0, $random_string);
		// If encrypting the raw value was a failure, return false
		if (!$raw_value) {
			return false;
		}

		// Return encoded data
		return base64_encode($random_string . $raw_value);
	}

	// Decrypts value
	public function decrypt( $raw_value ) {
		// Check that openssl library exists
		if (!extension_loaded('openssl')) {
			return $raw_value;
		}

		// Decode raw value
		$raw_value = base64_decode($raw_value, true);

		// Define and get encryption parameters
		$encryption_method = 'aes-256-ctr';
		$cipher_iv_len = openssl_cipher_iv_length($encryption_method);
		$random_string = substr($raw_value, 0, $cipher_iv_len);

		// Update raw value
		$raw_value = substr($raw_value, $cipher_iv_len);

		// Decrypt raw value to value
		$value = openssl_encrypt($raw_value, $encryption_method, $this->key, 0, $random_string);
		if (!$value || substr($value, -strlen($this->salt)) !== $this->salt) { // failed
			return false;
		}

		// Return back value
		return substr($value, 0, -strlen($this->salt));
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
