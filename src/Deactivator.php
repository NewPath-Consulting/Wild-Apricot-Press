<?php

// namespace WAWP;

class Deactivator {
	public static function deactivate() {
		// Delete entries in wp_options table
		require_once('Plugin.php');
		delete_option(Plugin::WILD_APRICOT_KEY);
	}
}
?>
