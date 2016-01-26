=== CiviCRM Event Organiser ===
Contributors: needle
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=PZSKM8T5ZP3SC
Tags: civicrm, event organiser, events, sync
Requires at least: 3.6
Tested up to: 4.4
Stable tag: 0.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Keep Event Organiser plugin Events in sync with CiviCRM Events.


== Description ==

A WordPress plugin for syncing Event Organiser Events, Venues and Event Categories to their corresponding entities in CiviCRM. It also plays nicely with BuddyPress Groups and Group Hierarchies.

Be warned: this plugin is still at an early stage of development.

This plugin requires at least WordPress 3.6, BuddyPress 1.8 and CiviCRM 4.4.

It requires:

* Event Organiser version 2.0.2 or greater
* Radio Buttons for Taxonomies to ensure only one event type is selected

If you are using a version of CiviCRM prior to 4.6, it also requires:

* the master branch of the CiviCRM WordPress plugin
* the custom WordPress.php hook file from the CiviCRM Hook Tester repo on GitHub installed so that it overrides the built-in CiviCRM hook file.



== Installation ==

1. Extract the plugin archive
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress


== Changelog ==

See https://github.com/christianwach/civicrm-event-organiser/commits/master
