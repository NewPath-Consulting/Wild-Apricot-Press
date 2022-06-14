<?php

namespace WAWP;

require_once __DIR__ . '/Addon.php';
require_once __DIR__ . '/WAIntegration.php';

class Activator {

	const CORE = 'wawp';

	private $slug;
	private $filename;
	private $plugin_name;

	private $license_req_option_name;

	/**
	 * Constructor for Activator class
	 */
	public function __construct($slug, $filename, $plugin_name) {
		$this->slug = $slug;
		$this->filename = $filename;
		$this->plugin_name = $plugin_name;
		$this->license_req_option_name = 'license-check-' . $slug;

		register_activation_hook($filename, array($this, 'activate_plugin_callback'));

		$this->register_license_hooks();

		add_action('admin_notices', array($this, 'license_admin_notices'));


		Addon::instance()::new_addon(array($slug => array(
			'title' => $plugin_name,
			'filename' => $filename,
			'license-check-option' => $this->license_req_option_name
		)));

	}

	/**
	 * Activates the WAWP plugin.
	 *
	 * Checks if user has already entered valid Wild Apricot credentials and license key
	 * -> If so, then the full Wild Apricot functionality is run
	 */
	public static function activate() {
		// Log back into Wild Apricot if credentials are entered and a valid license key is provided
		$stored_wa_credentials = get_option('wawp_wal_name');
		$wawp_licenses = get_option(WAIntegration::WAWP_LICENSES_KEY);
		if (isset($stored_wa_credentials) && $stored_wa_credentials != '' && !empty($wawp_licenses) && array_key_exists('wawp', $wawp_licenses) && $wawp_licenses['wawp'] != '') {
			// Run credentials obtained hook, which will read in the credentials in WAIntegration.php
			do_action('wawp_wal_credentials_obtained');
			// Also create CRON event to refresh the membership levels/groups
			require_once('MySettingsPage.php');
			MySettingsPage::setup_cron_job();
		}
	}

	/**
	 * Activates each plugin (including add-ons)
	 */
	public function activate_plugin_callback() {
		$this->activate();
		$license_exists = Addon::instance()::has_license($this->slug);
		if (!$license_exists) {
			update_option($this->license_req_option_name, 'false');
		} else {
			delete_option($this->license_req_option_name);
		}
	}

	/**
	 * Registers license hooks
	 */
	public function register_license_hooks() {
		$opt = get_option($this->license_req_option_name);
		if ($opt == 'false' || $opt == 'invalid') {
			add_action('admin_init', array($this, 'force_deactivate'));
		}
	}

	/**
	 * Displays notices in the WordPress admin menu to remind the user to enter their Wild Apricot credentials or other license keys
	 */
	public function license_admin_notices() {
		// Check if valid Wild Apricot credentials have been entered -> if not, output an alert
		$entered_wa_credentials = get_option('wawp_wal_name');
		$entered_license_keys = get_option(WAIntegration::WAWP_LICENSES_KEY);
		if (empty($entered_wa_credentials) || $entered_wa_credentials['wawp_wal_api_key'] == '') {
			// Wild Apricot has not been configured -> output alert
			echo "<div class='notice notice-warning'><p>";
			echo "Please enter your Wild Apricot credentials for <strong>" . esc_attr($this->plugin_name) . "</strong> in ";
			echo "<a href=" . admin_url('admin.php?page=wawp-login') . ">Wild Apricot Press > Authorization</a>.";
			echo "</p></div>";
		} else if (empty($entered_license_keys)) { // WAWP credentials have been entered but the license key has not
			echo "<div class='notice notice-warning'><p>";
			echo "Don't forget to enter the license key for <strong>" . esc_attr($this->plugin_name) . "</strong> in ";
			echo "<a href=" . admin_url('admin.php?page=wawp-licensing') . ">Wild Apricot Press > Licensing</a> ";
			echo "in order to activate the plugin's functionality!";
			echo "</p></div>";
		}

		// Check status of license, and instruct the user what to do next
		$option = get_option($this->license_req_option_name);

		if ($option == 'true') { // if license key is valid
			echo "<div class='notice notice-success is-dismissible'><p>";
			echo "Saved license key for <strong>" . $this->plugin_name . "</strong>.</p>";
			if ($this->slug != self::CORE) {
				echo "<p>Activating plugin.</p>";
			}
			echo "</div>";
		} else if ($option == 'false') { // missing license key
			echo "<div class='notice notice-warning'><p>";
			echo "Please enter a valid license key for <strong>" . $this->plugin_name . "</strong> in ";
			echo "<a href=" . admin_url('admin.php?page=wawp-licensing') . ">Wild Apricot Press > Licensing</a>.";
			echo "</p></div>";
			unset($_GET['activate']); // prevents printing "Plugin activated" message
		} else if ($option == 'invalid') { // invalid license entered
			echo "<div class='notice notice-error is-dismissible'><p>";
			echo "Invalid key entered for <strong>" . $this->plugin_name . "</strong>.</p>";
			if ($this->slug != self::CORE) {
				echo "<p>Deactivating plugin.</p>";
			}
			echo "</div>";
		}

		delete_site_option($this->license_req_option_name);
	}

	/**
	 * Forces plugin to deactivate if valid license is not found or entered yet
	 */
	public function force_deactivate() {
		if ($this->slug !== self::CORE) {
			deactivate_plugins($this->filename);
		}
		remove_action('admin_init', array($this, 'force_deactivate'));
	}

}
?>
