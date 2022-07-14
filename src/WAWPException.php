<?php

namespace WAWP;

require_once __DIR__ . '/Addon.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Log.php';

/**
 * Interface for custom exceptions.
 *
 * @copyright  2022 NewPath Consulting
 * @license    GNU General Public License 2.0
 * @version    Release: 1.0
 * @since      Class available since Release 1.0
 */
class Exception extends \Exception {

    /**
     * Constructor. To be extended from PHP Exception.
     *
     * @param string $message
     * @param integer $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);

        add_action('init', array($this ,'error_handler'));
        add_action('init', array($this, 'admin_notice_error_message'));
        // remove invalid creds admin notice
    }

    /**
     * Disables the plugin. Added to init hook when a fatal error has been
     * encountered.
     *
     * @return void
     */
    public static function error_handler() {
        disable_core();
    }

    /**
     * Displays appropriate error message on admin screen. Added to admin_notices
     * hook when a fatal error has been encountered.
     *
     * @return void
     */
    public static function admin_notice_error_message() {
        echo "<div class='notice notice-error<p>";
        echo "FATAL ERROR: Wild Apricot Press has encountered an error with encrypting your data. Please correct the error so the plugin can continue. More details can be found in the log file located in your WordPress directory in <code>wp-content/wapdebug.log</code>.";
        echo "<p>Contact the <a href='talk.newpathconsulting.com'>NewPath Consulting team</a> for support.</p>";
        echo "</p></div>";
    }


}

/**
 * Handles API exceptions.
 */
class APIException extends Exception {
    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public static function admin_notice_error_message() {
        echo "<div class='notice notice-error<p>";
        echo "FATAL ERROR: There has been an internal error with the Wild Apricot API and the plugin must be disabled. Please contact your site administrator";
        echo "</p></div>";
    }

    public static function api_connection_error() {
        return 'There was an error connecting to the Wild Apricot API and the plugin has to be disabled. Please try again.';
    }

    public static function api_response_error() {
        return 'There was an error in the Wild Apricot API and the plugin has to be disabled. Please try again.';
    }

    public static function get_error_type() {
        
    }

}

/**
 * Handles encryption/decryption exceptions.
 */
class EncryptionException extends Exception {

    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public static function admin_notice_error_message() {
        echo "<div class='notice notice-error<p>";
        echo "FATAL ERROR: Wild Apricot Press has encountered an error with encrypting your data. Please correct the error so the plugin can continue. More details can be found in the log file located in your WordPress directory in <code>wp-content/wapdebug.log</code>.";
        echo "<p>Contact the <a href='talk.newpathconsulting.com'>NewPath Consulting team</a> for support.</p>";
        echo "</p></div>";
    }

    public static function openssl_error() {
        return 'OpenSSL not installed.';
    }

    public static function encrypt_error() {
        return 'There was an error with encryption.';
    }

    public static function decrypt_error() {
        return 'There was an error with decryption.';
    }

    public static function get_error_type() {
        return 'encrypting your data';
    }
}