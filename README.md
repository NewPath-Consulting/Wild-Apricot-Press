=== WildApricot Press ===
Contributors: 1cookspe, nataliebrotherton, asirota
Tags: wap, wildapricot, wild apricot, sso, membership
Requires at least: 5.0
Tested up to: 6.0
Requires PHP: 7.4
Stable Tag: 1.0b5
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WildApricot Press enables WordPress websites to support the WildApricot membership management system.

WildApricot Press (WAP) Documentation

## Version 1.0b5 - August 4, 2022
- added selection of default menu item for login/logout link
- renamed plugin to WildApricot Press
- cleaned up Authorization screen cosmetically
- Added WAP Developer's Guide

## Version 1.0b4 - July 29, 2022
- adding WAP switch to toggle error logging to wp-content/wapdebug.log file
- added a ton of error checking routines to log errors in various conditions where plugin errors or has an API error
- finished all sanitization/escape output text to support submission to WordPress plugin repo

## Version 1.0b3 - June 30, 2022
- fixed images in settings page
- started to escape output text for issue #58
- refactored licensing code and introduced generic license checker URL

## Version 1.0b2b - June 15, 2022
- modified to new production webhook for licensing

## Version 1.0b2a - March 11, 2022

## Version 1.0b2 - February 17, 2022
- fixed support for custom themes like Avada

## Version 1.0b1 - September 2, 2021
- first release
- fixed images in settings page
- started to escape output text for issue #58

# Administrator's Guide

## Installing and Configuring the WAP Plugin

On the WordPress admin dashboard, using the left menu, navigate to to Plugins > Add New. Upload the plugin compressed archive (zip) and ensure the plugin is activated.

To configure the WildApricot Press, the WildApricot API settings must be configured.

### Create an Authorized Application in WildApricot

WildApricot Press communicates with your WildApricot website via the WildApricot API using an "Authorized Application." To create a WildApricot authorized application, use the [WildApricot administrative settings to create a WordPress authorized application](https://gethelp.wildapricot.com/en/articles/199). With your API key, client ID and client secret, navigate to WAP Settings > Authorization, and follow the instructions there to apply the WildApricot keys.

### Add API keys into WAP

Once you have created an authorized application in WildApricot, enter the API key, client ID and client secret into WAP. You can copy and paste these "keys" into the configuration screen in the WAP configuration.

After entering these credentials and pressing the "Save Changes" button, a green success message will display the WildApricot website that you have connected to! You can ensure that this matches your WildApricot URL. 

![Adding WildApricot Authorized Application Keys into WAP](https://user-images.githubusercontent.com/458134/182927385-37c42be3-c74c-4bb1-aebd-a04570150b8b.png)


If you do not see a green success message, then please make sure that you have the correct WildApricot authorized application credentials (without any extra spaces or gremlin characters) and re-enter them.



### Licensing WAP

Finally, please enter your WAP license key to unlock WAP! This can be done on the "Licensing" section under WAP Settings > Licensing.

<img width="746" alt="wap licensing" src="https://user-images.githubusercontent.com/458134/131911156-e8aab427-9a31-46f4-9e20-3cb5a3e45ebe.png">


The WAP core plugin license is available at no cost, with no credit card or payment ever required! In the future commercials add-ons that generate revenue for your organization will have a license fee. If you do not already have a WAP license key, please visit the [WAP website](https://newpathconsulting.com/wap/), and see [Licenses](#license) for more information. Once you enter your license key and click "Save", you're good to go!


<img width="198" alt="wap license saved" src="https://user-images.githubusercontent.com/458134/131911442-01c4c614-2ffa-49f9-8ce9-049d322c5e51.png">

Once activated, a login/logout button will appear on your configured menu(s) automatically on your WordPress site. The screenshot below illustrates an example of the "Log Out" button being added to the main menu of the website. In this case, the "Log Out" button can be seen in the red box in the top right corner. 

<img width="1427" alt="Screen Shot 2021-08-16 at 2 37 56 PM" src="https://user-images.githubusercontent.com/8737691/129614718-eb525e0e-026c-4223-9058-64f3ff651bde.png">

When WildApricot contacts or members click the "Log In" button, they are directed to log in with their WildApricot username (email) and password. Once completed, a WordPress user account is created for them (if it does not exist already), and their WildApricot data is synchronized to the WordPress account. If the user already has a WordPress account on the WordPress site, then the contact or member's WildApricot information is synced with the existing WordPress account.

All WordPress administrators can now manage access to restricted pages and posts based on a member's membership levels and membership groups.

***

## WildApricot  login/logout button
By default, the primary menu will have the login/logout link added automatically by WAP. You can configure specify which WordPress menu(s) you would like to add the "single signon" login/logout button to by selecting other menus you have in your WordPress site. You can find this setting in **WildApricot Press > Settings** under the **Content Restriction Options** tab.


![Login/Logout Button Menu Settings](https://user-images.githubusercontent.com/458134/182928772-31589f3b-a9f8-42ab-ab63-104a8f0fdbb5.png)


## WAP Global Access Settings

### Setting Membership Status Restrictions

To set which membership status can access restricted pages and posts, navigate to WAP in the left-hand menu, then select the "Content Restriction Options" tab.

<img width="385" alt="wap settings" src="https://user-images.githubusercontent.com/458134/131911687-00e74697-c1c7-4bf4-83f4-423e2eee2cce.png">

Set the membership statuses that will be allowed to view restricted posts or pages.

<img width="648" alt="Screen Shot 2021-08-16 at 12 48 01 PM" src="https://user-images.githubusercontent.com/8737691/129658641-7b02705b-fa62-4541-b76f-31462a127c4c.png">

If no boxes are checked, then all members (regardless of status) will be able to view resticted posts.

### Set Global Restriction Message

By default restricted pages show the Global Restriction Message. A default message is shown to visitors who are trying to access pages which they do not have access to.

<img width="1241" alt="Screen Shot 2021-08-16 at 1 05 20 PM" src="https://user-images.githubusercontent.com/8737691/129612116-5666ef23-8c5c-4ead-b60a-9e26b78a8e5c.png">

## Per Page and Post Settings
Access to pages and posts can be set with WAP, allowing members to have access to  various posts and pages. These restrictions are set on the "Edit" screen of each post or page. The content editor can specify the restrictions as you write content.

### Setting a custom page/post restricted message

Each page and post has a restricted message in a box called "Individual Restriction Message". This setting overrides the default Global Restriction Message. This box appears under the main content and can float down the page depending on what page builder is in use, if any. You can modify the individual restriction message as desired on a per post or page basis. If you leave the individal resriction message blank, the Global Restriction Message will be used.

![Individual Restriction Message](https://user-images.githubusercontent.com/458134/182929565-7a3db6cf-3911-4ec7-b496-cbdb1c9df50b.png)

IMPORTANT: To save the custom restricted message, make sure to save or publish the page or post.

### Page or Post Access Control

On every page or post, you can select which Wild Apricot membership levels and membership groups can view the content of the page. Access control is set by the box on the right side of the page or post's "Edit" screen.


![Page or Post Access control](https://user-images.githubusercontent.com/8737691/129618750-3ed1f127-f084-452a-b9a4-296718424062.png)

You can select one or more membership levels to restrict which levels have access to the post. WildApricot members who are in a checked membership level will be able to access the page or post once it is published. 

Likewise, you can also set access to one or more membership groups. You can select zero or more membership groups, which will allow members in those WildApricot membership groups to access the page or post.

Access to posts and pages based on membership levels and membership groups are set inclusively. If a member is in one of the checked membership levels OR they are in a checked membership group then they can see the page or post. If they donot belong to a checked membership level or membership group, they will instead receive the global restricted message or the individual restricted message, if one was configured.

By default none of the membership levels or membership groups are checked, and as a result a page or post is not restricted. Unrestricted, published pages can be seen by all visitors, both logged-in and logged-out of the WordPress site.

***

## Memberships and User Data Refresh

The membership levels that have been added, modified or deleted will be synced into WordPress from WildApricot automatically on user login and every 24 hours. During each  login, the common, system and membership fields  (e.g. status and membership level) will be updated from WildApricot. So, after syncing your WordPress site with the WAP plugin, any updates made in the WildApricot contact database will be automatically sync'd into WordPress during login  as well within 24 hours.

On each user login and daily user refresh, several WildApricot member fields are synced to the user's WordPress profile. You can view these WildApricot fields by viewing the WordPress user under "WildApricot Membership Details". The default WildApricot fields can be viewed in the screenshot below. 

PS: Can you guess who this member might be? :) 

![Screen Shot 2021-08-16 at 2 16 45 PM](https://user-images.githubusercontent.com/8737691/129620414-f7f3042a-1063-4bbf-b0b6-a3c47084980a.png)


## Data Synchronization

You can specify which common, membership and system fields are synchronized into WordPress using the "Synchronization Options" tab under "Settings". See the screenshot below for an illustration.

<img width="389" alt="wa settings sync options" src="https://user-images.githubusercontent.com/458134/131911860-869f9ca0-a11e-483a-8021-8388baf7660c.png">

For each field that you check off, the field will be synced to each WildApricot user on the WordPress site. The screenshot below shows some of the extra fields being checked off and thus imported into each user in WordPress:

![Screen Shot 2021-08-16 at 4 28 36 PM](https://user-images.githubusercontent.com/8737691/129625564-fabce129-a64d-497b-99bd-b5e1230778cb.png)

Now, the extra fields can be seen in each user's WordPress profile after they login or after the daily sync is performed.

![Screen Shot 2021-08-16 at 2 19 45 PM (1)](https://user-images.githubusercontent.com/8737691/129625837-ca418263-a0d2-4bf9-b397-5daa055935f8.png)

These fields are now shared for WordPress and for other plugins, which extends the WildApricot database to every part of the WordPress plugin ecosystem. This is very powerful because now other plugins know which WildApricot user is in WordPress.

## Plugin Options

If you decide to deactivate and delete the WAP plugin, navigate to WAP Settings and click on the “Plugin Options” tab. (Even though you will never want to delete WAP, right?) :)

<img width="957" alt="wap settings plugin options" src="https://user-images.githubusercontent.com/458134/131911994-1954ef24-4e44-4797-9b42-a8534b1fa16c.png">

By selecting the “Delete all WildApricot information from my WordPress site”, you will remove all synced WildApricot data from your WordPress site upon deletion of the WAP plugin. You can also leave this option unchecked if you would like to keep the synced users and roles on your WordPress site even after deleting WAP.

<img width="544" alt="Screen Shot 2021-08-16 at 6 07 21 PM" src="https://user-images.githubusercontent.com/8737691/129635421-3f80bb44-3c03-4659-8b28-2ce2c02125e6.png">

## WAP Debug Log

In Plugin Options tab you can turn on the "Print log messages to log file" to start logging errors and warnings to the filer wp-content/wapdebug.log. This can be used to troubleshoot plugin issues and provided to support.

## Embedding Content from WildApricot into WordPress

WildApricot content can be embedded into WordPress using a number of WAP add-ons; see the [WAP - Add Ons](#wap---add-ons) section for more.

***

# WAP - Add Ons
NewPath Consulting has developed several add-ons for the core WAP plugin that further enrich your experience with your WildApricot account in WordPress! Read more about them below:

## WildApricot IFrame Add-on
Embed a system page from WildApricot directly in your WordPress site! Fundamental WildApricot features including member profiles, events, and more can be displayed in an IFrame (Inline Frame) in a WordPress post with just the click of a button! [Learn more](https://newpathconsulting.com/wap).

## Member Directory Add-on
Want to display a directory of your WildApricot users in WordPress? Look no further! The Member Directory Add-on for WAP allows you to show your WildApricot users directly in your WordPress site. [Learn more](https://newpathconsulting.com/wap).



# License
The License for WildApricot Press is completely free, and is used to verify that your WildApricot website is connected to your WordPress website. Please visit the [WildApricot Press website](https://newpathconsulting.com/wap/) to get your free license key or to inquire further about the WAP plugin!

After installing each add-on, you can enter the license key for the WAP plugin and each add-on on the same Licensing page, under WAP Settings > Licensing.

<img width="422" alt="wap licensing of add ons" src="https://user-images.githubusercontent.com/458134/131912822-42e0d808-c21f-4ea2-a612-94501254a728.png">

