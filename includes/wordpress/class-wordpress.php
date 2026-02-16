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
	 * @param CiviCRM_Event_Organiser $parent The parent object.
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

	/**
	 * Replaces paragraph elements with double line-breaks.
	 *
	 * This is an attempt to reverse the behaviour of the wpautop() function found
	 * in WordPress which converts double line-breaks to paragraphs. Handy when you
	 * want to (mostly) undo whatever it did.
	 *
	 * Modified from code by Scott Thomason.
	 *
	 * @link https://scotthom.com/unautop-undo-wordpress-wpautop/
	 * @link https://core.trac.wordpress.org/ticket/25615
	 *
	 * @since 0.8.2
	 * @since 0.8.6 Switched to string replacement method.
	 *
	 * @param string $text The string to match paragraphs tags in.
	 * @param bool   $br (Optional) Whether to process line breaks.
	 * @return str
	 */
	public function unautop( $text, $br = true ) {

		// Bail if there's nothing to parse.
		if ( trim( $text ) === '' ) {
			return '';
		}

		// Remove new lines and carriage returns.
		$text = str_replace( "\n", '', $text );
		$text = str_replace( "\r", '', $text );

		// Maybe replace line breaks.
		if ( $br ) {
			$text = str_ireplace( [ '<br>', '<br/>', '<br />' ], "\n", $text );
		}

		// Convert paragraph tags to new lines and clean up.
		$text = str_ireplace( '<p>', "\n", $text );
		$text = str_ireplace( '</p>', "\n\n", $text );
		$text = str_replace( "\n\n\n", "\n\n", $text );

		// --<
		return $text;

	}

	/**
	 * Initialises the WordPress Filesystem.
	 *
	 * @since 0.8.2
	 *
	 * @return WP_Filesystem|bool The WordPress Filesystem object if intialised, false otherwise.
	 */
	public function filesystem_init() {

		global $wp_filesystem;

		// If not yet intialised.
		if ( ! $wp_filesystem || ! is_object( $wp_filesystem ) ) {

			// Require file if init function is unavailable.
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Suppress output to get direct access credentials.
			ob_start();
			$credentials = request_filesystem_credentials( '' );
			ob_end_clean();

			// Bail if init fails for some reason.
			if ( false === $credentials || ! WP_Filesystem( $credentials ) ) {
				return false;
			}

		}

		// --<
		return $wp_filesystem;

	}

}
