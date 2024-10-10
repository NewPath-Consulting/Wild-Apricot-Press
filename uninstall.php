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

delete_plugin_data();

/**
 * Conditionally deletes plugin data from the WordPress database and the
 * user metadata.
 *
 * @return void
 */
function delete_plugin_data() {
	// Get plugin deletion options and check if users and/or roles should be deleted
	$wawp_delete_options = get_option(WAWP\WA_Integration::WA_DELETE_OPTION);

	if (!$wawp_delete_options) return;

	if (in_array(WAWP\Admin_Settings::DELETE_DB_DATA, $wawp_delete_options)) {
		delete_all_db_data();
	}	

	if (in_array(WAWP\Admin_Settings::DELETE_USER_DATA, $wawp_delete_options)) {
		WAWP\WA_Integration::remove_wa_users();
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

    delete_option(WAWP\Log::LOG_OPTION_UPDATED);


	// delete license data
	WAWP\Addon::instance()::delete();

    delete_option(WAWP\WA_Integration::LIST_OF_ADMIN_FIELDS);


	// delete exception flag
	delete_option(WAWP\Exception::EXCEPTION_OPTION);

	// delete post meta added by the plugin
	delete_post_meta_by_key(WAWP\WA_Integration::RESTRICTED_GROUPS);
	delete_post_meta_by_key(WAWP\WA_Integration::RESTRICTED_LEVELS);
	delete_post_meta_by_key(WAWP\WA_Integration::IS_POST_RESTRICTED);
	delete_post_meta_by_key(WAWP\WA_Integration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY);

	// delete log file
	unlink(WAWP\Log::LOGFILE);

}

?>
