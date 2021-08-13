<?php

namespace WAWP;

require_once __DIR__ . '/Addon.php';

// use WAWP\Addon;

class Activator {

	const CORE = 'wawp';

	private $slug;
	private $filename;
	private $plugin_name;

	private $license_req_option_name;


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
			'filename' => $filename
		)));
	}

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
			// Set valid Wild Apricot credentials to true
			// update_option('wawp_wa_credentials_valid', true);
			// Run credentials obtained hook, which will read in the credentials in WAIntegration.php
			do_action('wawp_wal_credentials_obtained');
			// Also create CRON event to refresh the membership levels/groups
			require_once('MySettingsPage.php');
			MySettingsPage::setup_cron_job();
		}
	}

	public function activate_plugin_callback() {
		$this->activate();
		$license_exists = Addon::instance()::has_license($this->slug);
		if (!$license_exists) {
			update_option($this->license_req_option_name, 'false');
		} else {
			delete_option($this->license_req_option_name);
		}
	}

	public function register_license_hooks() {
		$opt = get_option($this->license_req_option_name);
		if ($opt == 'false' || $opt == 'invalid') {
			add_action('admin_init', array($this, 'force_deactivate'));
		}
	}

	public function license_admin_notices() {
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
			echo "<a href=" . admin_url('admin.php?page=wawp-licensing') . ">WA4WP > Licensing</a>.";
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

		// Check if valid Wild Apricot credentials have been entered -> if not, output an alert
		$entered_wa_credentials = get_option('wawp_wal_name');
		if (empty($entered_wa_credentials)) {
			// Wild Apricot has not been configured -> output alert
			echo "<div class='notice notice-warning'><p>";
			echo "Please enter your Wild Apricot credentials for <strong>" . $this->plugin_name . "</strong> in ";
			echo "<a href=" . admin_url('admin.php?page=wawp-login') . ">WA4WP > Authorization</a>.";
			echo "</p></div>";
		}
	}

	public function force_deactivate() {
		if ($this->slug !== self::CORE) {
			deactivate_plugins($this->filename);
		}
		remove_action('admin_init', array($this, 'force_deactivate'));
	}

}
?>
