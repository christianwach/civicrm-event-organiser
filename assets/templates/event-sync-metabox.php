<!-- assets/templates/event-sync-metabox.php -->
<style>
.civi_eo_event_sync_wrapper {
	border-bottom: 2px solid #ddd;
	padding-bottom: 0.8em;
}

.civi_eo_event_reg_header {
	border-top: 2px solid #eee;
	border-bottom: 2px solid #eee;
}

body.js .civi_eo_event_reg_toggle {
	<?php if ( ! $reg_checked ) : ?>display: none;<?php endif; ?>
}

.civi_eo_event_option_block {
	border-bottom: 2px solid #eee;
	padding-bottom: 0.8em;
}
</style>

<?php if ( current_user_can( 'publish_posts' ) ) : ?>

	<div class="civi_eo_event_sync_wrapper">

		<p>
			<label for="civi_eo_event_sync"><?php _e( 'Sync this event with CiviCRM:', 'civicrm-event-organiser' ); ?></label>
			<input type="checkbox" id="civi_eo_event_sync" name="civi_eo_event_sync" value="1" />
		</p>

		<p class="description">
			<?php _e( 'Choose whether or not to sync this event to CiviCRM. It is recommended that you finish configuring your event before you sync it to CiviCRM.', 'civicrm-event-organiser' ); ?>
		</p>

		<?php if ( $multiple ) : ?>
			<p>
				<label for="civi_eo_event_delete_unused"><?php _e( 'Delete unused CiviEvents:', 'civicrm-event-organiser' ); ?></label>
				<input type="checkbox" id="civi_eo_event_delete_unused" name="civi_eo_event_delete_unused" value="1" />
			</p>

			<p class="description">
				<?php _e( 'If the sequence has changed, choose whether or not to delete the unused corresponding CiviEvents. If you do not delete them, they will be set to "disabled".', 'civicrm-event-organiser' ); ?>
			</p>
		<?php endif; ?>

	</div>

<?php endif; ?>

<div class="civi_eo_event_options_wrapper">

	<h4><?php _e( 'CiviEvent Options', 'civicrm-event-organiser' ); ?></h4>

	<p>
		<?php _e( '<strong>NOTE:</strong> Changes that you make will override the defaults set on the CiviCRM Event Organiser Settings page.', 'civicrm-event-organiser' ); ?> <?php if ( $multiple ) : ?><?php _e( 'These options will be set for <em>all corresponding CiviEvents</em> when you sync this event to CiviCRM.', 'civicrm-event-organiser' ); ?><?php endif; ?>
	</p>

	<div class="civi_eo_event_reg_wrapper">

		<div class="civi_eo_event_reg_header">
			<p>
				<label for="civi_eo_event_reg"><?php _e( 'Enable Online Registration:', 'civicrm-event-organiser' ); ?></label>
				<input type="checkbox" id="civi_eo_event_reg" name="civi_eo_event_reg" value="1"<?php echo $reg_checked; ?> />
			</p>
		</div>

		<div class="civi_eo_event_reg_toggle">

			<div class="civi_eo_event_option_block">
				<p>
					<label for="civi_eo_event_profile"><?php _e( 'Online Registration Profile:', 'civicrm-event-organiser' ); ?></label>
					<select id="civi_eo_event_profile" name="civi_eo_event_profile">
						<?php echo $profiles; ?>
					</select>
				</p>

				<p class="description">
					<?php _e( 'The profile assigned to the online registration form.', 'civicrm-event-organiser' ); ?>
				</p>
			</div>

			<div class="civi_eo_event_option_block">
				<p>
					<label for="civi_eo_event_role"><?php _e( 'Participant Role:', 'civicrm-event-organiser' ); ?></label>
					<select id="civi_eo_event_role" name="civi_eo_event_role">
						<?php echo $roles; ?>
					</select>
				</p>

				<p class="description">
					<?php _e( 'This role is automatically assigned to people when they register online for this event and where the registration profile does not allow a role to be selected.', 'civicrm-event-organiser' ); ?>
				</p>
			</div>

			<?php

			/**
			 * Broadcast end of the Online Registration options.
			 *
			 * @since 0.5.3
			 *
			 * @param object $event The EO event object.
			 */
			do_action( 'civicrm_event_organiser_event_meta_box_online_reg_after', $event );

			?>

		</div>

	</div>

	<?php

	/**
	 * Broadcast end of Event Options.
	 *
	 * @since 0.5.3
	 *
	 * @param object $event The EO event object.
	 */
	do_action( 'civicrm_event_organiser_event_meta_box_options_after', $event );

	?>

</div>

<?php

/**
 * Broadcast end of metabox.
 *
 * @since 0.3
 *
 * @param object $event The EO event object.
 */
do_action( 'civicrm_event_organiser_event_meta_box_after', $event );
