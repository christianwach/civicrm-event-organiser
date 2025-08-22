# CiviCRM Event Organiser

**Contributors:** [needle](https://profiles.wordpress.org/needle/)<br/>
**Donate link:** https://www.paypal.me/interactivist<br/>
**Tags:** civicrm, event organiser, events, sync<br/>
**Requires at least:** 4.9<br/>
**Tested up to:** 6.7<br/>
**Stable tag:** 0.8.3a<br/>
**License:** GPLv2 or later<br/>
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Keep Event Organiser plugin Events in sync with CiviCRM Events.

---

## Description

A *WordPress* plugin for syncing *Event Organiser* plugin Events with *CiviCRM* Events. The plugin syncs *Event Organiser* Events, Venues and Event Categories to their corresponding entities in CiviCRM.

*Important note:* Please do not use with *CiviCRM 5.47*. Your Events in CiviCRM will not respect Daylight Savings offsets.

### ACF Integration

This plugin is compatible with [CiviCRM Profile Sync](https://wordpress.org/plugins/civicrm-wp-profile-sync/) which enables integration of Custom Fields on CiviCRM Events with ACF Fields attached to the Event Organiser "Event" Post Type.

*Important note:* Please make sure you have *CiviCRM Profile Sync* version 0.5 or greater.

#### CiviCRM Event ID Field

*CiviCRM Event Organiser* supplies a custom ACF Field called "CiviCRM Event ID" which can be used for Event Organiser Events that have a one-to-one correspondence with CiviCRM Events. The field *will not work* as expected for synced recurring Events.

This ACF Field is useful if, for example, you want to embed an ACF Extended form in an Event Organiser Event template - because the form can access the ID of the synced CiviCRM Event and target it for various operations. Use the syntax `{get_field:your_civicrm_event_id_field}` to access the CiviCRM Event ID.

### Apple Calendar compatibility

There is an issue with [Apple Calendar's display of Event Organiser iCal feeds](https://github.com/stephenharris/Event-Organiser/issues/356) which means that Apple Calendar requires special handling. To solve this, you can install the [Event Organiser ICS Feed for Apple Calendar](https://github.com/christianwach/event-organiser-apple-cal) plugin and use its shortcode instead of the one supplied by Event Organiser.

---

## Requirements

This plugin recommends a minimum of *WordPress 4.9* and *CiviCRM 5.75* (the latest ESR).

It also requires:

* [Event Organiser](https://wordpress.org/plugins/event-organiser/) version 3.0 or greater
* [Radio Buttons for Taxonomies](https://wordpress.org/plugins/radio-buttons-for-taxonomies/) to ensure only one event type is selected

Be aware that this plugin is in active development. Test often, test thoroughly and open an issue if you find a problem.

### Locations fixes

If you are using a version of CiviCRM lower than *CiviCRM 5.49.0* then you should apply [this patch](https://github.com/civicrm/civicrm-core/pull/23041) to get Event Locations to work as expected.

---

## Installation

Note: If installing on WordPress multisite, do not network-activate *CiviCRM Event Organiser*. Only activate it on the sites that *Event Organiser* is activated - even if *Event Organiser* is network-activated.

There are two ways to install from GitHub:

#### ZIP Download

If you have downloaded *CiviCRM Event Organiser* as a ZIP file from the GitHub repository, do the following to install and activate the plugin:

1. Unzip the .zip file and rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/civicrm-event-organiser`
2. Activate the plugin
3. Go to the plugin's admin page and follow the instructions
4. You are done!

#### git clone

If you have cloned the code from GitHub, it is assumed that you know what you're doing.

---

## Using CiviCRM Event Organiser

Now that you've installed the plugin, you'll want to find out:

* How to [configure the CiviCRM Event Organiser settings](/docs/settings.md).
* How to [add synced Events to your website](/docs/events.md).

### Shortcode

CiviCRM Event Organiser provides a `[ceo_register_link]` Shortcode through which you can render the Register button in your page wherever you want.

The attributes that the Shortcode accepts are:

* `event_id` which defaults to the currently displayed Event.
* `wrap` which defaults to no wrapper element - but can be "div" or "button".
* `wrap_class` which defaults to no classes on the wrapper element.
* `anchor_class` which defaults to no classes on the anchor element.
* `title` which defaults to "Register" for single Events.

### Known Issues

There is currently no proper integration with *CiviCRM's* implementation of repeating events because, at present, *CiviCRM* does not save (or expose) the schedule that generates the sequence. To get around this limitation, this plugin prioritises a workflow based on creating events in *Event Organiser* and then (optionally, via the "CiviCRM Settings" metabox on the event's edit page) passing the data over to *CiviCRM* when requested.

The plugin implements automatic linking to an event's online registration page(s) via the [`eventorganiser_additional_event_meta`](https://github.com/boonebgorges/Event-Organiser/commit/1c94d707741b12d5a8731fc39507aa80af805c4a) hook which has been available since *Event Organiser* 2.12.5. If you have overridden the *Event Organiser* template(s) you may have to apply the function to the appropriate hook in your template(s) yourself. See the documentation for the function `civicrm_event_organiser_register_links()` for details. Thanks to [Consilience Media](https://github.com/consilience/) for providing the resources to push this forward.
