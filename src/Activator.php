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

	private $redirected = false;

	public function __construct($slug, $filename, $plugin_name) {
		$this->activate();

		$this->slug = $slug;
		$this->filename = $filename;
		$this->plugin_name = $plugin_name;
		$this->license_req_option_name = 'license-check-' . $slug;

		do_action('qm/debug', '{a} in constructor', ['a' => $slug]);

		register_activation_hook($filename, array($this, 'activate_plugin_callback'));

		$this->register_license_hooks();

		Addon::instance()::new_addon(array($slug => array(
			'title' => $plugin_name,
			'filename' => $filename
		)));
	}

	public function activate_plugin_callback() {
		do_action('qm/debug', '{a} in activate_plugin_callback', ['a' => $this->slug]);
		$license_exists = Addon::instance()::has_license($this->slug);
		if (!$license_exists) {
			// if there is no license for the plugin/addon, deactivate
			do_action('qm/debug', '{a} has no license', ['a' => $this->slug]);
			update_option($this->license_req_option_name, 'false');
			$this->redirected = false;
		} else {
			do_action('qm/debug', '{a} has license', ['a' => $this->slug]);
			delete_option($this->license_req_option_name);
		}
	}

	public function register_license_hooks() {
		if (get_option($this->license_req_option_name)) {
			add_action('admin_init', array($this, 'force_deactivate'));
			add_action('admin_notices', array($this, 'show_activation_error'));
		}
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
			// Run credentials obtained hook, which will read in the credentials in WAIntegration.php
			do_action('wawp_wal_credentials_obtained');
		}
	}

	public function show_activation_error() {
		echo "<div class='error'><p>";
        echo "Please enter a valid license key for " . $this->plugin_name . " in WA4WP > Licensing. </p></div>";

		if (is_plugin_active($this->filename)) {
			echo "<p>Deactivating plugin.</p>";
		}

		remove_action('admin_notices', array($this, 'show_activation_error'));
		delete_site_option($this->license_req_option_name);
		unset($_GET['activate']); // prevents printing "Plugin activated" message
	}

	public function force_deactivate() {
		if ($this->slug !== self::CORE) {
			deactivate_plugins($this->filename);
		}
		remove_action('admin_init', array($this, 'force_deactivate'));
		if (!$this->redirected) {
			// exit(wp_redirect(admin_url('admin.php?page=wawp-licensing')));
			$this->redirected = true;
		}
	}

}
?>
