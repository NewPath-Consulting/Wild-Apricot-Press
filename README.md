=== NewPath WildApricot Press ===
Contributors: 1cookspe, nataliebrotherton, asirota
Tags: wildapricot, wild apricot, membership, event management, events, membership management, Single Sign-on, sso
Requires at least: 5.0
Tested up to: 6.6.2
Requires PHP: 7.4
Stable Tag: 1.0.3
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

NewPath WildApricot Press enables WordPress websites to support the WildApricot membership management system.

== Description ==

WordPress is the world’s most popular website builder. WildApricot is the #1 rated membership management software. Now, your organization can seamlessly combine the best of both with NewPath WildApricot Press plugin.

NewPath WildApricot Press and our custom theme library enable you to build and manage your own full-featured website using just your web browser—without having to learn how to code. In fact, if you’ve ever used a layout editor like Microsoft Word or PowerPoint, you’ll be right at home with the WildApricot Press blocks in the WordPress Block Editor. Enjoy the elegance, diversity of plugins and unparalleled versatility of WordPress with the user-friendliness power of WildApricot Membership Management software. Build faster, customize more and lower the learning curve to produce the high-quality membership websites your members deserve.

## Features

- Enable logging in to your WordPress site with WildApricot credentials, right from a page on your WordPress site (no unsightly popups!)
- When a WildApricot contact or member successfully logs in through a native WordPress login page created by the WildApricot Press plugin, their contact and member information is copied into the WordPress user database. Their role is assigned to the membership level of their WildApricot account.
- Add login/logout to any menu bar with 1 click (no coding!)
- You can select and automatically sync WildApricot contact/member data including ALL common, system and membership fields into the WordPress Users database
- WordPress administrators can manage access to restriced pages and posts by membership levels AND membership groups. Custom restriction messages can be added to specific pages or posts.
- Features an add-on architecture so you can add new functionality to NewPath WildApricot Press. Search the plugin directory for "WildApricot Press" for other add-ons.

Check out the [FAQ section](https://wordpress.org/plugins/newpath-wildapricot-press/#faq) and [screenshots](https://wordpress.org/plugins/newpath-wildapricot-press/#screenshots) for more details.

https://vimeo.com/newpath/wapintro

## Add-Ons

NewPath Consulting has developed several add-ons for NewPath WildApricot Press that further enrich your WildApricot integration experience WordPress!

### iFrame Widget Add-on

Embed a system page from WildApricot directly in your WordPress site! Fundamental WildApricot features including member profiles, events, and more can be displayed in an IFrame (Inline Frame) in a WordPress post with just the click of a button! [Learn more](https://newpathconsulting.com/wap).

### Member Directory Add-on

Want to display a directory of your WildApricot users in WordPress? Look no further! The Member Directory Add-on for WAP allows you to show your WildApricot users directly in your WordPress site. [Learn more](https://newpathconsulting.com/wap).

## License

Please visit the [NewPath WildApricot Press website](https://newpathconsulting.com/wap/) to obtain your license key or to inquire further about the plugin!

== Installation ==

Once installed and activated on your WordPress site, you must authorize and license NewPath WildApricot Press. Without both authorizing and licensing, the plugin and the related add-ons will **not** function. You can obtain a license key on the [NewPath WildApricot Press website](https://newpathconsulting.com/wap).

> Create an Authorized Application in WildApricot

NewPath WildApricot Press communicates with your WildApricot website via the WildApricot API using an "Authorized Application." To create a WildApricot authorized application, use the [WildApricot administrative settings to create a "Server application" authorized application](https://gethelp.wildapricot.com/en/articles/199). You must provide "full access" to the authorized application to enable NewPath WildApricot Press to read and write data into WildApricot.

_IMPORTANT NOTE: DO NOT CREATE A WORDPRESS AUTHORIZED APPLICATION. IT WILL NOT WORK!_

[Screenshot - Creating Server application in WildApricot](https://user-images.githubusercontent.com/458134/184677576-aad24cdd-c37a-4827-b54a-fc139ee95a1d.png)

[Screenshot - Granting Full access to Server application](https://user-images.githubusercontent.com/458134/184677619-e3f5b2f9-2b9f-4b73-908b-7caefe25968c.png)

> Add API keys into WAP

Once you have created an authorized application in WildApricot, navigate to **WildApricot Press > Authorization** and enter the API key, client ID and client secret into WAP. You can copy and paste these "keys" into the configuration screen in the WAP configuration.

After entering these credentials and pressing the "Save Changes" button, a green success message will display the WildApricot website that you have connected to! You can ensure that this matches your WildApricot URL.

[Screenshot - Adding WildApricot Authorized Application Keys into WAP](https://user-images.githubusercontent.com/458134/182927385-37c42be3-c74c-4bb1-aebd-a04570150b8b.png)

If you do not see a green success message, then please make sure that you have the correct WildApricot authorized application credentials (without any extra spaces or gremlin characters) and re-enter them.

> Licensing WAP

The NewPath WildApricot Press plugin license is available on [the NewPath WildApricot Press website](https://newpathconsulting.com/wap). Your license includes 2 free add-ons, the member directory and iframe widget blocks. Future commercials WAP add-ons that generate revenue for your organization will have a separate license fee.

To activate the plugin, enter your license key in **WildApricot Press > Licensing**.

[Screenshot - WildApricot Press > Licensing](https://user-images.githubusercontent.com/458134/131911156-e8aab427-9a31-46f4-9e20-3cb5a3e45ebe.png)

Once you enter your license key and click "Save", you're good to go!

[Screenshot - Successful License Key Added](https://user-images.githubusercontent.com/458134/131911442-01c4c614-2ffa-49f9-8ce9-049d322c5e51.png)

After installing any add-ons, you can enter the license key for each add-on on the Licensing page, under **Settings > Licensing**.

[Screenshot - Licensing of add ons](https://user-images.githubusercontent.com/458134/131912822-42e0d808-c21f-4ea2-a612-94501254a728.png)

== Frequently Asked Questions ==

= What is the difference between NewPath WildApricot Press and Wild Apricot Login plugin? =

[NewPath WildApricot Press website](https://newpathconsulting.com/wap/) is developed and maintained by [NewPath Consulting](https://newpathconsulting.com). Wild Apricot Login was released and developed by Personify several years ago, but unfortunately the plugin has not been improved or expanded in several years. So NewPath rewrote the functionality, from scratch and eliminated many nasty bugs.

NewPath WildApricot Press includes full support and maintenance, regular synchronization of data for logged in users, ability to sync all system, common and membership fields. Most importantly NewPath WildApricot Press has add-on architecture to enable more functionality to be made available over time via add-on plugins and blocks.

= Does it work with my theme? =

It should. We make every effort to provide standard plugin code, but some themes may break with the plugin. Please let us know on our [support forum](https://talk.newpathconsulting.com/c/wa-discuss/wap) if you encounter any issues. We also recommend the [GeneratePress theme](https://generatepress.com) site library for optimal design, mobile and desktop speed, Google SEO friendliness and overall aesthetic beauty.

= How do I authorize and license NewPath WildApricot Press? =

Read and follow [Installation](https://wordpress.org/plugins/newpath-wildapricot-press/installation) for detailed steps to authorize and license NewPath WildApricot Press.

= How do I set which membership statuses can access restricted pages? =

To set which membership status can access restricted pages and posts, navigate to WildApricot Press and select the "Content Restriction Options" tab.

[Screenshot - "Content Restriction Options" tab](https://user-images.githubusercontent.com/458134/131911687-00e74697-c1c7-4bf4-83f4-423e2eee2cce.png)

Set the membership statuses that will be allowed to view restricted posts or pages.

[Screenshot - allowed membership statuses](https://user-images.githubusercontent.com/8737691/129658641-7b02705b-fa62-4541-b76f-31462a127c4c.png)

If no boxes are checked, then all members (regardless of status) will be able to view resticted posts.

= How do I restrict which membership levels and membership groups can see a page or post? =

On every page or post, you can select which WildApricot membership levels and membership groups can view the content of the page. Access control is set by the box on the right side of the page or post's "Edit" screen.

[Screenshot - Page or Post Access control](https://user-images.githubusercontent.com/8737691/129618750-3ed1f127-f084-452a-b9a4-296718424062.png)

You can select one or more membership levels to restrict which levels have access to the post. WildApricot members who are in a checked membership level will be able to access the page or post once it is published.

Likewise, you can also set access to one or more membership groups. You can select zero or more membership groups, which will allow members in those WildApricot membership groups to access the page or post.

Access to posts and pages based on membership levels and membership groups are set inclusively. If a member is in one of the checked membership levels OR they are in a checked membership group then they can see the page or post. If they donot belong to a checked membership level or membership group, they will instead receive the global restricted message or the individual restricted message, if one was configured.

By default none of the membership levels or membership groups are checked, and as a result a page or post is not restricted. Unrestricted, published pages can be seen by all visitors, both logged-in and logged-out of the WordPress site.

= How do I set which messages show when access is restricted to a page or post? =

By default restricted pages show the Global Restriction Message. This message is shown to visitors who are trying to access pages which they do not have access to.

[Screenshot - Default restriction message](https://user-images.githubusercontent.com/8737691/129612116-5666ef23-8c5c-4ead-b60a-9e26b78a8e5c.png)

> Per Page and Post Settings

Access to pages and posts can be set with WAP, allowing members to have access to various posts and pages. These restrictions are set on the "Edit" screen of each post or page. The content editor can specify the restrictions as you write content.

> Setting a custom page/post restricted message

Each page and post has a restricted message in a box called "Individual Restriction Message". This setting overrides the default Global Restriction Message. This box appears under the main content and can float down the page depending on what page builder is in use, if any. You can modify the individual restriction message as desired on a per post or page basis. If you leave the individal resriction message blank, the Global Restriction Message will be used.

[Screenshot - Individual Restriction Message](https://user-images.githubusercontent.com/458134/182929565-7a3db6cf-3911-4ec7-b496-cbdb1c9df50b.png)

IMPORTANT: To save the custom restricted message, make sure to save or publish the page or post.

= How do I configure which WildApricot common and membership fields are copied into the WordPress database? =

You can specify which common, membership and system fields are synchronized into WordPress using the "Synchronization Options" tab under "Settings". See the screenshot below for an illustration.

[Screenshot - Sync Options Screen](https://user-images.githubusercontent.com/458134/131911860-869f9ca0-a11e-483a-8021-8388baf7660c.png)

For each field that you check off, the field will be synced to each WildApricot user on the WordPress site. The screenshot below shows some of the extra fields being checked off and thus imported into each user in WordPress:

[Screenshot - Sync Options Screen 2](https://user-images.githubusercontent.com/8737691/129625564-fabce129-a64d-497b-99bd-b5e1230778cb.png)

Now, the extra fields can be seen in each user's WordPress profile after they login or after the daily sync is performed.

[Screenshot - Membership Data in WordPress](https://user-images.githubusercontent.com/8737691/129625837-ca418263-a0d2-4bf9-b397-5daa055935f8.png)

These fields are now shared for WordPress and for other plugins, which extends the WildApricot database to every part of the WordPress plugin ecosystem. This is very powerful because now other plugins know which WildApricot user is in WordPress.

= Why are some custom contact and membership fields or Membership Groups not synchronized?

Due to security features in WildApricot, any contact field that has Member access configured as "For administator access only" or membership field with Member access as "No access - Internal use" cannot be sync'd via the WildApricot API and as a result data will not come across for these fields. In a future version of the plugin, these fields will be shown in the WAP user interface as "unavailable for synchronization".

The Groups Participation membeship field _MUST_ be set to "Edit" or "View only" for this member's Groups to sync into WordPress and be available for checking access control when using Membership Groups.

Ensure the following options are unchecked when configuring fields you wish to sync into WordPress:

[Screenshot - contact field configuration](https://raw.githubusercontent.com/NewPath-Consulting/Wild-Apricot-Press/1.0.2/images/contact-field-configuration.png)

[Screenshot - membership field configuration](https://raw.githubusercontent.com/NewPath-Consulting/Wild-Apricot-Press/1.0.2/images/membership-field-configuration.png)

= How often is contact and member user data synchronized? =

By default no WildApricot user data is added to the WordPress user data database _until_ a contact or member logs in for the first time into the WAP-enabled WordPress site. Once a successful login occurs the WordPress user is created with a core set of information like email address, userID, first name, last name and organization as well as membership level and membership status (if the contact is a member).

During every subseqeunt login, the WildApricot contact/member data is synchronized into the WordPress user database. Any contact and member data fields that have been updated will be synced into WordPress from WildApricot automatically as well every 24 hours for any contacts or members that have logged in.

So, after connecting your WordPress site with the WAP plugin, any updates _for contacts who have already logged in successfully to your WordPress site_ will be automatically sync'd into WordPress during a successful login as well within 24 hours

You can view the WildApricot fields that are synchronized by viewing the WordPress user under "WildApricot Membership Details". The default WildApricot fields can be viewed in the screenshot below.

[Screenshot - WordPress User Data with WildApricot](https://user-images.githubusercontent.com/8737691/129620414-f7f3042a-1063-4bbf-b0b6-a3c47084980a.png)

= When I delete the plugin, will there be a way to "clean" the WordPress database? =

If you decide to deactivate and delete the NewPath WildApricot Press plugin, you can set several options to "clean up" in the “Plugin Options” tab. (Even though you will never want to delete WAP, right?) :) You can also setup a debsugging log to troublshoot any issues you may encounter.

[Screenshot - Plugin Options](https://user-images.githubusercontent.com/458134/187314069-0d017710-630e-4f84-8f0b-11607065809f.png)

By default, upon deletion of the WildApricot Press plugin, none of the data created and stored by WildApricot Press is deleted. You can remove all database and post/page data created by WildApricot Press by checking "Delete WordPress database data and post/page data". You can remove all WildApricot users created by WildApricot Press by checking "Delete users added by WildApricot Press". With these settings checked, you can delete the NewPath WildApricot Press plugin and perform a "clean" install of the plugin when you install again.

> WAP Debug Log

In Plugin Options tab you can turn on the "Print log messages to log file" to start logging errors and warnings to the filer wp-content/wapdebug.log. This can be used to troubleshoot plugin issues and provided to support.

== Changelog ==

= Version 1.0.3 - September 20, 2024 =

- improved post restriction compatibility with other plugins
- fixed overly strict URL comparison between licensed URLs and WildApricot URL

= Version 1.0.2 - June 26, 2023 =

- updated licensing message
- improved performance of retrieving and sync'ing thousands of contacts
- checking the minimum PHP and required PHP Modules during install
- fixed bug where users with the '&' character in the password could not login
- added README FAQ on fields cannot be sync'd due to access control settings in WildApricot
- fixed syncing of Account ID (UserID) and access control issues
- fixed showing/hiding items in menus based on access control

= Version 1.0.1 - August 24, 2022 =

- plugin now removes any saved options and synchronized user meta data when switching WildApricot sites via new API keys and license key swap
- added options in Plugin Options to delete WildApricot meta data and sync'd users during plugin deletion
- cosmetic changes to several user interface string to be more consistent and clear
- plugin now called NewPath WildApricot Press

= Version 1.0.0 - August 9, 2022 =

- first public release

= Version 1.0b5 - August 4, 2022 =

- added selection of default menu item for login/logout link
- renamed plugin to WildApricot Press
- cleaned up Authorization screen cosmetically
- Added WAP Developer's Guide

= Version 1.0b4 - July 29, 2022 =

- adding WAP switch to toggle error logging to wp-content/wapdebug.log file
- added a ton of error checking routines to log errors in various conditions where plugin errors or has an API error
- finished all sanitization/escape output text to support submission to WordPress plugin repo

= Version 1.0b3 - June 30, 2022 =

- fixed images in settings page
- started to escape output text for issue #58
- refactored licensing code and introduced generic license checker URL

= Version 1.0b2b - June 15, 2022 =

- modified to new production webhook for licensing

= Version 1.0b2a - March 11, 2022 =

= Version 1.0b2 - February 17, 2022 =

- fixed support for custom themes like Avada

= Version 1.0b1 - September 2, 2021 =

- first release
- fixed images in settings page
- started to escape output text for issue #58

== Upgrade Notice ==

= 1.0.1 =
If you are having trouble, install this version and then use the new plugin options to remove all previous WAP daa, and then reinstall this version.

== Screenshots ==

1. Website with Login/Logout link added after WAP has been installed, activated and licensed.
2. Content Restriction Options
3. Setting which Membership Statuses can access content
4. Setting a "global restriction" message which can be changed per page or post
5. Setting access control to membership levels and groups per page or post
6. Synchronization options to sync common, membership and system fields into WordPress
7. Synchronized WordPress users get WildApricot data every 24 hours and during successful login.
8. Plugin options to clean up WordPress after deletion and a special WAP Debug Log
