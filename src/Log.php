<?php

namespace WAWP;

class Log {
    static function good_error_log($msg, $name='') {
        $trace = debug_backtrace();

        $name = ('' == $name) ? $trace[1]['function'] : $name;
        $error_dir = '/Users/natalieb/dev/npc/error.log';
        $msg = print_r($msg, true);
        $log = $name . " | " . $msg . "\n";
        error_log($log, 3, $error_dir);
    }

}

?>