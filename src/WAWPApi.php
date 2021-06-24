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
	 * Performs an API request to get data about the current Wild Apricot user
	 *
	 * @return $contact_info holds the body of the API response
	 */
    public function get_info_on_current_user() {
        // Get details of current WA user with API request
        $args = $this->request_data_args($this->access_token);
		$contact_info = wp_remote_get('https://api.wildapricot.org/publicview/v1/accounts/' . $this->wa_user_id . '/contacts/me?includeDetails=true', $args);

        // Return contact information
        $contact_info = self::response_to_data($contact_info);
        return $contact_info;
    }

	/**
	 * Returns the membership levels of the current Wild Apricot organization
	 *
	 * @return $membership_levels holds the membership levels from Wild Apricot
	 */
    public function get_membership_levels() {
        $args = $this->request_data_args();
        $url = 'https://api.wildapricot.org/publicview/v1/accounts/' . $this->wa_user_id . '/membershiplevels';
        $membership_levels = wp_remote_get($url, $args);

        // Return membership levels
        $membership_levels = self::response_to_data($membership_levels);
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
