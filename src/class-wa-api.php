<?php
namespace WAWP;

require_once __DIR__ . '/class-log.php';
require_once __DIR__ . '/wap-exception.php';

/**
 * Manages connections to and retrieves data from the WildApricot API.
 * 
 * @since 1.0b1
 * @author Spencer Gable-Cook
 * @copyright 2022 NewPath Consulting
 */
class WA_API {
	// Constants
	const ADMIN_API_VERSION = 'v2.2';
	const MEMBER_API_VERSION = 'v1';
	const WAP_USER_AGENT = 'WildApricotPress/1.0.2';
	const API_URL = 'https://api.wildapricot.org/';
	// const API_URL = 'https://google.com';

	// Class variables
    private $access_token;
    private $wa_user_id;

	/**
	 * Creates instance of class based on the user's access token and 
	 * WildApricot user ID
	 *
	 * @param string $access_token is the user's access token obtained from the
	 * WildApricot API
	 * @param string $wa_user_id is the user's WildApricot ID
	 */
    public function __construct($access_token, $wa_user_id) {
        $this->access_token = $access_token;
        $this->wa_user_id = $wa_user_id;
		// Include WA_Integration and Data_Encryption
		require_once('class-wa-integration.php');
		require_once('class-data-encryption.php');
    }

	/**
	 * Converts the API response to the body from which data can be extracted
	 *
	 * @param array $response holds the output from the API request, organized
	 *  in a key-value pattern
	 * @return array|bool $data is the body of the response, empty array if
	 * unauthorized
	 * @throws API_Exception
	 */
    private static function response_to_data($response) {
        if (is_wp_error($response)) {
			throw new API_Exception(API_Exception::api_connection_error());
		}

		// if user is unauthorized, return empty array
		if ($response['response']['code'] == '401') 
		{
			return array();
		} 

		// Get body of response
		$body = wp_remote_retrieve_body($response);
		// Get data from json response
		$data = json_decode($body, true);

		// Check if there is an error in body
		if (isset($data['error'])) { // error in body
			throw new API_Exception(API_Exception::api_response_error());
		} else {
			// remove exception flag so errors don't get incorrectly reported
			API_Exception::remove_error();
		}

		// Valid response; return data
		return $data;
    }

    /**
	 * Load WildApricot credentials that user has input in the WAP settings
	 *
	 * @return array Decrypted WildApricot credentials
	 */
	public static function load_user_credentials() {
		// Load encrypted credentials from database
		$credentials = get_option(WA_Integration::WA_CREDENTIALS_KEY);

		if (!$credentials) return array();

		$decrypted_credentials = array();
		// Ensure that credentials are not empty
		// Decrypt credentials
		// Encryption exceptions will propogate up
		$dataEncryption = new Data_Encryption();
		$decrypted_credentials[WA_Integration::WA_API_KEY_OPT] = $dataEncryption->decrypt($credentials[WA_Integration::WA_API_KEY_OPT]);
		$decrypted_credentials[WA_Integration::WA_CLIENT_ID_OPT] = $dataEncryption->decrypt($credentials[WA_Integration::WA_CLIENT_ID_OPT]);
		$decrypted_credentials[WA_Integration::WA_CLIENT_SECRET_OPT] = $dataEncryption->decrypt($credentials[WA_Integration::WA_CLIENT_SECRET_OPT]);


		return $decrypted_credentials;
	}

	/**
	 * Returns the arguments required for making API calls
	 *
	 * @return array arguments that will be passed in the API call
	 */
    private function request_data_args() {
        $args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->access_token,
				'Accept' => 'application/json',
				'User-Agent' => self::WAP_USER_AGENT
			),
		);
        return $args;
    }

	/**
	 * Checks if a new admin access token is required and returns a valid access
	 * token. If there are any encryption or decryption exceptions, an empty 
	 * array will be returned.
	 *
	 * @return array $verified_data holds the verified access token and account ID
	 */
	public static function verify_valid_access_token() {
		
		$verified_data = array(
			'access_token' => '',
			'wa_account_id' => ''
		);
        // Check if access token is still valid
		$access_token = get_transient(WA_Integration::ADMIN_ACCESS_TOKEN_TRANSIENT);
		$wa_account_id = get_transient(WA_Integration::ADMIN_ACCOUNT_ID_TRANSIENT);
		if (!$access_token || !$wa_account_id) { // access token is expired

			$dataEncryption = new Data_Encryption();
			// Refresh access token
			$refresh_token = get_option(WA_Integration::ADMIN_REFRESH_TOKEN_OPTION);
			$refresh_token = $dataEncryption->decrypt($refresh_token);
			$new_response = self::get_new_access_token($refresh_token);
			// Get variables from response
			$new_access_token = $new_response['access_token'];
			$new_expiring_time = $new_response['expires_in'];
			$new_account_id = $new_response['Permissions'][0]['AccountId'];
			// Set these new values to the transients
			$new_access_token_enc = $dataEncryption->encrypt($new_access_token);
			$new_account_id_enc = $dataEncryption->encrypt($new_account_id);
			
			set_transient(
				WA_Integration::ADMIN_ACCESS_TOKEN_TRANSIENT, 
				$new_access_token_enc, 
				$new_expiring_time
			);

			set_transient(
				WA_Integration::ADMIN_ACCOUNT_ID_TRANSIENT, 
				$new_account_id_enc, 
				$new_expiring_time
			);
			// Update values
			$access_token = $new_access_token;
			$wa_account_id = $new_account_id;
		} else {
			$dataEncryption = new Data_Encryption();
			$access_token = $dataEncryption->decrypt($access_token);
			$wa_account_id = $dataEncryption->decrypt($wa_account_id);
		}
		// Return array of access token and account id
		$verified_data['access_token'] = $access_token;
		$verified_data['wa_account_id'] = $wa_account_id;
		return $verified_data;
	}

	/**
	 * Lowercases and removes prefix to url for easy comparison between the license url and WildApricot url
	 *
	 * @param string  $original_url is the url to modify
	 * @return string $modified_url is the url that is all lowercase and has the prefix removed
	 */
	public static function create_consistent_url($original_url) {
		$modified_url = esc_url($original_url);
		// Lowercase
		$modified_url = strtolower($modified_url);
		// Get main part of url
		$parsed_url = parse_url($modified_url);
		$main_wa_url = $parsed_url['host'];
		return $main_wa_url;
	}

	/**
	 * Retrieves url and id for the account
	 *
	 * @return array $wild_apricot_values holds the WildApricot URL, indexed
	 * by 'Url', and the WildApricot ID, indexed by 'Id'
	 */
	public function get_account_url_and_id() {
		$args = $this->request_data_args();
		$url = self::API_URL . self::ADMIN_API_VERSION . '/accounts/' . $this->wa_user_id;
		$response_api = wp_remote_get($url, $args);

		try {
			$details_response = self::response_to_data($response_api);
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error retrieving the Wild Apricot account URL and ID.');
		}
		
		// Extract values
		$wild_apricot_values = array();
		if (array_key_exists('Id', $details_response)) {
			$wild_apricot_values['Id'] = $details_response['Id'];
		}
		$wild_apricot_url = '';
		if (array_key_exists('PrimaryDomainName', $details_response)) {
			$wild_apricot_values['Url'] = $details_response['PrimaryDomainName'];
			// Lowercase and remove https, http, or www from url
			$wild_apricot_values['Url'] = self::create_consistent_url($wild_apricot_values['Url']);
		}

		// Return values
		return $wild_apricot_values;
	}

	/**
	 * Retrieves the custom fields for contacts and members
	 * 
	 * @return void
	 */
	public function retrieve_custom_fields() {
		// Make API request for custom fields
		$args = $this->request_data_args();
		$url = self::API_URL . self::ADMIN_API_VERSION . '/accounts/' . 
			$this->wa_user_id . '/contactfields?showSectionDividers=true';
		$response_api = wp_remote_get($url, $args);

		try {
			$custom_field_response = self::response_to_data($response_api);
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error retrieving the Wild Apricot custom fields.');
		}
		

		// Loop through custom fields and get field names with IDs
		// Array that holds default fields
		$default_fields = array(
			'Group participation', 
			'User ID', 
			'Organization', 
			'Membership status'
		);
		// Do not add 'Group participation' or 'User ID' because those are already used by default
		$custom_fields = array();
		if (!empty($custom_field_response)) {
			foreach ($custom_field_response as $field_response) {
				$field_name = $field_response['FieldName'];
				$field_id = $field_response['SystemCode'];
				// Ensure that we are not displaying default options
				if (!in_array($field_name, $default_fields)) {
					$custom_fields[$field_id] = $field_name;
				}
			}
		}
		// Save custom fields in the options table
		update_option(WA_Integration::LIST_OF_CUSTOM_FIELDS, $custom_fields);
	}

	/**
	 * Gets user information for all WildApricot users in the WordPress database
	 * 
	 * @return void
	 */
	public function get_all_user_info() {
		// Get all of the WildApricot users in the WordPress database
		$users_args = array(
			'meta_key' => WA_Integration::WA_USER_ID_KEY,
		);
		$wa_users = get_users($users_args);

		// Loop through each WP_User and create filter
		$filter_string = 'filter=(';
		$i = 0;
		// Create array that stores the WildApricot ID associated with the WordPress ID
		$user_emails_array = array();
		foreach ($wa_users as $wa_user) {
			// Get user's WordPress ID
			$site_user_id = $wa_user->ID;
			// Get user email
			$user_email = $wa_user->data->user_email;
			// Get WildApricot ID
			$wa_synced_id = get_user_meta(
				$site_user_id, 
				WA_Integration::WA_USER_ID_KEY
			);
			$wa_synced_id = $wa_synced_id[0];
			// Save to email to array indexed by WordPress ID
			$user_emails_array[$site_user_id] = $user_email;
			$filter_string .= 'ID%20eq%20' . $wa_synced_id;
			// Combine IDs with OR
			if ($i < count($wa_users) - 1) { 
				// not last element
				$filter_string .= '%20OR%20';
			} else {
				$filter_string .= ')%20AND%20';
			}
			$i++;
		}

		// only retrieve contacts updated yesterday
		$yesterday = $this->get_yesterdays_date();
		$filter_string .= '\'Profile last updated\'%20gt%20' . $yesterday;
		
		$all_contacts = $this->retrieve_contacts_list($filter_string);

		if (empty($all_contacts)) return;
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
					$checked_custom_fields = get_option(WA_Integration::LIST_OF_CHECKED_FIELDS);
					$all_custom_fields = get_option(WA_Integration::LIST_OF_CUSTOM_FIELDS);
					if (!empty($contact_fields)) {
						$user_groups_array = array();
						// Loop through the fields until 'Group participation' is found
						// Also, store each field as a custom field to be presented to the user
						foreach ($contact_fields as $field) {
							$field_name = $field['FieldName'];
							$system_code = $field['SystemCode'];
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
							// Get other custom fields, if any
							if (!empty($checked_custom_fields)) {
								// Check if current system code is in the checked custom fields
								if (in_array($system_code, $checked_custom_fields)) {
									// We must extract this value and save it to the user meta data
									$custom_meta_key = 'wawp_' . str_replace(' ', '', $system_code);
									$custom_field_to_save = $field['Value'];
									// Save to user meta data
									update_user_meta($site_id, $custom_meta_key, $custom_field_to_save);
								}
							}
						}
						// Set user's groups to meta data
						update_user_meta($site_id, WA_Integration::WA_MEMBER_GROUPS_KEY, $user_groups_array);
					}
					// Update user meta data
					update_user_meta($site_id, WA_Integration::WA_USER_STATUS_KEY, $updated_status);
					update_user_meta($site_id, WA_Integration::WA_ORGANIZATION_KEY, $updated_organization);
					update_user_meta($site_id, WA_Integration::WA_MEMBERSHIP_LEVEL_KEY, $updated_membership_level);
					update_user_meta($site_id, WA_Integration::WA_MEMBERSHIP_LEVEL_ID_KEY, $updated_membership_level_id);
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

	/**
	 * Returns a new access token after it has expired
	 * https://gethelp.wildapricot.com/en/articles/484#:~:text=for%20this%20access_token-,How%20to%20refresh%20tokens,-To%20refresh%20the
	 *
	 * @return array $data holds the response for refreshing the token
	 */
	public static function get_new_access_token($refresh_token) {
		// Get decrypted credentials
		$decrypted_credentials = self::load_user_credentials();
		
		// Encode API key
		$authorization_string = $decrypted_credentials[WA_Integration::WA_CLIENT_ID_OPT] . ':' . $decrypted_credentials[WA_Integration::WA_CLIENT_SECRET_OPT];
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

		try {
			$data = self::response_to_data($response);
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error retrieving the Wild Apricot API access token.');
		}
		
		return $data;
	}

	/**
	 * Performs an API request to get data about the current WildApricot user
	 *
	 * @return array $contact_info holds the body of the API response
	 */
    public function get_info_on_current_user() {
        // Get details of current WA user with API request
		// Get user's contact ID
        $args = $this->request_data_args();
		$contact_info = wp_remote_get(self::API_URL . self::ADMIN_API_VERSION . '/accounts/' . $this->wa_user_id . '/contacts/me?getExtendedMembershipInfo=true', $args);

		try {
			$contact_info = self::response_to_data($contact_info);
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error retrieving Wild Apricot contact info.');
		} 
		
		// Get if user is administrator or not
		$is_administrator = $contact_info['IsAccountAdministrator'];
		// Perform API call based on if user is administrator or not
		$user_data_api = null;
		if (isset($is_administrator) && $is_administrator == '1') { // user is administrator
			$contact_id = $contact_info['Id'];
			$user_data_api = wp_remote_get(self::API_URL . self::ADMIN_API_VERSION . '/accounts/' . $this->wa_user_id . '/contacts/' . $contact_id . '?getExtendedMembershipInfo=true', $args);
		} else { // not administrator
			$user_data_api = wp_remote_get('https://api.wildapricot.org/publicview/' . self::MEMBER_API_VERSION . '/accounts/' . $this->wa_user_id . '/contacts/me?includeDetails=true', $args);
		}
		// Extract body

		try {
			$full_info = self::response_to_data($user_data_api);
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error retriving Wild Apricot user info.');
		}
		
		// Get all information for current user
        return $full_info;
    }

	/**
	 * Returns the membership levels of the current WildApricot organization
	 *
	 * @return array $membership_levels holds the membership levels from WildApricot
	 */
    public function get_membership_levels($request_groups = false) {
        $args = $this->request_data_args();
		// ABSTRACT VARIABLE IN URL
		$url = self::API_URL . self::ADMIN_API_VERSION . '/accounts/' . $this->wa_user_id . '/membershiplevels';
		if ($request_groups) {
        	$url = self::API_URL . self::ADMIN_API_VERSION . '/accounts/' . $this->wa_user_id . '/membergroups';
		}
        $membership_levels_response = wp_remote_get($url, $args);

        // Return membership levels
		try {
			$membership_levels_response = self::response_to_data($membership_levels_response);
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error retrieving the Wild Apricot membership levels.');
		}
        
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
	 * Static function that checks if application codes (API Key, Client ID,
	 * and Client Secret) are valid
	 *
	 * @param string $entered_api_key The WildApricot API Key to check
	 * @return array|bool $data An array of the response from the WA API
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
	 * Connect user to WildApricot API after obtaining their email and password
	 *
	 * https://gethelp.wildapricot.com/en/articles/484
	 *
	 * @param array $valid_login Holds the email and password entered into the
	 * login screen
	 * @return array $data Returns the response from the WA API
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
			'body' => array(
				'grant_type' => 'password',
                'username' => $valid_login['email'],
                'password' => $valid_login['password'],
				'scope' => 'auto'
			)
		);
		$response = wp_remote_post('https://oauth.wildapricot.org/auth/token', $args);

		try {
			$data = self::response_to_data($response);
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error authorizing a Wild Apricot user\'s credentials.');
		} 
		
		return $data;
	}

	/**
	 * Retrieves list of contacts from Wild Apricot.
	 *
	 * @param string $query additional query to append to the request url
	 * @param boolean $block whether a single block is requested or not, used
	 * for REST API requests. Default false. 
	 * @param integer $skip skip query for request url. Default 0.
	 * @param integer $top top query for request url. Default 500.
	 * @return array list of contacts
	 */
	public function retrieve_contacts_list($query, $block = false, $skip = 0, $top = 200) {
		$base_url = self::API_URL . self::ADMIN_API_VERSION . '/accounts/' . 
		$this->wa_user_id . '/contacts?%24async=false&%24' . $query;

		// return single block
		if ($block) {
			return $this->request_contact_block($base_url, $skip, $top);
		}

		$all_contacts = array(
			'Contacts' => array()
		);
		$count = $this->get_contacts_count();
		$done = false;

		// retrieve in blocks of 500
		while (!$done) {
			// if there are more than 500 entires left, include top query
			if (($count - $skip) <= $top) {
				$top = 0;
				$done = true;
			}

			// make API request and add block to list of all contacts
			$contacts_block = $this->request_contact_block($base_url, $skip, $top);
			// Log::wap_log_debug($contacts_block);
			$all_contacts['Contacts'] = array_merge(
				$all_contacts['Contacts'],
				$contacts_block['Contacts']
			);

			// increment by block size
			$skip += $top;
		}

		return $all_contacts;

	}

	/**
	 * Retrieves number of contacts from Wild Apricot.
	 *
	 * @return int number of contacts
	 */
	public function get_contacts_count() {

		$count = get_option(WA_Integration::WA_CONTACTS_COUNT_KEY);
		if ($count) return $count;

		$url = self::API_URL . self::ADMIN_API_VERSION . '/accounts/' . 
			$this->wa_user_id . '/contacts?%24async=false&%24count=true';

		$args = $this->request_data_args();
		$response = wp_remote_get($url, $args);

		try {
			$data = self::response_to_data($response);
			$count = $data['Count'];
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error retrieving the number of Wild Apricot contacts.');
		}

		update_option(WA_Integration::WA_CONTACTS_COUNT_KEY, $count);

		return $count;
	}

	/**
	 * Requests a single block of contacts from Wild Apricot.
	 *
	 * @param string $url base url to which to make the request
	 * @param int $skip the number of contacts to skip from the beginning
	 * @param int $top the number of contacts to return
	 * @return array block of contacts
	 */
	private function request_contact_block($url, $skip, $top) {

		if ($skip) {
			$url .= '&$skip=' . $skip; 
		 }

		if ($top) {
			$url .= '&$top=' . $top;
		}

		$args = $this->request_data_args();

		$response = wp_remote_get($url, $args);

		try {
			$data = self::response_to_data($response);
		} catch (API_Exception $e) {
			throw new API_Exception('There was an error retrieving Wild Apricot contacts.');
		}

		return $data;
	}

	/**
	 * Returns yesterday's date in the format yyyy-mm-dd.
	 *
	 * @return string
	 */
	private function get_yesterdays_date() {
		return date('Y-m-d',strtotime("-1 days"));
	}
}