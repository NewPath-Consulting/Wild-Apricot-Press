<?php
namespace WAWP;

class WAWPApi {
    private $access_token;
    private $wa_user_id;

	/**
	 * Creates instance of class based on the user's access token and Wild Apricot user ID
	 *
	 * @param string $access_token is the user's access token obtained from the Wild Apricot API
	 * @param string $wa_user_id is the user's Wild Apricot ID
	 */
    public function __construct($access_token, $wa_user_id) {
        $this->access_token = $access_token;
        $this->wa_user_id = $wa_user_id;
    }

	/**
	 * Removes CRON job
	 */
	public static function unsetCronJob($cron_hook_name, $args = [])
    {
		// Get the timestamp for the next event.
		$timestamp = wp_next_scheduled($cron_hook_name, $args);
		// Check that event is already scheduled
		if ($timestamp) {
			wp_unschedule_event($timestamp, $cron_hook_name, $args);
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
	 * Converts the API response to the body in which data can be extracted
	 *
	 * @param  array $response holds the output from the API request, organized in a key-value pattern
	 * @return array $data is the body of the response
	 */
    private static function response_to_data($response) {
        if (is_wp_error($response)) {
			return false;
		}
		// Get body of response
		$body = wp_remote_retrieve_body($response);
		// Get data from json response
		$data = json_decode($body, true);
		// Check if there is an error in body
		if (isset($data['error'])) { // error in body
			// Update successful login as false
			return false;
		}
		// Valid response; return data
		return $data;
    }

    /**
	 * Load Wild Apricot credentials that user has input in the WA4WP settings
	 *
	 * @return array $decrypted_credentials	Decrypted Wild Apricot credentials
	 */
	private static function load_user_credentials() {
		// Load encrypted credentials from database
		$credentials = get_option('wawp_wal_name');
		// Decrypt credentials
		$dataEncryption = new DataEncryption();
		$decrypted_credentials['wawp_wal_api_key'] = $dataEncryption->decrypt($credentials['wawp_wal_api_key']);
		$decrypted_credentials['wawp_wal_client_id'] = $dataEncryption->decrypt($credentials['wawp_wal_client_id']);
		$decrypted_credentials['wawp_wal_client_secret'] = $dataEncryption->decrypt($credentials['wawp_wal_client_secret']);

		return $decrypted_credentials;
	}

	/**
	 * Returns the arguments required for making API calls
	 *
	 * @return $args are the arguments that will be passed in the API call
	 */
    private function request_data_args() {
        $args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Accept' => 'application/json',
				'User-Agent' => 'WildApricotForWordPress/1.0'
			),
		);
        return $args;
    }

	/**
	 * Returns a new access token after it has expired
	 * https://gethelp.wildapricot.com/en/articles/484#:~:text=for%20this%20access_token-,How%20to%20refresh%20tokens,-To%20refresh%20the
	 */
	public static function get_new_access_token($refresh_token) {
		self::my_log_file('were getting a new access token!');
		// Get decrypted credentials
		$decrypted_credentials = self::load_user_credentials();
		// Encode API key
		$authorization_string = $decrypted_credentials['wawp_wal_client_id'] . ':' . $decrypted_credentials['wawp_wal_client_secret'];
		$encoded_authorization_string = base64_encode($authorization_string);

		// Perform API request
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $encoded_authorization_string,
				'Content-type' => 'application/x-www-form-urlencoded'
			),
			'body' => 'grant_type=refresh_token&refresh_token=' . $refresh_token
		);
		$response = wp_remote_post('https://oauth.wildapricot.org/auth/token', $args);
		self::my_log_file($response);
		$data = self::response_to_data($response);
		return $data;
	}

	/**
	 * Performs an API request to get data about the current Wild Apricot user
	 *
	 * @return $contact_info holds the body of the API response
	 */
    public function get_info_on_current_user() {
        // Get details of current WA user with API request
		// Get user's contact ID
        $args = $this->request_data_args();
		$contact_info = wp_remote_get('https://api.wildapricot.org/v2.2/accounts/' . $this->wa_user_id . '/contacts/me?getExtendedMembershipInfo=true', $args);
        self::my_log_file($contact_info);
		$contact_info = self::response_to_data($contact_info);
		// Get if user is administrator or not
		$is_administrator = $contact_info['IsAccountAdministrator'];
		// Perform API call based on if user is administrator or not
		$user_data_api = NULL;
		if (isset($is_administrator) && $is_administrator == '1') { // user is administrator
			$contact_id = $contact_info['Id'];
			$user_data_api = wp_remote_get('https://api.wildapricot.org/v2.2/accounts/' . $this->wa_user_id . '/contacts/' . $contact_id . '?getExtendedMembershipInfo=true', $args);
		} else { // not administrator
			$user_data_api = wp_remote_get('https://api.wildapricot.org/publicview/v1/accounts/' . $this->wa_user_id . '/contacts/me?includeDetails=true', $args);
		}
		// Extract body
		$full_info = self::response_to_data($user_data_api);
		// Get all information for current user
        return $full_info;
    }

	/**
	 * Returns the membership levels of the current Wild Apricot organization
	 *
	 * @return $membership_levels holds the membership levels from Wild Apricot
	 */
    public function get_membership_levels($request_groups = false) {
        $args = $this->request_data_args();
		$url = 'https://api.wildapricot.org/v2.2/accounts/' . $this->wa_user_id . '/membershiplevels';
		if ($request_groups) {
        	$url = 'https://api.wildapricot.org/v2.2/accounts/' . $this->wa_user_id . '/membergroups';
		}
        $membership_levels_response = wp_remote_get($url, $args);

        // Return membership levels
        $membership_levels_response = self::response_to_data($membership_levels_response);

		// Extract membership levels into array
		$membership_levels = array();
		if (!empty($membership_levels_response)) {
			foreach ($membership_levels_response as $level) {
				// Get current key and level
				$current_key = $level['Id'];
				$current_level = $level['Name'];
				// Set level to membership_levels array
				$membership_levels[$current_key] = $current_level;
			}
		}
        return $membership_levels;
    }

    /**
	 * Static function that checks if application codes (API Key, Client ID, and Client Secret are valid)
	 *
	 * @param string         $entered_api_key The Wild Apricot API Key to check
	 * @return array|boolean $data	          An array of the response from the WA API if the key is valid; false otherwise
	 */
	public static function is_application_valid($entered_api_key) {
		// Encode API key
		$api_string = 'APIKEY:' . $entered_api_key;
		$encoded_api_string = base64_encode($api_string);
		// Perform API request
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $encoded_api_string,
				'Content-type' => 'application/x-www-form-urlencoded'
			),
			'body' => 'grant_type=client_credentials&scope=auto&obtain_refresh_token=true'
		);
		$response = wp_remote_post('https://oauth.wildapricot.org/auth/token', $args);

		$data = self::response_to_data($response);
        return $data;
	}

    /**
	 * Connect user to Wild Apricot API after obtaining their email and password
	 *
	 * https://gethelp.wildapricot.com/en/articles/484
	 *
	 * @param array          $valid_login Holds the email and password entered into the login screen
	 * @return array|boolean $data        Returns the response from the WA API if the credentials are valid; false otherwise
	 */
	public static function login_email_password($valid_login) {
		// Get decrypted credentials
		$decrypted_credentials = self::load_user_credentials();
		// Encode API key
		$authorization_string = $decrypted_credentials['wawp_wal_client_id'] . ':' . $decrypted_credentials['wawp_wal_client_secret'];
		$encoded_authorization_string = base64_encode($authorization_string);
		// Perform API request
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $encoded_authorization_string,
				'Content-type' => 'application/x-www-form-urlencoded'
			),
			'body' => 'grant_type=password&username=' . $valid_login['email'] . '&password=' . $valid_login['password'] . '&scope=auto'
		);
		$response = wp_remote_post('https://oauth.wildapricot.org/auth/token', $args);

		$data = self::response_to_data($response);
		return $data;
	}
}
