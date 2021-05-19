Wild Apricot for Wordpress (WA4WP) Documentation

Version 2.0 - May 19, 2021

# WordPress Administrators Guide

## Installing and Configuring the WAWP Plugin

In the left menu, navigate to to Plugins > Add New. Upload the [wa4wp.zip](tobeadded) plugin and activate.

Note: This will automatically install and activate the Wild Apricot Login and Advanced Custom Fields plugins if these were not there previously. It will also import a ACF configuration file with 3 field groups.

To configuring the WAWP plugin, the Wild Apricot API settings must be set.

### Create an Authorized Application in Wild Apricot

Using a full Wild Apricot administrator account, create your WordPress site as an external application using the [detailed instructions on authorization external applications are.](https://www.google.com/url?q=https://gethelp.wildapricot.com/en/articles/199-integrating-with-wordpress%23authorizing&sa=D&source=editors&ust=1615306111122000&usg=AOvVaw2021mFF2bb930o6DAXmylq)

In the Wild Apricot web administration settings, view authorized applications as shown in Figure 1.

![image7](https://user-images.githubusercontent.com/458134/110492511-4125bb00-80c0-11eb-9d74-2c89befc392e.png)


Figure 1. Click Settings  (1) in the administration menu to display a settings menu. Click Authorized (2) applications  to display the list of authorized applications.

Start the authorization process as shown in Figure 2.

![image15](https://user-images.githubusercontent.com/458134/110492565-4e42aa00-80c0-11eb-8ccb-4566f3c19fa6.png)


Figure 2. Click Authorized application  (1) to begin the authorization process.

Select the Server application  type as shown in Figure 3.

![image12](https://user-images.githubusercontent.com/458134/110492648-6286a700-80c0-11eb-82af-f28bd0d684bf.png)


Figure 3. Select WordPress  (1),Click Continue  (2) to advance to the next form.

Fill in the application details form as shown in Figure 4. Copy the API key for the plugin configuration. Save the new authorized application.

![image5](https://user-images.githubusercontent.com/458134/110492722-73cfb380-80c0-11eb-89ed-5c41d6468479.png)


Figure 4. Enter an application name (1). Copy the API key (2). Select WordPress access  to allow the WAWP plugin to connect to Wild Apricot. (3) and (4) Copy the Client ID and Client Secret keys.

***

Continue the configuration in Figure 5 to allow WordPress logins via the single sign on (SSO) service:

![image9](https://user-images.githubusercontent.com/458134/110493016-88ac4700-80c0-11eb-8be8-844fb80542ec.png)


Figure 5. Check the Authorize user via Wild Apricot SSO service (1).Identify the organization name to be shown on the SSO screen for user signing  (2). If you'd like to include some introductory text, enter it in this text box (3) and (4) add the formal fully qualified domain names of your WordPress website(s) that will allow SSO.  Click Save  (5) to save the application authorization.

### Add API keys into WAWP Plugin

Once you have created an API key, Client ID and Client secret, copy and paste these strings into the configuration screen in your WAWP configuration.

![image17](https://user-images.githubusercontent.com/458134/110493303-9b268080-80c0-11eb-9628-d0cb5bd43a9f.png)


The WordPress administrators can now manage access to pages and posts based on Wild Apricot membership level and membership group.

**Important Note: If your WordPress site shares an email address with your Wild Apricot site, you MUST change the email address of the existing WordPress user. You can do this in the Users menu in the WordPress dashboard. You can login with your Wild Apricot email, and then you can elevate that user to an administrator in WordPress if required.**

Having the same email will cause "an unknown error has occurred" to display when trying to login on the website.

## Updating functions.php

Modify `functions.php` by adding these lines near the end. It is recommended that this is done within a child theme to ensure that the code is preserved if a theme is updated.

```
function get_user_role() {
    global $current_user;
    $user_roles = $current_user->roles;
    $user_role = array_shift($user_roles);
    return $user_role;
    }

//Add role in body class
add_filter('body_class','my_class_names');
function my_class_names($classes) {
    $classes[] = get_user_role();
    return $classes;
    }

//Hide admin bar for all users except administrators
add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar() {
if (!current_user_can('administrator') && !is_admin()) {
       show_admin_bar(false);
       }
  }
```

***

## WAWP Global Access Settings

### Setting Membership Status Restrictions

To set which membership status can access pages and posts, use the Global Access menu under the WAWP dashboard icon.

![image18](https://user-images.githubusercontent.com/458134/110493489-a7aad900-80c0-11eb-8f17-90701491afeb.png)


Set the membership statuses that will be allowed to view restricted posts or pages.

![image10](https://user-images.githubusercontent.com/458134/110493595-c4471100-80c0-11eb-879c-598b7c9db7a4.png)


### Set Global Restriction Message

You can show a default restricted message to visitors who are trying to access pages which they do not have access to. This message will be displayed to logged in members who do not have access to a restricted page.

![image11](https://user-images.githubusercontent.com/458134/110493644-cf9a3c80-80c0-11eb-8210-26380f967b83.png)

Be sure to include the shortcode below to the bottom of your message:

```
[wa_login login_label="Login/Reset Password" logout_label="Logout" redirect_page="/membership/member-hub/"]
```


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

## Website Menu Management

### Showing Member-only menus

To turn on the CSS Classes box, go to Screen option at the top of the page and check the CSS classes checkbox:

![image13](https://user-images.githubusercontent.com/458134/110493874-0708e900-80c1-11eb-904d-e92e5f844725.png)


With the CSS Classes administrators can control which menus are displayed for members by adding the class: wawp-menu-hide  to each menu's CSS class.

![image19](https://user-images.githubusercontent.com/458134/110493939-16883200-80c1-11eb-80e1-4f708e7b1397.png)


## Membership Level Sync

The membership levels that have been added, modified or deleted will be synced into WordPress automatically. During each member login, the membership meta data (eg status and membership level) will be updated.

## Embedding Content from Wild Apricot into WordPress

The page "Membership Profile" (/member-profile/) contains a Wild Apricot "widget" that is inserted from Wild Apricot using "widget" code. [Detailed documentation on widgets available to be embedded is available on the Wild Apricot help website.](https://gethelp.wildapricot.com/en/articles/222)

Edit the member-profile page to reveal the member profile "widget" embed code. Using this HTML you can resize the width and height of this code. Any changes made in the Wild Apricot database will be automatically reflected on this page and all other Wild Apricot widgets embedded into a WordPress page or post.

![image2](https://user-images.githubusercontent.com/458134/110494055-391a4b00-80c1-11eb-9e31-9994ff624be7.png)

The code is displayed below so that you may copy and paste it into your site. Please note that the `src` values are specific to your Wild Apricot website. The code below is for the `https://members-digitalnovascotia.wildapricot.org` website. If this is not the URL of your Wild Apricot website, please replace `https://members-digitalnovascotia.wildapricot.org` in both `src` tags with the URL of your Wild Apricot website. For example, if your Wild Apricot website is `https://kendra76548.wildapricot.org/`, then the first `src` tag would become `https://kendra76548.wildapricot.org/widget/Sys/profile` and the second `src` tag would be `https://kendra76548.wildapricot.org/Common/EnableCookies.js`.
```
<!-- wp:html -->
<p><iframe src="https://members-digitalnovascotia.wildapricot.org/widget/Sys/profile" width="1250px" height="600px" frameborder="no">
</iframe></p>
<p><script type="text/javascript" language="javascript" src="https://members-digitalnovascotia.wildapricot.org/Common/EnableCookies.js">
</script></p>
<!-- /wp:html -->
```

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

## Accessing Your Profile

Click Your Profile button to view your Wild Apricot Profile. You will have full access to your profile from this page including editing functions.

# WAWP - Add On
Wild Apricot for WordPress - Custom Directory Plugin

This plugin makes it easy to integrate Wild Apricot member directories into a WordPress site.

# Version Control
- v0.10.6 - Fix search bar bug when using php 7.4
- v0.10.5 - initial version
