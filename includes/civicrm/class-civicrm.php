<?php
/**
 * CiviCRM Class.
 *
 * Handles interactions with CiviCRM.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Class.
 *
 * A class that encapsulates interactions with CiviCRM.
 *
 * @since 0.1
 */
class CEO_CiviCRM {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * Event object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CEO_CiviCRM_Event
	 */
	public $event;

	/**
	 * Location object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CEO_CiviCRM_Location
	 */
	public $location;

	/**
	 * Registration object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CEO_CiviCRM_Registration
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
		add_action( 'ceo/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Perform initialisation tasks.
	 *
	 * @since 0.7
	 */
	public function initialise() {

		// Bootstrap object.
		$this->include_files();
		$this->setup_objects();
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
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm/class-civicrm-event.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm/class-civicrm-location.php';
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/civicrm/class-civicrm-registration.php';

	}

	/**
	 * Set up objects.
	 *
	 * @since 0.7
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->event        = new CEO_CiviCRM_Event( $this );
		$this->location     = new CEO_CiviCRM_Location( $this );
		$this->registration = new CEO_CiviCRM_Registration( $this );

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
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_include_path
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

		// Check CiviCRM permissions. Deny by default.
		$permitted = false;
		if ( CRM_Core_Permission::check( $permission ) ) {
			$permitted = true;
		}

		/**
		 * Filter the CiviCRM permission.
		 *
		 * @since 0.3.4
		 * @deprecated 0.8.0 Use the {@see 'ceo/civicrm/permitted'} filter instead.
		 *
		 * @param bool $permitted True if allowed, false otherwise.
		 * @param str $permission The CiviCRM permission string.
		 */
		$permitted = apply_filters_deprecated( 'civicrm_event_organiser_permitted', [ $permitted, $permission ], '0.8.0', 'ceo/civicrm/permitted' );

		/**
		 * Filter the CiviCRM permission.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $permitted True if allowed, false otherwise.
		 * @param str $permission The CiviCRM permission string.
		 */
		return apply_filters( 'ceo/civicrm/permitted', $permitted, $permission );

	}

	/**
	 * Utility for de-nullifying CiviCRM data.
	 *
	 * @since 0.7.3
	 *
	 * @param mixed $value The existing value.
	 * @return mixed $value The cleaned value.
	 */
	public function denullify( $value ) {

		// Catch inconsistent CiviCRM "empty-ish" values.
		if ( ! empty( $value ) && ( 'null' === $value || 'NULL' === $value ) ) {
			$value = '';
		}

		// --<
		return $value;

	}

}
