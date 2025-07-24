<?php
/**
 * ACF "CiviCRM Price Set Field" Class.
 *
 * Provides a "CiviCRM Price Set Field" Custom ACF Field in ACF 5+.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Custom ACF Field Type - CiviCRM Price Set Field.
 *
 * A class that encapsulates a "CiviCRM Price Set Field" Custom ACF Field in ACF 5+.
 *
 * @since 0.8.2
 */
class CEO_ACF_Custom_CiviCRM_Price_Set_Quick_Field extends acf_field {

	/**
	 * Plugin object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * Compatibility object.
	 *
	 * @since 0.8.0
	 * @access public
	 * @var CEO_Compat
	 */
	public $compat;

	/**
	 * Field Type name.
	 *
	 * Single word, no spaces. Underscores allowed.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var string
	 */
	public $name = 'ceo_civicrm_price_set_quick';

	/**
	 * Field Type label.
	 *
	 * This must be populated in the class constructor because it is translatable.
	 *
	 * Multiple words, can include spaces, visible when selecting a Field Type.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var string
	 */
	public $label = '';

	/**
	 * Field Type category.
	 *
	 * Choose between the following categories:
	 *
	 * basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
	 *
	 * @since 0.8.2
	 * @access public
	 * @var string
	 */
	public $category = 'CiviCRM';

	/**
	 * Field Type defaults.
	 *
	 * Array of default settings which are merged into the Field object.
	 * These are used later in settings.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var array
	 */
	public $defaults = [];

	/**
	 * Field Type settings.
	 *
	 * Contains "version", "url" and "path" as references for use with assets.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var array
	 */
	public $settings = [
		'version' => CIVICRM_WP_EVENT_ORGANISER_VERSION,
		'url'     => CIVICRM_WP_EVENT_ORGANISER_URL,
		'path'    => CIVICRM_WP_EVENT_ORGANISER_PATH,
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
	 * @since 0.8.2
	 * @access public
	 * @var array
	 */
	public $l10n = [];

	/**
	 * Sets up the Field Type.
	 *
	 * @since 0.8.2
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin = $parent->plugin;
		$this->compat = $parent;

		// Define label.
		$this->label = __( 'CiviCRM Event: Quick Config Price Set', 'civicrm-event-organiser' );

		// Define category.
		if ( function_exists( 'acfe' ) ) {
			$this->category = __( 'CiviCRM Event Organiser Sync only', 'civicrm-event-organiser' );
		} else {
			$this->category = __( 'CiviCRM Event Organiser Sync', 'civicrm-event-organiser' );
		}

		// Define translations.
		$this->l10n = [];

		// Call parent.
		parent::__construct();

	}

	/**
	 * Create extra Settings for this Field Type.
	 *
	 * These extra Settings will be visible when editing a Field.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field being edited.
	 */
	public function render_field_settings( $field ) {

		// Only render Debug Setting Field here in ACF prior to version 6.
		if ( ! version_compare( ACF_MAJOR_VERSION, '6', '>=' ) ) {
			acf_render_field_setting( $field, $this->setting_field_debug_get() );
		}

		// Define Currency setting Field.
		$financial_type_field = [
			'label'             => __( 'CiviCRM Currency', 'civicrm-event-organiser' ),
			'name'              => 'currency',
			'type'              => 'select',
			'instructions'      => __( 'Choose the Currency for this Price Set.', 'civicrm-event-organiser' ),
			'default_value'     => $this->compat->financial->currency_get_default(),
			'placeholder'       => '',
			'allow_null'        => 0,
			'multiple'          => 0,
			'ui'                => 0,
			'required'          => 0,
			'return_format'     => 'value',
			'choices'           => $this->compat->financial->currencies_get_mapped(),
			'conditional_logic' => 0,
		];

		// Now add it.
		acf_render_field_setting( $field, $financial_type_field );

		// Define Financial Type setting Field.
		$financial_type_field = [
			'label'             => __( 'CiviCRM Financial Type', 'civicrm-event-organiser' ),
			'name'              => 'financial_type_id',
			'type'              => 'select',
			'instructions'      => __( 'Choose the Financial Type for this Price Set.', 'civicrm-event-organiser' ),
			'default_value'     => '',
			'placeholder'       => '',
			'allow_null'        => 0,
			'multiple'          => 0,
			'ui'                => 0,
			'required'          => 0,
			'return_format'     => 'value',
			'choices'           => $this->compat->financial->types_get_mapped(),
			'conditional_logic' => 0,
		];

		// Now add it.
		acf_render_field_setting( $field, $financial_type_field );

		// Define Payment Processor setting Field.
		$payment_processor_field = [
			'label'             => __( 'CiviCRM Payment Processor', 'civicrm-event-organiser' ),
			'name'              => 'payment_processor_id',
			'type'              => 'select',
			'instructions'      => __( 'Choose the CiviCRM Payment Processor for transactions.', 'civicrm-event-organiser' ),
			'default_value'     => '',
			'placeholder'       => '',
			'allow_null'        => 0,
			'multiple'          => 0,
			'ui'                => 0,
			'required'          => 0,
			'return_format'     => 'value',
			'choices'           => $this->compat->financial->processors_get_mapped(),
			'conditional_logic' => 0,
		];

		// Now add it.
		acf_render_field_setting( $field, $payment_processor_field );

		// Define Pay Later setting Field.
		$pay_later_field = [
			'label'         => __( 'CiviCRM Pay Later', 'civicrm-event-organiser' ),
			'name'          => 'pay_later',
			'type'          => 'true_false',
			'instructions'  => __( 'Do you want to enable offline payments, e.g. mail in a check, call in a credit card?', 'civicrm-event-organiser' ),
			'ui'            => 1,
			'default_value' => 0,
			'required'      => 0,
		];

		// Now add it.
		acf_render_field_setting( $field, $pay_later_field );

		// Define Pay Later Label setting Field.
		$pay_later_label_field = [
			'label'             => __( 'CiviCRM Pay Later Label', 'civicrm-event-organiser' ),
			'name'              => 'pay_later_label',
			'type'              => 'text',
			'instructions'      => '',
			'required'          => 1,
			'default_value'     => '',
			'placeholder'       => __( 'I will send payment by check', 'civicrm-event-organiser' ),
			'maxlength'         => '',
			'conditional_logic' => [
				[
					[
						'field'    => 'pay_later',
						'operator' => '==',
						'value'    => 1,
					],
				],
			],
		];

		// Now add it.
		acf_render_field_setting( $field, $pay_later_label_field );

		// Define Pay Later Instructions setting Field.
		$pay_later_instructions_field = [
			'label'             => __( 'CiviCRM Pay Later Instructions', 'civicrm-event-organiser' ),
			'name'              => 'pay_later_instructions',
			'type'              => 'wysiwyg',
			'instructions'      => '',
			'required'          => 1,
			'default_value'     => '',
			'tabs'              => 'visual',
			'toolbar'           => 'full',
			'media_upload'      => 0,
			'delay'             => 0,
			'conditional_logic' => [
				[
					[
						'field'    => 'pay_later',
						'operator' => '==',
						'value'    => 1,
					],
				],
			],
		];

		// Now add it.
		acf_render_field_setting( $field, $pay_later_instructions_field );

		// Define Pay Later Billing Address required Field.
		$pay_later_billing_field = [
			'label'             => __( 'CiviCRM Pay Later Billing Address required', 'civicrm-event-organiser' ),
			'name'              => 'pay_later_billing_required',
			'type'              => 'true_false',
			'instructions'      => __( 'Enable this to require those who select the Pay Later option to provide a Billing Name and Address.', 'civicrm-event-organiser' ),
			'ui'                => 1,
			'default_value'     => 0,
			'required'          => 0,
			'conditional_logic' => [
				[
					[
						'field'    => 'pay_later',
						'operator' => '==',
						'value'    => 1,
					],
				],
			],
		];

		// Now add it.
		acf_render_field_setting( $field, $pay_later_billing_field );

	}

	/**
	 * Renders the Field settings used in the "Presentation" tab.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The field settings array.
	 */
	public function render_field_presentation_settings( $field ) {

		// Add the Debug Settings Field.
		acf_render_field_setting( $field, $this->setting_field_debug_get() );

	}

	/**
	 * Creates the HTML interface for this Field Type.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field being rendered.
	 */
	public function render_field( $field ) {

		// Change Field into a "repeater" Field.
		$field['type'] = 'repeater';

		// Render.
		acf_render_field( $field );

	}

	/**
	 * Prepare this Field Type for display.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field being rendered.
	 */
	public function prepare_field( $field ) {

		// Bail when Price Field Value ID should be shown.
		if ( ! empty( $field['show_pfv_id'] ) ) {
			return $field;
		}

		// Add hidden class to element.
		$field['wrapper']['class'] .= ' pfv_id_hidden';

		// --<
		return $field;

	}

	/**
	 * This method is called in the "admin_enqueue_scripts" action on the edit
	 * screen where this Field is created.
	 *
	 * Use this action to add CSS and JavaScript to assist your render_field()
	 * action.
	 *
	 * @since 0.8.2
	 */
	public function input_admin_enqueue_scripts() {

		// Enqueue our JavaScript.
		wp_enqueue_script(
			'acf-input-' . $this->name,
			plugins_url( 'assets/js/wordpress/acf/fields/civicrm-price-set-field.js', CIVICRM_WP_EVENT_ORGANISER_FILE ),
			[ 'acf-pro-input' ],
			CIVICRM_WP_EVENT_ORGANISER_VERSION, // Version.
			true
		);

	}

	/**
	 * This method is called in the admin_head action on the edit screen where
	 * this Field is created.
	 *
	 * Use this action to add CSS and JavaScript to assist your render_field()
	 * action.
	 *
	 * @since 0.8.2
	 */
	public function input_admin_head() {

		echo '
		<style type="text/css">
			/* Hide Repeater column */
			.pfv_id_hidden th[data-key="field_pfv_id"],
			.pfv_id_hidden td.civicrm_pfv_id
			{
				display: none;
			}
		</style>
		';

	}

	/**
	 * This filter is applied to the $value after it is loaded from the database.
	 *
	 * @since 0.8.2
	 *
	 * @param mixed          $value The value found in the database.
	 * @param integer|string $post_id The ACF "Post ID" from which the value was loaded.
	 * @param array          $field The Field array holding all the Field options.
	 * @return mixed $value The modified value.
	 */
	public function load_value( $value, $post_id, $field ) {

		// Make sure we have an array.
		if ( empty( $value ) && ! is_array( $value ) ) {
			$value = [];
		}

		// Strip keys and re-index.
		if ( is_array( $value ) ) {
			$value = array_values( $value );
		}

		// --<
		return $value;

	}

	/**
	 * This filter is applied to the $value before it is saved in the database.
	 *
	 * @since 0.8.2
	 *
	 * @param mixed   $value The value found in the database.
	 * @param integer $post_id The Post ID from which the value was loaded.
	 * @param array   $field The Field array holding all the Field options.
	 * @return mixed $value The modified value.
	 */
	public function update_value( $value, $post_id, $field ) {

		// Make sure we have an array.
		if ( empty( $value ) && ! is_array( $value ) ) {
			$value = [];
		}

		// --<
		return $value;

	}

	/**
	 * This filter is used to perform validation on the value prior to saving.
	 *
	 * All values are validated regardless of the Field's required setting.
	 * This allows you to validate and return messages to the user if the value
	 * is not correct.
	 *
	 * @since 0.8.2
	 *
	 * @param bool   $valid The validation status based on the value and the Field's required setting.
	 * @param mixed  $value The $_POST value.
	 * @param array  $field The Field array holding all the Field options.
	 * @param string $input The corresponding input name for $_POST value.
	 * @return string|bool $valid False if not valid, or string for error message.
	 */
	public function validate_value( $valid, $value, $field, $input ) {

		// Bail if it's not required and is empty.
		if ( 0 === (int) $field['required'] && empty( $value ) ) {
			return $valid;
		}

		// Grab the Fee Labels.
		$fee_labels = wp_list_pluck( $value, 'field_ceo_civicrm_fee_label' );

		// Sanitise array contents.
		array_walk(
			$fee_labels,
			function( &$item ) {
				$item = (string) trim( $item );
			}
		);

		// Check that all Fee Label Fields are populated.
		if ( in_array( '', $fee_labels, true ) ) {
			$valid = __( 'Please make sure all rows have a Fee Label ', 'civicrm-event-organiser' );
			return $valid;
		}

		// Grab the Amounts.
		$amounts = wp_list_pluck( $value, 'field_ceo_civicrm_amount' );

		// Sanitise array contents.
		array_walk(
			$amounts,
			function( &$item ) {
				$item = (string) trim( $item );
			}
		);

		// Check that all Amount Fields are populated.
		if ( in_array( '', $amounts, true ) ) {
			$valid = __( 'Please make sure all rows have an Amount', 'civicrm-event-organiser' );
			return $valid;
		}

		// --<
		return $valid;

	}

	/**
	 * This filter is applied to the Field after it is loaded from the database.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field array holding all the Field options.
	 * @return array $field The modified Field data.
	 */
	public function load_field( $field ) {

		// Cast min/max as integer.
		$field['min'] = (int) $field['min'];
		$field['max'] = (int) $field['max'];

		// Validate Subfields.
		if ( ! empty( $field['sub_fields'] ) ) {
			array_walk(
				$field['sub_fields'],
				function( &$item ) {
					$item = acf_validate_field( $item );
				}
			);
		}

		// --<
		return $field;

	}

	/**
	 * This filter is applied to the Field before it is saved to the database.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field array holding all the Field options.
	 * @return array $field The modified Field data.
	 */
	public function update_field( $field ) {

		// Modify the Field with defaults.
		$field = $this->modify_field( $field );

		// Delete any existing subfields to prevent duplication.
		if ( ! empty( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				acf_delete_field( $sub_field['name'] );
			}
		}

		// Add our Subfields.
		$field['sub_fields'] = $this->sub_fields_get( $field );

		// --<
		return $field;

	}

	/**
	 * Deletes any subfields after the Field has been deleted.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field array holding all the Field options.
	 */
	public function delete_field( $field ) {

		// Bail early if no subfields.
		if ( empty( $field['sub_fields'] ) ) {
			return;
		}

		// Delete any subfields.
		foreach ( $field['sub_fields'] as $sub_field ) {
			acf_delete_field( $sub_field['name'] );
		}

	}

	/**
	 * Modify the Field with defaults.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field array holding all the Field options.
	 * @return array $field The modified Field array.
	 */
	public function modify_field( $field ) {

		/*
		 * Set the max value to match the max in CiviCRM.
		 *
		 * @see civicrm/templates/CRM/Event/Form/ManageEvent/Fee.tpl:107
		 */
		$field['max'] = 10;
		$field['min'] = 0;

		// Set sensible defaults.
		$field['layout']       = 'table';
		$field['button_label'] = __( 'Add Fee Level', 'civicrm-event-organiser' );
		$field['collapsed']    = '';

		// Set wrapper class.
		$field['wrapper']['class'] = 'ceo_civicrm_price_set_quick';

		// --<
		return $field;

	}

	/**
	 * Get the Subfield definitions.
	 *
	 * @since 0.8.2
	 *
	 * @param array $field The Field array holding all the Field options.
	 * @return array $sub_fields The subfield array.
	 */
	public function sub_fields_get( $field ) {

		// Define Fee Label subfield.
		$fee_label = [
			'key'               => 'field_ceo_civicrm_fee_label',
			'label'             => __( 'Fee Label', 'civicrm-event-organiser' ),
			'name'              => 'ceo_civicrm_fee_label',
			'type'              => 'text',
			'parent'            => $field['key'],
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => [
				'width' => '60',
				'class' => 'civicrm_event_fee_label',
				'id'    => '',
			],
			'default_value'     => '',
			'placeholder'       => '',
			'prepend'           => '',
			'append'            => '',
			'maxlength'         => '',
			'prefix'            => '',
		];

		// Define Amount Field.
		$amount = [
			'key'               => 'field_ceo_civicrm_amount',
			'label'             => __( 'Amount', 'civicrm-event-organiser' ),
			'name'              => 'ceo_civicrm_amount',
			'type'              => 'number',
			'parent'            => $field['key'],
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => [
				'width' => '20',
				'class' => 'civicrm_event_amount',
				'id'    => '',
			],
			'default_value'     => '',
			'placeholder'       => '',
			'prepend'           => '',
			'append'            => '',
			'maxlength'         => '',
			'prefix'            => '',
		];

		// Define Default Field.
		$default = [
			'key'               => 'field_ceo_civicrm_default',
			'label'             => __( 'Is Default', 'civicrm-event-organiser' ),
			'name'              => 'ceo_civicrm_default',
			'type'              => 'checkbox',
			'parent'            => $field['key'],
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => [
				'width' => '10',
				'class' => 'civicrm_event_default',
				'id'    => '',
			],
			'choices'           => [
				1 => __( 'Default', 'civicrm-event-organiser' ),
			],
			'allow_null'        => 1,
			'other_choice'      => 0,
			'default_value'     => '',
			'layout'            => 'vertical',
			'return_format'     => 'value',
			'save_other_choice' => 0,
			'prefix'            => '',
		];

		// Define hidden "CiviCRM Price Set Field" Field.
		$price_field_value_id = [
			'readonly'          => true,
			'key'               => 'field_ceo_civicrm_pfv_id',
			'label'             => __( 'CiviCRM ID', 'civicrm-event-organiser' ),
			'name'              => 'ceo_civicrm_pfv_id',
			'type'              => 'textarea',
			'parent'            => $field['key'],
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => [
				'width' => '10',
				'class' => 'civicrm_event_pfv_id',
				'id'    => '',
			],
			'default_value'     => '',
			'placeholder'       => '',
			'prepend'           => '',
			'append'            => '',
			'min'               => '',
			'max'               => '',
			'step'              => '',
			'prefix'            => '',
		];

		// Build the Subfields array.
		$sub_fields = [ $fee_label, $amount, $default, $price_field_value_id ];

		// --<
		return $sub_fields;

	}

	/**
	 * Get the Debug Settings Field definition.
	 *
	 * @since 0.8.2
	 *
	 * @return array $field The Field array holding all the Field options.
	 */
	public function setting_field_debug_get() {

		// Define setting Field.
		$field = [
			'label'         => __( 'CiviCRM Price Field Value ID', 'civicrm-event-organiser' ),
			'name'          => 'show_pfv_id',
			'type'          => 'true_false',
			'instructions'  => __( 'Show the Price Field Value ID for debugging.', 'civicrm-event-organiser' ),
			'ui'            => 1,
			'ui_on_text'    => __( 'Show', 'civicrm-event-organiser' ),
			'ui_off_text'   => __( 'Hide', 'civicrm-event-organiser' ),
			'default_value' => 0,
			'required'      => 0,
		];

		// --<
		return $field;

	}

}
