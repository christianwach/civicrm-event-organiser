=== CiviCRM Event Organiser ===
Contributors: needle
Donate link: https://www.paypal.me/interactivist
Tags: civicrm, event organiser, events, sync
Requires at least: 4.9
Tested up to: 5.5
Stable tag: 0.5.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Keep Event Organiser plugin Events in sync with CiviCRM Events.



== Description ==

A WordPress plugin for syncing Event Organiser Events, Venues and Event Categories to their corresponding entities in CiviCRM.

### Requirements

This plugin requires at least WordPress 4.9 and CiviCRM 4.7.

It also requires:

* [Event Organiser](http://wordpress.org/plugins/event-organiser/) version 3.0 or greater
* [Radio Buttons for Taxonomies](http://wordpress.org/plugins/radio-buttons-for-taxonomies/) to ensure only one event type is selected

Be aware that this plugin is in active development. Test often, test thoroughly and open an issue if you find a problem.

#### CiviCRM ACF Integration

This plugin is compatible with [CiviCRM ACF Integration](https://github.com/christianwach/civicrm-acf-integration) which enables integration of Custom Fields on CiviCRM Events with ACF Fields attached to the Event Organiser "Event" Post Type.

*Important note:* Please make sure you have *CiviCRM ACF Integration* version 0.7 or greater.

### Known Issues

There is currently no proper integration with CiviCRM's implementation of repeating events in version 4.7.n because, at present, CiviCRM does not save (or expose) the schedule that generates the sequence. To get around this limitation, this plugin prioritises a workflow based on creating events in Event Organiser and then (optionally, via the "CiviCRM Settings" metabox on the event's edit page) passing the data over to CiviCRM when requested.

The plugin implements automatic linking to an event's online registration page(s) via the [`eventorganiser_additional_event_meta`](https://github.com/boonebgorges/Event-Organiser/commit/1c94d707741b12d5a8731fc39507aa80af805c4a) hook which has been available since Event Organiser 2.12.5. If you have overridden the *Event Organiser* template(s) you may have to apply the function to the appropriate hook in your template(s) yourself. See the documentation for the function `civicrm_event_organiser_register_links()` for details. Thanks to [Consilience Media](https://github.com/consilience/) for providing the resources to push this forward.

### Apple Calendar compatibility

There is an issue with [Apple Calendar's display of Event Organiser iCal feeds](https://github.com/stephenharris/Event-Organiser/issues/356) which means that Apple Calendar requires special handling. To solve this, you can install the [Event Organiser ICS Feed for Apple Calendar](https://github.com/christianwach/event-organiser-apple-cal) plugin and use its shortcode instead of the one supplied by Event Organiser.



== Installation ==

1. Extract the plugin archive
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress


== Changelog ==

See https://github.com/christianwach/civicrm-event-organiser/commits/master
