<?php
/**
 * Compatibility Class.
 *
 * Handles third-party plugin compatibility.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Compatibility Class.
 *
 * A class that encapsulates third-party plugin compatibility.
 *
 * @since 0.8.0
 */
class CEO_Compat {

	/**
	 * Plugin object.
	 *
	 * @since 0.8.0
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * CiviCRM Profile Sync compatibility object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var CEO_Compat_CWPS
	 */
	public $cwps;

	/**
	 * CiviCRM ACF Integration compatibility object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var CEO_Compat_CAI
	 */
	public $cai;

	/**
	 * Caldera Forms CiviCRM Redirect compatibility object.
	 *
	 * @since 0.5.3
	 * @access public
	 * @var CEO_Compat_CFCR
	 */
	public $cfcr;

	/**
	 * Post Duplicator compatibility object.
	 *
	 * @since 0.7.5
	 * @access public
	 * @var CEO_Compat_Post_Duplicator
	 */
	public $post_dupe;

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

		// Load our compatibility class files.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/class-compat-cwps.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/class-compat-cai.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/class-compat-cfcr.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/class-compat-post-duplicator.php';

	}

	/**
	 * Set up objects.
	 *
	 * @since 0.8.0
	 */
	public function setup_objects() {

		// Initialise compatibility objects.
		$this->cwps      = new CEO_Compat_CWPS( $this );
		$this->cai       = new CEO_Compat_CAI( $this );
		$this->cfcr      = new CEO_Compat_CFCR( $this );
		$this->post_dupe = new CEO_Compat_Post_Duplicator( $this );

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

	}

}
