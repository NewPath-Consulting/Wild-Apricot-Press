<?php

namespace WAWP;

require_once __DIR__ . '/admin-settings.php';
require_once __DIR__ . '/class-wa-api.php';
require_once __DIR__ . '/class-wa-integration.php';

/**
 * Deactivation controller.
 * 
 * @since 1.0b1
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @copyright 2022 NewPath Consulting
 */
class Deactivator {

	/**
	 * Deactivate function. Makes login page private and removes cron jobs.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Remove WAWP Login/Logout page
		$wawp_wal_page_id = get_option(WA_Integration::LOGIN_PAGE_ID_OPT);
		if (isset($wawp_wal_page_id) && $wawp_wal_page_id != '') {
			wp_delete_post($wawp_wal_page_id, true); // delete page entirely
		}

		// delete login page ID
		delete_option(WA_Integration::LOGIN_PAGE_ID_OPT);

		Addon::unschedule_all_cron_jobs();
	}
}
?>
