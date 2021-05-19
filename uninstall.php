<!-- Uninstall file -->
<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
	wp_die(sprintf(__('%s should only be called when uninstalling the plugin.', 'wawp'), __FILE__ ));
	exit;
}
