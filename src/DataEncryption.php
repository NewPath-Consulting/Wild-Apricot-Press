<?php
namespace WAWP;
class DataEncryption {
	// Holds key and salt values
	private $key;
	private $salt;

	// Constructor for class
	public function __construct() {
		$this->key = $this->get_default_key();
		$this->salt = $this->get_default_salt();
	}

	public function encrypt( $value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			Log::wap_log_error('OpenSSL not installed.');
			return $value;
		}

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = openssl_random_pseudo_bytes( $ivlen );

		$raw_value = openssl_encrypt( $value . $this->salt, $method, $this->key, 0, $iv );
		if ( ! $raw_value ) {
			Log::wap_log_error('Unable to encrypt');
			return false;
		}

		return base64_encode( $iv . $raw_value );
	}

	public function decrypt( $raw_value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			Log::wap_log_error('OpenSSL not installed.');
			return $raw_value;
		}

		$raw_value = base64_decode( $raw_value, true );

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = substr( $raw_value, 0, $ivlen );

		$raw_value = substr( $raw_value, $ivlen );

		$value = openssl_decrypt( $raw_value, $method, $this->key, 0, $iv );
		if ( ! $value || substr( $value, - strlen( $this->salt ) ) !== $this->salt ) {
			Log::wap_log_error('Unable to encrypt');
			return false;
		}

		return substr( $value, 0, - strlen( $this->salt ) );
	}

	private function get_default_key() {
		if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
			return LOGGED_IN_KEY;
		}
		// Error if we are down here
		Log::wap_log_error('No "logged in key" value set. Please set your LOGGED_IN_KEY in the "wp-config.php" file in your WordPress folder.');
		throw new Exception('No "logged in key" value set. Please set your LOGGED_IN_KEY in the "wp-config.php" file in your WordPress folder.');
	}

	private function get_default_salt() {
		if ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ) {
			return LOGGED_IN_SALT;
		}
		// Error if we are down here
		Log::wap_log_error('No "logged in salt" value set. Please set your LOGGED_IN_SALT in the "wp-config.php" file in your WordPress folder.');
		throw new Exception('No "logged in salt" value set. Please set your LOGGED_IN_SALT in the "wp-config.php" file in your WordPress folder.');
	}
}
?>
