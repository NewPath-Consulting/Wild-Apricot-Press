<?php

namespace WAWP;

const ERROR_DIR = ABSPATH . 'wp-content/wapdebug.log';

const DEBUG_OPTION = 'wawp-debug';

class Log {

    static function wap_debug_log($msg, $name='') {
        $trace = debug_backtrace();

        $name = ('' == $name) ? $trace[1]['function'] : $name;
        $error_dir = ERROR_DIR;
        $msg = print_r($msg, true);
        $log = $name . " | " . $msg . "\n";
        error_log($log, 3, $error_dir);
    }

    static function can_debug() {}

    static function set_debug() {}
}

?>