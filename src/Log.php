<?php

namespace WAWP;

/**
 * This class manages custom log messages for WAP and its blocks.
 *
 * @copyright  2022 NewPath Consulting
 * @license    GNU General Public License 2.0
 * @version    Release: 1.0
 * @since      Class available since Release 1.0
 */
class Log {

    /**
     * Log message types. 
     * 
     * @var string
     */
    const LOG_ERROR = 'ERROR';
    const LOG_WARNING = 'WARNING';
    const LOG_DEBUG = 'DEBUG';

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

    static public function can_debug() {
        return get_option(Log::LOG_OPTION);
    }

    /**
     * Print an error message to the log file.
     *
     * @param string $msg message to print
     * @param int $severity severity of the issue
     * @return void
     */
    static public function wap_log_error($msg, $severity) {
        self::print_message($msg, self::LOG_ERROR);
    }

    /**
     * Prints a warning message to the log file.
     *
     * @param string $msg message to print
     * @return void
     */
    static public function wap_log_warning($msg) {}

    static public function wap_log_debug($msg) {
        self::print_message($msg, self::LOG_DEBUG);
    }

    /**
     * Prints a log message to the designated log file defined in LOGFILE 
     * in the following format
     * 
     * [date] TYPE OF LOG MESSAGE | Name-of-Plugin | file.php:line# function() | message
     * 
     * see get_function_string for specifics on function format.
     * 
     * @param string $msg message to print to log file
     * @param string $error_type type of log: error, warning, or debug
     * @return void
     */
    static private function print_message($msg, $error_type) : void {
        $backtrace = debug_backtrace();

        // collect caller info to print to logfile
        $function = self::get_function($backtrace[2]);
        $line = $backtrace[1]['line'];
        $file = basename($backtrace[1]['file']);
        $plugin = self::get_plugin_name($backtrace[1]['file']);

        $date = self::get_current_datetime();

        // use print_r to format arrays and objects
        $msg = print_r($msg, true);

        // TODO: print severity for error messages
        // format log message and print it to the logfile
        $log_msg = sprintf("[%s] %s | %s | %s:%s%s | %s\n",
            $date,
            $error_type,
            $plugin,
            $file,
            $line,
            $function,
            $msg
        );

        error_log($log_msg, 3, self::LOGFILE);
    }

    /**
     * Returns the time and date.
     * Example format: Thu Jul 07, 2022 19:38
     *
     * @return string
     */
    static private function get_current_datetime() : string {
        // TODO: fix timezone
        return date("D M d, Y G:i");
    }

    static private function get_filename($filename) {

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
    static private function get_function($backtrace) {
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

    static private function set_debug() {}
}

?>