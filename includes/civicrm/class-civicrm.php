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
		add_action( 'civicrm_config', [ $this, 'register_form_directory' ] );

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
	 * Finds out if a CiviCRM Component is enabled.
	 *
	 * @since 0.8.2
	 *
	 * @param string $component The name of the CiviCRM Component, e.g. 'CiviContribute'.
	 * @return bool $active True if the Component is enabled, false otherwise.
	 */
	public function component_is_enabled( $component ) {

		// Init return.
		$active = false;

		// Bail if we can't initialise CiviCRM.
		if ( ! $this->is_active() ) {
			return $active;
		}

		// Get the Component array. CiviCRM handles caching.
		$components = CRM_Core_Component::getEnabledComponents();

		// Override if Component is active.
		if ( array_key_exists( $component, $components ) ) {
			$active = true;
		}

		// --<
		return $active;

	}

	/**
	 * Gets the ID of a CiviCRM Component.
	 *
	 * @since 0.8.2
	 *
	 * @param string $component The name of the CiviCRM Component, e.g. 'CiviContribute'.
	 * @return int|bool $component_id The ID of the CiviCRM Component, false otherwise.
	 */
	public function component_get_id( $component ) {

		// Init return.
		$component_id = false;

		// Bail if we can't initialise CiviCRM.
		if ( ! $this->is_active() ) {
			return $component_id;
		}

		// Get the Component array. CiviCRM handles caching.
		$components = CRM_Core_Component::getEnabledComponents();

		// Override if Component is active.
		if ( array_key_exists( $component, $components ) ) {
			$component_id = $components[ $component ]->componentID;
		}

		// --<
		return $component_id;

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

	/**
	 * Gets the CiviCRM logo.
	 *
	 * @since 0.8.2
	 *
	 * @return string $logo The CiviCRM logo, or false on failure.
	 */
	public function logo_get() {

		// Init return.
		static $logo;
		if ( isset( $logo ) ) {
			return $logo;
		}

		// Bail if the CiviCRM constant isn't set.
		if ( ! defined( 'CIVICRM_PLUGIN_DIR' ) ) {
			return false;
		}

		// Get the WordPress filesystem object.
		$wp_filesystem = $this->plugin->wordpress->filesystem_init();
		if ( ! $wp_filesystem ) {
			return false;
		}

		// Get the CiviCRM logo.
		$civicrm_logo = $wp_filesystem->get_contents( CIVICRM_PLUGIN_DIR . 'assets/images/civilogo.svg.b64' );
		if ( ! $civicrm_logo ) {
			return false;
		}

		// Remove stray whitespace.
		$logo = str_replace( "\n", '', $civicrm_logo );

		// --<
		return $logo;

	}

	/**
	 * Gets a CiviCRM Setting.
	 *
	 * @since 0.8.2
	 *
	 * @param string $name The name of the CiviCRM Setting.
	 * @return mixed $setting The value of the CiviCRM Setting, or false on failure.
	 */
	public function setting_get( $name ) {

		// Init return.
		$setting = false;

		// Init CiviCRM or bail.
		if ( ! $this->is_active() ) {
			return $setting;
		}

		// Construct params.
		$params = [
			'version'    => 3,
			'sequential' => 1,
			'name'       => $name,
		];

		// Call the CiviCRM API.
		$setting = civicrm_api( 'Setting', 'getvalue', $params );

		// Convert if the value has the special CiviCRM array-like format.
		if ( is_string( $setting ) && false !== strpos( $setting, CRM_Core_DAO::VALUE_SEPARATOR ) ) {
			$setting = CRM_Utils_Array::explodePadded( $setting );
		}

		// --<
		return $setting;

	}

	/**
	 * Gets a CiviCRM Option Group by name.
	 *
	 * @since 0.5
	 *
	 * @param string $name The name of the Option Group.
	 * @return array $option_group The array of Option Group data.
	 */
	public function option_group_get( $name ) {

		// Only do this once per named Option Group.
		static $pseudocache;
		if ( isset( $pseudocache[ $name ] ) ) {
			return $pseudocache[ $name ];
		}

		// Init return.
		$options = [];

		// Init CiviCRM or bail.
		if ( ! $this->is_active() ) {
			return $options;
		}

		// Define query params.
		$params = [
			'name'    => $name,
			'version' => 3,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionGroup', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $options;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $options;
		}

		// The result set should contain only one item.
		$options = array_pop( $result['values'] );

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[ $name ] ) ) {
			$pseudocache[ $name ] = $options;
		}

		// --<
		return $options;

	}

	/**
	 * Gets CiviCRM UFMatch data.
	 *
	 * Get UFMatch by CiviCRM "contact_id" or WordPress "user_id".
	 *
	 * It's okay not to find a UFMatch entry, so use "get" instead of "getsingle"
	 * and only log when there's a genuine API error.
	 *
	 * @since 0.6.8
	 *
	 * @param integer $id The CiviCRM Contact ID or WordPress User ID.
	 * @param string  $property Either 'contact_id' or 'uf_id'.
	 * @return array $result The UFMatch data, or empty array on failure.
	 */
	public function get_ufmatch( $id, $property ) {

		$ufmatch = [];

		// Bail if CiviCRM is not active.
		if ( ! $this->is_active() ) {
			return $ufmatch;
		}

		// Bail if there's a problem with the param.
		if ( ! in_array( $property, [ 'contact_id', 'uf_id' ], true ) ) {
			return $ufmatch;
		}

		// Build params.
		$params = [
			'version'    => 3,
			'sequential' => 1,
			$property    => $id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'UFMatch', 'get', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $ufmatch;

		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $ufmatch;
		}

		// The result set should contain only one item.
		$ufmatch = array_pop( $result['values'] );

		return $ufmatch;

	}

}
