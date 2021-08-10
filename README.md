Wild Apricot for Wordpress (WAWP) Documentation

Version 2.0 - May 19, 2021

# WordPress Administrators Guide

## Installing and Configuring the WAWP Plugin

On the WordPress admin dashboard, using the left menu, navigate to to Plugins > Add New. Upload the plugin [add link to zip] and activate.

[Add instructions on Wild Apricot credentials and license key]

To configuring the WAWP plugin, the Wild Apricot API settings must be set.

### Create an Authorized Application in Wild Apricot

Navigate to WAWP > Authorization, and follow the instructions there to acquire the Wild Apricot credentials.

### Add API keys into WAWP Plugin

Once you have created an API key, Client ID and Client secret, copy and paste these strings into the configuration screen in your WAWP configuration.

[NEW IMAGE]

Below, ou can specify which menu(s) you would like to add the login/logout button to be added to by selecting the checkboxes. Once saved, a login/logout button will appear on these menu(s) automatically on your WordPress site.

[Insert screenshot here]

The WordPress administrators can now manage access to pages and posts based on Wild Apricot membership levels and membership groups.

***

## WAWP Global Access Settings

### Setting Membership Status Restrictions

To set which membership status can access pages and posts, navigate to WAWP in the left-hand menu, then select the Content Restriction Options tab.

Set the membership statuses that will be allowed to view restricted posts or pages.

![image10](https://user-images.githubusercontent.com/458134/110493595-c4471100-80c0-11eb-879c-598b7c9db7a4.png)

If no boxes are checked, members with any status will be able to view resticted posts.

### Set Global Restriction Message

You can show a default restricted message to visitors who are trying to access pages which they do not have access to.

[Updated Screenshot]

## Per Page and Post Settings

### Setting a custom page/post restricted message

Each page and post has a restricted message in a box called "Individual Restriction Message". This box appears under the main content and can float down the page depending on what page builder is in use, if any. Modify as desired.

[NEW IMAGE]

IMPORTANT: To save the custom restricted message, make sure to save or publish the page or post.

### Setting per Page or Post Access Control

On every page, you can select which member levels can view the content of the page. Access control is set by the box on the right side of the page's or post's edit screen.

If you do not select any boxes, then thr post is not restricted, and can be seen by all users, logged in and logged-out.

You can select one or more membership levels to restrict which levels have access. 

[eveyone button]

[New image]

### Membership Groups

You can also set access to one or more membership groups using the Select All Group Levels options. You can select zero or more membership groups which will allow members in those WIld Apricot membership groups to access the page. Selecting a group will allow all users of that group to view the page, even if their membership level was not explicitly checked.

The levels and groups are set inclusively -- that means that if a member is in one of the configured levels OR they are in a configured membership group they can see the page. If they don't fit one of the criteria they will not be able to see the page. 

***

## Membership Level Sync

The membership levels that have been added, modified or deleted will be synced into WordPress automatically on user login and every 24 hours. During each member login, the membership meta data (eg status and membership level) will be updated.

[More]

[Synchronization tab explanation
You can select custom fields that can be automatically synced from your Wild Apricot account to your WordPress account. Navigate to WAWP Settings and click on the Synchronization tab. There, you will see all of the custom fields from your Wild Apricot workspace. By selecting a field, that field will then be imported to each user. ]

## Embedding Content from Wild Apricot into WordPress

See the Additional Plugins section

***

# WAWP - Add Ons
Wild Apricot for WordPress - Custom Directory Plugin

This plugin makes it easy to integrate Wild Apricot member directories into a WordPress site.

Something something iframe plugin
Iframe can be used to add member profiles, events, and more!


# Version Control
- v1.0 - initial version

# License
[explanation here?]

