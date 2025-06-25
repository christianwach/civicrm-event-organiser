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
.civi_eo_event_sync_wrapper {
	padding-bottom: 0.8em;
	border-bottom: 2px solid #ddd;
}

.civi_eo_event_reg_header {
	border-top: 2px solid #eee;
	border-bottom: 2px solid #eee;
}

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

.civi_eo_event_send_email_toggle {
	border-top: 1px solid transparent;
	border-top: 1px solid transparent;
}

.civi_eo_event_option_block {
	border-bottom: 2px solid #eee;
	padding-bottom: 0.8em;
}
</style>

<?php if ( current_user_can( 'publish_posts' ) ) : ?>

	<?php if ( $show_sync_checkbox || $multiple ) : ?>
		<div class="civi_eo_event_sync_wrapper">
			<?php if ( $show_sync_checkbox ) : ?>
				<p>
					<label for="civi_eo_event_sync"><?php esc_html_e( 'Sync this Event with CiviCRM:', 'civicrm-event-organiser' ); ?></label>
					<input type="checkbox" id="civi_eo_event_sync" name="civi_eo_event_sync" value="1" />
				</p>

				<p class="description">
					<?php esc_html_e( 'Choose whether or not to sync this Event to CiviCRM. It is recommended that you finish configuring your Event before you sync it to CiviCRM.', 'civicrm-event-organiser' ); ?>
				</p>
			<?php endif; ?>
			<?php if ( $multiple ) : ?>
				<p>
					<label for="civi_eo_event_delete_unused"><?php esc_html_e( 'Delete unused CiviCRM Events:', 'civicrm-event-organiser' ); ?></label>
					<input type="checkbox" id="civi_eo_event_delete_unused" name="civi_eo_event_delete_unused" value="1" />
				</p>

				<p class="description">
					<?php esc_html_e( 'If the sequence has changed, choose whether or not to delete the unused corresponding CiviCRM Events. If you do not delete them, they will be set to "disabled".', 'civicrm-event-organiser' ); ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php endif; ?>

<div class="civi_eo_event_options_wrapper">

	<h4><?php esc_html_e( 'CiviCRM Event Options', 'civicrm-event-organiser' ); ?></h4>

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
				esc_html__( 'These options will be set for %1$sall corresponding CiviCRM Events%2$s when you sync this Event to CiviCRM.', 'civicrm-event-organiser' ),
				'<em>',
				'</em>'
			);
			?>
		<?php endif; ?>
	</p>

	<div class="civi_eo_event_reg_wrapper">

		<div class="civi_eo_event_reg_header">
			<p>
				<label for="civi_eo_event_reg"><?php esc_html_e( 'Enable Online Registration:', 'civicrm-event-organiser' ); ?></label>
				<input type="checkbox" id="civi_eo_event_reg" name="civi_eo_event_reg" value="1"<?php checked( $reg_checked, 1 ); ?> />
			</p>
		</div>

		<div class="civi_eo_event_reg_toggle">

			<div class="civi_eo_event_option_block">
				<p>
					<label for="civi_eo_event_profile"><?php esc_html_e( 'Online Registration Profile:', 'civicrm-event-organiser' ); ?></label>
					<select id="civi_eo_event_profile" name="civi_eo_event_profile">
						<?php echo $profiles; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
					</select>
				</p>

				<p class="description">
					<?php esc_html_e( 'The profile assigned to the Online Registration form.', 'civicrm-event-organiser' ); ?>
				</p>
			</div>

			<div class="civi_eo_event_option_block">
				<p>
					<label for="civi_eo_event_dedupe_rule"><?php esc_html_e( 'Online Registration Dedupe Rule:', 'civicrm-event-organiser' ); ?></label>
					<select id="civi_eo_event_dedupe_rule" name="civi_eo_event_dedupe_rule">
						<?php echo $dedupe_rules; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
					</select>
				</p>

				<p class="description">
					<?php esc_html_e( 'The Dedupe Rule assigned to the Online Registration form.', 'civicrm-event-organiser' ); ?>
				</p>
			</div>

			<div class="civi_eo_event_option_block">
				<p>
					<label for="civi_eo_event_role"><?php esc_html_e( 'Participant Role:', 'civicrm-event-organiser' ); ?></label>
					<select id="civi_eo_event_role" name="civi_eo_event_role">
						<?php echo $roles; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
					</select>
				</p>

				<p class="description">
					<?php esc_html_e( 'This role is automatically assigned to people when they register online for this Event and where the Registration Profile does not allow a role to be selected.', 'civicrm-event-organiser' ); ?>
				</p>
			</div>

			<div class="civi_eo_event_option_block">
				<p>
					<label for="civi_eo_event_confirm"><?php esc_html_e( 'Use a Confirmation Screen:', 'civicrm-event-organiser' ); ?></label>
					<input type="checkbox" id="civi_eo_event_confirm" name="civi_eo_event_confirm" value="1"<?php checked( $confirm_checked, 1 ); ?> />
				</p>

				<p class="description">
					<?php esc_html_e( 'If this is a free Event, use a Registration Confirmation Screen.', 'civicrm-event-organiser' ); ?>
				</p>
			</div>

			<div class="civi_eo_event_option_block">
				<p>
					<label for="civi_eo_event_send_email"><?php esc_html_e( 'Send Confirmation Email:', 'civicrm-event-organiser' ); ?></label>
					<input type="checkbox" id="civi_eo_event_send_email" name="civi_eo_event_send_email" value="1"<?php checked( $send_email_checked, 1 ); ?> />
				</p>

				<p class="description">
					<?php esc_html_e( 'Email includes date(s), location and contact information. If this is a paid Event, the Email is also the receipt.', 'civicrm-event-organiser' ); ?>
				</p>

				<div class="civi_eo_event_send_email_toggle">
					<p>
						<label for="civi_eo_event_send_email_from_name"><?php esc_html_e( 'From Name:', 'civicrm-event-organiser' ); ?></label>
						<input type="text" class="widefat" id="civi_eo_event_send_email_from_name" name="civi_eo_event_send_email_from_name" value="<?php echo esc_attr( $send_email_from_name ); ?>" />
					</p>
					<p>
						<label for="civi_eo_event_send_email_from"><?php esc_html_e( 'From Email:', 'civicrm-event-organiser' ); ?></label>
						<input type="text" class="widefat" id="civi_eo_event_send_email_from" name="civi_eo_event_send_email_from" value="<?php echo esc_attr( $send_email_from ); ?>" />
					</p>
					<p>
						<label for="civi_eo_event_send_email_cc"><?php esc_html_e( 'CC Confirmation To:', 'civicrm-event-organiser' ); ?></label>
						<input type="text" class="widefat" id="civi_eo_event_send_email_cc" name="civi_eo_event_send_email_cc" value="<?php echo esc_attr( $send_email_cc ); ?>" /><br>
						<span class="description"><?php esc_html_e( 'Multiple email addresses should be separated by a comma (e.g. jane@example.org, paula@example.org).', 'civicrm-event-organiser' ); ?></span>
					</p>
					<p>
						<label for="civi_eo_event_send_email_bcc"><?php esc_html_e( 'BCC Confirmation To:', 'civicrm-event-organiser' ); ?></label>
						<input type="text" class="widefat" id="civi_eo_event_send_email_bcc" name="civi_eo_event_send_email_bcc" value="<?php echo esc_attr( $send_email_bcc ); ?>" />
						<span class="description"><?php esc_html_e( 'Multiple email addresses should be separated by a comma (e.g. jane@example.org, paula@example.org).', 'civicrm-event-organiser' ); ?></span>
					</p>
				</div>
			</div>

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

		</div>

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
