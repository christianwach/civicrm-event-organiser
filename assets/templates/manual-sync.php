<?php
/**
 * Manual Sync template.
 *
 * Handles markup for the Manual Sync admin page.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.2.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/manual-sync.php -->
<div class="wrap">

	<h1 class="nav-tab-wrapper">
		<a href="<?php echo $urls['settings']; ?>" class="nav-tab"><?php _e( 'Settings', 'civicrm-event-organiser' ); ?></a>
		<a href="<?php echo $urls['manual_sync']; ?>" class="nav-tab nav-tab-active"><?php _e( 'Manual Sync', 'civicrm-event-organiser' ); ?></a>
	</h1>

	<?php

	// If we've got any messages, show them.
	if ( isset( $messages ) AND ! empty( $messages ) ) echo $messages;

	?>

	<form method="post" id="civi_eo_manual_sync_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'civi_eo_manual_sync_action', 'civi_eo_manual_sync_nonce' ); ?>

		<p><?php _e( 'Things can be a little complicated on initial setup because there can be data in WordPress or CiviCRM or both. The most robust procedure for setting up the sync between Event Organiser events and CiviEvents is to sync in the following order:', 'civicrm-event-organiser' ); ?></p>

		<ol>
			<li><?php _e( 'Only sync Event Categories with CiviCRM Event Types', 'civicrm-event-organiser' ); ?></li>
			<li><?php _e( 'Only sync EO Venues with CiviCRM Locations', 'civicrm-event-organiser' ); ?></li>
			<li><?php _e( 'Only sync EO Events with CiviEvents.', 'civicrm-event-organiser' ); ?></li>
		</ol>

		<p><?php _e( 'Your set up may require some direct manipulation of the data, but the following procedures should help get things moving.', 'civicrm-event-organiser' ); ?></p>

		<hr>

		<h3><?php _e( 'Event Type Synchronisation', 'civicrm-event-organiser' ); ?></h3>

		<table class="form-table">

			<tr valign="top">
				<?php $identifier = 'civi_eo_tax_eo_to_civi'; ?>
				<th scope="row"><label for="<?php echo $identifier; ?>"><?php _e( 'Event Organiser Categories to CiviCRM Event Types', 'civicrm-event-organiser' ); ?></label></th>
				<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_tax_eo_to_civi_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-event-organiser' ); } else { _e( 'Continue Sync', 'civicrm-event-organiser' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_tax_eo_to_civi_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="<?php echo $identifier; ?>_stop" name="<?php echo $identifier; ?>_stop" value="<?php _e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" /><?php } ?></td>
			</tr>

			<tr valign="top">
				<td colspan="2" class="progress-bar-hidden"><div id="progress-bar-tax-eo-to-civi"><div class="progress-label"></div></div></td>
			</tr>

			<tr valign="top">
				<?php $identifier = 'civi_eo_tax_civi_to_eo'; ?>
				<th scope="row"><label for="<?php echo $identifier; ?>"><?php _e( 'CiviCRM Event Types to Event Organiser Categories', 'civicrm-event-organiser' ); ?></label></th>
				<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-event-organiser' ); } else { _e( 'Continue Sync', 'civicrm-event-organiser' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="<?php echo $identifier; ?>_stop" name="<?php echo $identifier; ?>_stop" value="<?php _e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" /><?php } ?></td>
			</tr>

			<tr valign="top">
				<td colspan="2" class="progress-bar-hidden"><div id="progress-bar-tax-civi-to-eo"><div class="progress-label"></div></div></td>
			</tr>

		</table>

		<hr>

		<h3><?php _e( 'Venue Synchronisation', 'civicrm-event-organiser' ); ?></h3>

		<table class="form-table">

			<tr valign="top">
				<?php $identifier = 'civi_eo_venue_eo_to_civi'; ?>
				<th scope="row"><label for="<?php echo $identifier; ?>"><?php _e( 'Event Organiser Venues to CiviEvent Locations', 'civicrm-event-organiser' ); ?></label></th>
				<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_venue_eo_to_civi_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-event-organiser' ); } else { _e( 'Continue Sync', 'civicrm-event-organiser' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_venue_eo_to_civi_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="<?php echo $identifier; ?>_stop" name="<?php echo $identifier; ?>_stop" value="<?php _e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" /><?php } ?></td>
			</tr>

			<tr valign="top">
				<td colspan="2" class="progress-bar-hidden"><div id="progress-bar-venue-eo-to-civi"><div class="progress-label"></div></div></td>
			</tr>

			<tr valign="top">
				<?php $identifier = 'civi_eo_venue_civi_to_eo'; ?>
				<th scope="row"><label for="<?php echo $identifier; ?>"><?php _e( 'CiviEvent Locations to Event Organiser Venues', 'civicrm-event-organiser' ); ?></label></th>
				<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_venue_civi_to_eo_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-event-organiser' ); } else { _e( 'Continue Sync', 'civicrm-event-organiser' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_venue_civi_to_eo_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="<?php echo $identifier; ?>_stop" name="<?php echo $identifier; ?>_stop" value="<?php _e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" /><?php } ?></td>
			</tr>

			<tr valign="top">
				<td colspan="2" class="progress-bar-hidden"><div id="progress-bar-venue-civi-to-eo"><div class="progress-label"></div></div></td>
			</tr>

		</table>

		<hr>

		<h3><?php _e( 'Event Synchronisation', 'civicrm-event-organiser' ); ?></h3>

		<table class="form-table">

			<tr valign="top">
				<?php $identifier = 'civi_eo_event_eo_to_civi'; ?>
				<th scope="row"><label for="<?php echo $identifier; ?>"><?php _e( 'Event Organiser Events to CiviEvents', 'civicrm-event-organiser' ); ?></label></th>
				<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_event_eo_to_civi_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-event-organiser' ); } else { _e( 'Continue Sync', 'civicrm-event-organiser' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_event_eo_to_civi_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="<?php echo $identifier; ?>_stop" name="<?php echo $identifier; ?>_stop" value="<?php _e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" /><?php } ?></td>
			</tr>

			<tr valign="top">
				<td colspan="2" class="progress-bar-hidden"><div id="progress-bar-event-eo-to-civi"><div class="progress-label"></div></div></td>
			</tr>

			<tr valign="top">
				<?php $identifier = 'civi_eo_event_civi_to_eo'; ?>
				<th scope="row"><label for="<?php echo $identifier; ?>"><?php _e( 'CiviEvents to Event Organiser Events', 'civicrm-event-organiser' ); ?></label></th>
				<td><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_event_civi_to_eo_offset', 'fgffgs' ) ) { _e( 'Sync Now', 'civicrm-event-organiser' ); } else { _e( 'Continue Sync', 'civicrm-event-organiser' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_event_civi_to_eo_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="<?php echo $identifier; ?>_stop" name="<?php echo $identifier; ?>_stop" value="<?php _e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" /><?php } ?></td>
			</tr>

			<tr valign="top">
				<td colspan="2" class="progress-bar-hidden"><div id="progress-bar-event-civi-to-eo"><div class="progress-label"></div></div></td>
			</tr>

		</table>

	</form>

</div><!-- /.wrap -->
