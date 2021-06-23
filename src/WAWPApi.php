<?php
namespace WAWP;

class WAWPApi {
	// Unique instance of class
	private static $instance = null;

	/**
     * Returns the instance of this class (singleton)
     * If the instance does not exist, creates one.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new WAWPApi();
        }
        return self::$instance;
    }

    // Debugging
	static function my_log_file( $msg, $name = '' )
	{
		// Print the name of the calling function if $name is left empty
		$trace=debug_backtrace();
		$name = ( '' == $name ) ? $trace[1]['function'] : $name;

		$error_dir = '/Applications/MAMP/logs/php_error.log';
		$msg = print_r( $msg, true );
		$log = $name . "  |  " . $msg . "\n";
		error_log( $log, 3, $error_dir );
	}

    private function __construct() {
        self::my_log_file('constructing wa api!');
    }
}
