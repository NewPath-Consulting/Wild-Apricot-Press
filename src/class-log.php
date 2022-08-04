<?php

namespace WAWP;

require_once __DIR__ . '/class-wa-api.php';

/**
 * This class manages custom log messages for WAP and its blocks.
 *
 * @since 1.0b4
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @copyright 2022 NewPath Consulting
 */
class Log {

    /**
     * Log message types. 
     * 
     * @var string
     */
    const LOG_ERROR     = 'ERROR';
    const LOG_WARNING   = 'WARNING';
    const LOG_DEBUG     = 'DEBUG';
    const LOG_FATAL     = 'FATAL ERROR';

    /**
     * Path of the log file to which messages will be printed.
     * 
     * @var string
     */
    const LOGFILE = ABSPATH . 'wp-content/wapdebug.log';

    /**
     * The name of the option stored in the options table
     * which contains the user's debug setting.   
     */
    const LOG_OPTION = 'wawp_logfile';

    /**
     * Returns the log file flag. 
     *
     * @return string|bool true if log file is enabled, false if not.
     */
    static public function can_debug() {
        return get_option(Log::LOG_OPTION);
    }

    /**
     * Print an error message to the log file.
     *
     * @param mixed $msg message to print
     * @param bool $fatal whether the error is a fatal error or not. Fatal errors
     * will print to the log file regardless of whether the user has enabled
     * log messages.
     * @return void
     */
    static public function wap_log_error($msg, $fatal = false) {
        self::print_message($msg, $fatal ? self::LOG_FATAL : self::LOG_ERROR, $fatal);
    }

    /**
     * Prints a warning message to the log file.
     *
     * @param mixed $msg message to print
     * @return void
     */
    static public function wap_log_warning($msg) {
        self::print_message($msg, self::LOG_WARNING);
    }

    /**
     * Prints a debug message to the log file
     *
     * @param mixed $msg message to print
     * @return void
     */
    static public function wap_log_debug($msg) {
        self::print_message($msg, self::LOG_DEBUG);
    }

    /**
     * Prints a log message to the designated log file defined in LOGFILE 
     * in the following format:
     * 
     * [date] TYPE OF LOG MESSAGE | Name-of-Plugin | file.php:line# function() | message
     * 
     * see get_function_name_string for specifics on function format.
     * 
     * @param mixed $msg message to print to log file
     * @param string $error_type type of log: error, warning, or debug
     * @return void
     */
    static private function print_message($msg, $error_type) {
        if (!self::can_debug() && $error_type != self::LOG_FATAL) { return; }
        $backtrace = debug_backtrace();

        // collect caller info to print to logfile
        $function = self::get_function_name($backtrace[2]);
        $line = $backtrace[1]['line'];
        $file = basename($backtrace[1]['file']);
        $plugin = self::get_plugin_name($backtrace[1]['file']);



        $date = self::get_current_datetime();

        // use print_r to format arrays and objects
        $msg = print_r($msg, true);

        // try to obtain WA account ID
        try {
            $account_id = get_transient(WA_Integration::ADMIN_ACCOUNT_ID_TRANSIENT);
            $decryption = new Data_Encryption();
            $account_id = $decryption->decrypt($account_id);
        } catch (Decryption_Exception $e) {
            $account_id = '';
        }

        // conditionally construct log message based on whether the WA account
        // ID is present or not
        if (!empty($account_id)) {
            // format log message and print it to the logfile
            $log_msg = sprintf("[%s] %s | %s | Account ID: %s | %s:%s%s | %s\n",
                $date,
                $error_type,
                $plugin,
                $account_id,
                $file,
                $line,
                $function,
                $msg
            );
        } else {
            $log_msg = sprintf("[%s] %s | %s | %s:%s%s | %s\n",
                $date,
                $error_type,
                $plugin,
                $file,
                $line,
                $function,
                $msg
            );
        }


        error_log($log_msg, 3, self::LOGFILE);
    }

    /**
     * Returns the formatted time and date.
     * Example format: 7/13/2022, 1:46:11 PM
     *
     * @return string
     */
    static private function get_current_datetime() {
        $tz = wp_timezone_string();
        $timestamp = time();
        $dt = new \DateTime("now", new \DateTimeZone($tz));
        $dt->setTimestamp($timestamp);
        return $dt->format('n/j/Y, g:i:s A');
    }

    /**
     * Returns the appropriately formatted caller function string. 
     * 
     * Formatting is as follows
     * 
     * Function: function()
     * 
     * Static function in a class: class::function()
     * 
     * Non-static function in a class: class->function()
     * 
     * Caller is not inside a function: returns empty string
     *
     * @param string[] $backtrace array of information about the caller obtained
     * from the debug backtrace
     * @return string formatted class string
     */
    static private function get_function_name($backtrace) {
        $function = $backtrace['function'];

        if ($function == 'include_once' || $function == 'require_once') return '';

        $function = $function . '()';
        if (array_key_exists('type', $backtrace)) {
            $class = $backtrace['class'];
            return $function = ' ' . $class . $backtrace['type'] . $function;
        }
        
        $function = ' ' . $function;
        

        return $function;
    }

    /**
     * Parses the filename string to obtain the folder name of the plugin in which
     * the caller is located.
     *
     * @param string $filename
     * @return string folder name of the plugin
     */
    static private function get_plugin_name($filename) {
        $name = explode('plugins/', $filename)[1];
        $name = explode('/', $name)[0];

        return $name;
    }

}

?>