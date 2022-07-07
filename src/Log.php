<?php

namespace WAWP;

const ERROR_DIR = ABSPATH . 'wp-content/wapdebug.log';

class Log {
    static function good_error_log($msg, $name='') {
        $trace = debug_backtrace();

        $name = ('' == $name) ? $trace[1]['function'] : $name;
        $error_dir = ERROR_DIR;
        $msg = print_r($msg, true);
        $log = $name . " | " . $msg . "\n";
        error_log($log, 3, $error_dir);
    }

}

?>