<?php
/**
 * ACF "CiviCRM Event ID Field" Class.
 *
 * Provides a "CiviCRM Event ID Field" Custom ACF Field in ACF 5+.
 *
 * @package CiviCRM_WP_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Custom ACF Field Type - CiviCRM Event ID Field.
 *
 * A class that encapsulates a "CiviCRM Event ID Field" Custom ACF Field in ACF 5+.
 *
 * @since 0.7.3
 */
class CEO_ACF_Custom_CiviCRM_Event_ID_Field extends acf_field {

	/**
	 * Plugin object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * ACF Loader object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $acf_loader The ACF Loader object.
	 */
	public $acf_loader;

	/**
	 * Advanced Custom Fields object.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var object $cpt The Advanced Custom Fields object.
	 */
	public $acf;

	/**
	 * Field Type name.
	 *
	 * Single word, no spaces. Underscores allowed.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var string $name The Field Type name.
	 */
	public $name = 'ceo_civicrm_event_id';

	/**
	 * Field Type label.
	 *
	 * This must be populated in the class constructor because it is translatable.
	 *
	 * Multiple words, can include spaces, visible when selecting a Field Type.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var string $label The Field Type label.
	 */
	public $label = '';

	/**
	 * Field Type category.
	 *
	 * Choose between the following categories:
	 *
	 * basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
	 *
	 * @since 0.7.3
	 * @access public
	 * @var string $label The Field Type category.
	 */
	public $category = 'CiviCRM';

	/**
	 * Field Type defaults.
	 *
	 * Array of default settings which are merged into the Field object.
	 * These are used later in settings.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var array $defaults The Field Type defaults.
	 */
	public $defaults = [];

	/**
	 * Field Type settings.
	 *
	 * Contains "version", "url" and "path" as references for use with assets.
	 *
	 * @since 0.7.3
	 * @access public
	 * @var array $settings The Field Type settings.
	 */
	public $settings = [
		'version' => CIVICRM_WP_EVENT_ORGANISER_VERSION,
		'url' => CIVICRM_WP_EVENT_ORGANISER_URL,
		'path' => CIVICRM_WP_EVENT_ORGANISER_PATH,
	];

	/**
	 * Field Type translations.
	 *
	 * This must be populated in the class constructor because it is translatable.
	 *
	 * Array of strings that are used in JavaScript. This allows JS strings
	 * to be translated in PHP and loaded via:
	 *
	 * var message = acf._e( 'civicrm_contact', 'error' );
	 *
	 * @since 0.7.3
	 * @access public
	 * @var array $l10n The Field Type translations.
	 */
	public $l10n = [];

	/**
	 * Sets up the Field Type.
	 *
	 * @since 0.7.3
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store references to objects.
		$this->plugin = $parent->plugin;

		// Define label.
		$this->label = __( 'CiviCRM Event: Event ID (Read Only)', 'civicrm-event-organiser' );

		// Define category.
		if ( function_exists( 'acfe' ) ) {
			$this->category = __( 'CiviCRM Post Type Sync only', 'civicrm-event-organiser' );
		} else {
			$this->category = __( 'CiviCRM Post Type Sync', 'civicrm-event-organiser' );
		}

		// Define translations.
		$this->l10n = [];

		// Call parent.
		parent::__construct();

	}

	/**
	 * Creates the HTML interface for this Field Type.
	 *
	 * @since 0.7.3
	 *
	 * @param array $field The Field being rendered.
	 */
	public function render_field( $field ) {

		// Change Field into a simple "number" Field.
		$field['type'] = 'number';
		$field['readonly'] = 1;
		$field['allow_null'] = 0;
		$field['prepend'] = '';
		$field['append'] = '';
		$field['step'] = '';

		// Populate Field.
		if ( ! empty( $field['value'] ) ) {

			// Cast value to an integer.
			$event_id = (int) $field['value'];

			// Apply Event ID to Field.
			$field['value'] = $event_id;

		}

		// Render.
		acf_render_field( $field );

	}

	/**
	 * This filter is applied to the $value after it is loaded from the database.
	 *
	 * @since 0.7.3
	 *
	 * @param mixed          $value The value found in the database.
	 * @param integer|string $post_id The ACF "Post ID" from which the value was loaded.
	 * @param array          $field The Field array holding all the Field options.
	 * @return mixed $value The modified value.
	 */
	public function load_value( $value, $post_id, $field ) {

		// Assign Event ID for this Field if empty.
		if ( empty( $value ) ) {

			// Get CiviCRM Events.
			$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );

			// If there is at least one CiviCRM Event.
			if ( ! empty( $civi_events ) ) {

				// Use the first Event ID.
				$event_id = array_pop( $civi_events );

				// Overwrite value.
				$value = (int) $event_id;

			}

		}

		// --<
		return $value;

	}

	/**
	 * This filter is applied to the $value before it is saved in the database.
	 *
	 * @since 0.7.3
	 *
	 * @param mixed   $value The value found in the database.
	 * @param integer $post_id The Post ID from which the value was loaded.
	 * @param array   $field The Field array holding all the Field options.
	 * @return mixed $value The modified value.
	 */
	public function update_value( $value, $post_id, $field ) {

		// Assign Event ID for this Field if empty.
		if ( empty( $value ) ) {

			// Get CiviCRM Events.
			$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );

			// If there is at least one CiviCRM Event.
			if ( ! empty( $civi_events ) ) {

				// Use the first Event ID.
				$event_id = array_pop( $civi_events );

				// Overwrite value.
				$value = (int) $event_id;

			}

		}

		// --<
		return $value;

	}

}
