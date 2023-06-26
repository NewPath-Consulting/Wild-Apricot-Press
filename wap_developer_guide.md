# WildApricot Press Developer's Guide

#### By Spencer Gable-Cook and Natalie Brotherton

##### *Updated December 2022*

WildApricot Press (WAP) is a WordPress plugin written primarily in PHP. It also contains two CSS files for formatting custom content and a vanilla JavaScript file controlling some UI functionality on the page editor.

This document describes the design of WAP as a guide for maintenance and extension.


## Required plugin files

### `plugin.php`
`plugin.php` is the main file of WAP required by WordPress. The [WordPress required header information](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/) (plugin name, version, etc.) is defined here. This file does not contain much  functionality for the plugin, but rather creates the classes that do: `Activator`, `Settings`, and `WA_Integration`. 

### `uninstall.php` 
Like `plugin.php`, this is another standard file required by WordPress. When the plugin is deleted from the WordPress site, the `uninstall.php` file is run to clean up any variables or other data that the plugin may have been using, but that are  no longer required since the plugin is deleted. 

The data deleted in `uninstall.php` depends on the user's setting configuration. The plugin settings allow users to choose one, both, or none of the options of 
* Deleting all options table data (API credentials, license keys, level/group data, etc.)
* Deleting all WildApricot synced users created by the plugin.

By default, none of these data are deleted when WAP is uninstalled.

## Classes
All classes and files in the `src/` folder are under the `WAWP` namespace.

### `Activator`
This class contains the code associated with the activation of the plugin. That is, this code runs once when the plugin is activated in WordPress, which is usually done immediately after installation. 

In `class-activator.php`, the WA Authorization credentials and the license key are checked for, and if they exist, the WildApricot login page is created. Refer to the `activate()` function in `class-activator.php` for more information.

### `Addon`
This class manages all the functionality relating to licensing and the add-on plugins for WAP. 

Data for core WAP plugin and activated add-ons are stored in the options table. 

One prominent function includes checking the license keys against the Make scenario to validate that the user has entered a valid license key.

A valid license key must

* Contain WAWP in its products list
* Not be expired
* Be registered to the WildApricot URL and account that correspond with the WildApricot authorization credentials

NOTE: to use the developer License Key database for testing license keys for developer purposes the following line in `wp-config.php` should be edited to use the https://newpathconsulting.com/checkdev webhook:

```php
    define( 'WAP_LICENSE_CHECK_DEV', true );
```


### `Data_Encryption`

The `Data_Encryption` class is responsible for encrypting and decrypting sensitive data. The code is adapted from [this article](https://felix-arntz.me/blog/storing-confidential-data-in-wordpress/). 

#### Usage
```php
// instantiate new Data_Encryption object
$data_encryption = new Data_Encryption();

// encrypt
$encrypted_value = $data_encryption->encrypt($value_to_encrypt);

// decrypt
$decrypted_value = $data_encryption->decrypt($value_to_decrypt);
```

The `Data_Encryption` class is primarily used to encrypt sensitive data stored in the options table. This inclues the WA API credentials/info (API key, client ID, client secret, access token, account ID, refresh token, WildApricot URL) and the license key.

### `Deactivator`
`Deactivator` runs when the plugin is deactivated. In `plugin.php`, you can see that it is registered to the WordPress deactivation hook.

`Deactivator` contains a single static function `deactivate()`.

`Deactivator::deactivate()` does the following:
* Deletes the WA user login page
* Removes the cron jobs
  * See more about cron jobs in the cron jobs section

### `Settings`
This class manages the admin settings pages for the WAP plugin; both the user interface and processing the data input on the settings pages. The settings page was set up with the techniques described in Chapter 3 of the “Professional WordPress Development” book.

The class constructs the following settings pages, the code for which is contained in its own class:
* `Admin_Settings` Main admin settings which has three tabs
  * Content restriction options
  * Synchronization options
  * Plugin options
* `WA_Auth_Settings` WildApricot Authorization credentials form
* `License_Settings` License keys form

### `WA_API`
This class manages the API calls to the WildApricot API. Each instance of the `WA_API` class requires the user’s access token and WildApricot account ID. These credentials can be obtained with the static function `WA_API::verify_valid_access_token`.

After passing in the access token and account ID, the plugin can access data from WildApricot, including membership levels, groups, users, and more. 

### `WA_Integration`
`WA_Integration` controls all the plugin's functionality relating to WildApricot including:

* Post restriction
  * Creating post meta boxes and saving the data
  * Modifying post content if post is restricted

* User-facing WildApricot functionality
  * Creating the user login page and adding it to the website menu
    * Adds to primary menu by default, can be changed in **WildApricot Press > Settings > Content Restriction Options > Login/Logout Button Menu Location**
  * Connecting to WA with user login credentials to obtain user data and adding the user to the WP user database
  * Displaying WA user data on the WP user profile
* Cron jobs updating WA user data

### `Log`
This class manages debug and error logging for WAP. 

It contains three main functions in its interface:
* `wap_log_debug($msg)`
* `wap_log_error($msg, $fatal = false)`
  * Pass in `true` after the log message to indicate a fatal error.
* `wap_log_warning($msg)`

Each of these functions is used to log a different type of message. Log messages are logged automatically to a custom log file located in the WordPress website directory under `wp-content/wapdebug.log`. 

By default, all log messages are disabled except for fatal errors. They can be enabled in **WildApricot Press > Settings > Plugin Log Messages**.

### `Exception`
This class manages custom exceptions. 

There is an `Exception` parent class extended from the PHP `Exception` type and the more specific exception types are all derived from that class. 

The child classes cover the two primary types of errors that interrupt normal WAP function: API errors and encryption errors. The child classes are as follows:
* `API_Exception`
* `Encryption_Exception`
* `Decryption_Exception`

These exceptions are thrown in the following functions:

Exception type         | Functions
---------------------- | -----------------------------
`API_Exception`        | `WA_API::response_to_data()`
`Encryption_Exception` | `Data_Encryption->encrypt()`, `Data_Encryption->get_default_key()`, `Data_Encryption->get_default_salt()`
`Decryption_Exception` | `Data_Encryption->decrypt()`


To sum it up, exceptions could be thrown at many points in the plugin: connecting to the WA API or accessing or inserting any sensitive user data in the options table. 

Generally, these exceptions are thrown for reasons out of our control. An `API_Exception` could be thrown if the WildApricot API was down. An `Encryption_Exception` could be thrown if OpenSSL wasn’t installed. However, since these errors interrupt plugin functionality, it cannot continue to function once the errors happen. 

The general procedure followed when handling exceptions is to catch them as high up on the call stack as possible, with a few exceptions to this rule. Many of these exceptions are caught in top-level functions, like any function hooked to a cron job or other action. 

When exceptions are caught, they are logged with `wap_log_error` as a **fatal error** and plugin functionality is disabled. Read more about disabling the plugin in the **Plugin states** section. 

## Custom stylesheets and scripts
### `css/wawp-styles-admin.css`
Contains the CSS styling for the menus on the admin page (i.e. the WAP Settings).

### `css/wawp-styles-shortcode.css` 
Contains the CSS styling for front-end WAP interfaces; most notably, this file contains the CSS for the shortcode that renders the WAP login page.

### `js/script.js`
Controls the functionality for the check-all boxes for the WA restriction membership levels and groups in the post/page editor.

## Custom WordPress hooks
The WAP plugin uses several custom WordPress hooks for controlling its operation; the custom hooks are defined below. The highlighted hooks are hooks for custom cron jobs, which are described on the next page.

Hook name                     | Hook function
----------------------------- | -----------------------------
`wawp_cron_refresh_user_hook` | <ul><li>Runs the Cron Job that refreshes and resyncs the users’ WildApricot data with their WordPress profiles.</li><li>Runs every 24 hours</li></ul>
`wawp_cron_refresh_license_check` | <ul><li>Runs the Cron Job that checks if the license key(s) are still valid </li><li>Runs every 24 hours</li></ul>
`wawp_cron_refresh_memberships_hook` | <ul><li>Runs the Cron Job that refreshes and resyncs the membership levels and groups from WildApricot to WordPress</li><li>Runs every 24 hours</li></ul>
`wawp_wal_credentials_obtained` | <ul><li>Runs when the WildApricot credentials and license key(s) have been entered and verified that they are valid</li><li>Inserts the WAP login page and the “Log In”/”Log Out” button to the menu</li><li>Essentially kickstarts the WAP functionality to the actual front-end of the website so that the user’s website may use the WAP functionality</li></ul>
`remove_wa_integration` | <ul><li>Replaces WA user login page content with the appropriate message: "access denied" or "fatal error encountered"</li><li>Runs when WAP functionality is disabled.</li></ul>
`disable_plugin` | <ul><li>Runs when WAP functionality must be disabled for invalid credentials or a fatal erorr</li><li>See **Plugin states** for more about disabling the plugin</li></ul>

To see all hooks used by WAP, refer to `WA_Integration` in `class-wa-integration.php`.

## WAP's cron jobs
WAP has 3 custom cron jobs, primarily for refreshing user data. The table below shows each cron job, the hook that activates it, the function that is run for each instance, and how often the cron job runs.


Hook | Function | Cron job | Frequency
------------|------------|------------|------------
`wawp_cron_refresh_user_hook` | `WA_Integration->refresh_user_info()` | Refreshes WA user info (only profiles updated in the last 24h) | Daily
`wawp_cron_refresh_license_check` | `WA_Integration->check_updated_credentials()` | Checks that WA API credentials and license keys are valid | Every user refresh
`wawp_cron_refresh_memberships_hook` | `Admin_Settings->cron_update_wa_memberships()` | Refreshes WA membership groups and levels | Daily

## How WAP works (from a developer's POV)
### Installation
1. User downloads the WAP plugin (from the NewPath website or the WordPress plugin store, etc.) as Wild-Apricot-Press.zip
2. User uploads plugin to their WordPress site
3. Plugin is activated
4. Activation code is run → located in `class-activator.php`
5. `Activator` checks if there’s already valid entered WA auth creds and license key
    * If so, `wawp_wal_credentials_obtained` will run and the WA user login page will be created
    * If not, nothing happens and the user is unable to use the plugin functionality (i.e. login page and post restriction)
6. WildApricot Press menu and settings pages are created and added to the WordPress dashboard
    * The code for the settings pages is located in `class-admin-settings.php`
7. If WA credentials/license keys are not found, admin notices are shown to remind the user to enter their WildApricot credentials and license key(s).
8. User enters their WildApricot credentials (API Key, Client ID, Client Secret) under WildApricot Press > Authorization
    * Inputs are sanitized
    * Credentials are checked with the WildApricot API
        * If valid, then a green message saying “Valid credentials” is displayed, and the admin notice to input WildApricot credentials is removed. Encrypted WildApricot credentials are saved in the options table.
        * Else, a red message indicating invalid credentials is shown
9. User enters their license key(s) under WildApricot Press > Licensing
    * Inputs are sanitized
    * License keys are checked with the Make scenario to see if they are correctly registered with the current WordPress site and WildApricot site and WildApricot user
        * If valid, then the WAP plugin is fully activated to the WordPress site, and the custom WordPress hook “wawp_wal_credentials_obtained” is run. User is presented with a success message. Encrypted license keys are saved in the options table.
        * If invalid, a message is shown to inform the user of their invalid license(s)
10. User selects which menu(s) they would like the “Log In”/”Log Out” button to appear on; this is also under WildApricot Press > Settings
    * If there is no menu selected, the login button is added to the primary menu by default. 
    * If user selected a menu location and changes to a theme which doesn’t have the same menu location, the new theme’s primary menu is used by default.
11. Following a successful step #8 and #9 (that is, both valid WildApricot credentials and valid license(s) have been entered), then the “Log In” button is added to the specified menu.
12. If it does not already exist, then the WAP login page is programmatically created. If this page already exists, then the login form content is restored. The “Log In” button is linked to this page.

### Post restriction
All the functionality related to post restriction is contained in `WA_Integration`. 
1. Post restriction starts with the post editor. There will be two custom meta boxes WAP will display on the editor: WildApricot Access Control and Individual Restriction Message. Meta boxes are created in `WA_Integration->post_access_add_post_meta_boxes()`.
    * WildApricot Access Control contains checkboxes with every user group and level associated with the admin’s WA account. 
    * The Individual Restriction Message box can be used to enter a custom restriction message for the current post only.
2. The user can check off levels and groups to restrict and optionally enter a custom message.
3. Once the user saves the post, the new post metadata is saved in `WA_Integration->post_access_load_restriction()`.
4. The user should see the correct post output based on their group/level and the restricted group(s)/level(s).
    * If the user’s group or level is checked in the meta, they should see the post content.
    * If it isn’t, they should see a restriction message.
        * Global message if there was no individual message entered.

### WildApricot user login
WA user login is where users can connect their WildApricot accounts to WordPress.
The user login form is controlled by the shortcode `wawp_custom_login_form`. All functionality related to WA login is contained in `WA_Integration`. 
#### Admin side
1. The user login page is created when the plugin is activated with valid credentials and a valid license key.
2. The link menu location can be configured under WildApricot Press > Settings > Content Restriction Options > Login/Logout Button Menu Location. The link will be in the primary menu by default.   
3. The user login page can be found with the custom URL `wawp-wild-apricot-login`. 
4. WildApricot users that log in through this form can be found in the Users panel.
5. WildApricot user data can be found under "WildApricot Membership Details". 
    *  This includes the user's account ID, membership level, membership groups, status, and organization.
    * Syncing additional custom fields can be configured under WildApricot Press > Settings > Synchronizaton Options
#### User side
1. If the plugin is enabled, the login page link will appear on the menu designated by the site admin.
2. Users can login with their WA email and password for the organization connected to the plugin.
3. After logging in, users are redirected to the previous page they were on before the login page.
4. Users can now access restricted posts that include their membership group(s)/level(s).

## Plugin states
### Deactivation
In the case that the WAP plugin is deactivated, the WAP functionality on the front-end of the website is disabled. 

The WA login page content will be replaced with an "Access Denied" message and users will no longer be able to use post restriction functionality.

WAP settings pages will also be removed.

### Invalid credentials
When the user enters invalid WA credentials/license key or valid credentials have not been entered yet, the plugin is **disabled**. 

### Fatal error
When a fatal error occurs, plugin functionality is **disabled**. An admin notice will display, notifying the user of the fatal error and what kind of error it is. 

The error will also be logged in the logfile `wp-content/wapdebug.log`. 

The post editor will also display this error. 

Any post or page using WAP user restriction will also display a fatal error message. 

Until the error is resolved, the above behavior will persist.

### Disabled
When the plugin is disabled, the user will experience the below behavior:
* “Plugin disabled” message on posts/pages previously using WA restriction
* Main admin settings page is blocked
* License settings page will be blocked if WA authorization credentials are missing
* User will see an admin notice prompting for the WA authorization credentials, license key, or both (whichever is missing/invalid) on all WAP settings pages
* WAP meta boxes will not be present in the post editor
* WA user login page will also display a “plugin disabled” message
* WA user login page link in menu is removed from website menu

### Upon re-activation
If the user has previously deactivated the plugin and then activates the plugin again, the following steps run:
1. Checks if WildApricot credentials and license key(s) are valid
    * If valid, then the WAP functionality is activated again
      * `wawp_wal_credentials_obtained` hook is run
      * Sets up cron events again

## Debugging

### WAP debugging
As previously mentioned in the `Log` class section, the custom WAP log functions are used for debugging and logging messages to the log file in `wp-content/wapdebug.log`. 

Debug messages can be logged with `wap_log_debug()`.

**WAP logging will work regardless of if WP_DEBUG is enabled or not.**

<mark>***Make sure the logfile toggle in WildApricot Press > Settings > Plugin Options is turned ON.***</mark>

The custom log file is useful for debugging website errors both during development and with users.

The other options for debugging in WordPress are as follows.
* Default logfile `debug.log`
* Query Monitor plugin

### Default logfile
To log messages to the default logfile, `wp-content/debug.log`, `WP_DEBUG` and `WP_DEBUG_LOG` must be enabled in `wp-config.php`.

Messages can be logged to this file using the `error_log()` function.

Generally, use of the default logfile is **not recommended** since it gets spammed with PHP errors from core and other plugins, so it is difficult to find WAP errors. 

However, it is useful if WAP or WordPress experiences a critical error.

### Query Monitor
[Query Monitor](https://wordpress.org/plugins/query-monitor/) is a developer tools panel plugin. It displays information such as hooks in use, AJAX calls, memory usage, database queries, and much more. It is very useful for accessing that kind of information that you can’t necessarily get with a log message.

It is possible to [log messages](https://querymonitor.com/docs/logging-variables/) with QM, but it doesn’t always work depending on where the message is being logged. For example, if you try to log a message with QM inside certain actions, it won’t display on the panel since the action may run before the panel is rendered.
