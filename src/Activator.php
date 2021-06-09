<?php

namespace WAWP;

require_once __DIR__ . '/Addon.php';

// use WAWP\Addon;

class Activator {

	private $slug;
	private $filename;
	private $plugin_name;

	private $license_req_option_name;

	public function __construct($slug, $filename, $plugin_name) {
		$this->slug = $slug;
		$this->filename = $filename;
		$this->plugin_name = $plugin_name;
		$this->license_req_option_name = 'license-check-' . $slug;

		$this->register_license_hooks();
	}

	// /**
	//  * Activates the WA4WP plugin.
	//  *
	//  * Write the full details of what happens here.
	//  */
	// public function activate() {}

	public function activate_plugin_callback() {
		$license_exists = Addon::instance()::has_license($this->slug);
		if (!$license_exists) {
			// if there is no license for the plugin/addon, deactivate
			update_option($this->license_req_option_name, 'false');
		} else {
			delete_option($this->license_req_option_name);
		}
	}

	public function register_license_hooks() {
		if (get_option($this->license_req_option_name)) {
			add_action('admin_init', array($this, 'force_deactivate'));
			add_action('admin_notices', array($this, 'show_activation_error'));
		}
	}

	public function show_activation_error() {
		echo "<div class='error'><p>";
        echo "Please enter a valid license key for " . $this->plugin_name . " in WA4WP > Licensing. </p></div>";

		remove_action('admin_notices', array($this, 'show_deactivation_error'));
	}

	public function force_deactivate() {
		deactivate_plugins($this->filename);
	}
}
?>
