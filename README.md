Wild Apricot for WordPress (WAWP) Documentation

Version 1.0 - September 1, 2021

# WordPress Administrators Guide

## Installing and Configuring the WAWP Plugin

On the WordPress admin dashboard, using the left menu, navigate to to Plugins > Add New. Upload the plugin [add link to zip] and activate.

 [Add instructions on Wild Apricot credentials and license key. note that new site requires a new license]

To configuring the WAWP plugin, the Wild Apricot API settings must be set.

### Create an Authorized Application in Wild Apricot

Navigate to WAWP > Authorization, and follow the instructions there to acquire the Wild Apricot credentials.

### Add API keys into WAWP Plugin

Once you have created an API key, Client ID and Client secret, copy and paste these strings into the configuration screen in your WAWP configuration.

[NEW IMAGE]

Below, you can specify which menu(s) you would like to add the login/logout button to be added to by selecting the checkboxes.

<img width="620" alt="Screen Shot 2021-08-16 at 12 40 34 PM" src="https://user-images.githubusercontent.com/8737691/129612544-ff19e86c-5395-4bc4-b82b-a1fa914f4057.png">

Once saved, a login/logout button will appear on these menu(s) automatically on your WordPress site. The screenshot below illustrates an example of the "Log Out" button being added to the main menu of the website. In this case, the "Log Out" button can be seen in the red box in the top right corner.

<img width="1427" alt="Screen Shot 2021-08-16 at 2 37 56 PM" src="https://user-images.githubusercontent.com/8737691/129614718-eb525e0e-026c-4223-9058-64f3ff651bde.png">

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

<img width="1241" alt="Screen Shot 2021-08-16 at 1 05 20 PM" src="https://user-images.githubusercontent.com/8737691/129612116-5666ef23-8c5c-4ead-b60a-9e26b78a8e5c.png">

## Per Page and Post Settings

### Setting a custom page/post restricted message

Each page and post has a restricted message in a box called "Individual Restriction Message". This box appears under the main content and can float down the page depending on what page builder is in use, if any. Modify as desired.

<img width="1127" alt="Screen Shot 2021-08-16 at 1 16 20 PM" src="https://user-images.githubusercontent.com/8737691/129611817-cd5c0503-3dad-49d2-938a-d1bab977f082.png">

IMPORTANT: To save the custom restricted message, make sure to save or publish the page or post.

### Page or Post Access Control

On every page, you can select which membership levels and groups can view the content of the page. Access control is set by the box on the right side of the page or post's "Edit" screen, as seen in the screenshot below.

<img width="583" alt="Screen Shot 2021-08-16 at 1 36 31 PM" src="https://user-images.githubusercontent.com/8737691/129618750-3ed1f127-f084-452a-b9a4-296718424062.png">

You can select one or more membership levels to restrict which levels have access to the post. For each membership level that you check off, the Wild Apricot members who have this membership level will be able to access the post once it is saved. 

Likewise, you can also set access to one or more membership groups. You can select zero or more membership groups, which will allow members in those Wild Apricot membership groups to access the page. Selecting a group will allow all users of that group to view the page, even if their membership level was not explicitly checked.

The levels and groups are set inclusively -- that means that if a member is in one of the configured levels OR they are in a configured membership group then they can see the page. If they don't fit one of the criteria, they will not be able to see the page. 

Finally, if you do not select any boxes, then the post is not restricted, and can be seen by all users, both logged-in and logged-out of Wild Apricot.

***

## Memberships and User Data Refresh

The membership levels that have been added, modified or deleted will be synced into WordPress from Wild Apricot automatically on user login and every 24 hours. During each member login, the membership meta data (e.g. status and membership level) will be updated from Wild Apricot. So, after syncing your WordPress site with the WAWP plugin, any updates you make on Wild Apricot will be automatically reflected on your WordPress site as well within 24 hours.

On each user login and daily user refresh, several Wild Apricot member fields are synced to the user's WordPress profile. You can view these fields by viewing the metadata added to the user's WordPress profile under "Wild Apricot Membership Details". The default Wild Apricot fields can be viewed in the screenshot below. 

PS: Can you guess who this member might be? :) 

![Screen Shot 2021-08-16 at 2 16 45 PM](https://user-images.githubusercontent.com/8737691/129620414-f7f3042a-1063-4bbf-b0b6-a3c47084980a.png)

[More]

## Data Synchronization

You can extend the default Wild Apricot fields beyond the five fields shown above through the "Synchronization" tab under "WAWP Settings". See the screenshot below for an illustration.

<img width="802" alt="Screen Shot 2021-08-16 at 1 52 31 PM" src="https://user-images.githubusercontent.com/8737691/129623578-5e025faa-5064-47bc-a731-2e4dcdad4c14.png">

For each checkbox that you check off, the membership field will be synced to each Wild Apricot user on the WordPress site. The screenshot below shows some of the extra fields being checked off and thus imported into each user in WordPress:

![Screen Shot 2021-08-16 at 4 28 36 PM](https://user-images.githubusercontent.com/8737691/129625564-fabce129-a64d-497b-99bd-b5e1230778cb.png)

Now, the extra fields can be seen in each user's WordPress profile.

![Screen Shot 2021-08-16 at 2 19 45 PM (1)](https://user-images.githubusercontent.com/8737691/129625837-ca418263-a0d2-4bf9-b397-5daa055935f8.png)

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

