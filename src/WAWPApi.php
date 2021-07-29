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
		// Include WAIntegration
		require_once('WAIntegration.php');
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
	 * Gets user information for all Wild Apricot users in the WordPress database
	 */
	public function get_all_user_info() {
		// Get all of the Wild Apricot users in the WordPress database
		$users_args = array(
			'meta_key' => 'wawp_wa_user_id',
		);
		$wa_users = get_users($users_args);

		// Loop through each WP_User and create filter
		$filter_string = 'filter=';
		$i = 0;
		// Create array that stores the Wild Apricot ID associated with the WordPress ID
		$user_emails_array = array();
		foreach ($wa_users as $wa_user) {
			// Get user's WordPress ID
			$site_user_id = $wa_user->ID;
			// Get user email
			$user_email = $wa_user->data->user_email;
			// Get Wild Apricot ID
			$wa_synced_id = get_user_meta($site_user_id, 'wawp_wa_user_id');
			$wa_synced_id = $wa_synced_id[0];
			// Save to email to array indexed by WordPress ID
			$user_emails_array[$site_user_id] = $user_email;
			$filter_string .= 'ID%20eq%20' . $wa_synced_id;
			// Combine IDs with OR
			if (!($i == count($wa_users) - 1)) { // not last element
				$filter_string .= '%20OR%20';
			}
			$i++;
		}
		// Make API request
		$args = $this->request_data_args();
		// https://api.wildapricot.org/v2.2/accounts/221748/contacts?%24async=false&%24filter=ID%20eq%2060699353
		$url = 'https://api.wildapricot.org/v2.2/accounts/' . $this->wa_user_id . '/contacts?%24async=false&%24' . $filter_string;
		$all_contacts_request = wp_remote_get($url, $args);
		// Ensure that responses are not empty
		if (!empty($all_contacts_request)) {
			$all_contacts = self::response_to_data($all_contacts_request);
			if (!empty($all_contacts)) {
				// Convert contacts object to an array
				$all_contacts = (array) $all_contacts;
				$all_contacts = $all_contacts['Contacts'];

				// Update each user in WordPress
				// Loop through each contact
				foreach ($user_emails_array as $key => $value) {
					$site_id = $key;
					$user_email = $value;
					// Find this wa_id in the contacts from the API
					foreach ($all_contacts as $contact) {
						// Get contact's email
						$contact_email = $contact['Email'];
						// Check if contact's email checks for the email we are searching for
						if (strcasecmp($contact_email, $user_email) == 0) { // equal
							// This is the correct user
							// Check if user is an administrator -> if so, do not modify them!
							$user_is_admin = false;
							if (user_can($site_id, 'manage_options')) {
								$user_is_admin = true;
							}
							// Let us update this site_id with its new data
							$updated_organization = $contact['Organization'];
							// Get membership level, if any
							$updated_membership_level = '';
							$updated_membership_level_id = '';
							// Check if the user has a membership level
							if (array_key_exists('MembershipLevel', $contact)) {
								$updated_membership_level = $contact['MembershipLevel']['Name'];
								$updated_membership_level_id = $contact['MembershipLevel']['Id'];
							}
							// Get status, if any
							$updated_status = '';
							if (array_key_exists('Status', $contact)) {
								$updated_status = $contact['Status'];
							}
							// Get membership groups through field values
							$contact_fields = $contact['FieldValues'];
							if (!empty($contact_fields)) {
								$user_groups_array = array();
								// Loop through the fields until 'Group participation' is found
								foreach ($contact_fields as $field) {
									$field_name = $field['FieldName'];
									if ($field_name == 'Group participation') {
										// Get membership groups array
										$group_array = $field['Value'];
										if (!empty($group_array)) {
											// Loop through each group
											foreach ($group_array as $group) {
												$user_groups_array[$group['Id']] = $group['Label'];
											}
										}
									}
								}
								// Set user's groups to meta data
								update_user_meta($site_id, WAIntegration::WA_MEMBER_GROUPS_KEY, $user_groups_array);
							}
							// Update user meta data
							update_user_meta($site_id, WAIntegration::WA_USER_STATUS_KEY, $updated_status);
							update_user_meta($site_id, WAIntegration::WA_ORGANIZATION_KEY, $updated_organization);
							update_user_meta($site_id, WAIntegration::WA_MEMBERSHIP_LEVEL_KEY, $updated_membership_level);
							update_user_meta($site_id, WAIntegration::WA_MEMBERSHIP_LEVEL_ID_KEY, $updated_membership_level_id);
							// Update user's role to their new membership level
							// Get user's current role(s)
							$current_user_data = get_userdata($site_id);
							$current_user_roles = $current_user_data->roles;
							$current_user_object = get_user_by('id', $site_id);
							// Loop through roles and remove roles
							foreach ($current_user_roles as $current_user_role) {
								if (substr($current_user_role, 0, 5) == 'wawp_') {
									// Remove this role
									$current_user_object->remove_role($current_user_role);
								}
							}
							// Add new membership level to user's roles
							$updated_role = 'wawp_' . str_replace(' ', '', $updated_membership_level);
							$current_user_object->add_role($updated_role);
						}
					}
				}
			}
		}
	}

	/**
	 * Returns a new access token after it has expired
	 * https://gethelp.wildapricot.com/en/articles/484#:~:text=for%20this%20access_token-,How%20to%20refresh%20tokens,-To%20refresh%20the
	 */
	public static function get_new_access_token($refresh_token) {
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
