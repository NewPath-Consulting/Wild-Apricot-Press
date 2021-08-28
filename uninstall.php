<?php
require_once plugin_dir_path(__FILE__) . 'src/Activator.php';
require_once plugin_dir_path(__FILE__) . 'src/Addon.php';
require_once plugin_dir_path(__FILE__) . 'src/WAIntegration.php';

use WAWP\Activator;
use WAWP\Addon;

if (!defined('WP_UNINSTALL_PLUGIN')) {
	wp_die(sprintf(__('%s should only be called when uninstalling the plugin.', 'wawp'), __FILE__ ));
	exit;
}

// Remove WAWP Login/Logout page
$wawp_wal_page_id = get_option('wawp_wal_page_id');
if (isset($wawp_wal_page_id) && $wawp_wal_page_id != '') {
	wp_delete_post($wawp_wal_page_id, true); // delete page entirely
}
delete_option('wawp_wal_page_id');

// Delete entries in wp_options table
delete_option('wawp_wal_name');
delete_option('wawp_wal_page_id');
delete_option('wawp_license_form_nonce');
delete_option('wawp_all_levels_key');
delete_option('wawp_all_groups_key');
// delete_option('wawp_wa_credentials_valid');
delete_option('wawp_restriction_name');
delete_option('wawp_restriction_status_name');
delete_option('wawp_list_of_custom_fields');
delete_option('wawp_fields_name');

// Delete the added post meta data to the restricted pages
// Get posts that contain the 'wawp_' post meta data
$wawp_find_posts_args = array('meta_key' => 'wawp_is_post_restricted', 'post_type' => 'any');
$wawp_posts_with_meta = get_posts($wawp_find_posts_args);
// Loop through each post and delete the associated 'wawp_' meta data from it
if (!empty($wawp_posts_with_meta)) {
	foreach ($wawp_posts_with_meta as $wawp_post) {
		// Get post ID
		$wawp_post_id = $wawp_post->ID;
		delete_post_meta($wawp_post_id, 'wawp_restricted_groups');
		delete_post_meta($wawp_post_id, 'wawp_restricted_levels');
		delete_post_meta($wawp_post_id, 'wawp_is_post_restricted');
		delete_post_meta($wawp_post_id, 'wawp_individual_restriction_message_key');
	}
}
// Delete restricted pages option value
delete_option('wawp_array_of_restricted_posts');
delete_option('wawp_admin_refresh_token');

// Delete transients, even if they have not expired yet
delete_transient('wawp_admin_access_token');
delete_transient('wawp_admin_account_id');

Addon::instance()::delete();

// Get plugin deletion options and check if users and/or roles should be deleted
$wawp_delete_options = get_option('wawp_delete_name');
if (!empty($wawp_delete_options)) {
	// Check if checkbox is checked
	if (in_array('wawp_delete_checkbox', $wawp_delete_options)) {
		// Get roles in WordPress user database
		$wawp_all_roles = wp_roles();
		$wawp_all_roles = (array) $wawp_all_roles;
		// Get role names
		$wawp_plugin_roles = array();
		if (!empty($wawp_all_roles) && array_key_exists('role_names', $wawp_all_roles)) {
			$wawp_all_role_names = $wawp_all_roles['role_names'];
			foreach ($wawp_all_role_names as $wawp_role_key => $wawp_role_name) {
				// Check if the role name starts with the prefix (wawp_)
				$wawp_prefix_role = substr($wawp_role_key, 0, 5);
				if ($wawp_prefix_role == 'wawp_') {
					// Add this level to the plugin roles (roles created by this plugin)
					$wawp_plugin_roles[] = $wawp_role_key;
				}
			}
		}
		// Get Wild Apricot users by looping through each plugin role
		foreach ($wawp_plugin_roles as $wawp_plugin_role) {
			$wawp_plugin_args = array('role' => $wawp_plugin_role);
			$wawp_users_by_role = get_users($wawp_plugin_args);
			// Remove plugin role from each of these users
			if (!empty($wawp_users_by_role)) {
				foreach ($wawp_users_by_role as $wawp_user) {
					// Remove role fro this user
					$wawp_user->remove_role($wawp_plugin_role);
				}
			}
			// delete this role entirely
			remove_role($wawp_plugin_role);
		}

		// Find users that have 'wawp_user_added_by_plugin' set to true
		$wawp_added_by_plugin_args = array(
			'meta_key' => WAWP\WAIntegration::USER_ADDED_BY_PLUGIN,
			'meta_value' => '1'
		);
		$wawp_users_added_by_plugin = get_users($wawp_added_by_plugin_args);
		// Loop through each user added by plugin
		foreach ($wawp_users_added_by_plugin as $wawp_user_plugin) {
			// Check that user has 1 or less roles
			$wawp_user_id = $wawp_user_plugin->ID;
			$wawp_user_meta = get_userdata($wawp_user_id);
			$wawp_user_roles = $wawp_user_meta->roles;
			if (count($wawp_user_roles) <= 1) {
				// We can delete this user
				wp_delete_user($wawp_user_id);
			}
		}

		// Delete user meta data associated with each remaining Wild Apricot user
		// Get users with Wild Apricot ID
		$wawp_users_args = array(
			'meta_key' => 'wawp_wa_user_id',
		);
		$wawp_users = get_users($wawp_users_args);
		// Loop through each user and remove their Wild Apricot associated meta data
		if (!empty($wawp_users)) {
			foreach ($wawp_users as $wawp_user) {
				// Get ID
				$wawp_user_id = $wawp_user->ID;
				// Get user meta data
				$wawp_user_meta_data = get_user_meta($wawp_user_id);
				// Find meta data starting with 'wawp_'
				foreach ($wawp_user_meta_data as $wawp_meta_data_entry => $wawp_meta_data_value) {
					if (substr($wawp_meta_data_entry, 0, 5) == 'wawp_') { // starts with 'wawp_'
						// Delete this user meta entry
						delete_user_meta($wawp_user_id, $wawp_meta_data_entry);
					}
				}
			}
		}
	}
}
delete_option('wawp_delete_name');
delete_option('wawp_menu_location_name');
delete_option('wawp_wa_url_key');

?>
