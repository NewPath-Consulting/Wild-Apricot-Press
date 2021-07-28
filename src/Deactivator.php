<?php

namespace WAWP;

class Deactivator {

	public static function deactivate() {
		// Deactivation code

		// Set WA4WP Wild Apricot Login page to a Private page so that users cannot access it
		// https://wordpress.stackexchange.com/questions/273557/how-to-set-post-status-to-delete
		// First, get the id of the Login page
		$login_page_id = get_option('wawp_wal_page_id');
		if (isset($login_page_id) && $login_page_id != '') { // valid
			$login_page = get_post($login_page_id, 'ARRAY_A');
			$login_page['post_status'] = 'private';
			wp_update_post($login_page);
		}

		// Remove custom, Wild Apricot roles
		// $old_wa_roles = get_option('wawp_all_levels_key');
        // if (!empty($old_wa_roles)) {
        //     // Loop through each role and delete it
        //     foreach ($old_wa_roles as $old_role) {
        //         remove_role('wawp_' . str_replace(' ', '', $old_role));
        //     }
        // }

		// Set valid Wild Apricot credentials to false because the plugin is not activated
		update_option('wawp_wa_credentials_valid', false);

		// Unschedule the CRON events
		// $next_event_timestamp = wp_next_scheduled('wawp_check_for_new_wa_data');
		// if ($next_event_timestamp) {
		// 	wp_unschedule_event($next_event_timestamp, 'wawp_check_for_new_wa_data');
		// }
		require_once('WAWPApi.php');
		WAWPApi::unsetCronJob('wawp_cron_refresh_memberships_hook');
		// Get user id
		// $user_id = get_option('wawp_cron_user_id');
		// $args = [
		// 	$user_id
		// ];
		WAWPApi::unsetCronJob('wawp_cron_refresh_user_hook');
	}
}
?>
