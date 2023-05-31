# WildApricot Press Integrator's Guide

#### *By Alex Sirota*

##### *Edited May 2023*


## Introduction

WildApricot Press can synchronize all contact, membership custom fields, and system fields into the WordPress user database. To set which fields are copied into WordPress during successful login, use the Synchronization Options tab in WildApricot Setings.

![Screenshot - Sync Options Screen](https://user-images.githubusercontent.com/458134/131911860-869f9ca0-a11e-483a-8021-8388baf7660c.png "Screenshot - Sync Options Screen")

## Viewing the user meta data structure

WildApricot Press writes these fields into the WordPress user database. The best way to view this data for your programming requirements is to login with a WildApricot user into your site and run the following code. This code can be added to your themes functions.php file and it will display above your website:

```php
// debugging code to display the logged in users user_meta
if (!is_admin())
	
	{

highlight_string("<?php\n\$data =\n" . var_export(get_user_meta( get_current_user_id()), true) . ";\n?>");
	}
```

Here's an example of the type of data stored in the WordPress user meta that is sync'd from WildApricot:

```
  'wawp_membership_level_id_key' => 
  array (
    0 => '860748',
  ),
  'wawp_membership_level_key' => 
  array (
    0 => 'Administrative Member',
  )
```

The key `wawp_membership_level_id_key` and `wawp_membership_level_key` store the WildApricot Membership Level ID and Membership Level name for a member.


```
  'wawp_user_status_key' => 
  array (
    0 => 'Active',
  ),
  'wawp_organization_key' => 
  array (
    0 => '',
  ),
  'wawp_wa_user_id' => 
  array (
    0 => '69177778',
  )
```

These 3 keys store the Membership Status, Organization and WildApricot unique User ID.

```
'wawp_Organization' => 
  array (
    0 => '',
  ),
  'wawp_FirstName' => 
  array (
    0 => 'Test',
  ),
  'wawp_LastName' => 
  array (
    0 => 'Account',
  ),
  'wawp_Email' => 
  array (
    0 => 'alexs+mpg@newpathconsulting.com',
  )
  ```
These keys store some of your profile information.

```
  'wawp_custom-8819428' => 
  array (
    0 => '888 123 1234',
  ),
  'wawp_custom-8819423' => 
  array (
    0 => 'Toronto',
  ),
  'wawp_custom-12970811' => 
  array (
    0 => 'a:2:{s:2:"Id";i:14757951;s:5:"Label";s:7:"Ontario";}',
  ),
  'wawp_custom-8819425' => 
  array (
    0 => 'M2N7E9',
  ),
  'wawp_list_of_groups_key' => 
  array (
    0 => 's:63:"a:2:{i:682566;s:9:"Exec Team";i:728084;s:15:"Admin Test Team";}";',
  )
```

The keys above are custom fields and can store information from a custom field. In order to match which fields these are you should populate your profile with an account and see what data is retrieved with the debugging code above. Note that some of the fields contain serialized data (`wawp_list_of_groups_key` and `wawp_custom-12970811`). You will need to unserialize this data with `maybe_unserialize()` call to retrieve the values out of the data.

IMPORTANT: The custom key names are unique to your particular WildApricot site, so yours will definitely be different and you'll need to retrieve them using the debugging code above.

## Integrating using Filters

The below code sample integrates Simply Scheduled Appointments customer information fields with WildApricot Press data. It can be added to the theme's `functions.php`. This code below assumes the SSA fields are named Name, Cell Phone and Home Phone. Note that the key for the WildApricot Press user meta is added as the second argument to the `get_user_meta()` call.

```php
add_filter(
	'ssa/appointments/customer_information/get_defaults',
	'ssa_customize_customer_information_defaults',
	1,
	1
);

function ssa_customize_customer_information_defaults( $defaults ) {
	
	if  (!is_user_logged_in()) {
	//do not prepopulate with data because nobody is logged in

    return $defaults;
	} 
    else
    {
		
	
// populate Cell Phone and Home Phone, prepend +1 because the SSA phone number fields needs this to set correct country
	$defaults = array_merge( $defaults, array(
	'Mobile Phone' => '+1' . get_user_meta( get_current_user_id(), 'wawp_custom-8819428', true )));

	$defaults = array_merge( $defaults, array(
	'Home Phone' => '+1' . get_user_meta( get_current_user_id(), 'wawp_Phone', true )));
	return $defaults;
	}
}
```


### Here are examples of system fields:

```
  'wawp_IsArchived' => 
  array (
    0 => '',
  ),
  'wawp_IsDonor' => 
  array (
    0 => '',
  ),
  'wawp_IsEventAttendee' => 
  array (
    0 => '',
  ),
  'wawp_IsMember' => 
  array (
    0 => '1',
  ),
  'wawp_IsSuspendedMember' => 
  array (
    0 => '',
  ),
  'wawp_ReceiveEventReminders' => 
  array (
    0 => '1',
  ),
  'wawp_ReceiveNewsletters' => 
  array (
    0 => '1',
  ),
  'wawp_EmailDisabled' => 
  array (
    0 => '',
  ),
  'wawp_EmailingDisabledAutomatically' => 
  array (
    0 => '',
  ),
  'wawp_RecievingEMailsDisabled' => 
  array (
    0 => '',
  ),
  'wawp_Balance' => 
  array (
    0 => '0',
  ),
  'wawp_TotalDonated' => 
  array (
    0 => '0',
  ),
  'wawp_RegistredForEvent' => 
  array (
    0 => NULL,
  ),
  'wawp_LastUpdated' => 
  array (
    0 => '2023-05-25T13:22:21.83-04:00',
  ),
  'wawp_LastUpdatedBy' => 
  array (
    0 => '69215811',
  ),
  'wawp_CreationDate' => 
  array (
    0 => '2023-05-01T10:58:40-04:00',
  ),
  'wawp_LastLoginDate' => 
  array (
    0 => '2023-05-25T13:24:07-04:00',
  ),
  'wawp_AdminRole' => 
  array (
    0 => 'a:1:{i:0;a:2:{s:2:"Id";i:256;s:5:"Label";s:35:"Account administrator (Full access)";}}',
  ),
  'wawp_Notes' => 
  array (
    0 => 'Membership approved on 1 May 2023 by Alex Sirota

25 May 2023: The Renewal date was changed by Sirota, Alex',
  ),
  'wawp_SystemRulesAndTermsAccepted' => 
  array (
    0 => '1',
  ),
  'wawp_SubscriptionSource' => 
  array (
    0 => 'a:0:{}',
  ),  
  'wawp_MemberSince' => 
  array (
    0 => '2023-05-01T00:00:00-04:00',
  ),
  'wawp_RenewalDue' => 
  array (
    0 => '2024-05-01T00:00:00',
  ),
  'wawp_MembershipLevelId' => 
  array (
    0 => '860748',
  ),
  'wawp_AccessToProfileByOthers' => 
  array (
    0 => '1',
  ),
  'wawp_RenewalDateLastChanged' => 
  array (
    0 => NULL,
  ),
  'wawp_LevelLastChanged' => 
  array (
    0 => '2023-05-01T10:58:40-04:00',
  ),
  'wawp_BundleId' => 
  array (
    0 => NULL,
  ),
  'wawp_MembershipEnabled' => 
  array (
    0 => '1',
  )
  ```