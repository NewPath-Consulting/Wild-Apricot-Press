<?php

namespace WAWP;

require_once __DIR__ . '/class-addon.php';
require_once __DIR__ . '/admin-settings.php';
require_once __DIR__ . '/class-log.php';
require_once __DIR__ . '/class-wa-integration.php';
require_once __DIR__ . '/wap-exception.php';

/**
 * Activation controller class
 * 
 * @since 1.0b1
 * @author Natalie Brotherton <natalie@newpathconsulting.com>
 * @copyright 2022 NewPath Consulting
 */
class Activator {

	/**
	 * @var string prefix for the license status option name
	 */
	const LICENSE_CHECK_OPTION = 'license-check-' . CORE_SLUG;


	/**
	 * Constructor for Activator class.
	 */
	public function __construct($filename) {
		register_activation_hook($filename, array($this, 'activate_plugin_callback'));

		add_action('admin_notices','WAWP\Activator::admin_notices_creds_check');

		Addon::instance()::new_addon(array(
			'slug' => CORE_SLUG,
			'name' => CORE_NAME,
			'filename' => $filename,
			'license_check_option' => self::LICENSE_CHECK_OPTION,
			'show_activation_notice' => Addon::WAWP_ACTIVATION_NOTICE_OPTION,
			'is_addon' => 0
		));

	}


	/**
	 * Activates the WAWP plugin.
	 *
	 * Checks if user has already entered valid WildApricot credentials
	 * and license key. If so, then the full WildApricot functionality is run.
	 * 
	 * @return void
	 */
	public static function activate_plugin_callback() {
		/**
		 * Log back into WildApricot if credentials are entered and a valid license key is provided
		 */

		// Call Addon's activation function
		// returns false & does disable_plugin if license is invalid/nonexistent
		
		if (!WA_Integration::valid_wa_credentials()) {
			do_action('disable_plugin', CORE_SLUG, Addon::LICENSE_STATUS_NOT_ENTERED);
			Log::wap_log_warning('Activation failed: missing WildApricot API credentials.');
		} else if (Addon::instance()::activate(CORE_SLUG)) {
			Log::wap_log_warning('Activation failed: missing license key for ' . CORE_NAME . '. Plugin functionality disabled.');
		}

		if (Addon::is_plugin_disabled()) {
			update_option(Addon::WAWP_ACTIVATION_NOTICE_OPTION, true);
			return;
		}

		// **** this code will only run if license AND wa credentials are valid ****
		// Run credentials obtained hook, which will read in the credentials in class-wa-integration.php
		do_action('wawp_wal_credentials_obtained');
		// Also create CRON event to refresh the membership levels/groups
		Settings::setup_cron_job();
	}


	/**
	 * Checks the status of the WA Authorization credentials and the license key
	 * Displays appropriate admin notice messages if either one is invalid or missing. 
	 * 
	 * @return void
	 */
	public static function admin_notices_creds_check() {
		$should_activation_show_notice = get_option(Addon::WAWP_ACTIVATION_NOTICE_OPTION);
		// only show wap notices on relevant pages: wap settings, installed plugins, and post editor
		if (!is_wawp_settings() && 
		   (!is_plugin_admin_page() && !$should_activation_show_notice) &&
		   !is_post_edit_page())
		{
			return;
		}

		// if there's been a fatal error, display message 
		$exception = Exception::fatal_error();
		if ($exception) {
			Exception::admin_notice_error_message_template($exception);
			return;
		}
		
		$valid_wa_creds = WA_Integration::valid_wa_credentials();
		$valid_license = Addon::instance()::has_valid_license(CORE_SLUG);

		// Check for minimun PHP version (7.4)
		if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 70400) {
			// remove plugin activated notice
			unset($_GET['activate']);
			// unsupported PHP version message
			self::unsupported_php_message();
		}

		// Check if required extension is installed
		if (!extension_loaded("mbstring")){
			// remove plugin activated notice
			unset($_GET['activate']);
			// missing requirements message
			self::missing_requirements_message();
		}

		// TODO: add is_post_editor to this conditional
		// only display these messages on wawp settings page or plugin page right after plugin is activated
		if (!$valid_wa_creds) {
			// remove plugin activated notice
			unset($_GET['activate']);
			Addon::update_show_activation_notice_option(CORE_SLUG, 0);
			if (!$valid_license) {
				/**
				 * if both creds are invalid show one message telling the user 
				 * to enter both instead of two separate messages
				 */
				self::empty_creds_message();
			} else if (Addon::is_plugin_disabled()) {
				self::empty_wa_message();
			}
			return;
		}

		// print out licensing messages
		Addon::instance()::license_admin_notices();
	}

	/**
	 * Prints admin notice informing of minimun requirements
	 *
	 * @return void
	 */
	private static function unsupported_php_message() {
		echo '<div class="notice notice-warning"><p>';
		echo '<strong>' . esc_html(CORE_NAME) . '</strong> requires the PHP verion 7.4 or higher.';
		echo '</p></div>';
	}

	/**
	 * Prints admin notice informing of minimun requirements
	 *
	 * @return void
	 */
	private static function missing_requirements_message() {
		echo '<div class="notice notice-warning"><p>';
		echo '<strong>' . esc_html(CORE_NAME) . '</strong> requires the <em>mbstring</em> extenstion to be enabled in PHP.';
		echo '</p></div>';
	}

	/**
	 * Prints admin notice prompting the user to enter their WA credentials and
	 * license key.
	 *
	 * @return void
	 */
	private static function empty_creds_message() {
		echo '<div class="notice notice-warning"><p>';
		echo 'Please enter your ';
		echo '<a href=' . esc_url(get_auth_menu_url()) . '>WildApricot credentials</a>';
		echo ' and ';
		echo '<a href=' . esc_url(get_licensing_menu_url()) . '> license key</a>';
		echo ' in order to use the <strong>' . esc_html(CORE_NAME) . '</strong> functionality.';
		echo '</p></div>';
	}


	/**
	 * Prints admin notice prompting the user to enter their WA credentials.
	 *
	 * @return void
	 */
	private static function empty_wa_message() {

		echo "<div class='notice notice-warning'><p>";
		echo "Please enter your WildApricot credentials";
		if (!is_wa_login_menu()) {
			echo " in <a href=" . esc_url(get_auth_menu_url()) . ">WildApricot Press > Authorization</a>";
		}
		echo " in order to use the <strong>" . esc_html(CORE_NAME) . "</strong> functionality.";
		echo "</p></div>";
	}

}