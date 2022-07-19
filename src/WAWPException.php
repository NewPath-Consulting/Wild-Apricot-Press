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
abstract class Exception extends \Exception {
    const EXCEPTION_OPTION = 'wawp-exception-type';
    /**
     * Constructor. Extended from PHP Exception.
     *
     * @param string $message
     * @param integer $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        if (!Addon::is_plugin_disabled()) {
            update_option(Addon::WAWP_DISABLED_OPTION, true);
        }
        update_option(self::EXCEPTION_OPTION, $this->get_error_type());

    }

    /**
     * Removes exception flag before calling functions that could throw exceptions
     * so errors don't get incorrectly reported but the flag will stay if 
     * the error persists.
     */
    public static abstract function remove_error();

    protected abstract function get_error_type();

    /**
     * Displays appropriate error message on admin screen. Added to admin_notices
     * hook when a fatal error has been encountered.
     *
     * @param string $error_type string containing a short description of the 
     * error to insert into the template.
     * @return void
     */
    public static function admin_notice_error_message_template($error_type) {
        echo "<div class='notice notice-error wawp-exception'>";
        echo "<h3>FATAL ERROR</h3>";
        echo "<p>Wild Apricot Press has encountered an error with ";
        esc_html_e($error_type);
        echo " and functionality must be disabled. Please correct the error so the plugin can continue. ";
        echo "More details can be found in the log file located in your WordPress directory in <code>wp-content/wapdebug.log</code>.</p>";
        echo "<p>Contact the <a href='talk.newpathconsulting.com'>NewPath Consulting team</a> for support.</p>";
        echo "</p></div>";
    } 
}

/**
 * Handles API exceptions. Child of custom Exception class.
 */
class APIException extends Exception {

    const ERROR_DESCRIPTION = 'connecting to Wild Apricot';

    public static function api_connection_error() {
        return 'There was an error connecting to the Wild Apricot API and the plugin has to be disabled. Please try again.';
    }

    public static function api_response_error() {
        return 'There was an error in the Wild Apricot API and the plugin has to be disabled. Please try again.';
    }

    protected function get_error_type() {
        return self::ERROR_DESCRIPTION;
    }

    public static function remove_error() {
        if (get_option(self::EXCEPTION_OPTION) == self::ERROR_DESCRIPTION) {
            delete_option(self::EXCEPTION_OPTION);
        }
    }

}

/**
 * Handles encryption/decryption exceptions. Child of custom Exception class.
 */
class EncryptionException extends Exception {

    const ERROR_DESCRIPTION = 'securing your data';

    public static function openssl_error() {
        return 'OpenSSL not installed.';
    }

    public static function encrypt_error() {
        return 'There was an error with encryption.';
    }

    public static function decrypt_error() {
        return 'There was an error with decryption.';
    }

    public static function remove_error() {
        if (get_option(self::EXCEPTION_OPTION) == self::ERROR_DESCRIPTION) {
            delete_option(self::EXCEPTION_OPTION);
        }
    }

    protected function get_error_type() {
        return self::ERROR_DESCRIPTION;
    }
}