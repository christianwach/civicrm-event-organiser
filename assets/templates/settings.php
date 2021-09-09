<?php
/**
 * Settings template.
 *
 * Handles markup for the Settings admin page.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.2.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/settings.php -->
<div class="wrap">

	<h1 class="nav-tab-wrapper">
		<a href="<?php echo $urls['settings']; ?>" class="nav-tab nav-tab-active"><?php _e( 'Settings', 'civicrm-event-organiser' ); ?></a>
		<a href="<?php echo $urls['manual_sync']; ?>" class="nav-tab"><?php _e( 'Manual Sync', 'civicrm-event-organiser' ); ?></a>
	</h1>

	<?php

	// If we've got any messages, show them.
	if ( isset( $messages ) AND ! empty( $messages ) ) echo $messages;

	?>

	<form method="post" id="civi_eo_settings_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'civi_eo_settings_action', 'civi_eo_settings_nonce' ); ?>

		<h3><?php _e( 'General Settings', 'civicrm-event-organiser' ); ?></h3>

		<p><?php _e( 'The following options configure some CiviCRM and Event Organiser defaults.', 'civicrm-event-organiser' ); ?></p>

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
					<th scope="row"><label for="civi_eo_event_default_type"><?php _e( 'Default CiviCRM Event Type', 'civicrm-event-organiser' ); ?></label></th>
					<td>
						<select id="civi_eo_event_default_type" name="civi_eo_event_default_type">
							<?php echo $types; ?>
						</select>
					</td>
				</tr>
			<?php endif; ?>

			<?php if ( $roles != '' ) : ?>
				<tr valign="top">
					<th scope="row"><label for="civi_eo_event_default_role"><?php _e( 'Default CiviCRM Participant Role for Events', 'civicrm-event-organiser' ); ?></label></th>
					<td>
						<select id="civi_eo_event_default_role" name="civi_eo_event_default_role">
							<?php echo $roles; ?>
						</select>
						<p class="description"><?php _e( 'This will be the Participant Role that is set for Event Registrations when there is a Registration Profile that does not contain a Participant Role selector.' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>

			<?php if ( $profiles != '' ) : ?>
				<tr valign="top">
					<th scope="row"><label for="civi_eo_event_default_profile"><?php _e( 'Default CiviCRM Event Registration Profile', 'civicrm-event-organiser' ); ?></label></th>
					<td>
						<select id="civi_eo_event_default_profile" name="civi_eo_event_default_profile">
							<?php echo $profiles; ?>
						</select>
						<p class="description"><?php _e( 'Event Registration Pages require a Profile in order to display correctly.' ); ?></p>
						<?php if ( $profile_required ) : ?>
							<div class="notice notice-warning inline"><p><?php _e( 'Please select a default Profile for Event Registration Pages.' ); ?></p></div>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>

			<tr valign="top">
				<th scope="row"><?php _e( 'Default CiviCRM Event Registration Confirmation Screen Setting', 'civicrm-event-organiser' ); ?></th>
				<td>
					<input type="checkbox" id="civi_eo_event_default_confirm" name="civi_eo_event_default_confirm" value="1"<?php echo $confirm_checked; ?>>
					<label for="civi_eo_event_default_confirm"><?php _e( 'Use a Registration Confirmation Screen for free Events.' ); ?></label>
					<?php if ( $confirm_required ) : ?>
						<div class="notice notice-warning inline"><p><?php _e( 'Please choose the default setting for Registration Confirmation Screens.' ); ?></p></div>
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

		?>

		<hr>

		<p class="submit">
			<input class="button-primary" type="submit" id="civi_eo_settings_submit" name="civi_eo_settings_submit" value="<?php _e( 'Save Changes', 'civicrm-event-organiser' ); ?>" />
		</p>

	</form>

</div><!-- /.wrap -->
