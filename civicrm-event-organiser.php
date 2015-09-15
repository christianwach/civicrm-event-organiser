<?php
/*
--------------------------------------------------------------------------------
Plugin Name: CiviCRM Event Organiser
Description: A WordPress plugin for syncing Event Organiser plugin Events with CiviCRM Events so they play nicely with BuddyPress Groups and Group Hierarchies
Version: 0.2
Author: Christian Wach
Author URI: http://haystack.co.uk
Plugin URI: http://haystack.co.uk
--------------------------------------------------------------------------------
*/



// set our debug flag here
define( 'CIVICRM_WP_EVENT_ORGANISER_DEBUG', false );

// set our version here
define( 'CIVICRM_WP_EVENT_ORGANISER_VERSION', '0.2' );

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
	 * Properties
	 */

	// Taxonomy class
	public $taxonomy;

	// Admin/DB class
	public $db;

	// CiviCRM utilities class
	public $civi;

	// Event Organiser utilities class
	public $eo;

	// Event Organiser venue utilities class
	public $eo_venue;



	/**
	 * Initialises this object
	 *
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
	 * Do stuff on plugin init
	 *
	 * @return void
	 */
	public function initialise() {

		// load our Taxonomy class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'civicrm-event-organiser-taxonomy.php' );

		// initialise
		$this->taxonomy = new CiviCRM_WP_Event_Organiser_Taxonomy;

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
		$this->taxonomy->set_references( $this );
		$this->db->set_references( $this );
		$this->civi->set_references( $this );
		$this->eo->set_references( $this );
		$this->eo_venue->set_references( $this );

	}



	/**
	 * Do stuff on plugin activation
	 *
	 * @return void
	 */
	public function activate() {

	}



	/**
	 * Do stuff on plugin deactivation
	 *
	 * @return void
	 */
	public function deactivate() {

	}



	//##########################################################################



	/**
	 * Load translation files
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @return void
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



// declare as global
global $civicrm_wp_event_organiser;

// init plugin
$civicrm_wp_event_organiser = new CiviCRM_WP_Event_Organiser;



/**
 * Utility to add link to settings page
 *
 * @param array $links The existing links array
 * @param str $file The name of the plugin file
 * @return array $links The modified links array
 */
function civicrm_wp_event_organiser_plugin_action_links( $links, $file ) {

	// add settings link
	if ( $file == plugin_basename( dirname( __FILE__ ) . '/civicrm-event-organiser.php' ) ) {

		// is this Network Admin?
		if ( is_network_admin() ) {
			$link = add_query_arg( array( 'page' => 'civi_eo_admin_page' ), network_admin_url( 'settings.php' ) );
		} else {
			$link = add_query_arg( array( 'page' => 'civi_eo_admin_page' ), admin_url( 'options-general.php' ) );
		}

		// add settings link
		$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Settings', 'civicrm-event-organiser' ) . '</a>';

	}

	// --<
	return $links;

}

// add filters for the above
add_filter( 'network_admin_plugin_action_links', 'civicrm_wp_event_organiser_plugin_action_links', 10, 2 );
add_filter( 'plugin_action_links', 'civicrm_wp_event_organiser_plugin_action_links', 10, 2 );



