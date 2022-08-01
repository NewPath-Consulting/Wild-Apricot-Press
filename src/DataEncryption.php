<?php
namespace WAWP;

require_once __DIR__ . '/WAWPException.php';

/**
 * Controls data encryption and decryption. Uses OpenSSL.
 */
class DataEncryption {
	// Holds key and salt values
	private $key;
	private $salt;

	// Constructor for class
	public function __construct() {	
		try {
			$this->key = $this->get_default_key();
			$this->salt = $this->get_default_salt();
		} catch (EncryptionException $e) {
			Log::wap_log_error($e->getMessage(), true);
		}


	}

	/**
	 * Encrypts data.
	 *
	 * @param mixed $value data to encrypt
	 * @return string encrypted data
	 * @throws EncryptionExcption thrown if OpenSSL does not exist or if encryption
	 * fails.
	 */
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
		} else {
			EncryptionException::remove_error();
		}
		

		return base64_encode( $iv . $raw_value );
	}

	/**
	 * Decrypts data.
	 *
	 * @param mixed $value data to decrypt
	 * @return string decrypted data
	 * @throws DecryptionException thrown if OpenSSL does not exist or if
	 * decryption fails.
	 */
	public function decrypt( $raw_value ) {
		// throw new DecryptionException('test decryption exception');
		if ( ! extension_loaded( 'openssl' ) ) {
			throw new DecryptionException(EncryptionException::openssl_error());
		}

		if (empty($raw_value)) return $raw_value;

		$raw_value = base64_decode( $raw_value, true );

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = substr( $raw_value, 0, $ivlen );

		

		$raw_value = substr( $raw_value, $ivlen );
		if (empty($raw_value)) return $raw_value;

		$value = openssl_decrypt( $raw_value, $method, $this->key, 0, $iv );
		if ( ! $value || substr( $value, - strlen( $this->salt ) ) !== $this->salt ) {
			throw new DecryptionException(DecryptionException::decrypt_error());
		} else {
			DecryptionException::remove_error();
		}
		

		return substr( $value, 0, - strlen( $this->salt ) );
	}

	/**
	 * Obtains private key from wp-config.
	 * 
	 * @return string private key
	 * @throws EncryptionException thrown if private key is not set.
	 */
	private function get_default_key() {
		if ( defined( 'LOGGED_IN_KEY' ) && !empty(LOGGED_IN_KEY) ) {
			return LOGGED_IN_KEY;
		}

		throw new EncryptionException('No "logged in key" value set. Please set your LOGGED_IN_KEY in the "wp-config.php" file in your WordPress folder.');
	}

	/**
	 * Obtains private salt from wp-config.
	 * 
	 * @return string private salt
	 * @throws EncryptionException thrown if private salt is not set.
	 */
	private function get_default_salt() {
		if ( defined( 'LOGGED_IN_SALT' ) && !empty(LOGGED_IN_SALT) ) {
			return LOGGED_IN_SALT;
		}

		throw new EncryptionException('No "logged in salt" value set. Please set your LOGGED_IN_SALT in the "wp-config.php" file in your WordPress folder.');
	}

}
?>
