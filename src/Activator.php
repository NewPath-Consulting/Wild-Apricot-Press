<?php

namespace WAWP;

require_once __DIR__ . '/Addon.php';
require_once __DIR__ . '/WAIntegration.php';

class Activator {

	const CORE = 'wawp';
	const SHOW_NOTICE_ACTIVATION = 'show_notice_activation';

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

		add_action('admin_notices', array($this, 'admin_notices_creds_check'));

		Addon::instance()::new_addon(array(
			'slug' => $slug,
			'name' => $plugin_name,
			'filename' => $filename,
			'license_check_option' => $this->license_req_option_name,
			'is_addon' => 0 // only core calls activator, so is_addon will always be false here
		));

	}


	/**
	 * Activates the WAWP plugin.
	 *
	 * Checks if user has already entered valid Wild Apricot credentials and license key
	 * -> If so, then the full Wild Apricot functionality is run
	 */
	public static function activate() {
		// Log back into Wild Apricot if credentials are entered and a valid license key is provided
		if (WAIntegration::valid_wa_credentials() && Addon::instance()::has_valid_license(CORE_SLUG)) {
			// Run credentials obtained hook, which will read in the credentials in WAIntegration.php
			do_action('wawp_wal_credentials_obtained');
			// Also create CRON event to refresh the membership levels/groups
			require_once('MySettingsPage.php');
			MySettingsPage::setup_cron_job();
		} else {
			update_option(self::SHOW_NOTICE_ACTIVATION, 1);
			do_action('disable_plugin', CORE_SLUG);
		}
	}

	/**
	 * Activates each plugin (including add-ons)
	 */
	public function activate_plugin_callback() {
		$this->activate();
		$license_exists = Addon::instance()::has_valid_license($this->slug);
		if (!$license_exists) {
			update_option($this->license_req_option_name, 'false');
		} 
	}


	/**
	 * Checks the status of the WA Authorization credentials and the license key. Displays appropriate admin notice messages if either one is invalid or missing. 
	 */
	public function admin_notices_creds_check() {
		// only display these messages on wawp settings page or plugin page right after plugin is activated
		$should_show_notice = get_option(self::SHOW_NOTICE_ACTIVATION);
		if (!is_wawp_settings() && !($should_show_notice && is_plugin_page())) return;

		// if it's the plugin page, set show notice to false so it doesn't appear every time you see the plugins page
		// also prevent the activation message from showing
		if (is_plugin_page()) {
			update_option(self::SHOW_NOTICE_ACTIVATION, 0);
			unset($_GET['activate']);
		}


		$valid_wa_creds = WAIntegration::valid_wa_credentials();
		$valid_license = Addon::instance()::has_valid_license(CORE_SLUG);

		if (!$valid_wa_creds) {
			if (!$valid_license) {
				// if both creds are invalid show one message telling the user to enter both instead of two separate messages
				self::empty_creds_message();
			} else {
				self::empty_wa_message();
			}
			return;
		}

		// print out licensing messages
		Addon::instance()::license_admin_notices();

	}

	private function empty_creds_message() {
		echo "<div class='notice notice-warning'><p>";
		echo "Please enter your ";
		echo "<a href=" . admin_url('admin.php?page=wawp-login') . ">Wild Apricot credentials</a>";
		echo " and ";
		echo "<a href=" . admin_url('admin.php?page=wawp-licensing') . "> license key</a>";
		echo " in order to use the <strong>" . CORE_NAME . "</strong> functionality.";
		echo "</p></div>";
	}


	private function empty_wa_message() {

		echo "<div class='notice notice-warning'><p>";
		echo "Please enter your Wild Apricot credentials in ";
		echo "<a href=" . admin_url('admin.php?page=wawp-login') . ">Wild Apricot Press > Authorization</a>";
		echo " in order to use the <strong>" . CORE_NAME . "</strong> functionality.";
		echo "</p></div>";
	}

}
?>
