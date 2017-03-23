<!-- assets/templates/settings.php -->
<div class="wrap">

	<h1 class="nav-tab-wrapper">
		<a href="<?php echo $urls['settings']; ?>" class="nav-tab nav-tab-active"><?php _e( 'Settings', 'civicrm-event-organiser' ); ?></a>
		<a href="<?php echo $urls['manual_sync']; ?>" class="nav-tab"><?php _e( 'Manual Sync', 'civicrm-event-organiser' ); ?></a>
	</h1>

	<?php

	// if we've got any messages, show them...
	if ( isset( $messages ) AND ! empty( $messages ) ) echo $messages;

	?>

	<form method="post" id="civi_eo_settings_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'civi_eo_settings_action', 'civi_eo_settings_nonce' ); ?>

		<h3><?php _e( 'General Settings', 'civicrm-event-organiser' ); ?></h3>

		<p><?php _e( 'The following options configure some CiviCRM and Event Organiser defaults.', 'civicrm-event-organiser' ); ?></p>

		<table class="form-table">

		<?php if ( $roles != '' ) : ?>
			<tr valign="top">
				<th scope="row"><label for="civi_eo_event_default_role"><?php _e( 'Default CiviCRM Participant Role for Events', 'civicrm-event-organiser' ); ?></label></th>
				<td><select id="civi_eo_event_default_role" name="civi_eo_event_default_role"><?php echo $roles; ?></select></td>
			</tr>
		<?php endif; ?>

		<?php if ( $types != '' ) : ?>
			<tr valign="top">
				<th scope="row"><label for="civi_eo_event_default_type"><?php _e( 'Default CiviCRM Event Type', 'civicrm-event-organiser' ); ?></label></th>
				<td><select id="civi_eo_event_default_type" name="civi_eo_event_default_type"><?php echo $types; ?></select></td>
			</tr>
		<?php endif; ?>

		</table>

		<hr>

		<p class="submit">
			<input class="button-primary" type="submit" id="civi_eo_settings_submit" name="civi_eo_settings_submit" value="<?php _e( 'Save Changes', 'civicrm-event-organiser' ); ?>" />
		</p>

	</form>

</div><!-- /.wrap -->



