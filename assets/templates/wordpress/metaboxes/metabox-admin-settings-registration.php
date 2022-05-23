<?php
/**
 * Settings Page "Online Registration Settings" template.
 *
 * Handles markup for the Settings Page "Online Registration Settings" meta box.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7.2
 */

?><!-- assets/templates/wordpress/metaboxes/metabox-admin-settings-registration.php -->
<p><?php esc_html_e( 'The following options configure some CiviCRM Online Registration defaults.', 'civicrm-event-organiser' ); ?></p>

<?php

/**
 * Before Settings table.
 *
 * @since 0.7.2
 */
do_action( 'civicrm_event_organiser_before_registration_table' );

?>

<table class="form-table">

	<?php

	/**
	 * Start of Settings table rows.
	 *
	 * @since 0.7.2
	 */
	do_action( 'civicrm_event_organiser_registration_table_first_row' );

	?>

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
	 * @since 0.7.2
	 */
	do_action( 'civicrm_event_organiser_registration_table_last_row' );

	?>

</table>

<hr>

<table class="form-table">

	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Default CiviCRM Event Confirmation Email Setting', 'civicrm-event-organiser' ); ?></th>
		<td>
			<input type="checkbox" id="civi_eo_event_default_send_email" name="civi_eo_event_default_send_email" value="1"<?php echo $send_email_checked; ?>>
			<label for="civi_eo_event_default_send_email"><?php esc_html_e( 'Send a Confirmation Email.', 'civicrm-event-organiser' ); ?></label>
			<p class="description"><?php esc_html_e( 'The Confirmation Email includes event date(s), location and contact information. For Paid Events, the Confirmation Email is also a receipt for payment.', 'civicrm-event-organiser' ); ?></p>
			<?php if ( $send_email_required ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please choose the default setting for sending a Confirmation Email.', 'civicrm-event-organiser' ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>

	<tr valign="top">
		<th scope="row"><label for="civi_eo_event_default_send_email_from_name"><?php esc_html_e( 'Default CiviCRM Event Confirmation Email "From Name"', 'civicrm-event-organiser' ); ?></label></th>
		<td>
			<input type="text" class="widefat" id="civi_eo_event_default_send_email_from_name" name="civi_eo_event_default_send_email_from_name" value="<?php echo esc_attr( $send_email_from_name ); ?>">
			<p class="description"><?php esc_html_e( 'The name to send the Confirmation Email from.', 'civicrm-event-organiser' ); ?></p>
			<?php if ( $send_email_from_name_required ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please add a default "From Name" for the Confirmation Email.', 'civicrm-event-organiser' ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>

	<tr valign="top">
		<th scope="row"><label for="civi_eo_event_default_send_email_from"><?php esc_html_e( 'Default CiviCRM Event Confirmation Email "From Email"', 'civicrm-event-organiser' ); ?></label></th>
		<td>
			<input type="text" class="widefat" id="civi_eo_event_default_send_email_from" name="civi_eo_event_default_send_email_from" value="<?php echo esc_attr( $send_email_from ); ?>">
			<p class="description"><?php esc_html_e( 'The Email Address to send the Confirmation Email from.', 'civicrm-event-organiser' ); ?></p>
			<?php if ( $send_email_from_required ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please add a default Email Address for sending a Confirmation Email from.', 'civicrm-event-organiser' ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>

</table>

<?php

/**
 * After Settings table.
 *
 * @since 0.7.2
 */
do_action( 'civicrm_event_organiser_after_registration_table' );
