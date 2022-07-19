<?php
namespace WAWP;

require_once __DIR__ . '/WAWPException.php';

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
			throw new EncryptionException(EncryptionException::openssl_error());
		}

		if (empty($value)) return $value;

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = openssl_random_pseudo_bytes( $ivlen );

		$raw_value = openssl_encrypt( $value . $this->salt, $method, $this->key, 0, $iv );
		if ( ! $raw_value ) {
			throw new EncryptionException(EncryptionException::encrypt_error());
		}

		return base64_encode( $iv . $raw_value );
	}

	public function decrypt( $raw_value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			throw new EncryptionException(EncryptionException::openssl_error());
		}

		if (empty($raw_value)) return $raw_value;

		$raw_value = base64_decode( $raw_value, true );

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = substr( $raw_value, 0, $ivlen );

		

		if (empty($raw_value)) return $raw_value;

		$value = openssl_decrypt( $raw_value, $method, $this->key, 0, $iv );
		if ( ! $value || substr( $value, - strlen( $this->salt ) ) !== $this->salt ) {
			throw new EncryptionException(EncryptionException::decrypt_error());
		}

		return substr( $value, 0, - strlen( $this->salt ) );
	}

	private function get_default_key() {
		if ( defined( 'LOGGED_IN_KEY' ) && !empty(LOGGED_IN_KEY) ) {
			return LOGGED_IN_KEY;
		}

		throw new EncryptionException('No "logged in key" value set. Please set your LOGGED_IN_KEY in the "wp-config.php" file in your WordPress folder.');
	}

	private function get_default_salt() {
		if ( defined( 'LOGGED_IN_SALT' ) && !empty(LOGGED_IN_SALT) ) {
			return LOGGED_IN_SALT;
		}

		throw new EncryptionException('No "logged in salt" value set. Please set your LOGGED_IN_SALT in the "wp-config.php" file in your WordPress folder.');
	}
}
?>
