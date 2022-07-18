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

    /**
     * Constructor. To be extended from PHP Exception.
     *
     * @param string $message
     * @param integer $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        update_option(Addon::WAWP_DISABLED_OPTION, true);
        $this->admin_notice_error_message();
    }

    protected abstract function admin_notice_error_message();

    /**
     * Disables the plugin. Added to init hook when a fatal error has been
     * encountered.
     *
     * @return void
     */
    public function init_disable_plugin() {
        disable_core();
        // $this->add_fatal_error_message();
    }

    /**
     * Displays appropriate error message on admin screen. Added to admin_notices
     * hook when a fatal error has been encountered.
     *
     * @param string $error_type string containing a short description of the 
     * error to insert into the template.
     * @return void
     */
    protected static function admin_notice_error_message_template($error_type) {
        return "<div class='notice notice-error'>
                    <h2>FATAL ERROR</h2>
                    <p>Wild Apricot Press has encountered an error with " . 
                    esc_html__($error_type) . ". Please correct the error so the plugin can continue.
                    More details can be found in the log file located in your WordPress directory in <code>wp-content/wapdebug.log</code>.</p>
                    <p>Contact the <a href='talk.newpathconsulting.com'>NewPath Consulting team</a> for support.</p>
                </p></div>";
    } 
}

/**
 * Handles API exceptions.
 */
class APIException extends Exception {
    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        add_action('admin_notices', 'WAWP\APIException::admin_notice_error_message', 1);
    }

    protected function admin_notice_error_message() {
        remove_action('admin_notices', 'WAWP\Activator::admin_notices_creds_check');
        echo parent::admin_notice_error_message_template(self::get_error_type());
    }

    public static function api_connection_error() {
        return 'There was an error connecting to the Wild Apricot API and the plugin has to be disabled. Please try again.';
    }

    public static function api_response_error() {
        return 'There was an error in the Wild Apricot API and the plugin has to be disabled. Please try again.';
    }

    public static function get_error_type() {
        return 'connecting to Wild Apricot';
    }

}

/**
 * Handles encryption/decryption exceptions.
 */
class EncryptionException extends Exception {

    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        add_action('admin_notices', 'WAWP\EncryptionException::admin_notice_error_message', 1);
    }

    protected function admin_notice_error_message() {
        echo parent::admin_notice_error_message_template(self::get_error_type());
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
        return 'securing your data';
    }
}