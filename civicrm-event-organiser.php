<?php
/*
--------------------------------------------------------------------------------
Plugin Name: CiviCRM Event Organiser
Description: A WordPress plugin for syncing Event Organiser plugin Events with CiviCRM Events so they play nicely with BuddyPress Groups and Group Hierarchies
Version: 0.1
Author: Christian Wach
Author URI: http://haystack.co.uk
Plugin URI: http://haystack.co.uk
--------------------------------------------------------------------------------
*/



// set our debug flag here
define( 'CIVICRM_WP_EVENT_ORGANISER_DEBUG', false );

// set our version here
define( 'CIVICRM_WP_EVENT_ORGANISER_VERSION', '0.1' );

// store reference to this file
if ( !defined( 'CIVICRM_WP_EVENT_ORGANISER_FILE' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_FILE', __FILE__ );
}

// store URL to this plugin's directory
if ( !defined( 'CIVICRM_WP_EVENT_ORGANISER_URL' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_URL', plugin_dir_url( CIVICRM_WP_EVENT_ORGANISER_FILE ) );
}
// store PATH to this plugin's directory
if ( !defined( 'CIVICRM_WP_EVENT_ORGANISER_PATH' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_PATH', plugin_dir_path( CIVICRM_WP_EVENT_ORGANISER_FILE ) );
}



/*
--------------------------------------------------------------------------------
CiviCRM_WP_Event_Organiser Class

return new WP_Error('eo_error',__('Start date not provided.','eventorganiser'));
--------------------------------------------------------------------------------
*/

class CiviCRM_WP_Event_Organiser {
	
	/** 
	 * properties
	 */
	
	// Admin/DB class
	public $db;
	
	// CiviCRM utilities class
	public $civi;
	
	// Event Organiser utilities class
	public $eo;
	
	// Event Organiser venue utilities class
	public $eo_venue;
	
	
	
	/** 
	 * @description: initialises this object
	 * @return object
	 */
	function __construct() {
		
		// initialise
		$this->initialise();
		
		// use translation files
		add_action( 'plugins_loaded', array( $this, 'enable_translation' ) );
		
		// --<
		return $this;
		
	}
	
	
	
	/**
	 * @description: do stuff on plugin init
	 * @return nothing
	 */
	public function initialise() {
		
		// load our Admin/DB class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'civicrm-event-organiser-admin.php' );
		
		// initialise
		$this->db = new CiviCRM_WP_Event_Organiser_Admin;
		
		// load our CiviCRM utility functions class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'civicrm-event-organiser-civi.php' );
		
		// initialise
		$this->civi = new CiviCRM_WP_Event_Organiser_CiviCRM;
		
		// load our Event Organiser utility functions class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'civicrm-event-organiser-eo.php' );
		
		// initialise
		$this->eo = new CiviCRM_WP_Event_Organiser_EO;
		
		// load our Event Organiser Venue utility functions class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'civicrm-event-organiser-eo-venue.php' );
		
		// initialise
		$this->eo_venue = new CiviCRM_WP_Event_Organiser_EO_Venue;
		
		// store references
		$this->db->set_references( $this );
		$this->civi->set_references( $this );
		$this->eo->set_references( $this );
		$this->eo_venue->set_references( $this );
		
	}
	
	
		
	/**
	 * @description: do stuff on plugin activation
	 * @return nothing
	 */
	public function activate() {
		
	}
	
	
		
	/**
	 * @description: do stuff on plugin deactivation
	 * @return nothing
	 */
	public function deactivate() {
		
	}
	
	
	
	//##########################################################################
	
	
	
	/** 
	 * @description: load translation files
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 * @return nothing
	 */
	public function enable_translation() {
		
		// not used, as there are no translations as yet
		load_plugin_textdomain(
			
			// unique name
			'civicrm-event-organiser', 
			
			// deprecated argument
			false,
			
			// relative path to directory containing translation files
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
			
		);
		
	}
	
	
	
} // class ends






/**
 * @description: initialise our plugin after CiviCRM initialises
 */
function civicrm_wp_event_organiser_init() {
	
	// declare as global
	global $civicrm_wp_event_organiser;
	
	// init plugin
	$civicrm_wp_event_organiser = new CiviCRM_WP_Event_Organiser;
	
}

// add action for plugin init
add_action( 'civicrm_instance_loaded', 'civicrm_wp_event_organiser_init' );






