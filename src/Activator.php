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

		Addon::instance()::new_addon(array(
			'slug' => $slug,
			'name' => $plugin_name,
			'filename' => $filename,
			'license_check_option' => $this->license_req_option_name
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

		$license_status = Addon::get_license_check_option($this->slug);
		Log::good_error_log(WAIntegration::valid_wa_credentials());


		// these messages will only show directly after the license form submission
		if (license_submitted()) {
			Log::good_error_log('license submenu');
			if ($license_status == 'true') {
				Addon::valid_license_key_notice($this->slug);
				return; // return early so plugins can be deactivated
			} 
		}
			} 
			
			if ($license_status == 'empty') {
				Addon::empty_license_key_notice($this->slug);
			} else if ($license_status == 'invalid') {
				Addon::invalid_license_key_notice($this->slug);
			}
		} else {
			// show the license key prompt if it hasn't been entered yet
			// don't show it if WA credentials haven't been entered yet
			if ($license_status == 'false') {
				// add_action('admin_notices', array($this, 'prompt_msg'));
				// do_action('admin_notices', array($this, 'prompt_msg'));
				Addon::license_key_prompt($this->slug);
			}
		}
	}

		delete_site_option($this->license_req_option_name);
	}

	private function empty_wa_message() {

		echo "<div class='notice notice-warning'><p>";
		echo "Please enter your Wild Apricot credentials in ";
		echo "<a href=" . admin_url('admin.php?page=wawp-login') . ">Wild Apricot Press > Authorization</a>.";
		echo "</p></div>";
	}

}
?>
