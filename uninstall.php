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

// Access plugin.
$ceo = civicrm_eo();

// Delete version.
$ceo->admin->option_delete( 'civi_eo_version' );

// Delete defaults.
$ceo->admin->option_delete( 'civi_eo_event_default_send_email' );
$ceo->admin->option_delete( 'civi_eo_event_default_send_email_from_name' );
$ceo->admin->option_delete( 'civi_eo_event_default_send_email_from' );
$ceo->admin->option_delete( 'civi_eo_event_default_send_email_cc' );
$ceo->admin->option_delete( 'civi_eo_event_default_send_email_bcc' );
$ceo->admin->option_delete( 'civi_eo_event_default_confirm' );
$ceo->admin->option_delete( 'civi_eo_event_default_profile' );
$ceo->admin->option_delete( 'civi_eo_event_default_role' );
$ceo->admin->option_delete( 'civi_eo_event_default_type' );
$ceo->admin->option_delete( 'civi_eo_event_default_civicrm_event_sync' );
$ceo->admin->option_delete( 'civi_eo_event_default_status_sync' );

// Delete data arrays.
$ceo->admin->option_delete( 'civi_eo_civi_event_disabled' );
$ceo->admin->option_delete( 'civi_eo_civi_event_data' );
