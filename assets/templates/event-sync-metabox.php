<!-- assets/templates/event-sync-metabox.php -->
<?php if ( current_user_can( 'publish_posts' ) ) : ?>

	<p class="civi_eo_event_desc">
		<?php _e( 'Choose whether or not to sync this event and (if the sequence has changed) whether or not to delete the unused corresponding CiviEvents. If you do not delete them, they will be set to "disabled".', 'civicrm-event-organiser' ); ?>
	</p>

	<p>
		<label for="civi_eo_event_sync"><?php _e( 'Sync this event with CiviCRM:', 'civicrm-event-organiser' ); ?></label>
		<input type="checkbox" id="civi_eo_event_sync" name="civi_eo_event_sync" value="1" />
	</p>

	<p>
		<label for="civi_eo_event_delete_unused"><?php _e( 'Delete unused CiviEvents:', 'civicrm-event-organiser' ); ?></label>
		<input type="checkbox" id="civi_eo_event_delete_unused" name="civi_eo_event_delete_unused" value="1" />
	</p>

	<hr />

<?php endif; ?>

<h4><?php _e( 'CiviEvent Options', 'civicrm-event-organiser' ); ?></h4>

<p class="civi_eo_event_desc">
	<?php _e( '<strong>NOTE:</strong> these options will be set for <em>all corresponding CiviEvents</em> when you sync this event to CiviCRM. Changes that you make will override the defaults set on the CiviCRM Event Organiser Settings page.', 'civicrm-event-organiser' ); ?>
</p>

<hr />

<p>
	<label for="civi_eo_event_reg"><?php _e( 'Enable Online Registration:', 'civicrm-event-organiser' ); ?></label>
	<input type="checkbox" id="civi_eo_event_reg" name="civi_eo_event_reg" value="1"<?php echo $reg_checked; ?> />
</p>

<hr />

<p>
	<label for="civi_eo_event_profile"><?php _e( 'Online Registration Profile:', 'civicrm-event-organiser' ); ?></label>
	<select id="civi_eo_event_profile" name="civi_eo_event_profile">
		<?php echo $profiles; ?>
	</select>
</p>

<p class="description"><?php _e( 'The profile assigned to the online registration form.', 'civicrm-event-organiser' ); ?></p>

<hr />

<p>
	<label for="civi_eo_event_role"><?php _e( 'Participant Role:', 'civicrm-event-organiser' ); ?></label>
	<select id="civi_eo_event_role" name="civi_eo_event_role">
		<?php echo $roles; ?>
	</select>
</p>

<p class="description">
	<?php _e( 'This role is automatically assigned to people when they register online for this event and where the registration profile does not allow a role to be selected.', 'civicrm-event-organiser' ); ?>
</p>
