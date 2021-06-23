<?php
namespace WAWP;

class WAWPApi {
    private $access_token;

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

    public function __construct($access_token) {
        $this->access_token = $access_token;
        self::my_log_file('constructing wa api!');
    }

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

    public function get_info_on_current_user($wa_user_id) {
        // Get details of current WA user with API request
        $args = $this->request_data_args($this->access_token);
		$contact_info = wp_remote_get('https://api.wildapricot.org/publicview/v1/accounts/' . $wa_user_id . '/contacts/me?includeDetails=true', $args);

        // Return contact information
        $contact_info = self::response_to_data($contact_info);
        return $contact_info;
    }

    public function get_membership_levels() {
        $args = $this->request_data_args();


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
}
