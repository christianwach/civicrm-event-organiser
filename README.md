CiviCRM Event Organiser
=======================

A *WordPress* plugin for syncing *Event Organiser* plugin Events with *CiviCRM* Events so they (will eventually) play nicely with *BuddyPress* Groups and Group Hierarchies. Be warned: this plugin is at a very early stage of development.

#### Notes ####

This plugin has been developed using *WordPress 3.6*, *BuddyPress 1.8* and *CiviCRM 4.3.5*. 

It requires 

* the master branch of the [CiviCRM WordPress plugin](https://github.com/civicrm/civicrm-wordpress) 
* the custom WordPress.php hook file from the [CiviCRM Hook Tester repo on GitHub](https://github.com/christianwach/civicrm-wp-hook-tester) installed so that it overrides the built-in *CiviCRM* hook file. 

It also requires 

* [Event Organiser](http://wordpress.org/plugins/event-organiser/) version 2.0.2 or greater
* [WooDojo](http://www.woothemes.com/woodojo/) HTML Category Descriptions so that HTML descriptions sync
* [Radio Buttons for Taxonomies](http://wordpress.org/plugins/radio-buttons-for-taxonomies/) to ensure only one event type is selected


#### Installation ####

There are two ways to install from GitHub:

###### ZIP Download ######

If you have downloaded *CiviCRM Event Organiser* as a ZIP file from the GitHub repository, do the following to install and activate the plugin and theme:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/civicrm-event-organiser`
2. Activate the plugin
3. You are done!
4. Not so fast… 

**This plugin is still in development and probably won't work for you** 

**You have been warned.**


###### git clone ######

If you have cloned the code from GitHub, it is assumed that you know what you're doing.
