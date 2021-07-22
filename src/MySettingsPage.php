<?php
namespace WAWP;

require_once __DIR__ . '/Addon.php';

use WAWP\Addon;

use function PHPSTORM_META\map;

// Include css file
/*?>
<style>
<?php include 'CSS/main.css'; ?>
</style>
<?php*/

class MySettingsPage
{
    const CRON_HOOK = 'wawp_cron_refresh_memberships_hook';

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Adds actions and includes files
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
        // Set default global page restriction message
        if (!get_option('wawp_restriction_name')) {
            add_option('wawp_restriction_name', '<h2>Restricted Content!</h2> <p>Oops! This post is restricted to specific Wild Apricot users. Log into your Wild Apricot account or ask your administrator to add you to the post!</p>');
        }

        // Add actions for cron update
        add_action(self::CRON_HOOK, array($this, 'cron_update_wa_memberships'));

        // Include files
        require_once('DataEncryption.php');
        require_once('WAWPApi.php');
    }

    /**
     * Removes the invalid, now deleted groups and levels after the membership levels and groups are updated
     * Please note that, while this function refers to "levels", this function works for both levels and groups
     *
     * @param $updated_levels        is an array of the new levels obtained from refresh
     * @param $old_levels            is an array of the previous levels before refresh
     * @param $restricted_levels_key is the key of the restricted levels to be saved
     */
    private function remove_invalid_groups_levels($updated_levels, $old_levels, $restricted_levels_key) {
        $restricted_posts = get_option('wawp_array_of_restricted_posts');

        // Convert levels arrays to its keys
        $updated_levels = array_keys($updated_levels);
        $old_levels = array_keys($old_levels);

        // Loop through each old level and check if it is in the updated levels
        foreach ($old_levels as $old_level) {
            if (!in_array($old_level, $updated_levels)) { // old level is NOT in the updated levels
                // This is a deleted level! ($old_level)
                $level_to_delete = $old_level;
                // Remove this level from restricted posts
                // Loop through each restricted post and check if its post meta data contains this level
                foreach ($restricted_posts as $restricted_post) {
                    // Get post's list of restricted levels
                    $post_restricted_levels = get_post_meta($restricted_post, $restricted_levels_key);
                    $post_restricted_levels = maybe_unserialize($post_restricted_levels[0]);
                    // See line 230 on WAIntegration.php
                    if (in_array($level_to_delete, $post_restricted_levels)) {
                        // Remove this updated level from post restricted levels
                        $post_restricted_levels = array_diff($post_restricted_levels, array($level_to_delete));
                    }
                    // Save new restricted levels to post meta data
                    $post_restricted_levels = maybe_serialize($post_restricted_levels);
                    // Delete past value
                    // $old_post_levels = get_post_meta($restricted_post);
                    update_post_meta($restricted_post, $restricted_levels_key, $post_restricted_levels); // single value
                }
            }
        }
    }

    /**
     * Updates the membership levels and groups from Wild Apricot into WordPress upon each CRON job
     */
    public function cron_update_wa_memberships() {

        $dataEncryption = new DataEncryption();

        // Get access token and account id
        $access_token = get_transient('wawp_admin_access_token');
        $wa_account_id = get_transient('wawp_admin_account_id');

        $same_credentials = true;

        // Check that the transients are still valid -> if not, get new token
        if (empty($access_token) || empty($wa_account_id)) {
            $same_credentials = false;
            // Retrieve refresh token from database
            $refresh_token = $dataEncryption->decrypt(get_option('wawp_admin_refresh_token'));
            // Get new access token
            $new_response = WAWPApi::get_new_access_token($refresh_token);
            // Get variables from response
            $new_access_token = $new_response['access_token'];
            $new_expiring_time = $new_response['expires_in'];
            $new_account_id = $new_response['Permissions'][0]['AccountId'];
            // Set these new values to the transients
            set_transient('wawp_admin_access_token', $dataEncryption->encrypt($new_access_token), $new_expiring_time);
            set_transient('wawp_admin_account_id', $dataEncryption->encrypt($new_account_id), $new_expiring_time);
            // Update values
            $access_token = $new_access_token;
            $wa_account_id = $new_account_id;
        }

        if (!empty($access_token) && !empty($wa_account_id)) {
            if ($same_credentials) {
                $access_token = $dataEncryption->decrypt($access_token);
                $wa_account_id = $dataEncryption->decrypt($wa_account_id);
            }

            // Create WAWP Api instance
            $wawp_api = new WAWPApi($access_token, $wa_account_id);

            // Get membership levels
            $updated_levels = $wawp_api->get_membership_levels();

            // Get membership groups
            $updated_groups = $wawp_api->get_membership_levels(true);

            // If the number of updated groups/levels is less than the number of old groups/levels, then this means that one or more group/level has been deleted
            // So, we must find the deleted group/level and remove it from the restriction post meta data of a post, if applicable
            $old_levels = get_option('wawp_all_levels_key');
            $old_groups = get_option('wawp_all_groups_key');
            $restricted_posts = get_option('wawp_array_of_restricted_posts');
            if (!empty($restricted_posts)) {
                if (!empty($old_levels) && !empty($updated_levels) && (count($updated_levels) < count($old_levels))) {
                    $this->remove_invalid_groups_levels($updated_levels, $old_levels, 'wawp_restricted_levels');
                }
                if (!empty($old_groups) && !empty($updated_groups) && (count($updated_groups) < count($old_groups))) {
                    $this->remove_invalid_groups_levels($updated_groups, $old_groups, 'wawp_restricted_groups');
                }
            }

            // Save updated levels to options table
            update_option('wawp_all_levels_key', $updated_levels);
            // Save updated groups to options table
            update_option('wawp_all_groups_key', $updated_groups);

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
        ?>
        <form method="post" action="options.php">
			<?php
                // Nonce for verification
                wp_nonce_field('wawp_restriction_nonce_action', 'wawp_restriction_nonce_name');
				// This prints out all hidden setting fields
				settings_fields( 'wawp_restriction_group' );
                settings_fields('wawp_restriction_status_group');
				do_settings_sections( 'wawp-wal-admin' );
				submit_button();
			?>
		</form>
        <?php
    }

    /**
     * Displays the checkboxes for selecting the restricted status(es)
     */
    public function restriction_status_callback() {
        // Display checkboxes for each Wild Apricot status
        // List of statuses here: https://gethelp.wildapricot.com/en/articles/137-member-and-contact-statuses
        $list_of_statuses = array(
            'Active' => 'Active',
            'Lapsed' => 'Lapsed',
            'PendingNew' => 'Pending - New',
            'PendingRenewal' => 'Pending - Renewal',
            'PendingLevel' => 'Pending - Level change'
        );
        // Should 'suspended' and 'archived' be included?

        // Load in the list of restricted statuses, if applicable
        $saved_statuses = get_option('wawp_restriction_status_name');
        // Check if saved statuses exists; if not, then create an empty array
        if (empty($saved_statuses)) { // create empty array
            $saved_statuses = array();
        }

        // Loop through the list of statuses and add each as a checkbox
        foreach ($list_of_statuses as $status_key => $status) {
            // Check if checkbox is already checked off
            $status_checked = '';
            if (in_array($status_key, $saved_statuses)) {
                $status_checked = 'checked';
            }
            ?>
            <input type="checkbox" name="wawp_restriction_status_name[]" class='wawp_class_status' value="<?php echo htmlspecialchars($status_key); ?>" <?php echo($status_checked); ?>/> <?php echo htmlspecialchars($status); ?> </input><br>
            <?php
        }
    }

    /**
     * Displays the restriction message text area
     */
    public function restriction_message_callback() {
        // Add wp editor
        // See: https://stackoverflow.com/questions/20331501/replacing-a-textarea-with-wordpress-tinymce-wp-editor
        // https://developer.wordpress.org/reference/functions/wp_editor/
        // Get default or saved restriction message
        $initial_content = get_option('wawp_restriction_name');
        $editor_id = 'wawp_restricted_message_textarea';
        $editor_name = 'wawp_restriction_name';
        $editor_settings = array('textarea_name' => $editor_name, 'tinymce' => true);
        // Create WP editor
        wp_editor($initial_content, $editor_id, $editor_settings);
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
					   <li>In the admin view on your Wild Apricot site, in the left hand menu, select Settings. On the Global settings screen, select the Authorized applications option (under Integration). <br><br>
					      <img src="https://user-images.githubusercontent.com/458134/122569603-e8e44a80-d018-11eb-86b9-0386c6d23a5f.png" alt="Settings > Integration > Authorized applications" width="500"> <br>
					   </li>
					   <li>On the Authorized applications screen, click the Authorize application button in the top left corner.
					      <br><br>
					      <img src="https://user-images.githubusercontent.com/458134/122569583-e2ee6980-d018-11eb-879a-bbbcbecbc349.png" alt="Authorized application button" width="500"> <br>
					   </li>
					   <li> On the Application authorization screen, click the Server application option then click the Continue button. <br><br>
					      <img src="https://raw.githubusercontent.com/kendrakleber/files/master/server.png" alt="Authorized application button" width="500"><br>
					   </li>
					   <li>
					      On the Application details screen, the following options should be set:
					      <ul>
						 <li>
						    Application name
						    <ul>
						       <li>The name used to identify this application within the list of authorized applications. Select whatever name you like. For our example, it will be called "Our WordPress Site"
						       </li>
						    </ul>
						 </li>
						 <li>
						    Access Level
						    <ul>
						       <li>Choose full access as the WAWP plugin requires ability to read and write to your Wild Apricot database.
						       </li>
						    </ul>
						 </li>
						 <li>
						    Client Secret
						    <ul>
						       <li>If there is no Client secret value displayed, click the green Generate client secret button. To delete the client secret, click the red X beside the value.
						       </li>
						    </ul>
					      </ul>
					   </li>
					   <li>
					      Click the Save button to save your changes.
					   </li>
					   <li>From the Application details screen, copy the API key, Client ID, and Client secret (the blue boxes). Input these values into their respective locations in Wordpress, to the rights of these instructions. <br><br>
					      <img src="https://raw.githubusercontent.com/kendrakleber/files/master/values.png" alt="Authorized application button" width="500">  <br>
					   </li>
					   <br>
					</ol>
				</div>
				<div class="loginChild">
					<form method="post" action="options.php">
					<?php
                        // Nonce for verification
                        wp_nonce_field('wawp_credentials_nonce_action', 'wawp_credentials_nonce_name');
						// This prints out all hidden setting fields
						settings_fields( 'wawp_wal_group' );
						do_settings_sections( 'wawp-login' );
						submit_button();
					?>
					</form>
					<!-- Check if form is valid -->
					<?php
                        // $successful_credentials_entered = get_option('wawp_wal_success');
						if (!isset($this->options['wawp_wal_api_key']) || !isset($this->options['wawp_wal_client_id']) || !isset($this->options['wawp_wal_client_secret']) || $this->options['wawp_wal_api_key'] == '' || $this->options['wawp_wal_client_id'] == '' || $this->options['wawp_wal_client_secret'] == '') { // not valid
							echo '<p style="color:red">Invalid credentials! Please try again!</p>';
                            // Save that wawp credentials are not fully activated
                            update_option('wawp_wa_credentials_valid', false);
                            do_action('wawp_wal_set_login_private');
						} else { // successful login
							echo '<p style="color:green">Success! Credentials saved!</p>';
                            // Save that wawp credentials have been fully activated
                            update_option('wawp_wa_credentials_valid', true);
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
     * Sanitize restriction
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function restriction_status_sanitize($input) {
        $valid = array();
        // Loop through each checkbox and sanitize
        if (!empty($input)) {
            foreach ($input as $key => $box) {
                $valid[$key] = filter_var($box, FILTER_SANITIZE_STRING);
            }
        }
        // Return sanitized value
        return $valid;
    }

    /**
     * Sanitize restriction message
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function restriction_sanitize($input) {
        // Check that nonce is valid
        if (!wp_verify_nonce($_POST['wawp_restriction_nonce_name'], 'wawp_restriction_nonce_action')) {
            wp_die('Your nonce for the restriction message could not be verified.');
        }
		// Create valid variable that will hold the valid input
        // Sanitize wp editor
        // https://wordpress.stackexchange.com/questions/262796/sanitize-content-from-wp-editor
		$valid = wp_kses_post($input);
        // Return valid input
        return $valid;
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
            'wawp-login' // Page
        );

		// Settings for API Key
        add_settings_field(
            'wawp_wal_api_key', // ID
            'API Key:', // Title
            array( $this, 'api_key_callback' ), // Callback
            'wawp-login', // Page
            'wawp_wal_id' // Section
        );

		// Settings for Client ID
        add_settings_field(
            'wawp_wal_client_id', // ID
            'Client ID:', // Title
            array( $this, 'client_id_callback' ), // Callback
            'wawp-login', // Page
            'wawp_wal_id' // Section
        );

		// Settings for Client Secret
		add_settings_field(
            'wawp_wal_client_secret', // ID
            'Client Secret:', // Title
            array( $this, 'client_secret_callback' ), // Callback
            'wawp-login', // Page
            'wawp_wal_id' // Section
        );

        // Settings for Menu to add Login/Logout button
        add_settings_field(
            'wawp_wal_login_logout_button', // ID
            'Menu:', // Title
            array( $this, 'login_logout_menu_callback' ), // Callback
            'wawp-login', // Page
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

        // ------------------------ Restriction status ---------------------------
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'restriction_status_sanitize'),
            'default' => NULL
        );
        register_setting(
            'wawp_restriction_status_group', // group name for settings
            'wawp_restriction_status_name', // name of option to sanitize and save
            $register_args
        );
        // Add settings section and field for restriction status
        add_settings_section(
            'wawp_restriction_status_id', // ID
            'Wild Apricot Status Restriction', // title
            array($this, 'print_restriction_status_info'), // callback
            'wawp-wal-admin' // page
        );
        // Field for membership statuses
        add_settings_field(
            'wawp_restriction_status_field_id', // ID
            'Membership Status(es):', // title
            array($this, 'restriction_status_callback'), // callback
            'wawp-wal-admin', // page
            'wawp_restriction_status_id' // section
        );

        // ------------------------ Restriction page ---------------------------
        // Register setting
        $register_args = array(
            'type' => 'string',
            'sanitize_callback' => array( $this, 'restriction_sanitize'),
            'default' => NULL
        );
        register_setting(
            'wawp_restriction_group', // group name for settings
            'wawp_restriction_name', // name of option to sanitize and save
            $register_args
        );

        // Add settings section and field for restriction message
        add_settings_section(
            'wawp_restriction_id', // ID
            'Global Restriction Message', // title
            array($this, 'print_restriction_info'), // callback
            'wawp-wal-admin' // page
        );
        // Field for restriction message
        add_settings_field(
            'wawp_restriction_field_id', // ID
            'Restriction Message:', // title
            array($this, 'restriction_message_callback'), // callback
            'wawp-wal-admin', // page
            'wawp_restriction_id' // section
        );
    }

    /**
	 * Setups up CRON job
	 */
	public static function setup_cron_job() {
        //If $timestamp === false schedule the event since it hasn't been done previously
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            //Schedule the event for right now, then to repeat daily using the hook
            wp_schedule_event(current_time('timestamp'), 'daily', self::CRON_HOOK);
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
        // require_once('DataEncryption.php');
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
        } else { // Valid input and valid response
            // Extract access token and ID, as well as expiring time
            $access_token = $valid_api['access_token'];
            $account_id = $valid_api['Permissions'][0]['AccountId'];
            $expiring_time = $valid_api['expires_in'];
            $refresh_token = $valid_api['refresh_token'];
            // Store access token and account ID as transients
            set_transient('wawp_admin_access_token', $dataEncryption->encrypt($access_token), $expiring_time);
            set_transient('wawp_admin_account_id', $dataEncryption->encrypt($account_id), $expiring_time);
            // Store refresh token in database
            update_option('wawp_admin_refresh_token', $dataEncryption->encrypt($refresh_token));
            // Get all membership levels and groups
            $wawp_api_instance = new WAWPApi($access_token, $account_id);
            $all_membership_levels = $wawp_api_instance->get_membership_levels();
            // Create a new role for each membership level
            // Delete old roles if applicable
            $old_wa_roles = get_option('wawp_all_levels_key');
            if (isset($old_wa_roles) && !empty($old_wa_roles)) {
                // Loop through each role and delete it
                foreach ($old_wa_roles as $old_role) {
                    remove_role('wawp_' . str_replace(' ', '', $old_role));
                }
            }
            foreach ($all_membership_levels as $level) {
                // In identifier, remove spaces so that the role can become a single word
                add_role('wawp_' . str_replace(' ', '', $level), $level);
            }
            $all_membership_groups = $wawp_api_instance->get_membership_levels(true);
            // Save membership levels and groups to options
            update_option('wawp_all_levels_key', $all_membership_levels);
            update_option('wawp_all_groups_key', $all_membership_groups);

            // Schedule CRON update for updating the available membership levels and groups
            self::setup_cron_job();
        }

        // Sanitize menu dropdown
        $valid['wawp_wal_login_logout_button'] = sanitize_text_field($input['wawp_wal_login_logout_button']);

		// Return array of valid inputs
		return $valid;
    }

    /**
     * Print instructions on how to use the restriction status checkboxes
     */
    public function print_restriction_status_info() {
        print 'Please select the Wild Apricot member/contact status(es) that will be able to see the restricted posts.';
    }

    /**
     * Print the Restriction text
     */
    public function print_restriction_info() {
        print 'The "Global Restriction Message" is the message that is shown to users who are not members of the Wild Apricot membership level(s) or group(s) required to access a restricted post. Try to make the message informative; for example, you can suggest what the user can do in order to be granted access to the post. You can also set a custom restriction message for each individual post by editing the "Individual Restriction Message" field under the post editor.';
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
