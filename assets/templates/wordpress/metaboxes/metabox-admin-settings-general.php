<?php
/**
 * Settings Page General Settings template.
 *
 * Handles markup for the Settings Page General Settings meta box.
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

	<?php if ( $profiles != '' ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_profile"><?php esc_html_e( 'Default CiviCRM Event Registration Profile', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_profile" name="civi_eo_event_default_profile">
					<?php echo $profiles; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Event Registration Pages require a Profile in order to display correctly.', 'civicrm-event-organiser' ); ?></p>
				<?php if ( $profile_required ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please select a default Profile for Event Registration Pages.', 'civicrm-event-organiser' ); ?></p></div>
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>

	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Default CiviCRM Event Registration Confirmation Screen Setting', 'civicrm-event-organiser' ); ?></th>
		<td>
			<input type="checkbox" id="civi_eo_event_default_confirm" name="civi_eo_event_default_confirm" value="1"<?php echo $confirm_checked; ?>>
			<label for="civi_eo_event_default_confirm"><?php esc_html_e( 'Use a Registration Confirmation Screen for free Events.', 'civicrm-event-organiser' ); ?></label>
			<?php if ( $confirm_required ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please choose the default setting for Registration Confirmation Screens.', 'civicrm-event-organiser' ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>

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
