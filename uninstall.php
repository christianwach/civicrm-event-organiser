<?php /*
================================================================================
CiviCRM Event Organiser Uninstaller
================================================================================
AUTHOR: Christian Wach <needle@haystack.co.uk>
--------------------------------------------------------------------------------
NOTES
=====


--------------------------------------------------------------------------------
*/



// Kick out if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();



// Access plugin.
global $civicrm_wp_event_organiser;

// Delete version.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_version' );

// Delete defaults.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_profile' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_role' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_type' );

// Delete data arrays.
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_civi_event_disabled' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_civi_event_data' );



