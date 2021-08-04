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
delete_option('wawp_wa_credentials_valid');
delete_option('wawp_restriction_name');
delete_option('wawp_restriction_status_name');
delete_option('wawp_list_of_custom_fields');
delete_option('wawp_fields_name');

// Delete the added post meta data to the restricted pages
// Get array of restricted pages
$restricted_pages = get_option('wawp_array_of_restricted_posts');
// Loop through each page and delete our extra post meta
if (!empty($restricted_pages)) {
	foreach ($restricted_pages as $restricted_page_id) {
		delete_post_meta($restricted_page_id, 'wawp_restricted_groups');
		delete_post_meta($restricted_page_id, 'wawp_restricted_levels');
		delete_post_meta($restricted_page_id, 'wawp_is_post_restricted');
		delete_post_meta($restricted_page_id, 'wawp_individual_restriction_message_key');
	}
}
// Delete restricted pages option value
delete_option('wawp_array_of_restricted_posts');
delete_option('wawp_cron_user_id');
delete_option('wawp_admin_refresh_token');

// Delete transients, even if they have not expired yet
delete_transient('wawp_admin_access_token');
delete_transient('wawp_admin_account_id');

Addon::instance()::delete();

// Debugging
function my_log_file( $msg, $name = '' )
{
	// Print the name of the calling function if $name is left empty
	$trace=debug_backtrace();
	$name = ( '' == $name ) ? $trace[1]['function'] : $name;

	$error_dir = '/Applications/MAMP/logs/php_error.log';
	$msg = print_r( $msg, true );
	$log = $name . "  |  " . $msg . "\n";
	error_log( $log, 3, $error_dir );
}

// Get plugin deletion options and check if users and/or roles should be deleted
$delete_options = get_option('wawp_delete_name');
if (!empty($delete_options)) {
	// Check if checkbox is checked
	if (in_array('wawp_delete_checkbox', $delete_options)) {
		// Delete user meta data associated with each Wild Apricot user
		// Get users with Wild Apricot ID
		$users_args = array(
			'meta_key' => 'wawp_wa_user_id',
		);
		$wa_users = get_users($users_args);
		// Loop through each user and remove their Wild Apricot associated meta data
		if (!empty($wa_users)) {
			foreach ($wa_users as $wa_user) {
				// Get ID
				$wa_user_id = $wa_user->ID;
				// Get user meta data
				$wa_user_meta_data = get_user_meta($wa_user_id);
				// Find meta data starting with 'wawp_'
				// my_log_file($wa_user_meta_data);
				foreach ($wa_user_meta_data as $meta_data_entry => $meta_data_value) {
					if (substr($meta_data_entry, 0, 5) == 'wawp_') { // starts with 'wawp_'
						// Delete this user meta entry
						delete_user_meta($wa_user_id, $meta_data_entry);
					}
				}
			}
		}


		// Get roles in WordPress user database
		$all_roles = wp_roles();
		$all_roles = (array) $all_roles;
		// my_log_file($all_roles);
		// Get role names
		$plugin_roles = array();
		if (!empty($all_roles) && array_key_exists('role_names', $all_roles)) {
			$all_role_names = $all_roles['role_names'];
			foreach ($all_role_names as $role_key => $role_name) {
				// Check if the role name starts with the prefix (wawp_)
				$prefix_role = substr($role_key, 0, 5);
				if ($prefix_role == 'wawp_') {
					// Add this level to the plugin roles (roles created by this plugin)
					$plugin_roles[] = $role_key;
				}
			}
		}
		// my_log_file($plugin_roles);
		// Check if roles should be deleted
		// $roles_delete = in_array('wawp_delete_checkbox_1', $delete_options);
		// $users_delete = in_array('wawp_delete_checkbox_0', $delete_options);
			// Get Wild Apricot users by looping through each plugin role
		foreach ($plugin_roles as $plugin_role) {
			$args = array('role' => $plugin_role);
			$wa_users_by_role = get_users($args);
			// Remove plugin role from each of these users
			// my_log_file($wa_users_by_role);
			if (!empty($wa_users_by_role)) {
				foreach ($wa_users_by_role as $user) {
					// Remove role fro this user
					$user->remove_role($plugin_role);
				}
			}
			// delete this role entirely
			remove_role($plugin_role);
		}

		// Find users that have 'wawp_user_added_by_plugin' set to true
		$added_by_plugin_args = array(
			'meta_key' => WAWP\WAIntegration::USER_ADDED_BY_PLUGIN,
			'meta_value' => '1'
		);
		$users_added_by_plugin = get_users($added_by_plugin_args);
		my_log_file($users_added_by_plugin);
		// Loop through each user added by plugin
		foreach ($users_added_by_plugin as $user_plugin) {
			// Check that user has 1 or less roles
			$user_id = $user_plugin->ID;
			$user_meta = get_userdata($user_id);
			$user_roles = $user_meta->roles;
			if (count($user_roles) <= 1) {
				// We can delete this user
				wp_delete_user($user_id);
			}
		}
	}
}
delete_option('wawp_delete_name');

?>
