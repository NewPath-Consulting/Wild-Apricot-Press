<?php
namespace WAWP;

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
            do_action('qm/debug', 'option does not exist!');
            // Create option
            add_option('wawp_wal_name');
        }
    }

    /**
     * Add options page
     */
    public function add_settings_page()
    {
        // Create WA4WP admin page
        add_menu_page(
            'WA4WP Settings',
            'WA4WP',
            'manage_options',
            'wawp-wal-admin',
            array( $this, 'create_admin_page' ),
			'dashicons-businesswoman',
			6
        );

		// Create Login sub-menu under WA4WP
		add_submenu_page(
			'wawp-wal-admin',
			'Wild Apricot Login',
			'Login',
			'manage_options',
			'wawp-login',
			array($this, 'create_login_page')
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
						// This prints out all hidden setting fields
						settings_fields( 'wawp_wal_group' );
						do_settings_sections( 'wawp-wal-admin' );
						submit_button();
					?>
					</form>
					<!-- Check if form is valid -->
					<?php
						if (!isset($this->options['wawp_wal_api_key']) || !isset($this->options['wawp_wal_client_id']) || !isset($this->options['wawp_wal_client_secret']) || $this->options['wawp_wal_api_key'] == '' || $this->options['wawp_wal_client_id'] == '' || $this->options['wawp_wal_client_secret'] == '') { // not valid
							echo '<p style="color:red">Invalid credentials!</p>';
						} else {
							echo '<p style="color:green">Success! Credentials saved!</p>';
                            // Implement hook here to tell Wild Apricot to connect to these credentials
                            do_action( 'wawp_wal_credentials_obtained' );
						}
					?>
				</div>
			</div>
        </div>
        <?php
	}

    /**
     * Register and add settings
     */
    public function page_init()
    {
        do_action('qm/debug', 'in wawp init!');

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
            'Wild Apricot Login', // Title
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
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function wal_sanitize( $input )
    {
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
        require_once('DataEncryption.php');
		$dataEncryption = new DataEncryption();
        // Check if inputs are valid
        if ($valid['wawp_wal_api_key'] !== $input['wawp_wal_api_key']) { // incorrect api key
            $valid['wawp_wal_api_key'] = '';
        } else { // valid
            $valid['wawp_wal_api_key'] = $dataEncryption->encrypt($valid['wawp_wal_api_key']);
        }
        if ($valid['wawp_wal_client_id'] !== $input['wawp_wal_client_id']) { // incorrect client ID
            $valid['wawp_wal_client_id'] = '';
        } else {
            $valid['wawp_wal_client_id'] = $dataEncryption->encrypt($valid['wawp_wal_client_id']);
        }
        if ($valid['wawp_wal_client_secret'] !== $input['wawp_wal_client_secret']) { // incorrect client secret
            $valid['wawp_wal_client_secret'] = '';
        } else {
            $valid['wawp_wal_client_secret'] = $dataEncryption->encrypt($valid['wawp_wal_client_secret']);
        }

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
}
