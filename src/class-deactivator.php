<?php

namespace WAWP;

require_once __DIR__ . '/class-admin-settings.php';
require_once __DIR__ . '/class-wa-api.php';
require_once __DIR__ . '/class-wa-integration.php';

class Deactivator {

	/**
	 * Deactivate function. Makes login page private and removes cron jobs.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Set WAWP WildApricot Login page to a Private page so that users cannot access it
		// https://wordpress.stackexchange.com/questions/273557/how-to-set-post-status-to-delete
		// First, get the id of the Login page
		$login_page_id = get_option(WA_Integration::LOGIN_PAGE_ID_OPT);
		if (isset($login_page_id) && $login_page_id != '') { // valid
			$login_page = get_post($login_page_id, 'ARRAY_A');
			$login_page['post_status'] = 'private';
			wp_update_post($login_page);
		}

		// Unschedule the CRON events
		WA_API::unsetCronJob(Admin_Settings::CRON_HOOK);
		WA_API::unsetCronJob(WA_Integration::USER_REFRESH_HOOK);
		WA_API::unsetCronJob(WA_Integration::LICENSE_CHECK_HOOK);
	}
}
?>
