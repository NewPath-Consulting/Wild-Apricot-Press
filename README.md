Wild Apricot for Wordpress (WA4WP) Documentation

Version 2.0 - May 19, 2021

# WordPress Administrators Guide

## Installing and Configuring the WAWP Plugin

In the left menu, navigate to to Plugins > Add New. Upload the [wa4wp.zip](tobeadded) plugin and activate.

[Add instructions on Wild Apricot credentials and license key]

To configuring the WAWP plugin, the Wild Apricot API settings must be set.

### Create an Authorized Application in Wild Apricot

On the WordPress admin dashboard, using the left-hand menu, navigate to WAWP > Authorization, and follow the instructions there to acquire the Wild Apricot credentials.

### Add API keys into WAWP Plugin

Once you have created an API key, Client ID and Client secret, copy and paste these strings into the configuration screen in your WAWP configuration.

[NEW IMAGE]

Below, ou can specify which menu(s) you would like to add the login/logout button to be added to by selecting the checkboxes. Once saved, a login/logout button will appear on these menu(s) automatically on your WordPress site.

[Insert screenshot here]

The WordPress administrators can now manage access to pages and posts based on Wild Apricot membership levels and membership groups.

***

## WAWP Global Access Settings

### Setting Membership Status Restrictions

To set which membership status can access pages and posts, navigate to WAWP in the left-hand menu, the select the Content Restriction Options tab.

Set the membership statuses that will be allowed to view restricted posts or pages.

![image10](https://user-images.githubusercontent.com/458134/110493595-c4471100-80c0-11eb-879c-598b7c9db7a4.png)

If no boxes are checked, members with any status will be able to view resticted posts.

### Set Global Restriction Message

You can show a default restricted message to visitors who are trying to access pages which they do not have access to. This message will be displayed to logged in members who do not have access to a restricted page.

[Updated Screenshot]

## Per Page and Post Settings<br>

### Setting a custom page/post restricted message

Each page and post has a restricted message in a box called "Restrict individual Page and Post". This box appears under the main content and can float down the page depending on what page builder is in use, if any.

![image3](https://user-images.githubusercontent.com/458134/110493742-e771c080-80c0-11eb-99eb-e9b3c0408109.png)

IMPORTANT : To save the custom restricted message, make sure to save or publish the page or post.

### Setting per Page or Post Access Control

On every page you can select which member levels can view the content of the page. Acces control is set by the box on the right side of the page's or post's edit screen. Look for the Member Access  box to the right hand side of the page editor.

![image1](https://user-images.githubusercontent.com/458134/110493795-f2c4ec00-80c0-11eb-9339-885ac90f5c70.png)


You can select one or more membership levels to restrict which levels have access. Contacts without membership level are called "WA non-member contacts". You can restrict pages to non-members and make sure a non-logged in visitor cannot see those pages.

### Membership Groups

You can also set access to one or more membership groups using the Select All Group Levels options. You can select zero or more membership groups which will allow members in those WIld Apricot membership groups to access the page. Selecting a group will allow all users of that group to view the page, even if their membership level was not explicitly checked.

The levels and groups are set inclusively -- that means that if a member is in one of the configured levels OR they are in a configured membership group they can see the page. If they don't fit one of the criteria they will be restricted to the page. Of course membership levels and groups can be unchecked to provide a wider level of access.

***

## Membership Level Sync

The membership levels that have been added, modified or deleted will be synced into WordPress automatically. During each member login, the membership meta data (eg status and membership level) will be updated.

[More]

## Embedding Content from Wild Apricot into WordPress

See the Additional Plugins section

***

# Member's Guide

## Logging In

As a Wild Apricot user you can login using the login menu item. Once you click login you will be presented with a Wild Apricot login page.

Step 1
![image4](https://user-images.githubusercontent.com/458134/110494221-62d37200-80c1-11eb-8625-03ee6ed4b41d.png)


Step 2 Type your Wild Apricot login credentials here
![image14](https://user-images.githubusercontent.com/458134/110494160-4fc0a200-80c1-11eb-9689-3e38e6a445c7.png)

Step 3 If you are logged in successfully you will see a Your Profile link to the right of Logout
![image16](https://user-images.githubusercontent.com/458134/110494118-46373a00-80c1-11eb-9738-7f016405347b.png)


***

# WAWP - Add On
Wild Apricot for WordPress - Custom Directory Plugin

This plugin makes it easy to integrate Wild Apricot member directories into a WordPress site.

# Version Control
- v1.0 - initial version

# License
[explanation here?]

