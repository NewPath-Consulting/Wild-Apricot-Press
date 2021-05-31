<?php
class WAWPLoginPage {
	static function wawp_construct_page() {
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
					<form action="options.php" method="post">
						<?php
						settings_fields( 'wawp_wal_options' );
						do_settings_sections( 'wawp_wal' );
						submit_button( 'Save', 'primary' );
						?>
					</form>
					<!-- Check if form is valid -->
					<?php
					$user_options = get_option( 'wawp_wal_options' );
					if (!isset($user_options['api_key']) || !isset($user_options['client_ID']) || !isset($user_options['client_secret']) || $user_options['api_key'] == '' || $user_options['client_ID'] == '' || $user_options['client_secret'] == '') { // not valid
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

	static function wawp_wal_admin_init() {
		// define the setting arguments
		$args = array(
			'type' => 'string',
			'sanitize_callback' => 'wawp_wal_validate_options',
			'default' => NULL
		);

		// Register settings
		register_setting( 'wawp_wal_options', 'wawp_wal_options', $args );

		// Add settings section
		add_settings_section(
			'wawp_wal_main',
			'Wild Apricot Login',
			array('WAWPLoginPage', 'wawp_wal_section_text'),
			'wawp_wal'
		);

		// Create settings field for api-key (originally name)
		add_settings_field(
			'wawp_wal_api_key',
			'API Key:',
			array('WAWPLoginPage', 'wawp_wal_setting_api_key'),
			'wawp_wal',
			'wawp_wal_main'
		);

		// Create settings field for client ID
		add_settings_field(
			'wawp_wal_client_ID',
			'Client ID:',
			array('WAWPLoginPage', 'wawp_wal_setting_client_ID'),
			'wawp_wal',
			'wawp_wal_main'
		);

		// Create settings field for client secret
		add_settings_field(
			'wawp_wal_client_secret',
			'Client Secret:',
			array('WAWPLoginPage', 'wawp_wal_setting_client_secret'),
			'wawp_wal',
			'wawp_wal_main'
		);
	}

	// Draw section header
	static function wawp_wal_section_text() {
		echo '<p>Enter your Wild Apricot credentials here. Your data is encrypted for your safety!</p>';
	}

	// Display and fill the Name form field
	static function wawp_wal_setting_api_key() {
		// Get options array from database
		$options = get_option( 'wawp_wal_options' );
		// Echo text field
		echo "<input id='api_key' name='wawp_wal_options[api_key]'
			type='text' placeholder='*************' />";
		// Check if api key has been set; if so, echo that the api key has been set!
		if (isset($options['api_key']) && $options['api_key'] != '') { // api key set or is empty
			echo "<p>API Key is set!</p>";
		}
	}

	// Display and fill the Client ID field
	static function wawp_wal_setting_client_ID() {
		// Get options array from database
		$options = get_option( 'wawp_wal_options' );
		// Echo text field
		echo "<input id='client_ID' name='wawp_wal_options[client_ID]'
			type='text' placeholder='*************' />";
		// Check if api key has been set; if so, echo that the api key has been set!
		if (isset($options['client_ID']) && $options['client_ID'] != '') {
			echo "<p>Client ID is set!</p>";
		}
	}

	// Display and fill the Client Secret field
	static function wawp_wal_setting_client_secret() {
		// Get options array from database
		$options = get_option( 'wawp_wal_options' );
		// Echo text field
		echo "<input id='client_secret' name='wawp_wal_options[client_secret]'
			type='text' placeholder='*************' />";
		// Check if api key has been set; if so, echo that the api key has been set!
		if (isset($options['client_secret']) && $options['client_secret'] != '') {
			echo "<p>Client Secret is set!</p>";
		}
	}

	// Validate user input (text and numbers only)
	function wawp_wal_validate_options( $input ) {
		// Create valid array that will hold the valid input
		$valid = array();
		// Use regex for text and numbers to detect if input is valid
		$valid_api_key = preg_match('/^[\w]+$/', $input['api_key']);
		if (!$valid_api_key) { // invalid input; save as ''
			$valid['api_key'] = '';
		} else { // valid input
			$valid['api_key'] = $input['api_key'];
		}
		// Repeat same process for Client ID
		$valid_client_ID = preg_match('/^[\w]+$/', $input['client_ID']);
		if (!$valid_client_ID) { // invalid input; save as ''
			$valid['client_ID'] = '';
		} else { // valid input
			$valid['client_ID'] = $input['client_ID'];
		}
		// Repeat same process for Client secret
		$valid_client_secret = preg_match('/^[\w]+$/', $input['client_secret']);
		if (!$valid_client_secret) { // invalid input; save as ''
			$valid['client_secret'] = '';
		} else { // valid input
			$valid['client_secret'] = $input['client_secret'];
		}

		// Encrypt valid inputs
		// include plugin_dir_url(__FILE__) . 'src/DataEncryption.php'; // path to DataEncryption.php
		require_once("src/DataEncryption.php");
		$dataEncryption = new DataEncryption();
		$valid['api_key'] = $dataEncryption->encrypt($valid['api_key']);
		$valid['client_ID'] = $dataEncryption->encrypt($valid['client_ID']);
		$valid['client_secret'] = $dataEncryption->encrypt($valid['client_secret']);

		// Return array of valid inputs
		return $valid;
	}
}
?>
