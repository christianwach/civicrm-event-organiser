<?php
/**
 * CiviCRM Event Organiser
 *
 * Plugin Name:       CiviCRM Event Organiser
 * Description:       Keeps Event Organiser plugin Events in sync with CiviCRM Events.
 * Version:           0.8.0a
 * Plugin URI:        https://github.com/christianwach/civicrm-event-organiser
 * GitHub Plugin URI: https://github.com/christianwach/civicrm-event-organiser
 * Author:            Christian Wach
 * Author URI:        https://haystack.co.uk
 * Requires at least: 4.9
 * Requires PHP:      7.3
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       civicrm-event-organiser
 * Domain Path:       /languages
 *
 * @package CiviCRM_WP_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Set our version here.
define( 'CIVICRM_WP_EVENT_ORGANISER_VERSION', '0.8.0a' );

// Store reference to this file.
if ( ! defined( 'CIVICRM_WP_EVENT_ORGANISER_FILE' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_FILE', __FILE__ );
}

// Store URL to this plugin's directory.
if ( ! defined( 'CIVICRM_WP_EVENT_ORGANISER_URL' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_URL', plugin_dir_url( CIVICRM_WP_EVENT_ORGANISER_FILE ) );
}

// Store PATH to this plugin's directory.
if ( ! defined( 'CIVICRM_WP_EVENT_ORGANISER_PATH' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_PATH', plugin_dir_path( CIVICRM_WP_EVENT_ORGANISER_FILE ) );
}

// Set debug constant.
if ( ! defined( 'CIVICRM_WP_EVENT_ORGANISER_DEBUG' ) ) {
	define( 'CIVICRM_WP_EVENT_ORGANISER_DEBUG', false );
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
	 * Admin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_Admin
	 */
	public $db;

	/**
	 * Mapping object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_Mapping
	 */
	public $mapping;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_CiviCRM
	 */
	public $civi;

	/**
	 * Event Organiser object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_EO
	 */
	public $eo;

	/**
	 * Event Organiser Venue object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_EO_Venue
	 */
	public $eo_venue;

	/**
	 * Taxonomy Sync object.
	 *
	 * @since 0.4.2
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_Taxonomy
	 */
	public $taxonomy;

	/**
	 * Shortcodes object.
	 *
	 * @since 0.6.3
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_Shortcodes
	 */
	public $shortcodes;

	/**
	 * Term Description object.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_Term_Description
	 */
	public $term_html;

	/**
	 * CiviCRM Profile Sync compatibility object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_CWPS
	 */
	public $cwps;

	/**
	 * CiviCRM ACF Integration compatibility object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_CAI
	 */
	public $cai;

	/**
	 * Caldera Forms CiviCRM Redirect compatibility object.
	 *
	 * @since 0.5.3
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_CFCR
	 */
	public $cfcr;

	/**
	 * Post Duplicator compatibility object.
	 *
	 * @since 0.7.5
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser_Post_Duplicator
	 */
	public $post_dupe;

	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Use translation files.
		add_action( 'plugins_loaded', [ $this, 'enable_translation' ] );

		// Initialise.
		add_action( 'plugins_loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Bail if Event Organiser plugin is not present.
		if ( ! defined( 'EVENT_ORGANISER_VER' ) ) {
			return;
		}

		// Bail if CiviCRM plugin is not present.
		if ( ! function_exists( 'civi_wp' ) ) {
			return;
		}

		// Bail if CiviCRM is not fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) || ! CIVICRM_INSTALLED ) {
			return false;
		}

		// Include files.
		$this->include_files();

		// Set up objects and references.
		$this->setup_objects();

		/**
		 * Fires when this plugin has fully loaded.
		 *
		 * @since 0.4.1
		 * @deprecated 0.8.0 Use the {@see 'ceo/loaded'} filter instead.
		 */
		do_action_deprecated( 'civicrm_wp_event_organiser_loaded', [], '0.8.0', 'ceo/loaded' );

		/**
		 * Fires when this plugin has fully loaded.
		 *
		 * This action is used internally by this plugin to initialise its objects
		 * and ensures that all includes and setup has occurred beforehand.
		 *
		 * @since 0.8.0
		 */
		do_action( 'ceo/loaded' );

	}

	/**
	 * Include files.
	 *
	 * @since 0.4
	 */
	public function include_files() {

		// Only do this once.
		static $done;
		if ( isset( $done ) && true === $done ) {
			return;
		}

		// Load our core class files.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/class-term-html.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/admin/class-admin.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/class-mapping.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm/class-civicrm.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/eo/class-eo.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/eo/class-eo-venue.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/class-taxonomy.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/class-shortcodes.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/theme-functions.php';

		// Load our compatibility class files.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/class-cwps.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/class-cai.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/class-cfcr.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/class-post-duplicator.php';

		// We're done.
		$done = true;

	}

	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.4
	 */
	public function setup_objects() {

		// Only do this once.
		static $done;
		if ( isset( $done ) && true === $done ) {
			return;
		}

		// Initialise core objects.
		$this->term_html  = new CiviCRM_WP_Event_Organiser_Term_Description( $this );
		$this->db         = new CiviCRM_WP_Event_Organiser_Admin( $this );
		$this->mapping    = new CiviCRM_WP_Event_Organiser_Mapping( $this );
		$this->civi       = new CiviCRM_WP_Event_Organiser_CiviCRM( $this );
		$this->eo         = new CiviCRM_WP_Event_Organiser_EO( $this );
		$this->eo_venue   = new CiviCRM_WP_Event_Organiser_EO_Venue( $this );
		$this->taxonomy   = new CiviCRM_WP_Event_Organiser_Taxonomy( $this );
		$this->shortcodes = new CiviCRM_WP_Event_Organiser_Shortcodes( $this );

		// Initialise compatibility objects.
		$this->cwps      = new CiviCRM_WP_Event_Organiser_CWPS( $this );
		$this->cai       = new CiviCRM_WP_Event_Organiser_CAI( $this );
		$this->cfcr      = new CiviCRM_WP_Event_Organiser_CFCR( $this );
		$this->post_dupe = new CiviCRM_WP_Event_Organiser_Post_Duplicator( $this );

		// We're done.
		$done = true;

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

	// -------------------------------------------------------------------------

	/**
	 * Load translation files.
	 *
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 0.1
	 */
	public function enable_translation() {

		// Load plugin translations.
		// phpcs:ignore WordPress.WP.DeprecatedParameters.Load_plugin_textdomainParam2Found
		load_plugin_textdomain(
			'civicrm-event-organiser', // Unique name.
			false, // Deprecated argument.
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' // Relative path to translation files.
		);

	}

	// -------------------------------------------------------------------------

	/**
	 * Check if this plugin is network activated.
	 *
	 * @since 0.7.1
	 *
	 * @return bool $is_network_active True if network activated, false otherwise.
	 */
	public function is_network_activated() {

		// Only need to test once.
		static $is_network_active;

		// Have we done this already?
		if ( isset( $is_network_active ) ) {
			return $is_network_active;
		}

		// If not multisite, it cannot be.
		if ( ! is_multisite() ) {
			$is_network_active = false;
			return $is_network_active;
		}

		// Make sure plugin file is included when outside admin.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Get path from 'plugins' directory to this plugin.
		$this_plugin = plugin_basename( CIVICRM_WP_EVENT_ORGANISER_FILE );

		// Test if network active.
		$is_network_active = is_plugin_active_for_network( $this_plugin );

		// --<
		return $is_network_active;

	}

	/**
	 * Check if CiviCRM is network activated.
	 *
	 * @since 0.7
	 *
	 * @return bool $civicrm_network_active True if network activated, false otherwise.
	 */
	public function is_civicrm_network_activated() {

		// Only need to test once.
		static $civicrm_network_active;
		if ( isset( $civicrm_network_active ) ) {
			return $civicrm_network_active;
		}

		// If not multisite, it cannot be.
		if ( ! is_multisite() ) {
			$civicrm_network_active = false;
			return $civicrm_network_active;
		}

		// If CiviCRM's constant is not defined, we'll never know.
		if ( ! defined( 'CIVICRM_PLUGIN_FILE' ) ) {
			$civicrm_network_active = false;
			return $civicrm_network_active;
		}

		// Make sure plugin file is included when outside admin.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Get path from 'plugins' directory to CiviCRM's directory.
		$civicrm = plugin_basename( CIVICRM_PLUGIN_FILE );

		// Test if network active.
		$civicrm_network_active = is_plugin_active_for_network( $civicrm );

		// --<
		return $civicrm_network_active;

	}

	/**
	 * Check if CiviCRM Admin Utilities is hiding CiviCRM except on main site.
	 *
	 * @since 0.7
	 *
	 * @return bool $civicrm_hidden True if CAU is hiding CiviCRM, false otherwise.
	 */
	public function is_civicrm_main_site_only() {

		// Only need to test once.
		static $civicrm_hidden;
		if ( isset( $civicrm_hidden ) ) {
			return $civicrm_hidden;
		}

		// If not multisite, it cannot be.
		if ( ! is_multisite() ) {
			$civicrm_hidden = false;
			return $civicrm_hidden;
		}

		// Bail if CiviCRM is not network-activated.
		if ( ! $this->is_civicrm_network_activated() ) {
			$civicrm_hidden = false;
			return $civicrm_hidden;
		}

		// If CAU's constant is not defined, we'll never know.
		if ( ! defined( 'CIVICRM_ADMIN_UTILITIES_VERSION' ) ) {
			$civicrm_hidden = false;
			return $civicrm_hidden;
		}

		// Grab the CAU plugin reference.
		$cau = civicrm_au();

		// Bail if CAU's multisite object is not defined.
		if ( empty( $cau->multisite ) ) {
			$civicrm_hidden = false;
			return $civicrm_hidden;
		}

		// Bail if not hidden.
		if ( $cau->multisite->setting_get( 'main_site_only', '0' ) === '0' ) {
			$civicrm_hidden = false;
			return $civicrm_hidden;
		}

		// CAU is hiding CiviCRM.
		$civicrm_hidden = true;
		return $civicrm_hidden;

	}

	/**
	 * Write to the error log.
	 *
	 * @since 0.8.0
	 *
	 * @param array $data The data to write to the log file.
	 */
	public function log_error( $data = [] ) {

		// Skip if not debugging.
		if ( CIVICRM_WP_EVENT_ORGANISER_DEBUG === false ) {
			return;
		}

		// Skip if empty.
		if ( empty( $data ) ) {
			return;
		}

		// Format data.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$error = print_r( $data, true );

		// Write to log file.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $error );

	}

}

/**
 * Utility to get a reference to this plugin.
 *
 * @since 0.2.2
 *
 * @return object $civicrm_wp_event_organiser The plugin reference.
 */
function civicrm_eo() {

	// Return instance.
	global $civicrm_wp_event_organiser;

	// Instantiate plugin if not yet instantiated.
	if ( ! isset( $civicrm_wp_event_organiser ) ) {
		$civicrm_wp_event_organiser = new CiviCRM_WP_Event_Organiser();
	}

	return $civicrm_wp_event_organiser;

}

// Bootstrap plugin.
civicrm_eo();

/**
 * Utility to add link to settings page.
 *
 * @since 0.1
 *
 * @param array $links The existing links array.
 * @param str   $file The name of the plugin file.
 * @return array $links The modified links array.
 */
function civicrm_wp_event_organiser_plugin_action_links( $links, $file ) {

	// Bail if Event Organiser plugin is not present.
	if ( ! defined( 'EVENT_ORGANISER_VER' ) ) {
		return $links;
	}

	// Bail if CiviCRM plugin is not present.
	if ( ! function_exists( 'civi_wp' ) ) {
		return $links;
	}

	// Add links only when CiviCRM is fully installed.
	if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
		return $links;
	}
	if ( ! CIVICRM_INSTALLED ) {
		return $links;
	}

	// Add settings link.
	if ( plugin_basename( dirname( __FILE__ ) . '/civicrm-event-organiser.php' ) === $file ) {

		// Is this Network Admin?
		if ( is_network_admin() ) {
			$link = add_query_arg( [ 'page' => 'civi_eo_parent' ], network_admin_url( 'settings.php' ) );
		} else {
			$link = add_query_arg( [ 'page' => 'civi_eo_parent' ], admin_url( 'admin.php' ) );
		}

		// Add settings link.
		$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Settings', 'civicrm-event-organiser' ) . '</a>';

		// Add Paypal link.
		$paypal  = 'https://www.paypal.me/interactivist';
		$links[] = '<a href="' . $paypal . '" target="_blank">' . __( 'Donate!', 'civicrm-event-organiser' ) . '</a>';

	}

	// --<
	return $links;

}

// Add filters for the above.
add_filter( 'network_admin_plugin_action_links', 'civicrm_wp_event_organiser_plugin_action_links', 10, 2 );
add_filter( 'plugin_action_links', 'civicrm_wp_event_organiser_plugin_action_links', 10, 2 );
