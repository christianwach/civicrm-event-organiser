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



// kick out if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();



// access plugin
global $civicrm_wp_event_organiser;

// delete version
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_version' );

// delete defaults
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_profile' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_role' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_event_default_type' );

// delete data arrays
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_civi_event_disabled' );
$civicrm_wp_event_organiser->db->option_delete( 'civi_eo_civi_event_data' );



