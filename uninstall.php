<?php
/**
 * Uninstaller.
 *
 * Handles uninstallation of this plugin.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Exit if uninstall not called from WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete version.
delete_option( 'civi_eo_version' );

// Delete defaults.
delete_option( 'civi_eo_event_default_send_email' );
delete_option( 'civi_eo_event_default_send_email_from_name' );
delete_option( 'civi_eo_event_default_send_email_from' );
delete_option( 'civi_eo_event_default_send_email_cc' );
delete_option( 'civi_eo_event_default_send_email_bcc' );
delete_option( 'civi_eo_event_default_confirm' );
delete_option( 'civi_eo_event_default_profile' );
delete_option( 'civi_eo_event_default_role' );
delete_option( 'civi_eo_event_default_type' );
delete_option( 'civi_eo_event_default_civicrm_event_sync' );
delete_option( 'civi_eo_event_default_status_sync' );

// Delete data arrays.
delete_option( 'civi_eo_civi_event_disabled' );
delete_option( 'civi_eo_civi_event_data' );
