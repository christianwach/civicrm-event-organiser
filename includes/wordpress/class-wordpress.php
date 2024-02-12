<?php
/**
 * WordPress Class.
 *
 * Handles loading Event plugin classes.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WordPress Class.
 *
 * A class that encapsulates loading Event plugin classes.
 *
 * @since 0.8.0
 */
class CEO_WordPress {

	/**
	 * Plugin object.
	 *
	 * @since 0.8.0
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * Term Description object.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var CEO_WordPress_Term_Description
	 */
	public $term_html;

	/**
	 * Taxonomy Sync object.
	 *
	 * @since 0.4.2
	 * @access public
	 * @var CEO_WordPress_Taxonomy
	 */
	public $taxonomy;

	/**
	 * Shortcodes object.
	 *
	 * @since 0.6.3
	 * @access public
	 * @var CEO_WordPress_Shortcodes
	 */
	public $shortcodes;

	/**
	 * Event Organiser object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CEO_WordPress_EO
	 */
	public $eo;

	/**
	 * Event Organiser Venue object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CEO_WordPress_EO_Venue
	 */
	public $eo_venue;

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin = $parent;

		// Add Event Organiser hooks when plugin is loaded.
		add_action( 'ceo/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Perform initialisation tasks.
	 *
	 * @since 0.8.0
	 */
	public function initialise() {

		// Bootstrap object.
		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		/**
		 * Fires when this class is loaded.
		 *
		 * @since 0.8.0
		 */
		do_action( 'ceo/wordpress/loaded' );

	}

	/**
	 * Include files.
	 *
	 * @since 0.8.0
	 */
	public function include_files() {

		// Include general classes.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/wordpress/class-wordpress-term-html.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/wordpress/class-wordpress-taxonomy.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/wordpress/class-wordpress-shortcodes.php';

		// Include Event plugin files.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/wordpress/eo/class-wordpress-eo.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/wordpress/eo/class-wordpress-eo-venue.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/wordpress/eo/theme-functions.php';

	}

	/**
	 * Set up objects.
	 *
	 * @since 0.8.0
	 */
	public function setup_objects() {

		// Instantiate general objects.
		$this->term_html  = new CEO_WordPress_Term_Description( $this );
		$this->taxonomy   = new CEO_WordPress_Taxonomy( $this );
		$this->shortcodes = new CEO_WordPress_Shortcodes( $this );

		// Instantiate Event plugin objects.
		$this->eo       = new CEO_WordPress_EO( $this );
		$this->eo_venue = new CEO_WordPress_EO_Venue( $this );

		// The Event Organiser classes need "aliases" for backpat.
		$this->plugin->eo       = $this->eo;
		$this->plugin->eo_venue = $this->eo_venue;

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

	}

}
