<?php
/**
 * Event Sync template.
 *
 * Handles markup for the Event Sync metabox.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- assets/templates/wordpress/metaboxes/metabox-event-sync.php -->
<?php wp_nonce_field( $this->nonce_action, $this->nonce_field ); ?>

<style>
body.js .civi_eo_event_reg_toggle {
	<?php if ( ! $reg_checked ) : ?>
		display: none;
	<?php endif; ?>
}

body.js .civi_eo_event_send_email_toggle {
	<?php if ( ! $send_email_checked ) : ?>
		display: none;
	<?php endif; ?>
}
</style>

<p>
	<?php
	echo sprintf(
		/* translators: 1: The opening strong tag, 2: The closing strong tag. */
		esc_html__( '%1$sNOTE%2$s: Changes that you make will override the defaults set on the CiviCRM Event Organiser Settings page.', 'civicrm-event-organiser' ),
		'<strong>',
		'</strong>'
	);
	?>
	<?php if ( $multiple ) : ?>
		<?php
		echo sprintf(
			/* translators: 1: The opening emphasis tag, 2: The closing emphasis tag. */
			esc_html__( 'Because this is a repeating Event, these options will be set for %1$sall corresponding CiviCRM Events%2$s when you sync this Event to CiviCRM.', 'civicrm-event-organiser' ),
			'<em>',
			'</em>'
		);
		?>
	<?php endif; ?>
</p>

<div class="civi_eo_event_options_wrapper">

	<table class="form-table">

		<tr valign="top">
			<th scope="row"><label for="civi_eo_event_reg"><?php esc_html_e( 'Online Registration', 'civicrm-event-organiser' ); ?></label></th>
			<td>
				<input type="checkbox" id="civi_eo_event_reg" name="civi_eo_event_reg" value="1"<?php checked( $reg_checked, 1 ); ?> />
				<p class="description"><?php esc_html_e( 'Check this to enable Online Registration.', 'civicrm-event-organiser' ); ?></p>
			</td>
		</tr>

	</table>

	<div class="civi_eo_event_reg_toggle">

		<table class="form-table civi_eo_event_reg_toggle">

			<tr valign="top">
				<th scope="row"><label for="civi_eo_event_profile"><?php esc_html_e( 'Profile', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<select id="civi_eo_event_profile" name="civi_eo_event_profile">
						<?php foreach ( $profiles as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_profile, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach ?>
					</select>
					<p class="description"><?php esc_html_e( 'Select the Registration Profile assigned to the Online Registration form.', 'civicrm-event-organiser' ); ?></p>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><label for="civi_eo_event_dedupe_rule"><?php esc_html_e( 'Dedupe Rule', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<select id="civi_eo_event_dedupe_rule" name="civi_eo_event_dedupe_rule">
						<option value="0" <?php selected( $default_dedupe_rule, false ); ?>><?php esc_html_e( 'CiviCRM Default', 'civicrm-event-organiser' ); ?></option>
						<?php foreach ( $dedupe_rules as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_dedupe_rule, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach ?>
					</select>
					<p class="description"><?php esc_html_e( 'Select the CiviCRM the Dedupe Rule assigned to the Online Registration form.', 'civicrm-event-organiser' ); ?></p>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><label for="civi_eo_event_role"><?php esc_html_e( 'Participant Role', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<select id="civi_eo_event_role" name="civi_eo_event_role">
						<?php foreach ( $roles as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_role, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach ?>
					</select>
					<p class="description"><?php esc_html_e( 'This role is automatically assigned to people when they register online for this Event and where the Registration Profile does not allow a role to be selected.', 'civicrm-event-organiser' ); ?></p>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><label for="civi_eo_event_confirm"><?php esc_html_e( 'Confirmation Screen', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<input type="checkbox" id="civi_eo_event_confirm" name="civi_eo_event_confirm" value="1"<?php checked( $confirm_checked, 1 ); ?> />
					<p class="description"><?php esc_html_e( 'If this is a free Event, use a Registration Confirmation Screen.', 'civicrm-event-organiser' ); ?></p>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><label for="civi_eo_event_send_email"><?php esc_html_e( 'Send Confirmation Email', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<input type="checkbox" id="civi_eo_event_send_email" name="civi_eo_event_send_email" value="1"<?php checked( $send_email_checked, 1 ); ?> />
					<p class="description"><?php esc_html_e( 'Email includes date(s), location and contact information. If this is a paid Event, the Email is also the receipt.', 'civicrm-event-organiser' ); ?></p>
				</td>
			</tr>

			<tr valign="top" class="civi_eo_event_send_email_toggle">
				<th scope="row"><label for="civi_eo_event_send_email_from_name"><?php esc_html_e( 'From Name', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<input type="text" class="widefat" id="civi_eo_event_send_email_from_name" name="civi_eo_event_send_email_from_name" value="<?php echo esc_attr( $send_email_from_name ); ?>" />
				</td>
			</tr>

			<tr valign="top" class="civi_eo_event_send_email_toggle">
				<th scope="row"><label for="civi_eo_event_send_email_from"><?php esc_html_e( 'From Name', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<input type="text" class="widefat" id="civi_eo_event_send_email_from" name="civi_eo_event_send_email_from" value="<?php echo esc_attr( $send_email_from ); ?>" />
				</td>
			</tr>

			<tr valign="top" class="civi_eo_event_send_email_toggle">
				<th scope="row"><label for="civi_eo_event_send_email_cc"><?php esc_html_e( 'CC Confirmation To', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<input type="text" class="widefat" id="civi_eo_event_send_email_cc" name="civi_eo_event_send_email_cc" value="<?php echo esc_attr( $send_email_cc ); ?>" /><br>
					<p class="description"><?php esc_html_e( 'Multiple email addresses should be separated by a comma (e.g. jane@example.org, paula@example.org).', 'civicrm-event-organiser' ); ?></p>
				</td>
			</tr>

			<tr valign="top" class="civi_eo_event_send_email_toggle">
				<th scope="row"><label for="civi_eo_event_send_email_bcc"><?php esc_html_e( 'BCC Confirmation To', 'civicrm-event-organiser' ); ?></label></th>
				<td>
					<input type="text" class="widefat" id="civi_eo_event_send_email_bcc" name="civi_eo_event_send_email_bcc" value="<?php echo esc_attr( $send_email_bcc ); ?>" />
					<p class="description"><?php esc_html_e( 'Multiple email addresses should be separated by a comma (e.g. jane@example.org, paula@example.org).', 'civicrm-event-organiser' ); ?></p>
				</td>
			</tr>

			<?php

			/**
			 * Fires at the end of the Online Registration options.
			 *
			 * @since 0.5.3
			 * @deprecated 0.8.0 Use the {@see 'ceo/event/metabox/event/sync/online_reg/after'} filter instead.
			 *
			 * @param object $event The Event Organiser Event object.
			 */
			do_action_deprecated( 'civicrm_event_organiser_event_meta_box_online_reg_after', [ $event ], '0.8.0', 'ceo/event/metabox/event/sync/online_reg/after' );

			/**
			 * Fires at the end of the Online Registration options.
			 *
			 * @since 0.8.0
			 *
			 * @param object $event The Event Organiser Event object.
			 */
			do_action( 'ceo/event/metabox/event/sync/online_reg/after', $event );

			?>

		</table>

	</div>

	<?php

	/**
	 * Fires at the end of the Event Options.
	 *
	 * @since 0.5.3
	 * @deprecated 0.8.0 Use the {@see 'ceo/event/metabox/event/sync/options/after'} filter instead.
	 *
	 * @param object $event The Event Organiser Event object.
	 */
	do_action_deprecated( 'civicrm_event_organiser_event_meta_box_options_after', [ $event ], '0.8.0', 'ceo/event/metabox/event/sync/options/after' );

	/**
	 * Fires at the end of the Event Options.
	 *
	 * @since 0.8.0
	 *
	 * @param object $event The Event Organiser Event object.
	 */
	do_action( 'ceo/event/metabox/event/sync/options/after', $event );

	?>

</div>

<?php

/**
 * Fires at end of Event Sync meta box.
 *
 * @since 0.3
 * @deprecated 0.8.0 Use the {@see 'ceo/event/metabox/event/sync/after'} filter instead.
 *
 * @param object $event The Event Organiser Event object.
 */
do_action_deprecated( 'civicrm_event_organiser_event_meta_box_after', [ $event ], '0.8.0', 'ceo/event/metabox/event/sync/after' );

/**
 * Fires at end of Event Sync meta box.
 *
 * @since 0.8.0
 *
 * @param object $event The Event Organiser Event object.
 */
do_action( 'ceo/event/metabox/event/sync/after', $event );
