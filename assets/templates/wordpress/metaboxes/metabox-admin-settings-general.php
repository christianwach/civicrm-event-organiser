<?php
/**
 * Settings Page "General Settings" template.
 *
 * Handles markup for the Settings Page "General Settings" meta box.
 *
 * @package CiviCRM_Event_Organiser
 */

?><!-- assets/templates/wordpress/metaboxes/metabox-admin-settings-general.php -->
<p><?php esc_html_e( 'The following options configure some CiviCRM and Event Organiser defaults.', 'civicrm-event-organiser' ); ?></p>

<?php

/**
 * Before Settings table.
 *
 * @since 0.3.1
 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/metabox/general/before'} filter instead.
 */
do_action_deprecated( 'civicrm_event_organiser_before_settings_table', [], '0.8.0', 'ceo/admin/settings/metabox/general/before' );

/**
 * Before Settings table.
 *
 * @since 0.8.0
 */
do_action( 'ceo/admin/settings/metabox/general/before' );

?>

<table class="form-table">

	<?php

	/**
	 * Start of Settings table rows.
	 *
	 * @since 0.3.2
	 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/metabox/general/table/first_row'} filter instead.
	 */
	do_action_deprecated( 'civicrm_event_organiser_settings_table_first_row', [], '0.8.0', 'ceo/admin/settings/metabox/general/table/first_row' );

	/**
	 * Start of Settings table rows.
	 *
	 * @since 0.8.0
	 */
	do_action( 'ceo/admin/settings/metabox/general/table/first_row' );

	?>

	<?php if ( ! empty( $types ) ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_type"><?php esc_html_e( 'Default CiviCRM Event Type', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_type" name="civi_eo_event_default_type">
					<?php foreach ( $types as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_type, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach ?>
				</select>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( ! empty( $roles ) ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_role"><?php esc_html_e( 'Default CiviCRM Participant Role for Events', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_role" name="civi_eo_event_default_role">
					<?php foreach ( $roles as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_role, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach ?>
				</select>
				<p class="description"><?php esc_html_e( 'This will be the Participant Role that is set for Event Registrations when there is a Registration Profile that does not contain a Participant Role selector.', 'civicrm-event-organiser' ); ?></p>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( ! empty( $civicrm_event_sync ) ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_civievent_sync"><?php esc_html_e( 'Syncing CiviCRM Events to Event Organiser', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_civievent_sync" name="civi_eo_event_default_civievent_sync">
					<?php foreach ( $civicrm_event_sync as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_civicrm_event_sync, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach ?>
				</select>
				<p class="description"><?php esc_html_e( 'By default, all Events in CiviCRM will sync to Event Organiser. If you do not want all CiviCRM Events to sync, then you can enable a checkbox on each CiviCRM Event that must be cheecked in order to sync data to Event Organiser.', 'civicrm-event-organiser' ); ?></p>
				<?php if ( $civicrm_event_sync_required ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please select a default for syncing CiviCRM Events to Event Organiser.', 'civicrm-event-organiser' ); ?></p></div>
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( ! empty( $eo_event_sync ) ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_eoevent_sync"><?php esc_html_e( 'Syncing Event Organiser Events to CiviCRM', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_eoevent_sync" name="civi_eo_event_default_eoevent_sync">
					<?php foreach ( $eo_event_sync as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_eo_event_sync, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach ?>
				</select>
				<p class="description"><?php esc_html_e( 'The default is that there should always be a "Sync this Event with CiviCRM" checkbox. This allows you to create Events in Event Organiser that are not linked to Events in CiviCRM. When the Event in Event Organiser has a linked Event in CiviCRM, then the checkbox will no longer appear and sync will happen every time the Event is saved.', 'civicrm-event-organiser' ); ?></p>
				<p class="description"><?php esc_html_e( 'If you select the "Sync all Event Organiser Events to CiviCRM" option, then an Event Organiser Event will be synced every time it is saved. The only exception to this is when the Event is in "Draft" status, e.g. when a new Event is created. To sync an Event Organiser Event to CiviCRM without publishing it, you can set its status to "Pending Review".', 'civicrm-event-organiser' ); ?></p>
				<p class="description"><?php esc_html_e( 'However, if you create a repeating Event in Event Organiser, then the checkbox will always be present regardless of this setting. This because significant changes to repeating schedules for Event Organiser Events that are already have corresponding CiviCRM Events can result in unexpected outcomes. It is strongly recommended that you finalize your schedule before syncing the repeating Event to CiviCRM.', 'civicrm-event-organiser' ); ?></p>
				<?php if ( $eo_event_sync_required ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please select a default for syncing Event Organiser Events to CiviCRM.', 'civicrm-event-organiser' ); ?></p></div>
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( ! empty( $status_sync ) ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_status_sync"><?php esc_html_e( 'Map CiviCRM Event Status and EO Event Status', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_status_sync" name="civi_eo_event_default_status_sync">
					<?php foreach ( $status_sync as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_status_sync, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach ?>
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
	 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/metabox/general/table/last_row'} filter instead.
	 */
	do_action_deprecated( 'civicrm_event_organiser_settings_table_last_row', [], '0.8.0', 'ceo/admin/settings/metabox/general/table/last_row' );

	/**
	 * End of Settings table rows.
	 *
	 * @since 0.8.0
	 */
	do_action( 'ceo/admin/settings/metabox/general/table/last_row' );

	?>

</table>

<?php

/**
 * After Settings table.
 *
 * @since 0.3.1
 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/metabox/general/after'} filter instead.
 */
do_action_deprecated( 'civicrm_event_organiser_after_settings_table', [], '0.8.0', 'ceo/admin/settings/metabox/general/after' );

/**
 * After Settings table.
 *
 * @since 0.8.0
 */
do_action( 'ceo/admin/settings/metabox/general/after' );
