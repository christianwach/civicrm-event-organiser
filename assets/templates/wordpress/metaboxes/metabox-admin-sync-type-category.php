<?php
/**
 * CiviCRM Event Types to Event Organiser Categories sync template.
 *
 * Handles markup for the CiviCRM Event Types to Event Organiser Categories meta box.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

?><!-- assets/templates/wordpress/metaboxes/metabox-admin-sync-type-category.php -->
<?php $identifier = 'civi_eo_tax_civi_to_eo'; ?>

<p><input type="submit" id="<?php echo $identifier; ?>" name="<?php echo $identifier; ?>" data-security="<?php echo esc_attr( wp_create_nonce( $identifier ) ); ?>" value="<?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) { esc_attr_e( 'Sync Now', 'civicrm-event-organiser' ); } else { esc_attr_e( 'Continue Sync', 'civicrm-event-organiser' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) {} else { ?> <input type="submit" id="<?php echo $identifier; ?>_stop" name="<?php echo $identifier; ?>_stop" value="<?php esc_attr_e( 'Stop Sync', 'civicrm-event-organiser' ); ?>" class="button-secondary" /><?php } ?></p>

<div id="progress-bar-tax-civi-to-eo"><div class="progress-label"></div></div>
