<?php
namespace WAWP;

require_once __DIR__ . '/Addon.php';

use WAWP\Addon;

use function PHPSTORM_META\map;

class MySettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );

        // Activate option in table if it does not exist yet
        // Currently, there is a WordPress bug that calls the 'sanitize' function twice if the option is not already in the database
        // See: https://core.trac.wordpress.org/ticket/21989
        if (!get_option('wawp_wal_name')) { // does not exist
            // Create option
            add_option('wawp_wal_name');
        }
    }

    // Debugging
	static function my_log_file( $msg, $name = '' )
	{
		// Print the name of the calling function if $name is left empty
		$trace=debug_backtrace();
		$name = ( '' == $name ) ? $trace[1]['function'] : $name;

		$error_dir = '/Applications/MAMP/logs/php_error.log';
		$msg = print_r( $msg, true );
		$log = $name . "  |  " . $msg . "\n";
		error_log( $log, 3, $error_dir );
	}

    /**
     * Add options page
     */
    public function add_settings_page()
    {
        // Create WA4WP admin page
        add_menu_page(
            'WA4WP Settings',
            'WA4WP Settings',
            'manage_options',
            'wawp-wal-admin',
            array( $this, 'create_admin_page' ),
			'dashicons-businesswoman',
			6
        );

		// Create Login sub-menu under WA4WP
		add_submenu_page(
			'wawp-wal-admin',
			'Wild Apricot Authorization',
			'Authorization',
			'manage_options',
			'wawp-login',
			array($this, 'create_login_page')
		);

        // Create submenu for license key forms
        add_submenu_page(
            'wawp-wal-admin',
            'Licensing',
            'Licensing',
            'manage_options',
            'wawp-licensing',
            array($this, 'wawp_licensing_page')
        );

        // Add new submenu here
    }

    /**
     * Settings page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'wawp_wal_name' );
    }

	/**
	 * Login page callback
	 */
	public function create_login_page() {
		$this->options = get_option( 'wawp_wal_name' );
		?>
        <div class="wrap">
			<h1>Connect Wild Apricot with WordPress!</h1>
			<div class="waSettings">
				<div class="loginChild">
					<p>In order to connect your Wild Apricot with your WordPress website, WA4WP requires the following credentials from your Wild Apricot account:</p>
					<ul>
						<li>API key</li>
						<li>Client ID</li>
						<li>Client secret</li>
					</ul>
					<p>If you currently do not have these credentials, no problem! Please follow the steps below to obtain them.</p>
					<ol>
						<li>First
					</ol>
				</div>
				<div class="loginChild">
					<form method="post" action="options.php">
					<?php
                        // Nonce for verification
                        wp_nonce_field('wawp_credentials_nonce_action', 'wawp_credentials_nonce_name');
						// This prints out all hidden setting fields
						settings_fields( 'wawp_wal_group' );
						do_settings_sections( 'wawp-wal-admin' );
						submit_button();
					?>
					</form>
					<!-- Check if form is valid -->
					<?php
                        // $successful_credentials_entered = get_option('wawp_wal_success');
						if (!isset($this->options['wawp_wal_api_key']) || !isset($this->options['wawp_wal_client_id']) || !isset($this->options['wawp_wal_client_secret']) || $this->options['wawp_wal_api_key'] == '' || $this->options['wawp_wal_client_id'] == '' || $this->options['wawp_wal_client_secret'] == '') { // not valid
							echo '<p style="color:red">Invalid credentials! Please try again!</p>';
                            do_action('wawp_wal_set_login_private');
						} else { // successful login
							echo '<p style="color:green">Success! Credentials saved!</p>';
                            // Implement hook here to tell Wild Apricot to connect to these credentials
                            do_action('wawp_wal_credentials_obtained');
						}
					?>
				</div>
			</div>
        </div>
        <?php
	}

    // Create license form page
    public function wawp_licensing_page() {
        ?>
        <div class="wrap">
            <form method="post" action="options.php">
            <?php
            settings_fields('wawp_license_keys');
            do_settings_sections('wawp_licensing');
            submit_button('Save', 'primary');
            ?> </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {

        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'wal_sanitize'),
            'default' => NULL
        );

		// Register setting
        register_setting(
            'wawp_wal_group', // Option group
            'wawp_wal_name', // Option name
            $register_args // Sanitize
        );

		// Create settings section
        add_settings_section(
            'wawp_wal_id', // ID
            'Wild Apricot Authorized Application Credentials', // Title
            array( $this, 'wal_print_section_info' ), // Callback
            'wawp-wal-admin' // Page
        );

		// Settings for API Key
        add_settings_field(
            'wawp_wal_api_key', // ID
            'API Key:', // Title
            array( $this, 'api_key_callback' ), // Callback
            'wawp-wal-admin', // Page
            'wawp_wal_id' // Section
        );

		// Settings for Client ID
        add_settings_field(
            'wawp_wal_client_id', // ID
            'Client ID:', // Title
            array( $this, 'client_id_callback' ), // Callback
            'wawp-wal-admin', // Page
            'wawp_wal_id' // Section
        );

		// Settings for Client Secret
		add_settings_field(
            'wawp_wal_client_secret', // ID
            'Client Secret:', // Title
            array( $this, 'client_secret_callback' ), // Callback
            'wawp-wal-admin', // Page
            'wawp_wal_id' // Section
        );

        // Settings for Menu to add Login/Logout button
        add_settings_field(
            'wawp_wal_login_logout_button', // ID
            'Menu:', // Title
            array( $this, 'login_logout_menu_callback' ), // Callback
            'wawp-wal-admin', // Page
            'wawp_wal_id' // Section
        );

        // Registering and adding settings for the license key forms
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'validate_license_form'),
            'default' => NULL
        );

        register_setting(
            'wawp_license_keys',
            'wawp_license_keys',
            $register_args
        );

        add_settings_section(
            'wawp_license',
            'License',
            array($this, 'license_print_info'),
            'wawp_licensing' // page
        );

        // Render the WAWP license form
        add_settings_field(
            'wawp_license_form', // ID
            'Wild Apricot for Wordpress', // title
            array($this, 'license_key_input'), // callback
            'wawp_licensing', // page
            'wawp_license', // section
            array('slug' => 'wawp', 'title' => 'Wild Apricot for Wordpress') // args for callback
        );

        // For each addon installed, render a license key form
        $addons = Addon::instance()::get_addons();
        foreach ($addons as $slug => $addon) {
            if ($slug == Activator::CORE) {continue;}
            $title = $addon['title'];
            add_settings_field(
                'wawp_license_form_' . $slug, // ID
                $title, // title
                array($this, 'license_key_input'), // callback
                'wawp_licensing', // page
                'wawp_license', // section
                array('slug' => $slug, 'title', $title) // args for callback
            );
        }
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function wal_sanitize( $input )
    {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_credentials_nonce_name'], 'wawp_credentials_nonce_action')) {
            wp_die('Your nonce could not be verified.');
        }
		// Create valid array that will hold the valid input
		$valid = array();

        // Get valid api key
        $valid['wawp_wal_api_key'] = preg_replace(
            '/[^A-Za-z0-9]+/', // match only letters and numbers
            '',
            $input['wawp_wal_api_key']
        );
        // Get valid client id
        $valid['wawp_wal_client_id'] = preg_replace(
            '/[^A-Za-z0-9]+/', // match only letters and numbers
            '',
            $input['wawp_wal_client_id']
        );
        // Get valid client secret
        $valid['wawp_wal_client_secret'] = preg_replace(
            '/[^A-Za-z0-9]+/', // match only letters and numbers
            '',
            $input['wawp_wal_client_secret']
        );

        // Encrypt values if they are valid
        $entered_valid = true;
        $entered_api_key = '';
        require_once('DataEncryption.php');
		$dataEncryption = new DataEncryption();
        // Check if inputs are valid
        if ($valid['wawp_wal_api_key'] !== $input['wawp_wal_api_key'] || $input['wawp_wal_api_key'] == '') { // incorrect api key
            $valid['wawp_wal_api_key'] = '';
            $entered_valid = false;
        } else { // valid
            $entered_api_key = $valid['wawp_wal_api_key'];
            $valid['wawp_wal_api_key'] = $dataEncryption->encrypt($valid['wawp_wal_api_key']);
        }
        if ($valid['wawp_wal_client_id'] !== $input['wawp_wal_client_id'] || $input['wawp_wal_client_id'] == '') { // incorrect client ID
            $valid['wawp_wal_client_id'] = '';
            $entered_valid = false;
        } else {
            $valid['wawp_wal_client_id'] = $dataEncryption->encrypt($valid['wawp_wal_client_id']);
        }
        if ($valid['wawp_wal_client_secret'] !== $input['wawp_wal_client_secret'] || $input['wawp_wal_client_secret'] == '') { // incorrect client secret
            $valid['wawp_wal_client_secret'] = '';
            $entered_valid = false;
        } else {
            $valid['wawp_wal_client_secret'] = $dataEncryption->encrypt($valid['wawp_wal_client_secret']);
        }

        // If input is valid, check if it can connect to the API
        $valid_api = '';
        if ($entered_valid) {
            require_once('WAWPApi.php');
            $valid_api = WAWPApi::is_application_valid($entered_api_key);
        }
        // Set all elements to '' if api call is invalid or invalid input has been entered
        if ($valid_api == false || !$entered_valid) {
            // Set all inputs to ''
            $keys = array_keys($valid);
            $valid = array_fill_keys($keys, '');
        }

        // Sanitize menu dropdown
        $valid['wawp_wal_login_logout_button'] = sanitize_text_field($input['wawp_wal_login_logout_button']);

		// Return array of valid inputs
		return $valid;
    }

    /**
     * Print the Section text
     */
    public function wal_print_section_info()
    {
        print 'Enter your Wild Apricot credentials here. Your data is encrypted for your safety!';
    }

    /**
     * Print the licensing settings section text
     */
    public function license_print_info() {
        print 'Enter your license key(s) here.';
    }

    /**
     * Get the api key
     */
    public function api_key_callback()
    {
        // Display the text field for api key
		echo "<input id='wawp_wal_api_key' name='wawp_wal_name[wawp_wal_api_key]'
			type='text' placeholder='*************' />";
		// Check if api key has been set; if so, echo that the client secret has been set!
		if (isset($this->options['wawp_wal_api_key']) && $this->options['wawp_wal_api_key'] != '') {
			echo "<p>API Key is set!</p>";
		}
    }

    /**
     * Get the client id
     */
    public function client_id_callback()
    {
        // Display text field for client id
		echo "<input id='wawp_wal_client_id' name='wawp_wal_name[wawp_wal_client_id]'
			type='text' placeholder='*************' />";
		// Check if client id has been set; if so, echo that the client secret has been set!
		if (isset($this->options['wawp_wal_client_id']) && $this->options['wawp_wal_client_id'] != '') {
			echo "<p>Client ID is set!</p>";
		}
    }

	/**
     * Get the client secret
     */
    public function client_secret_callback()
    {
		// Display text field for client secret
		echo "<input id='wawp_wal_client_secret' name='wawp_wal_name[wawp_wal_client_secret]'
			type='text' placeholder='*************' />";
		// Check if client secret has been set; if so, echo that the client secret has been set!
		if (isset($this->options['wawp_wal_client_secret']) && $this->options['wawp_wal_client_secret'] != '') {
			echo "<p>Client Secret is set!</p>";
		}
    }

    /**
     * Get the desired menu to add the login/logout button
     */
    public function login_logout_menu_callback() {
        // Get menu items: https://wordpress.stackexchange.com/questions/111060/retrieving-a-list-of-menu-items-in-an-array
        $menu_locations = get_nav_menu_locations();
        $menu_items = array();
        // Save each menu name in menu_items
        foreach ($menu_locations as $key => $value) {
            // Append key to menu_items
            $menu_items[] = $key;
        }
        // Display dropdown menu
        echo "<select id='wawp_selected_menu' name='wawp_wal_name[wawp_wal_login_logout_button]'>";
        // Loop through each option
        foreach ($menu_items as $item) {
            echo "<option value='" . esc_attr( $item ) . "' >" . esc_html( $item ) . "</option>";
        }
    }

    /**
     * Create the license key input box for the form
     * @param array $args contains arguments with (slug, title) as keys.
     */
    public function license_key_input(array $args) {
        $slug = $args['slug'];
        $license = Addon::instance()::get_licenses();
        echo "<input id='license_key " . esc_attr($slug) . "' name='wawp_license_keys[" . esc_attr($slug) ."]' type='text' value='" . $license[$slug] . "'  />" ;
    }

    /**
     * License form callback.
     * For each license submitted, check if the license is valid.
     * If it is valid, it gets added to the array of valid license keys.
     * Otherwise, the user receives an error.
     * @param array $input settings form input array mapping addon slugs to license keys
     */
    public function validate_license_form($input) {
        $slug = array_key_first($input);
        $license = $input[$slug];
        $valid = array();

        foreach($input as $slug => $license) {
            $key = Addon::instance()::validate_license_key($license, $slug);
            $option_name = 'license-check-' . $slug;
            if (is_null($key)) {
                update_option($option_name, 'invalid');
                // add errors here okay.
            } else {
                // delete_option($option_name);
                if ($key == 'unchanged') {
                    delete_option($option_name);
                    $valid[$slug] = Addon::instance()::get_license($slug);
                } else {
                    update_option($option_name, 'true');
                    $valid[$slug] = $key;
                }
            }
        }
        return $valid;
    }
}
