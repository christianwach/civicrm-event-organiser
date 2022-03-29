<?php
/**
 * CiviCRM Class.
 *
 * Handles interactions with CiviCRM.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser CiviCRM Class.
 *
 * A class that encapsulates interactions with CiviCRM.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_CiviCRM {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Event object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $event The Event object.
	 */
	public $event;

	/**
	 * Location object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $location The Location object.
	 */
	public $location;

	/**
	 * Registration object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $registration The Registration object.
	 */
	public $registration;



	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin = $parent;

		// Add CiviCRM hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Perform initialisation tasks.
	 *
	 * @since 0.7
	 */
	public function initialise() {

		// Include files.
		$this->include_files();

		// Set up objects and references.
		$this->setup_objects();

		// Register hooks.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.7
		 */
		do_action( 'ceo/civicrm/loaded' );

	}

	/**
	 * Include files.
	 *
	 * @since 0.7
	 */
	public function include_files() {

		// Include classes.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm/ceo-civicrm-event.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm/ceo-civicrm-location.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm/ceo-civicrm-registration.php';

	}

	/**
	 * Set up objects.
	 *
	 * @since 0.7
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->event = new CiviCRM_WP_Event_Organiser_CiviCRM_Event( $this );
		$this->location = new CiviCRM_WP_Event_Organiser_CiviCRM_Location( $this );
		$this->registration = new CiviCRM_WP_Event_Organiser_CiviCRM_Registration( $this );

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Register template directory for form amends.
		add_action( 'civicrm_config', [ $this, 'register_form_directory' ], 10 );

	}

	/**
	 * Test if CiviCRM plugin is active.
	 *
	 * @since 0.1
	 *
	 * @return bool True if CiviCRM initialized, false otherwise.
	 */
	public function is_active() {

		// Bail if no CiviCRM init function.
		if ( ! function_exists( 'civi_wp' ) ) {
			return false;
		}

		// Try and init CiviCRM.
		return civi_wp()->initialize();

	}

	/**
	 * Register directory that CiviCRM searches in for our form template file.
	 *
	 * @since 0.1
	 * @since 0.6.3 Renamed.
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_form_directory( &$config ) {

		// Get template instance.
		$template = CRM_Core_Smarty::singleton();

		// Define our custom path.
		$custom_path = CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/civicrm';

		// Add our custom template directory.
		$template->addTemplateDir( $custom_path );

		// Register template directory.
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );

	}

	/**
	 * Check a CiviCRM permission.
	 *
	 * @since 0.3
	 *
	 * @param str $permission The permission string.
	 * @return bool $permitted True if allowed, false otherwise.
	 */
	public function check_permission( $permission ) {

		// Always deny if CiviCRM is not active.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Deny by default.
		$permitted = false;

		// Check CiviCRM permissions.
		if ( CRM_Core_Permission::check( $permission ) ) {
			$permitted = true;
		}

		/**
		 * Return permission but allow overrides.
		 *
		 * @since 0.3.4
		 *
		 * @param bool $permitted True if allowed, false otherwise.
		 * @param str $permission The CiviCRM permission string.
		 * @return bool $permitted True if allowed, false otherwise.
		 */
		return apply_filters( 'civicrm_event_organiser_permitted', $permitted, $permission );

	}

} // Class ends.
