<?php

namespace WAWP;

require_once __DIR__ . '/class-addon.php';
require_once __DIR__ . '/class-log.php';
require_once __DIR__ . '/class-wa-integration.php';
require_once __DIR__ . '/helpers.php';

/**
 * Interface for custom exceptions.
 * 
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @since 1.0b4
 * @copyright  2022 NewPath Consulting
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
     * 
     * @return void
     */
    public static function remove_error() {
        refresh_credentials();
        if (Addon::has_valid_license(CORE_SLUG) && WA_Integration::valid_wa_credentials() && self::fatal_error()) {
            // delete_option(self::EXCEPTION_OPTION);
            update_option(Addon::WAWP_DISABLED_OPTION, false);
        }

    }

    /**
     * Returns whether there's been a fatal error or not.
     *
     * @return string|bool option value, false if empty or DNE
     */
    public static function fatal_error() {
        return get_option(Exception::EXCEPTION_OPTION);
    }

    /**
     * Abstract function for child classes to implement.
     */
    protected abstract function get_error_type();

    public static function get_user_facing_error_message() {
        return '<div class="wap-exception">
        <h3>FATAL ERROR</h3><p>WildApricot Press has encountered a fatal error and must be disabled.
        Please contact your site administrator.</p></div>';
    }

    /**
     * Displays appropriate error message on admin screen. Added to admin_notices
     * hook when a fatal error has been encountered.
     *
     * @param string $error_type string containing a short description of the 
     * error to insert into the template.
     * @return void
     */
    public static function admin_notice_error_message_template($error_type) {
        echo "<div class='notice notice-error wap-exception'>";
        echo "<h3>FATAL ERROR</h3>";
        echo "<p>WildApricot Press has encountered an error with ";
        echo esc_html($error_type);
        echo " and functionality must be disabled. Please correct the error so the plugin can continue. ";
        echo "More details can be found in the log file located in your WordPress directory in <code>wp-content/wapdebug.log</code>.</p>";
        echo "<p>Contact the <a href='https://talk.newpathconsulting.com/'>NewPath Consulting team</a> for support.</p>";
        echo "</p></div>";
    } 
}

/**
 * Handles API exceptions. Child of custom Exception class.
 * 
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @since 1.0b4
 * @copyright  2022 NewPath Consulting
 */
class API_Exception extends Exception {

    /**
     * Exception descroption
     * 
     * @var string
     */
    const ERROR_DESCRIPTION = 'connecting to WildApricot';

    /**
     * Returns description for API connection error.
     *
     * @return string
     */
    public static function api_connection_error() {
        return 'There was an error connecting to the WildApricot API and the plugin has to be disabled. Please try again.';
    }

    /**
     * Returns description for API response error.
     *
     * @return string
     */
    public static function api_response_error() {
        return 'There was an error in the WildApricot API and the plugin has to be disabled. Please try again.';
    }

    /**
     * Returns API exception description.
     *
     * @return string
     */
    protected function get_error_type() {
        return self::ERROR_DESCRIPTION;
    }

    /**
     * Removes exception option.
     *
     * @return void
     */
    public static function remove_error() {
        if (get_option(self::EXCEPTION_OPTION) != self::ERROR_DESCRIPTION) return;
       
        // parent::remove_error();
        delete_option(self::EXCEPTION_OPTION);
    }

}

/**
 * Handles encryption exceptions. Child of custom Exception class.
 * 
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @since 1.0b4
 * @copyright  2022 NewPath Consulting
 */
class Encryption_Exception extends Exception {

    /**
     * Exception descroption
     * 
     * @var string
     */
    const ERROR_DESCRIPTION = 'encrypting your data';

    /**
     * Constructor.
     *
     * @param string $message
     * @param integer $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        if (empty($message)) $message = self::encrypt_error();
        parent::__construct($message, $code, $previous);
        if (!Addon::is_plugin_disabled()) {
            update_option(Addon::WAWP_DISABLED_OPTION, true);
        }
        update_option(self::EXCEPTION_OPTION, $this->get_error_type());

    }

    /**
     * Returns description of OpenSSL error.
     *
     * @return string
     */
    public static function openssl_error() {
        return 'OpenSSL not installed.';
    }

    /**
     * Returns description of encryption error.
     *
     * @return string
     */
    public static function encrypt_error() {
        return 'There was an error with encryption.';
    }

    /**
     * Removes exception option.
     *
     * @return void
     */
    public static function remove_error() {
        if (get_option(self::EXCEPTION_OPTION) != self::ERROR_DESCRIPTION) return;
       
        // parent::remove_error();
        delete_option(self::EXCEPTION_OPTION);
    }

    /**
     * Returns error description.
     *
     * @return string
     */
    protected function get_error_type() {
        return self::ERROR_DESCRIPTION;
    }
}

/**
 * Handles decryption exceptions. Child class of custom Exception class.
 * 
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @since 1.0b4
 * @copyright  2022 NewPath Consulting
 */
class Decryption_Exception extends Exception {
    const ERROR_DESCRIPTION = 'decrypting your data';

    public function __construct($message = '', $code = 0, \Throwable $previous = null) {
        if (empty($message)) $message = self::decrypt_error();
        parent::__construct($message, $code, $previous);
        if (!Addon::is_plugin_disabled()) {
            update_option(Addon::WAWP_DISABLED_OPTION, true);
        }
        update_option(self::EXCEPTION_OPTION, $this->get_error_type());

    }

    /**
     * Removes exception option.
     *
     * @return void
     */
    public static function remove_error() {
        if (get_option(self::EXCEPTION_OPTION) != self::ERROR_DESCRIPTION) return;
       
        // parent::remove_error();
        delete_option(self::EXCEPTION_OPTION);
    }

    /**
     * Returns decryption error description.
     *
     * @return string
     */
    public static function decrypt_error() {
        return 'There was an error with decryption.';
    }

    /** 
     * Returns error description.
     */
    protected function get_error_type() {
        return self::ERROR_DESCRIPTION;
    }
}