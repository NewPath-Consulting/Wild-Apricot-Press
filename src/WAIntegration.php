<?php
namespace WAWP;

/**
 * Class for managing the user's Wild Apricot account
 */
class WAIntegration {
	// Constants for keys used for database management
	const ACCESS_TOKEN_META_KEY = 'wawp_wa_access_token';
	const REFRESH_TOKEN_META_KEY = 'wawp_wa_refresh_token';
	const TIME_TO_REFRESH_TOKEN = 'wawp_time_to_refresh_token';
	const WA_USER_ID_KEY = 'wawp_wa_user_id';
	const WA_MEMBERSHIP_LEVEL_KEY = 'wawp_membership_level_key';
	const WA_MEMBERSHIP_LEVEL_ID_KEY = 'wawp_membership_level_id_key';
	const WA_USER_STATUS_KEY = 'wawp_user_status_key';
	const WA_ORGANIZATION_KEY = 'wawp_organization_key';
	const WA_MEMBER_GROUPS_KEY = 'wawp_list_of_groups_key';
	const WA_ALL_MEMBERSHIPS_KEY = 'wawp_all_levels_key';
	const RESTRICTED_GROUPS = 'wawp_restricted_groups';
	const RESTRICTED_LEVELS = 'wawp_restricted_levels';
	const IS_POST_RESTRICTED = 'wawp_is_post_restricted';
	const ARRAY_OF_RESTRICTED_POSTS = 'wawp_array_of_restricted_posts';
	const INDIVIDUAL_RESTRICTION_MESSAGE_KEY = 'wawp_individual_restriction_message_key';
	const CRON_USER_ID = 'wawp_cron_user_id';
	const ADMIN_ACCOUNT_ID_TRANSIENT = 'wawp_admin_account_id';
	const ADMIN_ACCESS_TOKEN_TRANSIENT = 'wawp_admin_access_token';
	const ADMIN_REFRESH_TOKEN_OPTION = 'wawp_admin_refresh_token';

	const USER_REFRESH_HOOK = 'wawp_cron_refresh_user_hook';

	/**
	 * Constructs an instance of the WAIntegration class
	 *
	 * Adds the actions and filters required. Includes other required files. Initializes class variables.
	 *
	 */
	public function __construct() {
		// Hook that runs after Wild Apricot credentials are saved
		add_action('wawp_wal_credentials_obtained', array($this, 'create_login_page'));
		// Action for when login page is updated when submit button is pressed
		add_action('template_redirect', array($this, 'create_user_and_redirect'));
		// Filter for adding to menu
		add_filter('wp_nav_menu_items', array($this, 'create_wa_login_logout'), 10, 2); // 2 arguments
		// Shortcode for login form
		add_shortcode('wawp_custom_login_form', array($this, 'custom_login_form_shortcode'));
		// Add redirectId to query vars array
		add_filter('query_vars', array($this, 'add_custom_query_vars'));
		// Action for making profile page private
		add_action('wawp_wal_set_login_private', array($this, 'make_login_private'));
		// Actions for displaying membership levels on user profile
		add_action('show_user_profile', array($this, 'show_membership_level_on_profile'));
		add_action('edit_user_profile', array($this, 'show_membership_level_on_profile'));
		// Fire our meta box setup function on the post editor screen
		add_action('load-post.php', array($this, 'post_access_meta_boxes_setup'));
		add_action('load-post-new.php', array($this, 'post_access_meta_boxes_setup'));
		// Loads in restricted groups/levels when post is saved
		add_action('save_post', array($this, 'post_access_load_restrictions'), 10, 2); // changed to all types of posts
		// On post load check if the post can be accessed by the current user
		add_action('the_content', array($this, 'restrict_post_wa'));
		// Action for creating 'select all' checkboxes
		add_action('wawp_create_select_all_checkboxes', array($this, 'select_all_checkboxes_jquery'));
		// Action for user refresh cron hook
		add_action(self::USER_REFRESH_HOOK, array($this, 'refresh_user_wa_info'));
		// Action for when the user logs out
		// add_action('wp_logout', array($this, 'remove_user_wa_update'));
		// Include any required files
		require_once('DataEncryption.php');
		require_once('WAWPApi.php');
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
	 * Add query vars to WordPress
	 *
	 * See: https://stackoverflow.com/questions/20379543/wordpress-get-query-var
	 *
	 * @param array  $vars Current, incoming query vars
	 * @return array $vars Updated vars array with added query var
	 */
	public function add_custom_query_vars($vars) {
		// Add redirectId to query vars
		$vars[] = 'redirectId';
		return $vars;
	}

	/**
	 * Creates login page that allows user to enter their email and password credentials for Wild Apricot
	 *
	 * See: https://stackoverflow.com/questions/32314278/how-to-create-a-new-wordpress-page-programmatically
	 * https://stackoverflow.com/questions/13848052/create-a-new-page-with-wp-insert-post
	 *
	 */
	public function create_login_page() {
		// Check if Login page exists first
		$login_page_id = get_option('wawp_wal_page_id');
		if (isset($login_page_id) && $login_page_id != '') { // Login page already exists
			// Set existing login page to publish
			$login_page = get_post($login_page_id, 'ARRAY_A');
			$login_page['post_status'] = 'publish';
			wp_update_post($login_page);
			// Add user roles
			$saved_wa_roles = get_option('wawp_all_levels_key');
			// Loop through roles and add them as roles to WordPress
			if (!empty($saved_wa_roles)) {
				foreach ($saved_wa_roles as $role) {
					add_role('wawp_' . str_replace(' ', '', $role), $role);
				}
			}
		} else { // Login page does not exist
			// Create details of page
			// See: https://wordpress.stackexchange.com/questions/222810/add-a-do-action-to-post-content-of-wp-insert-post
			$post_details = array(
				'post_title' => 'WA4WP Wild Apricot Login',
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_content' => '[wawp_custom_login_form]' // shortcode
			);
			$page_id = wp_insert_post($post_details, FALSE);
			// Add page id to options so that it can be removed on deactivation
			update_option('wawp_wal_page_id', $page_id);
		}
		// Remove from header if it is automatically added
		$menu_with_button = get_option('wawp_wal_name')['wawp_wal_login_logout_button']; // get this from settings
		// https://wordpress.stackexchange.com/questions/86868/remove-a-menu-item-in-menu
		// https://stackoverflow.com/questions/52511534/wordpress-wp-insert-post-adds-page-to-the-menu
		$page_id = get_option('wawp_wal_page_id');
		$menu_item_ids = wp_get_associated_nav_menu_items($page_id, 'post_type');
		// Loop through ids and remove
		foreach ($menu_item_ids as $menu_item_id) {
			wp_delete_post($menu_item_id, true);
		}
	}

	/**
	 * Sets the login page to private if the plugin is deactivated or invalid credentials are entered
	 */
	public function make_login_private() {
		// Check if login page exists
		$login_page_id = get_option('wawp_wal_page_id');
		if (isset($login_page_id) && $login_page_id != '') { // Login page already exists
			// Make login page private
			// Set existing login page to publish
			$login_page = get_post($login_page_id, 'ARRAY_A');
			$login_page['post_status'] = 'private';
			wp_update_post($login_page);
		}
	}

	/**
	 * Adds error to login shortcode page
	 *
	 * @param  string $content Holds the existing content on the page
	 * @return string $content Holds the new content on the page
	 */
	public function add_login_error($content) {
		// Only run on wa4wp page
		$login_page_id = get_option('wawp_wal_page_id');
		if (is_page($login_page_id)) {
			return $content . '<p style="color:red;">Invalid credentials! Please check that you have entered the correct email and password.
			If you are sure that you entered the correct email and password, please contact your administrator.</p>';
		}
		return $content;
	}

	/**
	 * Generates the URL to the WA4WP Login page on the website
	 */
	private function get_login_link() {
		$login_url = esc_url(site_url() . '/index.php?pagename=wa4wp-wild-apricot-login');
		// Get current page id
		// https://wordpress.stackexchange.com/questions/161711/how-to-get-current-page-id-outside-the-loop
		$current_page_id = get_queried_object_id();
		$login_url = esc_url(add_query_arg(array(
			'redirectId' => $current_page_id,
		), $login_url));
		// Return login url
		return $login_url;
	}

	/**
	 * Determines whether or not to restrict the post to the current user based on the user's levels/groups and the post's list of restricted levels/groups
	 *
	 * @param string $post_content holds the post content in HTML form
	 *
	 * @return string $post_content is the new post content - either the original post content if the post is not restricted, or the restriction message if otherwise
	 */
	public function restrict_post_wa($post_content) {
		// Get ID of current post
		$current_post_ID = get_queried_object_id();
		// Check if valid Wild Apricot credentials have been entered
		$valid_wa_credentials = get_option('wawp_wa_credentials_valid');

		// Make sure a page/post is requested and the user has already entered their valid Wild Apricot credentials
		if (is_singular() && isset($valid_wa_credentials) && $valid_wa_credentials) {
			// Check that this current post is restricted
			$is_post_restricted = get_post_meta($current_post_ID, WAIntegration::IS_POST_RESTRICTED, true); // return single value
			if (isset($is_post_restricted) && $is_post_restricted) {
				// Load in restriction message from message set by user
				$restriction_message = wpautop(get_option('wawp_restriction_name'));
				// Check if current post has a custom restriction message
				$individual_restriction_message = wpautop(get_post_meta($current_post_ID, WAIntegration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, true));
				if (!empty($individual_restriction_message) && $individual_restriction_message != '') { // this post has an individual restriction message
					$restriction_message = $individual_restriction_message;
				}
				// Append 'Log In' button to the restriction message
				$login_url = $this->get_login_link();
				$restriction_message .= '<li id="wawp_restriction_login_button"><a href="'. $login_url .'">Log In</a></li>';
				// Automatically restrict the post if user is not logged in
				if (!is_user_logged_in()) {
					return $restriction_message;
				}
				// Show a warning/notice on the restriction page if the user is logged into WordPress but is not synced with Wild Apricot
				// Get user's Wild Apricot ID -> if it does not exist, then the user is not synced with Wild Apricot
				$current_user_ID = wp_get_current_user()->ID;
				$user_wa_id = get_user_meta($current_user_ID, WAIntegration::WA_USER_ID_KEY, true);
				if (empty($user_wa_id)) {
					// Present notice that user is not synced with Wild Apricot
					$restriction_message .= '<p style="color:red;">Please note that while you are logged into WordPress, you have not synced your account with Wild Apricot. Please <a href="'. $login_url .'">Log In</a> into your Wild Apricot account to sync your data in your WordPress site.</p>';
					return $restriction_message;
				}

				// Get post meta data
				// Get post's restricted groups
				$post_restricted_groups = get_post_meta($current_post_ID, WAIntegration::RESTRICTED_GROUPS);
				// Unserialize
				$post_restricted_groups = maybe_unserialize($post_restricted_groups[0]);
				// Get post's restricted levels
				$post_restricted_levels = get_post_meta($current_post_ID, WAIntegration::RESTRICTED_LEVELS);
				// Unserialize
				$post_restricted_levels = maybe_unserialize($post_restricted_levels[0]);

				// If no options are selected, then the post is unrestricted, as there cannot be a post with no viewers
				if (empty($post_restricted_groups) && empty($post_restricted_levels)) {
					update_post_meta($current_post_ID, WAIntegration::IS_POST_RESTRICTED, false);
					return $post_content;
				}

				// Get user meta data
				$user_groups = get_user_meta($current_user_ID, WAIntegration::WA_MEMBER_GROUPS_KEY);
				$user_level = get_user_meta($current_user_ID, WAIntegration::WA_MEMBERSHIP_LEVEL_ID_KEY, true);
				$user_status = get_user_meta($current_user_ID, WAIntegration::WA_USER_STATUS_KEY, true);

				// Check if user's status is allowed to view restricted posts
				// Get restricted status(es) from options table
				$restricted_statuses = get_option('wawp_restriction_status_name');
				// If there are restricted statuses, then we must check them against the user's status
				if (!empty($restricted_statuses)) {
					// If user's status is not in the restricted statuses, then the user cannot see the post
					if (!in_array($user_status, $restricted_statuses)) {
						// User cannot access the post
						return $restriction_message;
					}
				}

				// Find common groups between the user and the post's restrictions
				// If user_groups is NULL, then the user is not part of any groups
				$common_groups = array();
				if (!empty($user_groups) && !empty($post_restricted_groups)) {
					$user_groups = maybe_unserialize($user_groups[0]);
					// Get keys of each group
					$user_groups = array_keys($user_groups);

					// Check if post groups and user groups overlap
					$common_groups = array_intersect($user_groups, $post_restricted_groups); // not empty if one or more of the user's groups are within the post's restricted groups
				}

				// Find common levels between the user and the post's restrictions
				$common_level = false;
				if (!empty($post_restricted_levels) && !empty($user_level)) {
					$common_level = in_array($user_level, $post_restricted_levels); // true if the user's level is one of the post's restricted levels
				}

				// Determine if post should be restricted
				if (empty($common_groups) && !$common_level) {
					// Page should be restricted
					return $restriction_message;
				}
			}
		}
		// Return original post content if no changes are made
		return $post_content;
	}

	/**
	 * Processes the restricted groups set in the post meta data and update these levels/groups to the current post's meta data
	 *
	 * @param int     $post_id holds the ID of the current post
	 * @param WP_Post $post holds the current post
	 */
	public function post_access_load_restrictions($post_id, $post) {
		// Verify the nonce before proceeding
		if (!isset($_POST['wawp_post_access_control']) || !wp_verify_nonce($_POST['wawp_post_access_control'], basename(__FILE__))) {
			// Invalid nonce
			return;
		}

		// Return if user does not have permission to edit the post
		if (!current_user_can('edit_post', $post_id)) {
			// User cannot edit the post
			return;
		}

		// Get levels and groups that the user checked off
		// Get value if index has been set to $_POST, and set to an empty array if NOT
		$checked_groups_ids = array_key_exists('wawp_membership_groups', $_POST) ? $_POST['wawp_membership_groups'] : array();
		$checked_levels_ids = array_key_exists('wawp_membership_levels', $_POST) ? $_POST['wawp_membership_levels'] : array();
		// Serialize results for storage
		$checked_groups_ids = maybe_serialize($checked_groups_ids);
		$checked_levels_ids = maybe_serialize($checked_levels_ids);
		// Delete past restricted groups if they exist
		$old_groups = get_post_meta($post_id, WAIntegration::RESTRICTED_GROUPS);
		if (isset($old_groups)) {
			delete_post_meta($post_id, WAIntegration::RESTRICTED_GROUPS);
		}
		$old_levels = get_post_meta($post_id, WAIntegration::RESTRICTED_LEVELS);
		if (isset($old_levels)) {
			delete_post_meta($post_id, WAIntegration::RESTRICTED_LEVELS);
		}
		// Store these levels and groups to this post's meta data
		update_post_meta($post_id, WAIntegration::RESTRICTED_GROUPS, $checked_groups_ids, true); // only add single value
		update_post_meta($post_id, WAIntegration::RESTRICTED_LEVELS, $checked_levels_ids, true); // only add single value

		// Add the 'restricted' property to this post's meta data
		if (!empty($checked_groups_ids) || !empty($checked_levels_ids)) {
			update_post_meta($post_id, WAIntegration::IS_POST_RESTRICTED, true);
		}

		// Add this post to the 'restricted' posts in the options table so that its extra post meta data can be deleted upon uninstall
		// Get current array of restricted post, if applicable
		$site_restricted_posts = get_option(WAIntegration::ARRAY_OF_RESTRICTED_POSTS);
		$updated_restricted_posts = array();
		// Check if restricted posts already exist
		if (!empty($site_restricted_posts)) {
			// Append this current post to the array if it is not already added
			if (!in_array($post_id, $site_restricted_posts)) {
				$site_restricted_posts[] = $post_id;
			}
			$updated_restricted_posts = $site_restricted_posts;
		} else {
			// No restricted posts yet; we must make the array from scratch
			$updated_restricted_posts[] = $post_id;
		}
		// Save updated restricted posts to options table
		update_option(WAIntegration::ARRAY_OF_RESTRICTED_POSTS, $updated_restricted_posts);

		// Save individual restriction message to post meta data
		$individual_message = $_POST['wawp_individual_post_restricted_message_textarea'];
		if (!empty($individual_message)) {
			// Filter restriction message
			$individual_message = wp_kses_post($individual_message);
			// Save to post meta data
			update_post_meta($post_id, WAIntegration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, $individual_message);
		}
	}

	/**
	 * Allows for the 'select all' checkbox to select all boxes
	 */
	public function select_all_checkboxes_jquery() {
		?>
		<script language="javascript">
			// Check all levels
			jQuery('#wawp_check_all_levels').click(function () {
				jQuery('.wawp_case_level').prop('checked', true);
			});

			// Check all groups
			jQuery('#wawp_check_all_groups').click(function () {
				jQuery('.wawp_case_group').prop('checked', true);
			});

			// If all checkboxes are selected, check the select-all checkbox, and vice versa
			// Levels
			jQuery(".wawp_case_level").click(function() {
				if(jQuery(".wawp_case_level").length == jQuery(".wawp_case_level:checked").length) {
					jQuery("#wawp_check_all_levels").attr("checked", "checked");
				} else {
					jQuery("#wawp_check_all_levels").removeAttr("checked");
				}
			});
			// Groups
			jQuery(".wawp_case_group").click(function() {
				if(jQuery(".wawp_case_group").length == jQuery(".wawp_case_group:checked").length) {
					jQuery("#wawp_check_all_groups").attr("checked", "checked");
				} else {
					jQuery("#wawp_check_all_groups").removeAttr("checked");
				}
			});
    	</script>
		<?php
	}

	/**
	 * Displays the post meta box for the custom restriction message for the individual post
	 *
	 * @param WP_Post $post is the current post being edited
	 */
	public function individual_restriction_message_display($post) {
		// Get link to the global restriction page
		$global_restriction_link = site_url('/wp-admin/admin.php?page=wawp-wal-admin');
		?>
		<p>If you like, you can enter a restriction message that is custom to this individual post! If not, just leave this field blank - the global restriction message set under <a href="<?php echo $global_restriction_link ?>">WA4WP Settings</a> will be displayed to restricted users.</p>
		<?php
		$current_post_id = $post->ID;
		// Get individual restriction message from post meta data
		$initial_message = get_post_meta($current_post_id, WAIntegration::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, true); // return single value
		// Set initial message to blank if there is no saved message
		if (empty($initial_message)) {
			$initial_message = '';
		}
		// Create wp editor
		$editor_id = 'wawp_individual_post_restricted_message_editor';
        $editor_name = 'wawp_individual_post_restricted_message_textarea';
        $editor_settings = array('textarea_name' => $editor_name, 'tinymce' => false);
		wp_editor($initial_message, $editor_id, $editor_settings);
	}

	/**
	 * Displays the post meta data on each post to select which levels and groups can access the post
	 *
	 * @param WP_Post $post is the current post being edited
	 */
	public function post_access_display($post) {
		// Load in saved membership levels
		$all_membership_levels = get_option('wawp_all_levels_key');
		$all_membership_groups = get_option('wawp_all_groups_key');
		$current_post_id = $post->ID;
		// Add a nonce field to check on save
		wp_nonce_field(basename(__FILE__), 'wawp_post_access_control', 10, 2);
		?>
			<!-- Membership Levels -->
			<ul>
			<li style="margin:0;font-weight: 600;">
                <label for="wawp_check_all_levels"><input type="checkbox" value="wawp_check_all_levels" id='wawp_check_all_levels' name="wawp_check_all_levels" /> Select All Membership Levels</label>
            </li>
			<?php
			// Get checked levels from post meta data
			$already_checked_levels = get_post_meta($current_post_id, WAIntegration::RESTRICTED_LEVELS);
			if (isset($already_checked_levels) && !empty($already_checked_levels)) {
				$already_checked_levels = $already_checked_levels[0];
			}
			// Loop through each membership level and add it as a checkbox to the meta box
			foreach ($all_membership_levels as $membership_key => $membership_level) {
				// Check if level is already checked
				$level_checked = '';
				if (isset($already_checked_levels) && !empty($already_checked_levels)) {
					// Unserialize into array
					$already_checked_levels = maybe_unserialize($already_checked_levels);
					// Check if membership_key is in already_checked_levels
					if (is_array($already_checked_levels)) {
						if (in_array($membership_key, $already_checked_levels)) { // already checked
							$level_checked = 'checked';
						}
					} else {
						if ($membership_key == $already_checked_levels) {
							$level_checked = 'checked';
						}
					}
				}
				?>
					<li>
						<input type="checkbox" name="wawp_membership_levels[]" class='wawp_case_level' value="<?php echo htmlspecialchars($membership_key); ?>" <?php echo($level_checked); ?>/> <?php echo htmlspecialchars($membership_level); ?> </input>
					</li>
				<?php
			}
			?>
			</ul>
			<!-- Membership Groups -->
			<ul>
			<li style="margin:0;font-weight: 600;">
                <label for="wawp_check_all_groups"><input type="checkbox" value="wawp_check_all_groups" id='wawp_check_all_groups' name="wawp_check_all_groups" /> Select All Membership Groups</label>
            </li>
			<?php
			// Get checked groups from post meta data
			$already_checked_groups = get_post_meta($current_post_id, WAIntegration::RESTRICTED_GROUPS);
			if (isset($already_checked_groups) && !empty($already_checked_groups)) {
				$already_checked_groups = $already_checked_groups[0];
			}
			// Loop through each membership group and add it as a checkbox to the meta box
			foreach ($all_membership_groups as $membership_key => $membership_group) {
				// Check if group is already checked
				$group_checked = '';
				if (isset($already_checked_groups) && !empty($already_checked_groups)) {
					// Unserialize into array
					$already_checked_groups = maybe_unserialize($already_checked_groups);
					// Check if membership_key is in already_checked_levels
					if (is_array($already_checked_groups)) {
						if (in_array($membership_key, $already_checked_groups)) { // already checked
							$group_checked = 'checked';
						}
					} else {
						if ($membership_key == $already_checked_groups) {
							$group_checked = 'checked';
						}
					}
				}
				?>
					<li>
						<input type="checkbox" name="wawp_membership_groups[]" class="wawp_case_group" value="<?php echo htmlspecialchars($membership_key); ?>" <?php echo($group_checked); ?>/> <?php echo htmlspecialchars($membership_group); ?> </input>
					</li>
				<?php
			}
			?>
			</ul>
		<?php
		// Fire action to allow "select all" checkboxes to select all options
		do_action('wawp_create_select_all_checkboxes');
	}

	/**
	 * Adds post meta box when editing a post
	 */
	public function post_access_add_post_meta_boxes() {
		// Get post types to add the meta boxes to
		// Get all post types, including built-in WordPress post types and custom post types
		$post_types = get_post_types(array('public' => true));

		// Add meta box for post access
		add_meta_box(
			'wawp_post_access_meta_box_id', // ID
			'Wild Apricot Access Control', // title
			array($this, 'post_access_display'), // callback
			$post_types, // screen
			'side', // location of meta box
			'high' // priority in comparison to other meta boxes
		);

		// Add meta box for post's custom restriction message
		add_meta_box(
			'wawp_post_access_custom_message_id', // ID
			'Individual Restriction Message', // title
			array($this, 'individual_restriction_message_display'), // callback
			$post_types, // screen
			'normal', // location of meta box
			'high' // priority in comparison to other meta boxes
		);
	}

	/**
	 * Sets up the post meta data for Wild Apricot access control if valid Wild Apricot credentials have already been entered
	 */
	public function post_access_meta_boxes_setup() {
		// Add meta boxes if and only if the Wild Apricot credentials have been entered and are valid
		$valid_wa_credentials = get_option('wawp_wa_credentials_valid');
		if (isset($valid_wa_credentials) && $valid_wa_credentials) {
			// Add meta boxes on the 'add_meta_boxes' hook
			add_action('add_meta_boxes', array($this, 'post_access_add_post_meta_boxes'));
		}
	}

	/**
	 * Show membership levels on user profile
	 *
	 * @param WP_User $user is the user of the current profile
	 */
	public function show_membership_level_on_profile($user) {
		// Load in parameters from user's meta data
		$membership_level = get_user_meta($user->ID, WAIntegration::WA_MEMBERSHIP_LEVEL_KEY, true);
		$user_status = get_user_meta($user->ID, WAIntegration::WA_USER_STATUS_KEY, true);
		$wa_account_id = get_user_meta($user->ID, WAIntegration::WA_USER_ID_KEY, true);
		$organization = get_user_meta($user->ID, WAIntegration::WA_ORGANIZATION_KEY, true);
		$user_groups = get_user_meta($user->ID, WAIntegration::WA_MEMBER_GROUPS_KEY);
		// Create list of user groups, if applicable
		$group_list = '';
		if (!empty($user_groups)) {
			$user_groups = maybe_unserialize($user_groups[0]);
			// Add comma after group only if it is NOT the last group
			$i = 0;
			$len = count($user_groups);
			foreach ($user_groups as $key => $value) {
				// Check if index is NOT the last index
				if (!($i == $len - 1)) { // NOT last
					$group_list .= $value . ', ';
				} else {
					$group_list .= $value;
				}
				// Increment counter
				$i++;
			}
		} else {
			// Set user groups to empty array
			$user_groups = array();
		}
		// Check if user has valid Wild Apricot credentials, and if so, display them
		if (isset($membership_level) && isset($user_status) && isset($wa_account_id) && isset($organization) && isset($user_groups)) { // valid
			// Display Wild Apricot parameters
			?>
			<h2>Wild Apricot Membership Details</h2>
			<table class="form-table">
				<!-- Wild Apricot Account ID -->
				<tr>
					<th><label>Account ID</label></th>
					<td>
					<?php
						echo '<label>' . $wa_account_id . '</label>';
					?>
					</td>
				</tr>
				<!-- Membership Level -->
				<tr>
					<th><label>Membership Level</label></th>
					<td>
					<?php
						echo '<label>' . $membership_level . '</label>';
					?>
					</td>
				</tr>
				<!-- User Status -->
				<tr>
					<th><label>User Status</label></th>
					<td>
					<?php
						echo '<label>' . $user_status . '</label>';
					?>
					</td>
				</tr>
				<!-- Organization -->
				<tr>
					<th><label>Organization</label></th>
					<td>
					<?php
						echo '<label>' . $organization . '</label>';
					?>
					</td>
				</tr>
				<!-- Groups -->
				<tr>
					<th><label>Groups</label></th>
					<td>
					<?php
						echo '<label>' . $group_list . '</label>';
					?>
					</td>
				</tr>
			</table>
			<?php
		}
	}

	/**
	 * Updates the user's Wild Apricot information in WordPress
	 *
	 * @param int $current_user_id The user's WordPress ID
	 */
	public function refresh_user_wa_info() {
		// Get all user ids of Wild Apricot logged in users
		$dataEncryption = new DataEncryption();
		// Get admin account ID
		$admin_account_id = get_transient(self::ADMIN_ACCOUNT_ID_TRANSIENT);
		$admin_access_token = get_transient(self::ADMIN_ACCESS_TOKEN_TRANSIENT);
		// Check if transient is expired
		if (!$admin_account_id || !$admin_access_token || true) { // true for testing
			// Get new admin access token
			$refresh_token = $dataEncryption->decrypt(get_option(self::ADMIN_REFRESH_TOKEN_OPTION));
            $new_response = WAWPApi::get_new_access_token($refresh_token);
			// Get variables from response
            $new_access_token = $new_response['access_token'];
            $new_expiring_time = $new_response['expires_in'];
            $new_account_id = $new_response['Permissions'][0]['AccountId'];
			// Get new refresh token
			$new_refresh_token = $new_response['refresh_token'];
			update_option(self::ADMIN_REFRESH_TOKEN_OPTION, $dataEncryption->encrypt($new_refresh_token));
            // Set these new values to the transients
            set_transient(self::ADMIN_ACCESS_TOKEN_TRANSIENT, $dataEncryption->encrypt($new_access_token), $new_expiring_time);
            set_transient(self::ADMIN_ACCOUNT_ID_TRANSIENT, $dataEncryption->encrypt($new_account_id), $new_expiring_time);
            // Update values
            $admin_access_token = $new_access_token;
            $admin_account_id = $new_account_id;

			// Get all of the Wild Apricot users in the WordPress database
			$users_args = array(
				'meta_key' => WA_USER_ID_KEY,
			);
			$wa_users = get_users($users_args);
			// Get IDs of users
			self::my_log_file($wa_users);
		}

		// Ensure that user is logged into a Wild Apricot synced account
		// $wa_account_id = get_user_meta($current_user_id, self::WA_USER_ID_KEY, true);
		// if (!empty($wa_account_id)) { // user is also synced with Wild Apricot
		// 	$access_token = get_user_meta($current_user_id, self::ACCESS_TOKEN_META_KEY, true);
		// 	// Decrypt access token
		// 	$access_token = $dataEncryption->decrypt($access_token);
		// 	// Check if access token has expired (most likely will be expired)
		// 	$current_unix_time = time();
		// 	$expire_unix_time = get_user_meta($current_user_id, self::TIME_TO_REFRESH_TOKEN, true);

		// 	// Get new access token and update data if expire time has been exceeded
		// 	if ($current_unix_time > $expire_unix_time) { // passed expiration time
		// 		// Retrieve refresh token
		// 		$refresh_token = get_user_meta($current_user_id, self::REFRESH_TOKEN_META_KEY, true);
		// 		$refresh_token = $dataEncryption->decrypt($refresh_token);
		// 		// Get new access token
		// 		$new_response = WAWPApi::get_new_access_token($refresh_token);
		// 		// Retrieve values from response
		// 		$new_access_token = $new_response['access_token'];
		// 		$new_refresh_token = $new_response['refresh_token'];
		// 		$new_expires_in = $new_response['expires_in'];
		// 		// Update current values
		// 		$access_token = $new_access_token;
		// 		// Save new values in user meta data
		// 		update_user_meta($current_user_id, self::ACCESS_TOKEN_META_KEY, $dataEncryption->encrypt($new_access_token));
		// 		update_user_meta($current_user_id, self::REFRESH_TOKEN_META_KEY, $dataEncryption->encrypt($new_refresh_token));
		// 		$updated_new_expiry_time = time() + $new_expires_in;
		// 		update_user_meta($current_user_id, self::TIME_TO_REFRESH_TOKEN, $updated_new_expiry_time);

		// 		// Update rest of data
		// 		// Get updated fields of user's Wild Apricot information
		// 		$wawp_api = new WAWPApi($access_token, $wa_account_id);
		// 		$updated_user_info = $wawp_api->get_info_on_current_user();

		// 		// Extract updated information into user meta data
		// 		$first_name = $updated_user_info['FirstName'];
		// 		update_user_meta($current_user_id, 'first_name', $first_name);
		// 		$last_name = $updated_user_info['LastName'];
		// 		update_user_meta($current_user_id, 'last_name', $last_name);
		// 		// $email = $updated_user_info['Email'];
		// 		// $display_name = $updated_user_info['DisplayName'];
		// 		// update_user_meta($current_user_id, 'display_name', $display_name);
		// 		$organization = $updated_user_info['Organization'];
		// 		update_user_meta($current_user_id, self::WA_ORGANIZATION_KEY, $organization);
		// 		$status = $updated_user_info['Status'];
		// 		update_user_meta($current_user_id, self::WA_USER_STATUS_KEY, $status);
		// 		$membership_level_id = $updated_user_info['MembershipLevel']['Id'];
		// 		update_user_meta($current_user_id, self::WA_MEMBERSHIP_LEVEL_ID_KEY, $membership_level_id);
		// 		$membership_level_name = $updated_user_info['MembershipLevel']['Name'];
		// 		update_user_meta($current_user_id, self::WA_MEMBERSHIP_LEVEL_KEY, $membership_level_name);

		// 		// Check here for the custom fields that we want to sync
		// 		// Field Values:
		// 		$field_values = $updated_user_info['FieldValues'];
		// 		// Get field names from options table
		// 		$field_names = array();
		// 		$field_names[] = 'Group participation';
		// 		$field_names[] = 'Your favourite foods';
		// 		// Loop through field values
		// 		foreach ($field_values as $field_value) {
		// 			// Check if the current value is within the desired field names
		// 			$current_field_name = $field_value['FieldName'];
		// 			if (in_array($current_field_name, $field_names)) {
		// 				// We will save this field value
		// 				// Get value
		// 				$current_field_value = $field_value['Value'];
		// 				// Save current_field_value to user meta data
		// 				update_user_meta($current_user_id, 'wawp_' . preg_replace('/\s+/', '_', $current_field_name), maybe_serialize($current_field_value));
		// 			}
		// 		}
		// 	}
		// }
		// If user is NOT logged in, then all is well because their updated Wild Apricot data will be synced with WordPress anyways when they log in again
	}

	/**
	 * Schedules the hourly event to update the user's Wild Apricot information in their WordPress profile
	 *
	 * @param int $user_id  User's WordPress ID
	 */
	public static function create_cron_for_user_refresh() {
		// Place user id in arguments
		$args = [
			$user_id
		];
		// Schedule event if it is not already scheduled
		if (!wp_next_scheduled(self::USER_REFRESH_HOOK)) {
			wp_schedule_event(time(), 'daily', self::USER_REFRESH_HOOK);
		}
	}

	/**
	 * Syncs Wild Apricot logged in user with WordPress user database
	 *
	 * @param string $login_data  The login response from the API
	 * @param string $login_email The email that the user has logged in with
	 */
	public function add_user_to_wp_database($login_data, $login_email) {
		// Get access token and refresh token
		$access_token = $login_data['access_token'];
		$refresh_token = $login_data['refresh_token'];
		// Get time that token is valid
		$time_remaining_to_refresh = $login_data['expires_in'];
		// Get user's permissions
		$member_permissions = $login_data['Permissions'][0];
		// Get email of current WA user
		// https://gethelp.wildapricot.com/en/articles/391-user-id-aka-member-id
		$wa_user_id = $member_permissions['AccountId'];
		// Get user's contact information
		$wawp_api = new WAWPApi($access_token, $wa_user_id);
		$contact_info = $wawp_api->get_info_on_current_user();
		// Get membership level
		$membership_level = '';
		$membership_level_id = '';
		// Check that these are valid indicies in the array
		if (array_key_exists('MembershipLevel', $contact_info)) {
			$membership_level_array = $contact_info['MembershipLevel'];
			if (!empty($membership_level_array)) {
				if (array_key_exists('Name', $membership_level_array)) {
					$membership_level = $membership_level_array['Name'];
				}
				if (array_key_exists('Id', $membership_level_array)) {
					$membership_level_id = $membership_level_array['Id'];
				}
			}
		}
		// Get user status
		$user_status = $contact_info['Status'];
		if (!isset($user_status)) {
			$user_status = ''; // changed to blank
		}
		// Get first and last name
		$first_name = $contact_info['FirstName'];
		$last_name = $contact_info['LastName'];
		// Get organization
		$organization = $contact_info['Organization'];
		// Get field values
		$field_values = $contact_info['FieldValues'];
		// Check if user is administator or not
		$is_adminstrator = isset($contact_info['IsAccountAdministrator']);

		// Wild Apricot contact details
		// membership groups - one member can be in 0 or more groups
		// membership level - one member has one level
		// membership status
		// not dropdowns, just text fields
		// add membership level to "roles"
		// support for groups and levels
		// cron to resynchronize every hour for data from wild apricot
		// establish session with wild apricot

		// Check if WA email exists in the WP user database
		$current_wp_user_id = 0;
		if (email_exists($login_email)) { // email exists; we will update user
			// Get user
			$current_wp_user = get_user_by('email', $login_email); // returns WP_User
			$current_wp_user_id = $current_wp_user->ID;
			// Update user's first and last name if they are not set yet
			$current_first_name = get_user_meta($current_wp_user_id, 'first_name', true);
			$current_last_name = get_user_meta($current_wp_user_id, 'last_name', true);
			if (!isset($current_first_name) || $current_first_name == '') {
				wp_update_user([
					'ID' => $current_wp_user_id,
					'first_name' => $first_name
				]);
			}
			if (!isset($current_last_name) || $current_last_name == '') {
				wp_update_user([
					'ID' => $current_wp_user_id,
					'last_name' => $last_name
				]);
			}
		} else { // email does not exist; we will create a new user
			// Set user data
			// Generated username is 'firstName . lastName' with a random number on the end, if necessary
			$generated_username = $first_name . $last_name;
			// Check if generated username has been taken. If so, append a random number to the end of the user-id until a unique username is set
			while (username_exists($generated_username)) {
				// Generate random number
				$random_user_num = wp_rand(0, 9);
				$generated_username .= $random_user_num;
			}
			// Get role
			// $user_role = 'subscriber';
			$user_role = 'wawp_' . str_replace(' ', '', $membership_level);
			if ($is_adminstrator) {
				$user_role = 'administrator';
			}
			$user_data = array(
				'user_email' => $login_email,
				'user_pass' => wp_generate_password(),
				'user_login' => $generated_username,
				'role' => $user_role,
				'display_name' => $first_name . ' ' . $last_name,
				'first_name' => $first_name,
				'last_name' => $last_name
			);
			// Insert user
			$current_wp_user_id = wp_insert_user($user_data); // returns user ID
			// Show error if necessary
			if (is_wp_error($current_wp_user_id)) {
				echo $current_wp_user_id->get_error_message();
			}
		}

		// Add access token and secret token to user's metadata
		$dataEncryption = new DataEncryption();
		update_user_meta($current_wp_user_id, WAIntegration::ACCESS_TOKEN_META_KEY, $dataEncryption->encrypt($access_token));
		update_user_meta($current_wp_user_id, WAIntegration::REFRESH_TOKEN_META_KEY, $dataEncryption->encrypt($refresh_token));
		// Store time that access token expires
		$new_time_to_save = time() + $time_remaining_to_refresh;
		update_user_meta($current_wp_user_id, WAIntegration::TIME_TO_REFRESH_TOKEN, $new_time_to_save);
		// Add Wild Apricot id to user's metadata
		update_user_meta($current_wp_user_id, WAIntegration::WA_USER_ID_KEY, $wa_user_id);
		// Add Wild Apricot membership level to user's metadata
		update_user_meta($current_wp_user_id, WAIntegration::WA_MEMBERSHIP_LEVEL_ID_KEY, $membership_level_id);
		update_user_meta($current_wp_user_id, WAIntegration::WA_MEMBERSHIP_LEVEL_KEY, $membership_level);
		// Add Wild Apricot user status to user's metadata
		update_user_meta($current_wp_user_id, WAIntegration::WA_USER_STATUS_KEY, $user_status);
		// Add Wild Apricot organization to user's metadata
		update_user_meta($current_wp_user_id, WAIntegration::WA_ORGANIZATION_KEY, $organization);

		// Get groups
		// Loop through each field value until 'Group participation' is found
		$user_groups_array = array();
		foreach ($field_values as $field_value) {
			if ($field_value['FieldName'] == 'Group participation') { // Found
				$group_array = $field_value['Value'];
				// Loop through each group
				foreach ($group_array as $group) {
					$user_groups_array[$group['Id']] = $group['Label'];
				}
			}
		}
		// Serialize the user groups array so that it can be added as user meta data
		$user_groups_array = maybe_serialize($user_groups_array);
		// Save to user's meta data
		update_user_meta($current_wp_user_id, WAIntegration::WA_MEMBER_GROUPS_KEY, $user_groups_array);

		// Log user into WP account
		wp_set_auth_cookie($current_wp_user_id, 1, is_ssl());

		// Schedule refresh of user's Wild Apricot credentials every hour (maybe day)
		update_option(self::CRON_USER_ID, $current_wp_user_id);
		self::create_cron_for_user_refresh();
	}

	/**
	 * Handles the redirect after the user is logged in
	 */
	public function create_user_and_redirect() {
		// Check that we are on the login page
		$login_page_id = get_option('wawp_wal_page_id');
		if (is_page($login_page_id)) {
			// Get id of last page from url
			// https://stackoverflow.com/questions/13652605/extracting-a-parameter-from-a-url-in-wordpress
			if (isset($_POST['wawp_login_submit'])) {
				// Create array to hold the valid input
				$valid_login = array();

				// Check email form
				$email_input = $_POST['wawp_login_email'];
				if (!empty($email_input) && is_email($email_input)) { // email is well-formed
					// Sanitize email
					$valid_login['email'] = sanitize_email($email_input);
				} else { // email is NOT well-formed
					// Output error
					add_filter('the_content', array($this, 'add_login_error'));
					return;
				}

				// Check password form
				// Wild Apricot password requirements: https://gethelp.wildapricot.com/en/articles/22-passwords
				// Any combination of letters, numbers, and characters (except spaces)
				$password_input = $_POST['wawp_login_password'];
				// https://stackoverflow.com/questions/1384965/how-do-i-use-preg-match-to-test-for-spaces
				if (!empty($password_input) && sanitize_text_field($password_input) == $password_input) { // not empty and valid password
					// Sanitize password
					$valid_login['password'] = sanitize_text_field($password_input);
				} else { // password is NOT valid
					// Output error
					add_filter('the_content', array($this, 'add_login_error'));
					return;
				}
				// Check that nonce is valid
				if (!wp_verify_nonce($_POST['wawp_login_nonce_name'], 'wawp_login_nonce_action')) {
					wp_die('Your nonce could not be verified.');
				}
				// Send POST request to Wild Apricot API to log in if input is valid
				$login_attempt = WAWPApi::login_email_password($valid_login);
				// If login attempt is false, then the user could not log in
				if (!$login_attempt) {
					// Present user with log in error
					add_filter('the_content', array($this, 'add_login_error'));
					return;
				}
				// If we are here, then it means that we have not come across any errors, and the login is successful!
				$this->add_user_to_wp_database($login_attempt, $valid_login['email']);

				// Redirect user to previous page, or home page if there is no previous page
				$last_page_id = get_query_var('redirectId', false);
				$redirect_code_exists = false;
				if ($last_page_id != false) { // get id of last page
					$redirect_code_exists = true;
				}
				// Redirect user to page they were previously on
				// https://wordpress.stackexchange.com/questions/179934/how-to-redirect-on-particular-page-in-wordpress/179939
				$redirect_after_login_url = '';
				if ($redirect_code_exists) {
					$redirect_after_login_url = esc_url(get_permalink($last_page_id));
				} else { // no redirect id; redirect to home page
					$redirect_after_login_url = esc_url(site_url());
				}
				wp_safe_redirect($redirect_after_login_url);
				exit();
			}
		}
	}

	/**
	 * Creates the shortcode that holds the login form
	 *
	 * @return string Holds the HTML content of the form
	 */
	public function custom_login_form_shortcode() {
		// Create page content -> login form
		ob_start(); ?>
			<p>Log into your Wild Apricot account here:</p>
			<form method="post" action="">
				<?php wp_nonce_field("wawp_login_nonce_action", "wawp_login_nonce_name");?>
				<label for="wawp_login_email">Email:</label>
				<input type="text" id="wawp_login_email" name="wawp_login_email" placeholder="example@website.com">
				<br><label for="wawp_login_password">Password:</label>
				<input type="password" id="wawp_login_password" name="wawp_login_password" placeholder="***********">
				<br><input type="submit" name="wawp_login_submit" value="Submit">
			</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Removes the CRON update when the user logs out
	 *
	 * @param int $user_id The user's WordPress ID
	 */
	public function remove_user_wa_update(int $user_id) {
		// Remove this user's CRON update
		$args = [
			$user_id
		];
		$timestamp = wp_next_scheduled(self::USER_REFRESH_HOOK, $args);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::USER_REFRESH_HOOK, $args);
		}
	}

	/**
	 * Create login and logout buttons in the menu
	 *
	 * @param  string $items  HTML of menu items
	 * @param  array  $args   Arguments supplied to the filter
	 * @return string $items  The updated items with the login/logout button
	 */
	// see: https://developer.wordpress.org/reference/functions/wp_create_nav_menu/
	// Also: https://www.wpbeginner.com/wp-themes/how-to-add-custom-items-to-specific-wordpress-menus/
	public function create_wa_login_logout($items, $args) {
		// Get login url based on user's Wild Apricot site
		// First, check if Wild Apricot credentials are valid
		$wa_credentials_saved = get_option('wawp_wal_name');
		if (isset($wa_credentials_saved) && isset($wa_credentials_saved['wawp_wal_api_key']) && $wa_credentials_saved['wawp_wal_api_key'] != '') {
			// https://wp-mix.com/wordpress-difference-between-home_url-site_url/
			// Get current page id
			// https://wordpress.stackexchange.com/questions/161711/how-to-get-current-page-id-outside-the-loop
			$current_page_id = get_queried_object_id();
			// Get login url
			$login_url = $this->get_login_link();
			// Check if user is logged in or logged out
			$menu_to_add_button = get_option('wawp_wal_name')['wawp_wal_login_logout_button'];
			if (is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Logout
				$items .= '<li id="wawp_login_logout_button"><a href="'. wp_logout_url(esc_url(get_permalink($current_page_id))) .'">Log Out</a></li>';
			} elseif (!is_user_logged_in() && $args->theme_location == $menu_to_add_button) { // Login
				$items .= '<li id="wawp_login_logout_button"><a href="'. $login_url .'">Log In</a></li>';
			}
		}
		return $items;
	}
}
?>
