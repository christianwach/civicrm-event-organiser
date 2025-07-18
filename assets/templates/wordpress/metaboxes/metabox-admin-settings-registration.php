<?php
/**
 * Settings Page "Online Registration Settings" template.
 *
 * Handles markup for the Settings Page "Online Registration Settings" meta box.
 *
 * @package CiviCRM_Event_Organiser
 */

?><!-- assets/templates/wordpress/metaboxes/metabox-admin-settings-registration.php -->
<p><?php esc_html_e( 'The following options configure some CiviCRM Online Registration defaults.', 'civicrm-event-organiser' ); ?></p>

<?php

/**
 * Before Registration table.
 *
 * @since 0.7.2
 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/metabox/registration/before'} filter instead.
 */
do_action_deprecated( 'civicrm_event_organiser_before_registration_table', [], '0.8.0', 'ceo/admin/settings/metabox/registration/before' );

/**
 * Before Registration table.
 *
 * @since 0.8.0
 */
do_action( 'ceo/admin/settings/metabox/registration/before' );

?>

<table class="form-table">

	<?php

	/**
	 * Start of Registration table rows.
	 *
	 * @since 0.7.2
	 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/metabox/registration/table/first_row'} filter instead.
	 */
	do_action_deprecated( 'civicrm_event_organiser_registration_table_first_row', [], '0.8.0', 'ceo/admin/settings/metabox/registration/table/first_row' );

	/**
	 * Start of Registration table rows.
	 *
	 * @since 0.8.0
	 */
	do_action( 'ceo/admin/settings/metabox/registration/table/first_row' );

	?>

	<?php if ( ! empty( $profiles_all ) ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_allowed_profiles"><?php esc_html_e( 'Limit CiviCRM Profiles for Event Registration', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_allowed_profiles" name="civi_eo_event_allowed_profiles[]" multiple="multiple" style="min-width: 50%;">
					<?php foreach ( $profiles_all as $profile ) : ?>
						<option value="<?php echo esc_attr( $profile['id'] ); ?>" <?php selected( in_array( (int) $profile['id'], $profiles_allowed, true ), true ); ?>><?php echo esc_html( ( ! empty( $profile['title'] ) ? $profile['title'] : __( 'Untitled', 'civicrm-event-organiser' ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select the CiviCRM Profiles that you want to appear in the dropdown for Event Registration.', 'civicrm-event-organiser' ); ?></p>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( ! empty( $profiles ) ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_profile"><?php esc_html_e( 'Default CiviCRM Event Registration Profile', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_profile" name="civi_eo_event_default_profile">
					<?php foreach ( $profiles as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_profile, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach ?>
				</select>
				<p class="description"><?php esc_html_e( 'Event Registration Pages require a Profile in order to display correctly.', 'civicrm-event-organiser' ); ?></p>
				<?php if ( $profile_required ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please select a default Profile for Event Registration Pages.', 'civicrm-event-organiser' ); ?></p></div>
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( ! empty( $dedupe_rules ) ) : ?>
		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_default_dedupe"><?php esc_html_e( 'Default CiviCRM Event Registration Dedupe Rule', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<select id="civi_eo_event_default_dedupe" name="civi_eo_event_default_dedupe">
					<option value="0" <?php selected( $default_dedupe_rule, false ); ?>><?php esc_html_e( 'CiviCRM Default', 'civicrm-event-organiser' ); ?></option>
					<?php foreach ( $dedupe_rules as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_dedupe_rule, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach ?>
				</select>
				<p class="description"><?php esc_html_e( 'By default, CiviCRM will use the "Unsupervised" Dedupe Rule to match Participants in anonymous registrations with existing Contacts.', 'civicrm-event-organiser' ); ?></p>
			</td>
		</tr>
	<?php endif; ?>

	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Default CiviCRM Event Registration Confirmation Screen Setting', 'civicrm-event-organiser' ); ?></th>
		<td>
			<input type="checkbox" id="civi_eo_event_default_confirm" name="civi_eo_event_default_confirm" value="1"<?php checked( $confirm_checked, 1 ); ?>>
			<label for="civi_eo_event_default_confirm"><?php esc_html_e( 'Use a Registration Confirmation Screen for free Events.', 'civicrm-event-organiser' ); ?></label>
			<?php if ( $confirm_required ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please choose the default setting for Registration Confirmation Screens.', 'civicrm-event-organiser' ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>

	<?php

	/**
	 * End of Registration table rows.
	 *
	 * @since 0.7.2
	 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/metabox/registration/table/last_row'} filter instead.
	 */
	do_action_deprecated( 'civicrm_event_organiser_registration_table_last_row', [], '0.8.0', 'ceo/admin/settings/metabox/registration/table/last_row' );

	/**
	 * End of Settings table rows.
	 *
	 * @since 0.8.0
	 */
	do_action( 'ceo/admin/settings/metabox/registration/table/last_row' );

	?>

</table>

<hr>

<table class="form-table">

	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Default CiviCRM Event Confirmation Email Setting', 'civicrm-event-organiser' ); ?></th>
		<td>
			<input type="checkbox" id="civi_eo_event_default_send_email" name="civi_eo_event_default_send_email" value="1"<?php checked( $send_email_checked, 1 ); ?>>
			<label for="civi_eo_event_default_send_email"><?php esc_html_e( 'Send a Confirmation Email.', 'civicrm-event-organiser' ); ?></label>
			<p class="description"><?php esc_html_e( 'The Confirmation Email includes event date(s), location and contact information. For Paid Events, the Confirmation Email is also a receipt for payment.', 'civicrm-event-organiser' ); ?></p>
			<?php if ( $send_email_required ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please choose the default setting for sending a Confirmation Email.', 'civicrm-event-organiser' ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>

	<tr valign="top" class="ceo_confirm_email_sub_setting">
		<th scope="row" ><label for="civi_eo_event_default_send_email_from_name"><?php esc_html_e( 'Default CiviCRM Event Confirmation Email "From Name"', 'civicrm-event-organiser' ); ?></label></th>
		<td>
			<input type="text" class="widefat" id="civi_eo_event_default_send_email_from_name" name="civi_eo_event_default_send_email_from_name" value="<?php echo esc_attr( $send_email_from_name ); ?>">
			<p class="description"><?php esc_html_e( 'The name to send the Confirmation Email from.', 'civicrm-event-organiser' ); ?></p>
			<?php if ( $send_email_from_name_required ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please add a default "From Name" for the Confirmation Email.', 'civicrm-event-organiser' ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>

	<tr valign="top" class="ceo_confirm_email_sub_setting">
		<th scope="row"><label for="civi_eo_event_default_send_email_from"><?php esc_html_e( 'Default CiviCRM Event Confirmation Email "From Email"', 'civicrm-event-organiser' ); ?></label></th>
		<td>
			<input type="text" class="widefat" id="civi_eo_event_default_send_email_from" name="civi_eo_event_default_send_email_from" value="<?php echo esc_attr( $send_email_from ); ?>">
			<p class="description"><?php esc_html_e( 'The Email Address to send the Confirmation Email from.', 'civicrm-event-organiser' ); ?></p>
			<?php if ( $send_email_from_required ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Please add a default Email Address for sending a Confirmation Email from.', 'civicrm-event-organiser' ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>

	<tr valign="top" class="ceo_confirm_email_sub_setting">
		<th scope="row"><label for="civi_eo_event_default_send_email_cc"><?php esc_html_e( 'Default CiviCRM Event Confirmation Email "CC Recipients"', 'civicrm-event-organiser' ); ?></label></th>
		<td>
			<input type="text" class="widefat" id="civi_eo_event_default_send_email_cc" name="civi_eo_event_default_send_email_cc" value="<?php echo esc_attr( $send_email_cc ); ?>">
			<p class="description"><?php esc_html_e( 'Carbon Copied recipients of each Confirmation Email. Multiple email addresses should be separated by a comma (e.g. jane@example.org, paula@example.org).', 'civicrm-event-organiser' ); ?></p>
		</td>
	</tr>

	<tr valign="top" class="ceo_confirm_email_sub_setting">
		<th scope="row"><label for="civi_eo_event_default_send_email_bcc"><?php esc_html_e( 'Default CiviCRM Event Confirmation Email "BCC Recipients"', 'civicrm-event-organiser' ); ?></label></th>
		<td>
			<input type="text" class="widefat" id="civi_eo_event_default_send_email_bcc" name="civi_eo_event_default_send_email_bcc" value="<?php echo esc_attr( $send_email_bcc ); ?>">
			<p class="description"><?php esc_html_e( 'Blind Carbon Copied recipients of each Confirmation Email. Multiple email addresses should be separated by a comma (e.g. jane@example.org, paula@example.org).', 'civicrm-event-organiser' ); ?></p>
		</td>
	</tr>

</table>

<?php

/**
 * After Settings table.
 *
 * @since 0.7.2
 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/metabox/registration/after'} filter instead.
 */
do_action_deprecated( 'civicrm_event_organiser_after_registration_table', [], '0.8.0', 'ceo/admin/settings/metabox/registration/after' );

/**
 * After Settings table.
 *
 * @since 0.8.0
 */
do_action( 'ceo/admin/settings/metabox/registration/after' );
