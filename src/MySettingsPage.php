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
        add_action( 'admin_menu', array( $this, 'wawp_add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'wawp_page_init' ) );
    }

    // For debugging
    function my_log_file( $msg, $name = '' )
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
    public function wawp_add_settings_page()
    {
        // Create WA4WP admin page
        // This page will be under "Settings"
        add_menu_page(
            'WA4WP Settings',
            'WA4WP',
            'manage_options',
            'wawp-wal-admin',
            array( $this, 'wawp_create_admin_page' ),
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
			array($this, 'wawp_create_login_page')
		);

        // Add new submenu here
    }

    /**
     * Settings page callback
     */
    public function wawp_create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'wawp_wal_name' );
    }

	/**
	 * Login page callback
	 */
	public function wawp_create_login_page() {
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
    public function wawp_page_init()
    {
        do_action('qm/debug', 'in wawp init!');

        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'wawp_wal_sanitize'),
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
            array( $this, 'wawp_wal_print_section_info' ), // Callback
            'wawp-wal-admin' // Page
        );

		// Settings for API Key
        add_settings_field(
            'wawp_wal_api_key', // ID
            'API Key:', // Title
            array( $this, 'wawp_api_key_callback' ), // Callback
            'wawp-wal-admin', // Page
            'wawp_wal_id' // Section
        );

		// Settings for Client ID
        add_settings_field(
            'wawp_wal_client_id', // ID
            'Client ID:', // Title
            array( $this, 'wawp_client_id_callback' ), // Callback
            'wawp-wal-admin', // Page
            'wawp_wal_id' // Section
        );

		// Settings for Client Secret
		add_settings_field(
            'wawp_wal_client_secret', // ID
            'Client Secret:', // Title
            array( $this, 'wawp_client_secret_callback' ), // Callback
            'wawp-wal-admin', // Page
            'wawp_wal_id' // Section
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function wawp_wal_sanitize( $input )
    {
        // $new_input = array();
        // if( isset( $input['wawp_wal_api_key'] ) )
        //     $new_input['wawp_wal_api_key'] = sanitize_text_field( $input['wawp_wal_api_key'] );

        // if( isset( $input['wawp_wal_client_id'] ) )
        //     $new_input['wawp_wal_client_id'] = sanitize_text_field( $input['wawp_wal_client_id'] );

		// if( isset( $input['wawp_wal_client_secret'] ) )
        //     $new_input['wawp_wal_client_secret'] = sanitize_text_field( $input['wawp_wal_client_secret'] );

        // return $new_input;

		// Create valid array that will hold the valid input
		do_action( 'qm/debug', 'Validate options!' );
        $this->my_log_file('we are validating in real plugin!');
        print_r($input);
		$valid = array();
		// Use regex for text and numbers to detect if input is valid
		$valid_api_key = preg_match('/^[\w]+$/', $input['wawp_wal_api_key']);
		if (!$valid_api_key) { // invalid input; save as ''
			$valid['wawp_wal_api_key'] = '';
		} else { // valid input
			$valid['wawp_wal_api_key'] = $input['wawp_wal_api_key'];
		}
		// Repeat same process for Client ID
		$valid_client_ID = preg_match('/^[\w]+$/', $input['wawp_wal_client_id']);
		if (!$valid_client_ID) { // invalid input; save as ''
			$valid['wawp_wal_client_id'] = '';
		} else { // valid input
			$valid['wawp_wal_client_id'] = $input['wawp_wal_client_id'];
		}
		// Repeat same process for Client secret
		$valid_client_secret = preg_match('/^[\w]+$/', $input['wawp_wal_client_secret']);
		if (!$valid_client_secret) { // invalid input; save as ''
			$valid['wawp_wal_client_secret'] = '';
		} else { // valid input
			$valid['wawp_wal_client_secret'] = $input['wawp_wal_client_secret'];
		}

		// Encrypt valid inputs
		// include plugin_dir_url(__FILE__) . 'src/DataEncryption.php'; // path to DataEncryption.php
		require_once('DataEncryption.php');
		$dataEncryption = new DataEncryption();
		$valid['wawp_wal_api_key'] = $dataEncryption->encrypt($valid['wawp_wal_api_key']);
		$valid['wawp_wal_client_id'] = $dataEncryption->encrypt($valid['wawp_wal_client_id']);
		$valid['wawp_wal_client_secret'] = $dataEncryption->encrypt($valid['wawp_wal_client_secret']);

		// Return array of valid inputs
		return $valid;
    }

    /**
     * Print the Section text
     */
    public function wawp_wal_print_section_info()
    {
        print 'Enter your Wild Apricot credentials here. Your data is encrypted for your safety!';
    }

    /**
     * Get the api key
     */
    public function wawp_api_key_callback()
    {
        // printf(
        //     '<input type="text" id="wawp_wal_api_key" name="wawp_wal_name[wawp_wal_api_key]" value="%s" />',
        //     isset( $this->options['wawp_wal_api_key'] ) ? esc_attr( $this->options['wawp_wal_api_key']) : ''
        // );

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
    public function wawp_client_id_callback()
    {
        // printf(
        //     '<input type="text" id="wawp_wal_client_id" name="wawp_wal_name[wawp_wal_client_id]" value="%s" />',
        //     isset( $this->options['wawp_wal_client_id'] ) ? esc_attr( $this->options['wawp_wal_client_id']) : ''
        // );

		echo "<input id='wawp_wal_client_id' name='wawp_wal_name[wawp_wal_client_id]'
			type='text' placeholder='*************' />";
		// Check if api key has been set; if so, echo that the client secret has been set!
		if (isset($this->options['wawp_wal_client_id']) && $this->options['wawp_wal_client_id'] != '') {
			echo "<p>Client ID is set!</p>";
		}
    }

	/**
     * Get the client secret
     */
    public function wawp_client_secret_callback()
    {
        // printf(
        //     '<input type="text" id="wawp_wal_client_secret" name="wawp_wal_name[wawp_wal_client_secret]" value="%s" />',
        //     isset( $this->options['wawp_wal_client_secret'] ) ? esc_attr( $this->options['wawp_wal_client_secret']) : ''
        // );

		// $options = get_option( 'wawp_wal_options' );
		// Echo text field
		echo "<input id='wawp_wal_client_secret' name='wawp_wal_name[wawp_wal_client_secret]'
			type='text' placeholder='*************' />";
		// Check if api key has been set; if so, echo that the client secret has been set!
		if (isset($this->options['wawp_wal_client_secret']) && $this->options['wawp_wal_client_secret'] != '') {
			echo "<p>Client Secret is set!</p>";
		}
    }
}
