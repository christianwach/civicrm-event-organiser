<?php /*
--------------------------------------------------------------------------------
Plugin Name: CiviCRM Event Organiser
Description: Sync Event Organiser Events with CiviCRM Events.
Version: 0.3.4
Author: Christian Wach
Author URI: http://haystack.co.uk
Plugin URI: https://github.com/christianwach/civicrm-event-organiser
Text Domain: civicrm-event-organiser
Domain Path: /languages
--------------------------------------------------------------------------------
*/



// set our version here
define( 'CIVICRM_WP_EVENT_ORGANISER_VERSION', '0.3.4' );

// store reference to this file
if ( ! defined( 'CIVICRM_WP_EVENT_ORGANISER_FILE' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_FILE', __FILE__ );
}

// store URL to this plugin's directory
if ( ! defined( 'CIVICRM_WP_EVENT_ORGANISER_URL' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_URL', plugin_dir_url( CIVICRM_WP_EVENT_ORGANISER_FILE ) );
}
// store PATH to this plugin's directory
if ( ! defined( 'CIVICRM_WP_EVENT_ORGANISER_PATH' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_PATH', plugin_dir_path( CIVICRM_WP_EVENT_ORGANISER_FILE ) );
}



/**
 * CiviCRM Event Organiser Class.
 *
 * A class that encapsulates this plugin's functionality.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser {

	/**
	 * Admin/DB object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $db The admin/db object
	 */
	public $db;

	/**
	 * CiviCRM utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $civi The CiviCRM utilities object
	 */
	public $civi;

	/**
	 * Event Organiser utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $eo The Event Organiser utilities object
	 */
	public $eo;

	/**
	 * Event Organiser venue utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $eo_venue The Event Organiser venue utilities object
	 */
	public $eo_venue;

	/**
	 * Taxonomy object.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var object $taxonomy The taxonomy HTML descriptions object
	 */
	public $taxonomy;



	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// initialise
		$this->initialise();

		// use translation files
		add_action( 'plugins_loaded', array( $this, 'enable_translation' ) );

	}



	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// load our Taxonomy class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm-event-organiser-taxonomy.php' );

		// initialise
		$this->taxonomy = new CiviCRM_WP_Event_Organiser_Taxonomy;

		// load our Admin/DB class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm-event-organiser-admin.php' );

		// initialise
		$this->db = new CiviCRM_WP_Event_Organiser_Admin;

		// load our CiviCRM utility functions class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm-event-organiser-civi.php' );

		// initialise
		$this->civi = new CiviCRM_WP_Event_Organiser_CiviCRM;

		// load our Event Organiser utility functions class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm-event-organiser-eo.php' );

		// initialise
		$this->eo = new CiviCRM_WP_Event_Organiser_EO;

		// load our Event Organiser Venue utility functions class
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm-event-organiser-eo-venue.php' );

		// initialise
		$this->eo_venue = new CiviCRM_WP_Event_Organiser_EO_Venue;

		// load our template functions
		require( CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm-event-organiser-functions.php' );

		// store references
		$this->taxonomy->set_references( $this );
		$this->db->set_references( $this );
		$this->civi->set_references( $this );
		$this->eo->set_references( $this );
		$this->eo_venue->set_references( $this );

	}



	/**
	 * Do stuff on plugin activation.
	 *
	 * @since 0.1
	 */
	public function activate() {

	}



	/**
	 * Do stuff on plugin deactivation.
	 *
	 * @since 0.1
	 */
	public function deactivate() {

	}



	//##########################################################################



	/**
	 * Load translation files.
	 *
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 0.1
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
 * Utility to get a reference to this plugin.
 *
 * @since 0.2.2
 *
 * @return object $civicrm_wp_event_organiser The plugin reference
 */
function civicrm_eo() {

	// return instance
	global $civicrm_wp_event_organiser;
	return $civicrm_wp_event_organiser;

}


/**
 * Utility to add link to settings page.
 *
 * @since 0.1
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
			$link = add_query_arg( array( 'page' => 'civi_eo_parent' ), network_admin_url( 'settings.php' ) );
		} else {
			$link = add_query_arg( array( 'page' => 'civi_eo_parent' ), admin_url( 'options-general.php' ) );
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



