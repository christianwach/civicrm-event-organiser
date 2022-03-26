<?php
/**
 * Event Organiser Events to CiviCRM Events sync template.
 *
 * Handles markup for the Event Organiser Events to CiviCRM Events meta box.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

?><!-- assets/templates/wordpress/metaboxes/metabox-admin-sync-eo-civicrm.php -->
<?php $identifier = 'civi_eo_event_eo_to_civi'; ?>

<p><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_event_eo_to_civi_offset', 'fgffgs' ) ) { esc_attr_e( 'Sync Now', 'civicrm-event-organiser' ); } else { esc_attr_e( 'Continue Sync', 'civicrm-event-organiser' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_event_eo_to_civi_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="<?php echo $identifier; ?>_stop" name="<?php echo $identifier; ?>_stop" value="<?php esc_attr_e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" /><?php } ?></p>

<div id="progress-bar-event-eo-to-civi"><div class="progress-label"></div></div>
