<?php
require_once plugin_dir_path(__FILE__) . 'src/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'src/class-activator.php';
require_once plugin_dir_path(__FILE__) . 'src/class-addon.php';
require_once plugin_dir_path(__FILE__) . 'src/class-log.php';
require_once plugin_dir_path(__FILE__) . 'src/class-wa-integration.php';
require_once plugin_dir_path(__FILE__) . 'src/helpers.php';
require_once plugin_dir_path(__FILE__) . 'src/wap-exception.php';

if (!defined('WP_UNINSTALL_PLUGIN')) {
	wp_die(sprintf('%s should only be called when uninstalling the plugin.', __FILE__ ));
	exit;
}

// Delete the added post meta data to the restricted pages
// Get posts that contain the 'wawp_' post meta data
$wawp_find_posts_args = array('meta_key' => WAWP\WA_Integration::IS_POST_RESTRICTED, 'post_type' => 'any');
$wawp_posts_with_meta = get_posts($wawp_find_posts_args);
// Loop through each post and delete the associated 'wawp_' meta data from it
if (!empty($wawp_posts_with_meta)) {
	foreach ($wawp_posts_with_meta as $wawp_post) {
		// Get post ID
		$wawp_post_id = $wawp_post->ID;
		delete_post_meta($wawp_post_id, WAWP\WA_Integration::RESTRICTED_GROUPS);
		delete_post_meta($wawp_post_id, WAWP\WA_Integration::RESTRICTED_LEVELS);
		delete_post_meta($wawp_post_id, WAWP\WA_Integration::IS_POST_RESTRICTED);
		delete_post_meta($wawp_post_id, WAWP\WA_Integration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY);
	}
}




// Get plugin deletion options and check if users and/or roles should be deleted
$wawp_delete_options = get_option(WAWP\WA_Integration::WA_DELETE_OPTION);

if (in_array(WAWP\Admin_Settings::DELETE_DB_DATA, $wawp_delete_options)) {
	delete_all_db_data();
}	

if (in_array(WAWP\Admin_Settings::DELETE_USER_DATA, $wawp_delete_options)) {
	delete_all_user_data();	
}


/**
 * Deletes all data in the user meta created by the plugin.
 *
 * @return void
 */
function delete_all_user_data() {
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
	// Get WildApricot users by looping through each plugin role
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
		'meta_key' => WAWP\WA_Integration::USER_ADDED_BY_PLUGIN,
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

	// Delete user meta data associated with each remaining WildApricot user
	// Get users with WildApricot ID
	$wawp_users_args = array(
		'meta_key' => WAWP\WA_Integration::WA_USER_ID_KEY,
	);
	$wawp_users = get_users($wawp_users_args);
	// Loop through each user and remove their WildApricot associated meta data
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

/**
 * Deletes all data in `wp_options` and post/page meta created and stored by 
 * the plugin.
 *
 * @return void
 */
function delete_all_db_data() {

	// Delete WA authorized application data
	delete_transient(WAWP\WA_Integration::ADMIN_ACCESS_TOKEN_TRANSIENT);
	delete_transient(WAWP\WA_Integration::ADMIN_ACCOUNT_ID_TRANSIENT);
	delete_option(WAWP\WA_Integration::ADMIN_REFRESH_TOKEN_OPTION);
	delete_option(WAWP\WA_Integration::WA_URL_KEY);

	// delete options added in admin settings
	delete_option(WAWP\WA_Integration::MENU_LOCATIONS_KEY);
	delete_option(WAWP\WA_Integration::GLOBAL_RESTRICTED_STATUSES);
	delete_option(WAWP\WA_Integration::GLOBAL_RESTRICTION_MESSAGE);
	delete_option(WAWP\WA_Integration::LIST_OF_CHECKED_FIELDS);
	delete_option(WAWP\WA_Integration::WA_DELETE_OPTION);
	delete_option(WAWP\Log::LOG_OPTION);
	
	// delete authorized application credentials	
	delete_option(WAWP\WA_Integration::WA_CREDENTIALS_KEY);

	// delete license data
	WAWP\Addon::instance()::delete();

	// data from wild apricot
	delete_option(WAWP\WA_Integration::WA_ALL_MEMBERSHIPS_KEY);
	delete_option(WAWP\WA_Integration::WA_ALL_GROUPS_KEY);
	delete_option(WAWP\WA_Integration::LIST_OF_CUSTOM_FIELDS);
	
	// delete stored list of restricted post
	delete_option(WAWP\WA_Integration::ARRAY_OF_RESTRICTED_POSTS);

	// delete exception flag
	delete_option(WAWP\Exception::EXCEPTION_OPTION);

	// delete post meta added by the plugin
	delete_post_meta_by_key(WAWP\WA_Integration::RESTRICTED_GROUPS);
	delete_post_meta_by_key(WAWP\WA_Integration::RESTRICTED_LEVELS);
	delete_post_meta_by_key(WAWP\WA_Integration::IS_POST_RESTRICTED);
	delete_post_meta_by_key(WAWP\WA_Integration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY);

}

?>
