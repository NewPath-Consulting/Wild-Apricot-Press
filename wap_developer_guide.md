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

* Contain WAP in its products list
* Not be expired
* Be registered to the WildApricot URL and account that correspond with the WildApricot authorization credentials

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
`Encryption_Exception` | <ul><li>`Data_Encryption->encrypt()`</li><li>`Data_Encryption->get_default_key()`</li><li>`Data_Encryption->get_default_salt()`</li></ul>
`Decryption_Exception` | `Data_Encryption->decrypt()`

To sum it up, exceptions could be thrown at many points in the plugin: connecting to the WA API or accessing or inserting any sensitive user data in the options table. 

Generally, these exceptions are thrown for reasons out of our control. An `API_Exception` could be thrown if the WildApricot API was down. An `Encryption_Exception` could be thrown if OpenSSL wasn’t installed. However, since these errors interrupt plugin functionality, it cannot continue to function once the errors happen. 

The general procedure followed when handling exceptions is to catch them as high up on the call stack as possible, with a few exceptions to this rule. Many of these exceptions are caught in top-level functions, like any function hooked to a cron job or other action. 

When exceptions are caught, they are logged with `wap_log_error` as a **fatal error** and plugin functionality is disabled. Read more about disabling the plugin in the **Plugin states** section. 
