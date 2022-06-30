=== NewPath Wild Apricot Press (WAP) ===
Contributors: asirota
Tags: wildapricot, wild apricot, sso, membership
Requires at least: 5.0
Tested up to: 6.0
Requires PHP: 7.4
Stable Tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Wild Apricot Press enables WordPress websites to support the Wild Apricot membership management system.

Wild Apricot Press (WAP) Documentation

# Release History

## Version 1.0b3 - June 30, 2022
- fixed images in settings page
- started to escape output text for issue #58
- refactored licensing code and introduced generic license checker URL

## Version 1.0b2 - February 17, 2022
- fixed support for custom themes like Avada

## Version 1.0b1 - September 2, 2021
- first release
- fixed images in settings page
- started to escape output text for issue #58

# Administrator's Guide

## Installing and Configuring the WAP Plugin

On the WordPress admin dashboard, using the left menu, navigate to to Plugins > Add New. Upload the plugin compressed archive (zip) and activate.

To configure the Wild Apricot Press, the Wild Apricot API settings must be configured.

### Create an Authorized Application in Wild Apricot

Wild Apricot Press communicates with your Wild Apricot website via the Wild Apricot API using an "Authorized Application." To create a Wild Apricot authorized application, navigate to WAP Settings > Authorization, and follow the instructions there to acquire the authorized application credentials.

<img width="946" alt="wap authorization" src="https://user-images.githubusercontent.com/458134/131910967-158c659d-9f73-4be2-b84a-4683bd6b8975.png">


### Add API keys into WAP

Once you have created an authorized application in Wild Apricot, enter the API key, client ID and client secret into WAP. You can copy and paste these "keys" into the configuration screen in the WAP configuration.

<img width="540" alt="Screen Shot 2021-08-16 at 7 20 41 PM" src="https://user-images.githubusercontent.com/8737691/129640967-bbfc72e8-a9d4-4a1e-aa1e-990c45f1539f.png">

After entering these credentials and pressing the "Save Changes" button, a green success message will display the Wild Apricot website that you have connected to! You can ensure that this matches your Wild Apricot URL. If you do not see this green success message, then please make sure that you have the correct authorized application credentials (without any extra spaces or gremlin characters) and re-enter them.

You can also configure specify which WordPress menu(s) you would like to add the "single signon" login/logout button to by selecting the appropriate checkboxes. WAP will automatically configure your menu(s) with the login/logout links.

<img width="620" alt="Screen Shot 2021-08-16 at 12 40 34 PM" src="https://user-images.githubusercontent.com/8737691/129612544-ff19e86c-5395-4bc4-b82b-a1fa914f4057.png">

### Licensing WAP

Finally, please enter your WAP license key to unlock WAP! This can be done on the "Licensing" section under WAP Settings > Licensing.

<img width="746" alt="wap licensing" src="https://user-images.githubusercontent.com/458134/131911156-e8aab427-9a31-46f4-9e20-3cb5a3e45ebe.png">


The WAP core plugin license is available at no cost, with no credit card or payment ever required! In the future commercials add-ons that generate revenue for your organization will have a license fee. If you do not already have a WAP license key, please visit the [WAP website](https://newpathconsulting.com/wap/), and see [Licenses](#license) for more information. Once you enter your license key and click "Save", you're good to go!

<img width="198" alt="wap license saved" src="https://user-images.githubusercontent.com/458134/131911442-01c4c614-2ffa-49f9-8ce9-049d322c5e51.png">

Once activated, a login/logout button will appear on your configured menu(s) automatically on your WordPress site. The screenshot below illustrates an example of the "Log Out" button being added to the main menu of the website. In this case, the "Log Out" button can be seen in the red box in the top right corner.

<img width="1427" alt="Screen Shot 2021-08-16 at 2 37 56 PM" src="https://user-images.githubusercontent.com/8737691/129614718-eb525e0e-026c-4223-9058-64f3ff651bde.png">

When Wild Apricot contacts or members click the "Log In" button, they are directed to log in with their Wild Apricot username (email) and password. Once completed, a WordPress user account is created for them (if it does not exist already), and their Wild Apricot data is synchronized to the WordPress account. If the user already has a WordPress account on the WordPress site, then the contact or member's Wild Apricot information is synced with the existing WordPress account.

All WordPress administrators can now manage access to restricted pages and posts based on a member's membership levels and membership groups.

***

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

<img width="1127" alt="Screen Shot 2021-08-16 at 1 16 20 PM" src="https://user-images.githubusercontent.com/8737691/129611817-cd5c0503-3dad-49d2-938a-d1bab977f082.png">

IMPORTANT: To save the custom restricted message, make sure to save or publish the page or post.

### Page or Post Access Control

On every page, you can select which Wild Apricto membership levels and membership groups can view the content of the page. Access control is set by the box on the right side of the page or post's "Edit" screen.

<img width="583" alt="Screen Shot 2021-08-16 at 1 36 31 PM" src="https://user-images.githubusercontent.com/8737691/129618750-3ed1f127-f084-452a-b9a4-296718424062.png">

You can select one or more membership levels to restrict which levels have access to the post. Wild Apricot members who are in a checked membership level will be able to access the page or post once it is published. 

Likewise, you can also set access to one or more membership groups. You can select zero or more membership groups, which will allow members in those Wild Apricot membership groups to access the page or post.

Access to posts and pages based on membership levels and membership groups are set inclusively. If a member is in one of the checked membership levels OR they are in a checked membership group then they can see the page or post. If they donot belong to a checked membership level or membership group, they will instead receive the global restricted message or the individual restricted message, if one was configured.

By default none of the membership levels or membership groups are checked, and as a result a page or post is not restricted. Unrestricted, published pages can be seen by all visitors, both logged-in and logged-out of the WordPress site.

***

## Memberships and User Data Refresh

The membership levels that have been added, modified or deleted will be synced into WordPress from Wild Apricot automatically on user login and every 24 hours. During each  login, the common, system and membership fields  (e.g. status and membership level) will be updated from Wild Apricot. So, after syncing your WordPress site with the WAP plugin, any updates made in the Wild Apricot contact database will be automatically sync'd into WordPress during login  as well within 24 hours.

On each user login and daily user refresh, several Wild Apricot member fields are synced to the user's WordPress profile. You can view these Wild Apricot fields by viewing the WordPress user under "Wild Apricot Membership Details". The default Wild Apricot fields can be viewed in the screenshot below. 

PS: Can you guess who this member might be? :) 

![Screen Shot 2021-08-16 at 2 16 45 PM](https://user-images.githubusercontent.com/8737691/129620414-f7f3042a-1063-4bbf-b0b6-a3c47084980a.png)


## Data Synchronization

You can extend the default Wild Apricot fields using the "Synchronization Options" tab under "WAP Settings". See the screenshot below for an illustration.

<img width="389" alt="wa settings sync options" src="https://user-images.githubusercontent.com/458134/131911860-869f9ca0-a11e-483a-8021-8388baf7660c.png">

For each checkbox that you check off, the  field will be synced to each Wild Apricot user on the WordPress site. The screenshot below shows some of the extra fields being checked off and thus imported into each user in WordPress:

![Screen Shot 2021-08-16 at 4 28 36 PM](https://user-images.githubusercontent.com/8737691/129625564-fabce129-a64d-497b-99bd-b5e1230778cb.png)

Now, the extra fields can be seen in each user's WordPress profile.

![Screen Shot 2021-08-16 at 2 19 45 PM (1)](https://user-images.githubusercontent.com/8737691/129625837-ca418263-a0d2-4bf9-b397-5daa055935f8.png)

These fields are now shared for WordPress and for other plugins, which extends the Wild Apricot database to every part of he WordPress ecosysem. This is very powerful!

## Plugin Options

If you decide to deactivate and delete the WAP plugin, navigate to WAP Settings and click on the “Plugin Options” tab. (Even though you will never want to delete WAP, right?) :)

<img width="957" alt="wap settings plugin options" src="https://user-images.githubusercontent.com/458134/131911994-1954ef24-4e44-4797-9b42-a8534b1fa16c.png">

By selecting the “Delete all Wild Apricot information from my WordPress site”, you will remove all synced Wild Apricot data from your WordPress site upon deletion of the WAP plugin. You can also leave this option unchecked if you would like to keep the synced users and roles on your WordPress site even after deleting WAP.

<img width="544" alt="Screen Shot 2021-08-16 at 6 07 21 PM" src="https://user-images.githubusercontent.com/8737691/129635421-3f80bb44-3c03-4659-8b28-2ce2c02125e6.png">

## Embedding Content from Wild Apricot into WordPress

Wild Apricot content can be embedded into WordPress using a number of WAP add-ons; see the [WAP - Add Ons](#wap---add-ons) section for more.

***

# WAP - Add Ons
NewPath Consulting has developed several add-ons for the core WAP plugin that further enrich your experience with your Wild Apricot account in WordPress! Read more about them below:

## Wild Apricot IFrame Add-on
Embed a system page from Wild Apricot directly in your WordPress site! Fundamental Wild Apricot features including member profiles, events, and more can be displayed in an IFrame (Inline Frame) in a WordPress post with just the click of a button! [Learn more](https://newpathconsulting.com/wap).

## Member Directory Add-on
Want to display a directory of your Wild Apricot users in WordPress? Look no further! The Member Directory Add-on for WAP allows you to show your Wild Apricot users directly in your WordPress site. [Learn more](https://newpathconsulting.com/wap).

## HUEnique Theme
Derived from GeneratePress, the HUEnique theme works directly with the WAP plugin! HUEnique automatically finds the dominant colors in your company’s logo within seconds, thus customizing the colors in the theme to complement your company’s logo perfectly! [Learn more](https://newpathconsulting.com/wap).


# Changelog
- v1.0b1 - Initial version (September 2, 2021)

# License
The License for Wild Apricot Press is completely free, and is used to verify that your Wild Apricot website is connected to your WordPress website. Please visit https://newpathconsulting.com/wap/ to get your free license key or to inquire further about the WAP plugin!

After installing each add-on, you can enter the license key for the WAP plugin and each add-on on the same Licensing page, under WAP Settings > Licensing.

<img width="422" alt="wap licensing of add ons" src="https://user-images.githubusercontent.com/458134/131912822-42e0d808-c21f-4ea2-a612-94501254a728.png">

