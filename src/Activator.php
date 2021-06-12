<?php

namespace WAWP;

class Activator {

	/**
	 * Activates the WA4WP plugin.
	 *
	 * Write the full details of what happens here.
	 */
	public static function activate() {
		// Activation code

		// Log back into Wild Apricot if credentials are entered
		$stored_wa_credentials = get_option('wawp_wal_name');
		if (isset($stored_wa_credentials) && $stored_wa_credentials != '') {
			// Run credentials obtained hook, which will read in the credentials in WAIntegration.php
			do_action('wawp_wal_credentials_obtained');
		}
	}
}
?>
