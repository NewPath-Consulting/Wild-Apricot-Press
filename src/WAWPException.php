<?php

namespace WAWP;

require_once __DIR__ . '/Addon.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Log.php';

/**
 * This class and its children implement custom exceptions for WAP-specific errors.
 *
 * @copyright  2022 NewPath Consulting
 * @license    GNU General Public License 2.0
 * @version    Release: 1.0
 * @since      Class available since Release 1.0
 */
class Exception extends \Exception {

    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        // TODO: remove error logs in catch blocks
        if (!empty($message)) {
            Log::wap_log_error($message);
        }
        
    
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
    /**
     * General error handler for Wild Apricot credentials errors.
     *
     * @param string[] $input input from form used to return an array with
     * empty keys corresponding to the input keys.
     * @return string[] array of empty strings
     */
    public function WA_creds_handler($input) {
        Log::wap_log_error($this->getMessage());
        do_action('disable_plugin', CORE_SLUG, Addon::LICENSE_STATUS_NOT_ENTERED);
        return empty_string_array($input);
    }
}

class APIException extends Exception {}

class EncryptionException extends Exception {

    public static function openssl_error() {
        return 'OpenSSL not installed.';
    }

    public static function encrypt_error() {
        return 'There was an error with encryption.';
    }

    public static function decrypt_error() {
        return 'There was an error with decryption.';
    }
}