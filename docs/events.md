## Events integration

When adding a New Event in Event Organiser there are now some CiviCRM Settings that can be used if event will use CiviCRM integration for Registration.  When adding a New Event there are fewer settings visible.

![CiviCRM Event Organiser Event Specific Settings](./images/ceo-event-specific-settings.jpg)

Once your event details are in place, if this event will also be in CiviCRM, it is required to Select an Event Category for the CiviCRM Sync option to be enabled and event published. Then click on the **Sync this event with CiviCRM** option for the event to be created in CiviCRM.

If you will also be enabling Registration, select the **Enable Online Registration** option and select the Profile and Participant Role. The default [settings](./settings.md) will be used if nothing is changed.

![CiviCRM Event Organiser Event Specific Settings for Syncing](./images/ceo-event-specific-settings-sync.jpg)

Once you've enabled registration when viewing an event on the website, there will be a Register link that brings visitors to the CiviCRM Event Registration page for that event.

![CiviCRM Event Organiser Register Link](./images/ceo-event-register-link.jpg)

### Quick Config Price Set

#### Setup

With ACF Pro and CiviCRM Profile Sync active, you can add a "CiviCRM Event: Quick Config Price Set" Field to manage registration fees from the WordPress Event editor.

1. Go to **ACF > Field Groups** and create a **Event Fees** a Field Group.
2. Set its Location Rule to **Post Type is equal to Event**.
3. Add a Field with the type **CiviCRM Event: Quick Config Price Set**. Choose a Field Label and Field Name, for example "Fee Options" and `fee_options`.
4. Select the **CiviCRM Currency**, **CiviCRM Financial Type** (typically Event Fees) and **CiviCRM Payment Processor**. **CiviCRM Pay Later** if you want to allow offline payments.
5. Save the Field Group.
6. Edit an Event, enter the fee labels and amounts, and save with **Sync this event with CiviCRM** selected.

#### Display

To display the fees, add `[ceo_register_fees]` to the Event content. To display another Event's fees, use `[ceo_register_fees event_id="123"]`, where `123` is the WordPress Event Post ID.

Fees are not added to the event automatically; you should customize your theme to output the shortcode where you want the fee list to appear.

### Permissions & Capabilities

For Users to sync Events between Event Organiser and CiviCRM, they must have the `publish_events` capability and the `access CiviEvent` permission.
