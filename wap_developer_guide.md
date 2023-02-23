# WildApricot Press Developer's Guide

#### *By Spencer Gable-Cook and Natalie Brotherton*

##### *Edited August 2022*

WildApricot Press (WAP) is a WordPress plugin written primarily in PHP. It also contains two CSS files for formatting custom content.

This document describes the design of WAP as a guide for maintenance and extension.


## Required plugin files

### `plugin.php`
`plugin.php` is the main file of WAP required by WordPress. The plugin information that displays to users (title, description, version) are all defined here. This file does not contain much  functionality for the plugin, but rather creates the classes that do: `Activator`, `Settings`, and `WA_Integration`. 

### `uninstall.php` 
Like `plugin.php`, this is another standard file required by WordPress. When the plugin is deleted from the WordPress site, the `uninstall.php` file is run to clean up any variables or other data that the plugin may have been using, but that are  no longer required since the plugin is deleted. 

In `uninstall.php`, all of the `wp_options` table entries added by the plugin are deleted from the user’s database. Depending on the plugin options setting "Attributes to Remove Upon Plugin Deletion", the WildApricot synced users are deleted from the WordPress user database as well. Learn more about this setting in `admin-settings.php`. 

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

NOTE: to use the developer License Key database for testing dev license keys the following line in `class-addon.php` should be edited to use the https://newpathconsulting.com/check-dev webhook:

```php
    const HOOK_URL = 'https://newpathconsulting.com/check-dev';
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

### `Deactivator`
`Deactivator` runs when the plugin is deactivated. In `plugin.php`, you can see that it is registered to the WordPress deactivation hook.

`Deactivator` contains a single static function `deactivate()`.

`Deactivator::deactivate()` does the following:
* Removes the WA user login link from the website menu
* Hides the WA user login page by making it private
* Removes the CRON jobs
  * See more about CRON jobs here

### `Settings`
This class manages the admin settings pages for the WAP plugin; both the user interface and processing the data input on the settings pages. The settings page was set up with the techniques described in Chapter 3 of the “Professional WordPress Development” book.

The class constructs the following settings pages the code for which is contained in their own class:
* `Admin_Settings` Main admin settings which has three tabs
  * Restriction options
  * Synchronization options
  * Plugin options
* `WA_Auth_Settings` WildApricot Authorization credentials form
* `License_Settings` License keys form

### `WA_API`
This class manages the API calls to the WildApricot API. Each instance of the `WA_API` class requires the user’s access token and WildApricot account ID. (You can see this in the constructor of the class). 

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
* CRON jobs updating WA user data

### `Log`
This class manages debug and error logging for WAP. 

It contains three main functions in its interface:
* `wap_log_debug($msg)`
* `wap_log_error($msg, $fatal = false)`
  * Pass in true after the log message to indicate a fatal error.
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