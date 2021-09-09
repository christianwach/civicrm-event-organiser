<?php
/**
 * Uninstaller.
 *
 * Handles uninstallation of the CiviCRM Event Organiser plugin.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Exit if uninstall not called from WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;



// Access plugin.
global $civicrm_wp_event_organiser;

// Delete version.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_version' );

// Delete defaults.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_confirm' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_profile' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_role' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_type' );

// Delete data arrays.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_civi_event_disabled' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_civi_event_data' );
