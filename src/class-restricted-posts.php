<?php

namespace WAWP;

require_once __DIR__ . '/class-addon.php';
require_once __DIR__ . '/util/class-data-encryption.php';
require_once __DIR__ . '/util/class-log.php';
require_once __DIR__ . '/class-wa-api.php';
require_once __DIR__ . '/util/helpers.php';
require_once __DIR__ . '/class-wa-login.php';

class WA_Restricted_Posts
{
    /**
     * Stores restricted groups in the post meta data.
     *
     * @var string
     */
    public const RESTRICTED_GROUPS 					= 'wawp_restricted_groups';

    /**
     * Stores restricted levels in the post meta data.
     *
     * @var string
     */
    public const RESTRICTED_LEVELS 					= 'wawp_restricted_levels';

    /**
     * Stores whether the post is restricted or not in the post meta data.
     *
     * @var string
     */
    public const IS_POST_RESTRICTED 					= 'wawp_is_post_restricted';

    /** Stores custom restriction message in post meta data.
    *
    * @var string
    */
    public const INDIVIDUAL_RESTRICTION_MESSAGE_KEY	= 'wawp_individual_restriction_message_key';

    /**
     * Stores array of all restricted posts in the options table. Used for
     * deleting custom post metadata upon plugin deletion.
     *
     * @var string
     */
    public const ARRAY_OF_RESTRICTED_POSTS 			= 'wawp_array_of_restricted_posts';

    /**
     * Stores global restriction message in options table.
     *
     * @var string
     */
    public const GLOBAL_RESTRICTION_MESSAGE			= 'wawp_global_restriction_message';

    /**
     * Stores WA statuses for which posts are not restricted.
     * Controlled in the admin settings.
     *
     * @var string
     */
    public const GLOBAL_RESTRICTED_STATUSES					= 'wawp_restriction_status_name';

    public function __construct()
    {
        // Fires on the add meta boxes hook, adds custom WAP meta boxes
        add_action('add_meta_boxes', array($this, 'post_access_add_post_meta_boxes'));
        // Fires when post is saved, processes custom post metadata
        add_action('save_post', array($this, 'post_access_load_restrictions'), 10, 2);

        // Fires when post is loaded, restricts post content based on custom meta
        add_filter('the_content', array($this, 'restrict_post_wa'), 1000);
    }

    /**
     * Determines whether or not to restrict the post to the current user based
     * on the user's levels/groups and the post's list of restricted levels/groups
     *
     * @param string $post_content holds the post content in HTML form
     * @return string $post_content is the new post content. If the plugin is
     * disabled or experiencing a fatal error, content will reflect that and
     * display the appropriate message.
     */
    public function restrict_post_wa($post_content)
    {
        // TODO: fix restriction message appearing in header and footer
        // Get ID of current post
        $current_post_ID = get_queried_object_id();

        // Check that this current post is restricted
        $is_post_restricted = get_post_meta($current_post_ID, self::IS_POST_RESTRICTED, true);
        if (!$is_post_restricted) {
            return $post_content;
        }

        if (Exception::fatal_error()) {
            // if there is an exception, display exception error
            return Exception::get_user_facing_error_message();
        } elseif (Addon::is_plugin_disabled()) {
            // if plugin is disabled, display error message
            $message = "<div class='wawp-disabled'>
			<p>WildApricot Press is currently disabled. Please contact your site administrator.</p></div>";
            return $message;
        }

        // If post is not singular or it's the login page, don't restrict
        if (!is_singular() || is_user_login_page()) {
            return $post_content;
        }

        // Load in restriction message from message set by user
        $restriction_message = wpautop(get_option(self::GLOBAL_RESTRICTION_MESSAGE));
        // Check if current post has a custom restriction message
        $individual_restriction_message = wpautop(get_post_meta($current_post_ID, self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, true));
        if (!empty($individual_restriction_message)) {
            $restriction_message = $individual_restriction_message;
        }

        // Append 'Log In' button and the styling div to the restriction message
        $login_url = WA_Login::get_login_link();
        $restriction_message = '<div class="wawp_restriction_content_div">' . wp_kses_post($restriction_message);

        // Automatically restrict the post if user is not logged in
        if (!is_user_logged_in()) {
            $restriction_message .= '<a id="wawp_restriction_login_button" href="'. esc_url($login_url) .'">Log In</a>';
            $restriction_message .= '</div>';
            return $restriction_message;
        }

        // Show a warning/notice on the restriction page if the user is logged into WordPress but is not synced with WildApricot
        // Get user's WildApricot ID -> if it does not exist, then the user is not synced with WildApricot
        if (!WA_User::is_wa_user_logged_in()) {
            // Present notice that user is not synced with WildApricot
            $restriction_message .= '<p style="color:red;">Please note that while you are logged into WordPress, you have not synced your account with WildApricot. ';
            $restriction_message .= 'Please <a href="'. esc_url($login_url) .'">Log In</a> into your WildApricot account to sync your data to your WordPress site.</p>';
            $restriction_message .= '</div>';
            return $restriction_message;
        }
        $restriction_message .= '</div>';

        // Get post meta data
        // Get post's restricted groups
        $post_restricted_groups = get_post_meta($current_post_ID, self::RESTRICTED_GROUPS);
        // Unserialize
        $post_restricted_groups = maybe_unserialize($post_restricted_groups[0]);
        // Get post's restricted levels
        $post_restricted_levels = get_post_meta($current_post_ID, self::RESTRICTED_LEVELS);
        // Unserialize
        $post_restricted_levels = maybe_unserialize($post_restricted_levels[0]);

        // If no options are selected, then the post is unrestricted, as there cannot be a post with no viewers
        if (empty($post_restricted_groups) && empty($post_restricted_levels)) {
            update_post_meta($current_post_ID, self::IS_POST_RESTRICTED, false);
            return $post_content;
        }

        $current_user_ID = wp_get_current_user()->ID;

        // Get user meta data
        $user_groups = get_user_meta($current_user_ID, WA_User::WA_MEMBER_GROUPS_KEY);
        $user_level = get_user_meta($current_user_ID, WA_User::WA_MEMBERSHIP_LEVEL_ID_KEY, true);
        $user_status = get_user_meta($current_user_ID, WA_User::WA_USER_STATUS_KEY, true);

        // Check if user's status is allowed to view restricted posts
        // Get restricted status(es) from options table
        $restricted_statuses = get_option(self::GLOBAL_RESTRICTED_STATUSES);
        // If there are restricted statuses, then we must check them against the user's status
        if (!empty($restricted_statuses)) {
            // If user's status is not in the restricted statuses, then the user cannot see the post
            if (!in_array($user_status, $restricted_statuses)) {
                // User cannot access the post
                return $restriction_message;
            }
        }

        // Find common groups between the user and the post's restrictions
        // If user_groups is null, then the user is not part of any groups
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

        // Return original post content if no changes are made
        return $post_content;
    }

    /**
     * Processes the restricted groups set in the post meta data and update
     * these levels/groups to the current post's meta data.
     * Called when a post is saved.
     *
     * @param int     $post_id holds the ID of the current post
     * @param WP_Post $post holds the current post
     * @return void
     */
    public function post_access_load_restrictions($post_id, $post)
    {
        if (Exception::fatal_error()) {
            return;
        }

        // if post isn't being saved *by the user*, return
        if (!isset($_POST['action']) || $_POST['action'] != 'editpost') {
            return;
        }

        // Verify the nonce before proceeding
        if (!isset($_POST['wawp_post_access_control']) || !wp_verify_nonce($_POST['wawp_post_access_control'], basename(__FILE__))) {
            // Invalid nonce
            Log::wap_log_error('Your nonce for the post access control input could not be verified');
            add_action('admin_notices', 'WAWP\invalid_nonce_error_message');
            return;
        }

        // Return if user does not have permission to edit the post
        if (!current_user_can('edit_post', $post_id)) {
            // User cannot edit the post
            return;
        }

        // actually need to use post if it's the first time getting post meta.
        $wa_post_meta = self::get_wa_post_meta_from_post_data($_POST);
        // Get levels and groups that the user checked off
        $checked_groups_ids = $wa_post_meta[self::RESTRICTED_GROUPS];
        $checked_levels_ids = $wa_post_meta[self::RESTRICTED_LEVELS];

        // Add the 'restricted' property to this post's meta data and check if page is indeed restricted
        $this_post_is_restricted = false;
        if (!empty($checked_groups_ids) || !empty($checked_levels_ids)) {
            $this_post_is_restricted = true;
            update_post_meta($post_id, self::IS_POST_RESTRICTED, true);
        }
        // Set post's meta data to false if it is not restricted
        if (!$this_post_is_restricted) {
            update_post_meta($post_id, self::IS_POST_RESTRICTED, false);
        }

        // Add this post to the 'restricted' posts in the options table so that its extra post meta data can be deleted upon uninstall
        // Get current array of restricted post, if applicable
        $site_restricted_posts = get_option(self::ARRAY_OF_RESTRICTED_POSTS);
        $updated_restricted_posts = array();
        // Possible cases here:
        // If this post is NOT restricted and is already in $site_restricted_posts, then remove it
        // If the post is restricted and is NOT already in $site_restricted_posts, then add it
        // If the post is restricted and $site_restricted_posts is empty, then create the array and add the post to it
        if ($this_post_is_restricted) { // the post is to be restricted
            // Check if $site_restricted_posts is empty or not
            if (empty($site_restricted_posts)) {
                // Add post id to the new array
                $updated_restricted_posts[] = $post_id;
            } else { // There are already restricted posts
                // Check if the post id is already in the restricted posts -> if not, then add it
                if (!in_array($post_id, $site_restricted_posts)) {
                    $site_restricted_posts[] = $post_id;
                }
                $updated_restricted_posts = $site_restricted_posts;
            }
        } else { // the post is NOT to be restricted
            // Check if this post is located in $site_restricted_posts -> if so, then remove it
            if (!empty($site_restricted_posts)) {
                if (in_array($post_id, $site_restricted_posts)) {
                    $updated_restricted_posts = array_diff($site_restricted_posts, [$post_id]);
                } else {
                    $updated_restricted_posts = $site_restricted_posts;
                }
            }
        }

        // Serialize results for storage
        $checked_groups_ids = maybe_serialize($checked_groups_ids);
        $checked_levels_ids = maybe_serialize($checked_levels_ids);

        // Store these levels and groups to this post's meta data
        update_post_meta($post_id, self::RESTRICTED_GROUPS, $checked_groups_ids); // only add single value
        update_post_meta($post_id, self::RESTRICTED_LEVELS, $checked_levels_ids); // only add single value

        // Save updated restricted posts to options table
        update_option(self::ARRAY_OF_RESTRICTED_POSTS, $updated_restricted_posts);

        // Save individual restriction message to post meta data
        $individual_message = $wa_post_meta[self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY];
        if (!empty($individual_message)) {
            // Filter restriction message
            $individual_message = wp_kses_post($individual_message);
            // Save to post meta data
            update_post_meta($post_id, self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, $individual_message);
        } else {
            delete_post_meta($post_id, self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY);
        }
    }

    /**
     * Displays the post meta box for the custom restriction message
     * for the individual post.
     *
     * @param WP_Post $post is the current post being edited
     * @return void
     */
    public function individual_restriction_message_display($post)
    {
        // Get link to the global restriction page
        $global_restriction_link = site_url('/wp-admin/admin.php?page=wawp-wal-admin');
        ?>
<p>If you like, you can enter a restriction message that is custom to this individual post. If not, just leave this
    field blank - the global restriction message set under <a
        href="<?php echo esc_url($global_restriction_link) ?>">WildApricot
        Press > Settings</a> will be displayed to
    restricted users.</p>
<?php
        $current_post_id = $post->ID;
        // Get individual restriction message from post meta data
        $initial_message = get_post_meta($current_post_id, self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, true); // return single value
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
     * Displays the WAP custom post meta data on each post to select which
     * levels and groups can access the post.
     *
     * @param WP_Post $post is the current post being edited
     */
    public function post_access_display($post)
    {
        // INCLUDE A MESSAGE TO DESCRIBE IF ACCESS LEVELS ARE CHECKED OFF
        // INCLUDE CHECKBOX FOR 'ALL MEMBERS AND CONTACTS'
        // if no boxes are checked, then this post is available to everyone, including logged out users
        // Load in saved membership levels
        $all_membership_levels = get_option(WA_Integration::WA_ALL_MEMBERSHIPS_KEY);
        $all_membership_groups = get_option(WA_Integration::WA_ALL_GROUPS_KEY);
        $current_post_id = $post->ID;

        // Add a nonce field to check on save
        wp_nonce_field(basename(__FILE__), 'wawp_post_access_control', 10, 2);
        ?>
<!-- Membership Levels -->
<ul>
    <p>If you would like everyone (including non WildApricot users) to see the current post, then leave all the
        checkboxes blank! You can restrict this post to specific WildApricot groups and levels by selecting the
        checkboxes below.</p>
    <li style="margin:0;font-weight: 600;">
        <label for="wawp_check_all_levels"><input type="checkbox" value="wawp_check_all_levels"
                id='wawp_check_all_levels' name="wawp_check_all_levels" /> Select All Membership Levels</label>
    </li>
    <?php
            // Get checked levels from post meta data
            $already_checked_levels = get_post_meta($current_post_id, self::RESTRICTED_LEVELS);
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
        <input type="checkbox" name="wawp_membership_levels[]" class='wawp_case_level'
            value="<?php echo esc_attr($membership_key); ?>" <?php echo esc_attr($level_checked); ?> />
        <?php echo esc_html($membership_level); ?> </input>
    </li>
    <?php
        }
        ?>
</ul>
<!-- Membership Groups -->
<p>Group Restriction will only work if the Group Participation membership field is <em>not set to</em> "No access -
    Internal use".</p>
<ul>
    <li style="margin:0;font-weight: 600;">
        <label for="wawp_check_all_groups"><input type="checkbox" value="wawp_check_all_groups"
                id='wawp_check_all_groups' name="wawp_check_all_groups" /> Select All Membership Groups</label>
    </li>
    <?php
        // Get checked groups from post meta data
        $already_checked_groups = get_post_meta($current_post_id, self::RESTRICTED_GROUPS);
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
        <input type="checkbox" name="wawp_membership_groups[]" class="wawp_case_group"
            value="<?php echo esc_attr($membership_key); ?>" <?php echo esc_attr($group_checked); ?> />
        <?php echo esc_html($membership_group); ?> </input>
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
     * Adds WAP custom post meta box when editing a post.
     *
     * @return void
     */
    public function post_access_add_post_meta_boxes()
    {
        // Get post types to add the meta boxes to
        // Get all post types, including built-in WordPress post types and custom post types
        $post_types = get_post_types(array('public' => true));

        if (Addon::is_plugin_disabled()) {
            return;
        }

        // Add meta box for post access
        add_meta_box(
            'wawp_post_access_meta_box_id', // ID
            'WildApricot Access Control', // title
            array($this, 'post_access_display'), // callback
            $post_types, // screen
            'side', // location of meta box
            'default' // priority in comparison to other meta boxes
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
     * Recursive function to sanitize post meta data.
     *
     * @param array $post_meta post meta data to sanitize.
     * @return array sanitized array of post meta data.
     */
    private static function sanitize_post_meta($post_meta)
    {
        // loop through all values in the array
        foreach ($post_meta as $key => &$value) {
            // if the value is a string, sanitize it
            if (gettype($value) == 'string') {
                if (str_contains($key, 'textarea')) {
                    $value = sanitize_textarea_field($value);
                } else {
                    $value = sanitize_text_field($value);
                }
            } elseif (gettype($value) == 'array') {
                /**
                 * if the value is an array, recursively call this function and
                 * obtain the sanitized inner array that it will return
                 */
                $value = self::sanitize_post_meta($value);
            }

        }

        return $post_meta;
    }

    /**
     * Obtains the relevant WA post meta data from the $_POST response data and
     * formats it similar to the post meta data structure obtained by using
     * get_post_meta.
     *
     * @param string[] $post_data
     * @return string[] formatted array of the post meta data.
     */
    private static function get_wa_post_meta_from_post_data($post_data)
    {

        $memgroups = "";
        $memlevels = "";
        $restmsg = "";

        $post_data = self::sanitize_post_meta($post_data);
        if (array_key_exists('wawp_membership_groups', $post_data)) {
            $memgroups = $post_data['wawp_membership_groups'];
        }
        if (array_key_exists('wawp_membership_levels', $post_data)) {
            $memlevels = $post_data['wawp_membership_levels'];
        }
        if (array_key_exists('wawp_individual_post_restricted_message_textarea', $post_data)) {
            $restmsg = $post_data['wawp_individual_post_restricted_message_textarea'];
        }

        return array(
            self::RESTRICTED_GROUPS => $memgroups,
            self::RESTRICTED_LEVELS => $memlevels,
            self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY => $restmsg
        );
    }

    /**
     * Returns the post meta values pertaining to WildApricot.
     * The list of restricted groups and levels, flag of whether the post is
     * restricted or not, and the restriction message.
     *
     * @param array $meta metadata of a post.
     * @return array array of the restricted groups and levels, each in their own
     * respective element.
     */
    public static function get_wa_post_meta($nav_id)
    {
        $restricted_groups = array();
        $restricted_levels = array();
        $is_restricted = 0;
        $individual_restriction_msg = '';

        $meta = get_post_meta($nav_id);

        /**
         * these will only be present in the post meta if there are restricted
         * groups/levels.
         */
        if (array_key_exists(self::RESTRICTED_GROUPS, $meta)) {
            $restricted_groups = $meta[self::RESTRICTED_GROUPS][0];
        }
        if (array_key_exists(self::RESTRICTED_LEVELS, $meta)) {
            $restricted_levels = $meta[self::RESTRICTED_LEVELS][0];
        }

        // restriction flag will always be present
        if (array_key_exists(self::IS_POST_RESTRICTED, $meta)) {
            $is_restricted = $meta[self::IS_POST_RESTRICTED][0];

            $is_restricted = $meta[self::IS_POST_RESTRICTED][0] ? true : false;
        }

        // like groups and levels, restriction message will not always be in the meta
        if (array_key_exists(self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY, $meta)) {
            $individual_restriction_msg = $meta[self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY][0];
        }

        /**
         * need to call maybe_unserialize twice since the array of restricted
         * groups/levels is a serialized string containing a serialized array.
         */
        $wa_meta = array(
            self::IS_POST_RESTRICTED => $is_restricted,
            self::RESTRICTED_GROUPS => maybe_unserialize(maybe_unserialize($restricted_groups)),
            self::RESTRICTED_LEVELS => maybe_unserialize(maybe_unserialize($restricted_levels)),
            self::INDIVIDUAL_RESTRICTION_MESSAGE_KEY => $individual_restriction_msg

        );

        return $wa_meta;
    }
}