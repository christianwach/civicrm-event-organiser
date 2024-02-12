<?php
/**
 * CiviCRM Events to Event Organiser Events sync template.
 *
 * Handles markup for the CiviCRM Events to Event Organiser Events meta box.
 *
 * @package CiviCRM_Event_Organiser
 */

?><!-- assets/templates/wordpress/metaboxes/metabox-admin-sync-civicrm-eo.php -->
<?php $identifier = 'civi_eo_event_civi_to_eo'; ?>

<p>
	<input type="submit" id="<?php echo esc_attr( $identifier ); ?>" name="<?php echo esc_attr( $identifier ); ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php echo ( ( 'fgffgs' === get_option( '_civi_eo_event_civi_to_eo_offset', 'fgffgs' ) ) ? esc_attr__( 'Sync Now', 'civicrm-event-organiser' ) : esc_attr__( 'Continue Sync', 'civicrm-event-organiser' ) ); ?>" class="button-primary" />
	<?php if ( 'fgffgs' !== get_option( '_civi_eo_event_civi_to_eo_offset', 'fgffgs' ) ) : ?>
		<input type="submit" id="<?php echo esc_attr( $identifier ); ?>_stop" name="<?php echo esc_attr( $identifier ); ?>_stop" value="<?php esc_attr_e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" />
	<?php endif; ?>
</p>

<div id="progress-bar-event-civi-to-eo"><div class="progress-label"></div></div>
