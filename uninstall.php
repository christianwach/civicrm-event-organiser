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
$civicrm_wp_event_organiser = civicrm_eo();

// Delete version.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_version' );

// Delete defaults.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_send_email' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_send_email_from_name' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_send_email_from' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_send_email_cc' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_send_email_bcc' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_confirm' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_profile' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_role' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_type' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_status_sync' );

// Delete data arrays.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_civi_event_disabled' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_civi_event_data' );
