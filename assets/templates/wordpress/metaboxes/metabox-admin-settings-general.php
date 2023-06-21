<?php
/**
 * Settings Page "General Settings" template.
 *
 * Handles markup for the Settings Page "General Settings" meta box.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

?><!-- assets/templates/wordpress/metaboxes/metabox-admin-settings-general.php -->
<p><?php esc_html_e( 'The following options configure some CiviCRM and Event Organiser defaults.', 'civicrm-event-organiser' ); ?></p>

<?php

/**
 * Before Settings table.
 *
 * @since 0.3.1
 */
do_action( 'civicrm_event_organiser_before_settings_table' );

?>

<table class="form-table">

	<?php

	/**
	 * Start of Settings table rows.
	 *
	 * @since 0.3.2
	 */
	do_action( 'civicrm_event_organiser_settings_table_first_row' );

	?>

	<?php if ( $types != '' ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_type"><?php esc_html_e( 'Default CiviCRM Event Type', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_type" name="civi_eo_event_default_type">
					<?php echo $types; ?>
				</select>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( $roles != '' ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_role"><?php esc_html_e( 'Default CiviCRM Participant Role for Events', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_role" name="civi_eo_event_default_role">
					<?php echo $roles; ?>
				</select>
				<p class="description"><?php esc_html_e( 'This will be the Participant Role that is set for Event Registrations when there is a Registration Profile that does not contain a Participant Role selector.', 'civicrm-event-organiser' ); ?></p>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( $civicrm_event_sync != '' ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_civievent_sync"><?php esc_html_e( 'Syncing CiviCRM Events to Event Organiser', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_civievent_sync" name="civi_eo_event_default_civievent_sync">
					<?php echo $civicrm_event_sync; ?>
				</select>
				<p class="description"><?php esc_html_e( 'By default, all Events in CiviCRM will sync to Event Organiser. If you do not want all CiviCRM Events to sync, then you can enable a checkbox on each CiviCRM Event that must be cheecked in order to sync data to Event Organiser.', 'civicrm-event-organiser' ); ?></p>
				<?php if ( $civicrm_event_sync_required ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please select a default for syncing CiviCRM Events to Event Organiser.', 'civicrm-event-organiser' ); ?></p></div>
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( $status_sync != '' ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_status_sync"><?php esc_html_e( 'Map CiviCRM Event Status and EO Event Status', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_status_sync" name="civi_eo_event_default_status_sync">
					<?php echo $status_sync; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Choose how the CiviCRM Event "Is Public" and "Is Active" settings sync with the Event Organiser "Status" and "Visibility" settings.', 'civicrm-event-organiser' ); ?></p>
				<?php if ( $status_sync_required ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please select a default for Event Status sync.', 'civicrm-event-organiser' ); ?></p></div>
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>

	<?php

	/**
	 * End of Settings table rows.
	 *
	 * @since 0.3.2
	 */
	do_action( 'civicrm_event_organiser_settings_table_last_row' );

	?>

</table>

<?php

/**
 * After Settings table.
 *
 * @since 0.3.1
 */
do_action( 'civicrm_event_organiser_after_settings_table' );
