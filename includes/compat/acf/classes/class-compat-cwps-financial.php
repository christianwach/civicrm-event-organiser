<?php
/**
 * CiviCRM Financial Class.
 *
 * Handles CiviCRM Financial functionality.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Financial Class.
 *
 * A class that encapsulates CiviCRM Financial functionality.
 *
 * @since 0.8.2
 */
class CEO_Compat_CWPS_Financial {

	/**
	 * Plugin object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * CiviCRM Profile Sync compatibility object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var CEO_Compat_CWPS
	 */
	public $cwps;

	/**
	 * Constructor.
	 *
	 * @since 0.8.2
	 *
	 * @param CEO_Compat_CWPS $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store references to objects.
		$this->plugin = $parent->plugin;
		$this->cwps   = $parent;

		// Init when the CiviCRM object is loaded.
		add_action( 'ceo/civicrm/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 0.8.2
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.8.2
	 */
	public function register_hooks() {

	}

	/**
	 * Unregister hooks.
	 *
	 * @since 0.8.2
	 */
	public function unregister_hooks() {

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the complete set of CiviCRM Financial Types.
	 *
	 * @since 0.8.2
	 *
	 * @param bool $active False gets all Financial Types, true gets just the active ones.
	 * @return array|bool $financial_types The array of CiviCRM Financial Types, or false on failure.
	 */
	public function types_get_all( $active = true ) {

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Build common query.
			$query = \Civi\Api4\FinancialType::get( false )
				->addSelect( '*' );

			// Maybe set weight.
			if ( ! empty( $active ) ) {
				$query->addWhere( 'is_active', '=', true );
			}

			// Call the API.
			$result = $query->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Return if nothing found.
		if ( 0 === $result->count() ) {
			return [];
		}

		// We only need the ArrayObject.
		$financial_types = $result->getArrayCopy();

		/**
		 * Filters the Financial Types retrieved from the CiviCRM API.
		 *
		 * @since 0.8.2
		 *
		 * @param array $financial_types The array of CiviCRM Financial Types.
		 */
		$financial_types = apply_filters( 'ceo/civicrm/financial/types_get_all', $financial_types );

		// --<
		return $financial_types;

	}

	/**
	 * Gets the CiviCRM Financial Type names keyed by ID.
	 *
	 * @since 0.8.2
	 *
	 * @return array $financial_types The array of CiviCRM Financial Types keyed by ID.
	 */
	public function types_get_mapped() {

		// Get the full set of Financial Types.
		$raw_financial_types = $this->types_get_all();
		if ( empty( $raw_financial_types ) ) {
			return [];
		}

		// Build keyed array.
		$financial_types = [];
		foreach ( $raw_financial_types as $key => $value ) {
			$financial_types[ (int) $value['id'] ] = $value['name'];
		}

		// --<
		return $financial_types;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the active CiviCRM Price Sets.
	 *
	 * @since 0.8.2
	 *
	 * @return array $price_sets The array of Price Sets, or false on failure.
	 */
	public function price_sets_get() {

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Define Price Set query params.
		$params = [
			'sequential'         => 1,
			'is_active'          => 1,
			'is_reserved'        => 0,
			'options'            => [ 'limit' => 0 ],
			'api.PriceField.get' => [
				'sequential'   => 0,
				'price_set_id' => '$value.id',
				'is_active'    => 1,
				'options'      => [ 'limit' => 0 ],
			],
		];

		try {

			$result = civicrm_api3( 'PriceSet', 'get', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = $e->getExtraParams();

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );

			return false;

		}

		// Bail if no Price Sets.
		if ( ! $result['count'] ) {
			return false;
		}

		// We want the result set.
		$price_sets = $result['values'];

		// --<
		return $price_sets;

	}

	/**
	 * Gets the data for the active CiviCRM Price Sets.
	 *
	 * The return array also includes nested arrays for the corresponding Price
	 * Fields and Price Field Values for each Price Set.
	 *
	 * @since 0.8.2
	 *
	 * @return array $price_set_data The array of Price Set data, or false on failure.
	 */
	public function price_sets_get_populated() {

		// Return early if already built.
		static $price_set_data;
		if ( isset( $price_set_data ) ) {
			return $price_set_data;
		}

		// Get the active Price Sets.
		$price_sets = $this->price_sets_get();
		if ( empty( $price_sets ) ) {
			return false;
		}

		// Get the active Price Field Values.
		$price_field_values = $this->price_field_values_get();
		if ( empty( $price_field_values ) ) {
			return false;
		}

		// Get the CiviCRM Tax Rates and "Tax Enabled" status.
		$tax_rates   = $this->tax_rates_get();
		$tax_enabled = $this->tax_is_enabled();

		$price_sets_data = [];

		foreach ( $price_sets as $key => $price_set ) {

			// Add renamed ID.
			$price_set_id              = (int) $price_set['id'];
			$price_set['price_set_id'] = $price_set_id;

			// Let's give the chained API result array a nicer name.
			$price_set['price_fields'] = $price_set['api.PriceField.get']['values'];

			foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) {

				// Add renamed ID.
				$price_set['price_fields'][ $price_field_id ]['price_field_id'] = $price_field_id;

				foreach ( $price_field_values as $value_id => $price_field_value ) {

					// Add renamed ID.
					$price_field_value['price_field_value_id'] = $value_id;

					// Skip unless matching item.
					if ( (int) $price_field_id !== (int) $price_field_value['price_field_id'] ) {
						continue;
					}

					// Add Tax data if necessary.
					if ( $tax_enabled && ! empty( $tax_rates ) && array_key_exists( $price_field_value['financial_type_id'], $tax_rates ) ) {
						$price_field_value['tax_rate']   = $tax_rates[ $price_field_value['financial_type_id'] ];
						$price_field_value['tax_amount'] = $this->percentage( $price_field_value['amount'], $price_field_value['tax_rate'] );
					}

					// Nest the Price Field Value keyed by its ID.
					$price_set['price_fields'][ $price_field_id ]['price_field_values'][ $value_id ] = $price_field_value;

				}

			}

			// We don't need the chained API array.
			unset( $price_set['api.PriceField.get'] );

			// Add Price Set data to return.
			$price_sets_data[ $price_set_id ] = $price_set;

		}

		// --<
		return $price_sets_data;

	}

	/**
	 * Gets the formatted options array of the active CiviCRM Price Sets.
	 *
	 * The return array is formatted for the select with optgroups setting.
	 *
	 * @since 0.8.2
	 *
	 * @param bool $zero_option True adds the "Select a Price Field" option.
	 * @return array $price_set_options The formatted options array of Price Set data.
	 */
	public function price_sets_get_options( $zero_option = true ) {

		// Get the Price Sets array.
		$price_sets = $this->price_sets_get_populated();
		if ( empty( $price_sets ) ) {
			return [];
		}

		// Init options array.
		$price_set_options = [];
		if ( true === $zero_option ) {
			$price_set_options[0] = __( 'Select a Price Field', 'civicrm-event-organiser' );
		}

		// Build the array for the select with optgroups.
		foreach ( $price_sets as $price_set_id => $price_set ) {
			foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) {
				/* translators: 1: Price Set title, 2: Price Field label */
				$optgroup_label   = sprintf( __( '%1$s (%2$s)', 'civicrm-event-organiser' ), $price_set['title'], $price_field['label'] );
				$optgroup_content = [];
				foreach ( $price_field['price_field_values'] as $price_field_value_id => $price_field_value ) {
					$optgroup_content[ esc_attr( $price_field_value_id ) ] = esc_html( $price_field_value['label'] );
				}
				$price_set_options[ esc_attr( $optgroup_label ) ] = $optgroup_content;
			}
		}

		// --<
		return $price_set_options;

	}

	/**
	 * Gets the Price Set data for a given Price Field Value ID.
	 *
	 * @since 0.8.2
	 *
	 * @param integer $price_field_value_id The numeric ID of the Price Field Value.
	 * @return array|bool $price_set The array of Price Set data, or false on failure.
	 */
	public function price_set_get_by_price_field_value_id( $price_field_value_id ) {

		// Get the nested Price Set data.
		$price_sets = $this->price_sets_get_populated();

		// Drill down to find the matching Price Field Value ID.
		foreach ( $price_sets as $price_set ) {
			foreach ( $price_set['price_fields'] as $price_field ) {
				foreach ( $price_field['price_field_values'] as $price_field_value ) {

					// If it matches, return the enclosing Price Set data array.
					if ( (int) $price_field_value_id === (int) $price_field_value['id'] ) {
						return $price_set;
					}

				}
			}
		}

		// Fallback.
		return false;

	}

	/**
	 * Deletes a CiviCRM Price Set for a given ID.
	 *
	 * @since 0.8.2
	 *
	 * @param int $price_set_id The ID of the CiviCRM Price Set to delete.
	 * @return int|bool $price_set_id The ID of the deleted CiviCRM Price Set, or false on failure.
	 */
	public function price_set_delete( $price_set_id ) {

		// Sanity checks.
		if ( empty( $price_set_id ) || ! is_numeric( $price_set_id ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\PriceSet::delete( false )
				->addWhere( 'id', '=', $price_set_id )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'       => __METHOD__,
				'price_set_id' => $price_set_id,
				'error'        => $e->getMessage(),
				'backtrace'    => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
		}

		// Return failure if no result.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_set = $result->first();

		// Cast as integer.
		$price_set_id = (int) $price_set['id'];

		// --<
		return $price_set_id;

	}

	/**
	 * Creates a Price Set Entity that links a Price Set with a CiviCRM Entity.
	 *
	 * @since 0.8.2
	 *
	 * @param int    $price_set_id The ID of the CiviCRM Price Set.
	 * @param int    $entity_id The ID of the CiviCRM Entity.
	 * @param string $entity_table The name of the CiviCRM Entity database table.
	 * @return array $price_set_entity The array of Price Set Entity data.
	 */
	public function price_set_entity_create( $price_set_id, $entity_id, $entity_table ) {

		// Sanity checks.
		if ( empty( $price_set_id ) || ! is_numeric( $price_set_id ) ) {
			return false;
		}
		if ( empty( $entity_id ) || ! is_numeric( $entity_id ) ) {
			return false;
		}
		if ( empty( $entity_table ) || ! is_string( $entity_table ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\PriceSetEntity::create( false )
				->addValue( 'entity_table', $entity_table )
				->addValue( 'price_set_id', $price_set_id )
				->addValue( 'entity_id', $entity_id )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'       => __METHOD__,
				'entity_id'    => $entity_id,
				'price_set_id' => $price_set_id,
				'entity_table' => $entity_table,
				'error'        => $e->getMessage(),
				'backtrace'    => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Return if nothing found.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_set_entity = $result->first();

		// --<
		return $price_set_entity;

	}

	/**
	 * Deletes a Price Set Entity for a given ID.
	 *
	 * @since 0.8.2
	 *
	 * @param int $price_set_entity_id The ID of the CiviCRM Price Set Entity to delete.
	 * @return int|bool $price_set_entity_id The ID of the deleted CiviCRM Price Set Entity, or false on failure.
	 */
	public function price_set_entity_delete( $price_set_entity_id ) {

		// Sanity checks.
		if ( empty( $price_set_entity_id ) || ! is_numeric( $price_set_entity_id ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\PriceSetEntity::delete( false )
				->addWhere( 'id', '=', $price_set_entity_id )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'              => __METHOD__,
				'price_set_entity_id' => $price_set_entity_id,
				'error'               => $e->getMessage(),
				'backtrace'           => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
		}

		// Return failure if no result.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_set_entity = $result->first();

		// Cast as integer.
		$price_set_entity_id = (int) $price_set_entity['id'];

		// --<
		return $price_set_entity_id;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the Price Field data for a given Price Field Value ID.
	 *
	 * @since 0.8.2
	 *
	 * @param integer $price_field_value_id The numeric ID of the Price Field Value.
	 * @return array|bool $price_field The array of Price Field data, or false on failure.
	 */
	public function price_field_get_by_price_field_value_id( $price_field_value_id ) {

		// Get the nested Price Set data.
		$price_sets = $this->price_sets_get_populated();

		// Drill down to find the matching Price Field Value ID.
		foreach ( $price_sets as $price_set ) {
			foreach ( $price_set['price_fields'] as $price_field ) {
				foreach ( $price_field['price_field_values'] as $price_field_value ) {

					// If it matches, return the enclosing Price Field data array.
					if ( (int) $price_field_value_id === (int) $price_field_value['id'] ) {
						return $price_field;
					}

				}
			}
		}

		// Fallback.
		return false;

	}

	/**
	 * Deletes a Price Field for a given ID.
	 *
	 * @since 0.8.2
	 *
	 * @param int $price_field_id The ID of the CiviCRM Price Field to delete.
	 * @return int|bool $price_field_id The ID of the deleted CiviCRM Price Field, or false on failure.
	 */
	public function price_field_delete( $price_field_id ) {

		// Sanity checks.
		if ( empty( $price_field_id ) || ! is_numeric( $price_field_id ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\PriceField::delete( false )
				->addWhere( 'id', '=', $price_field_id )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'         => __METHOD__,
				'price_field_id' => $price_field_id,
				'error'          => $e->getMessage(),
				'backtrace'      => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
		}

		// Return failure if no result.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_field = $result->first();

		// Cast as integer.
		$price_field_id = (int) $price_field['id'];

		// --<
		return $price_field_id;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the active CiviCRM Price Field Values.
	 *
	 * @since 0.8.2
	 *
	 * @return array $price_field_values The array of Price Field Values, or false on failure.
	 */
	public function price_field_values_get() {

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Define Price Field Value query params.
		$params = [
			'sequential' => 0,
			'is_active'  => 1,
			'options'    => [
				'limit' => 0,
				'sort'  => 'weight ASC',
			],
		];

		try {

			$result = civicrm_api3( 'PriceFieldValue', 'get', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = $e->getExtraParams();

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );

			return false;

		}

		// Bail if no Price Field Values.
		if ( ! $result['count'] ) {
			return false;
		}

		// We want the result set.
		$price_field_values = $result['values'];

		// --<
		return $price_field_values;

	}

	/**
	 * Gets the Price Field Value data for a given Price Field Value ID.
	 *
	 * @since 0.8.2
	 *
	 * @param integer $price_field_value_id The numeric ID of the Price Field Value.
	 * @return array|bool $price_field_value The array of Price Field Value data, or false on failure.
	 */
	public function price_field_value_get_by_id( $price_field_value_id ) {

		// Get the nested Price Set data.
		$price_sets = $this->price_sets_get_populated();

		// Drill down to find the matching Price Field Value ID.
		foreach ( $price_sets as $price_set ) {
			foreach ( $price_set['price_fields'] as $price_field ) {
				foreach ( $price_field['price_field_values'] as $price_field_value ) {

					// If it matches, return the Price Field Value data array.
					if ( (int) $price_field_value_id === (int) $price_field_value['id'] ) {
						return $price_field_value;
					}

				}
			}
		}

		// Fallback.
		return false;

	}

	/**
	 * Deactivates a Price Field Value for a given ID.
	 *
	 * @since 0.8.2
	 *
	 * @param int $price_field_value_id The ID of the CiviCRM Price Field Value to delete.
	 * @return int|bool $price_field_value_id The ID of the deactivated CiviCRM Price Field Value, or false on failure.
	 */
	public function price_field_value_deactivate( $price_field_value_id ) {

		// Sanity checks.
		if ( empty( $price_field_value_id ) || ! is_numeric( $price_field_value_id ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\PriceFieldValue::update( false )
				->addWhere( 'id', '=', $price_field_value_id )
				->addValue( 'is_active', false )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'               => __METHOD__,
				'price_field_value_id' => $price_field_value_id,
				'error'                => $e->getMessage(),
				'backtrace'            => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
		}

		// Return failure if no result.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_field_value = $result->first();

		// Cast as integer.
		$price_field_value_id = (int) $price_field_value['id'];

		// --<
		return $price_field_value_id;

	}

	/**
	 * Deletes a Price Field Value for a given ID.
	 *
	 * @since 0.8.2
	 *
	 * @param int $price_field_value_id The ID of the CiviCRM Price Field Value to delete.
	 * @return int|bool $price_field_value_id The ID of the deleted CiviCRM Price Field Value, or false on failure.
	 */
	public function price_field_value_delete( $price_field_value_id ) {

		// Sanity checks.
		if ( empty( $price_field_value_id ) || ! is_numeric( $price_field_value_id ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\PriceFieldValue::delete( false )
				->addWhere( 'id', '=', $price_field_value_id )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'               => __METHOD__,
				'price_field_value_id' => $price_field_value_id,
				'error'                => $e->getMessage(),
				'backtrace'            => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
		}

		// Return failure if no result.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_field_value = $result->first();

		// Cast as integer.
		$price_field_value_id = (int) $price_field_value['id'];

		// --<
		return $price_field_value_id;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the CiviCRM "Enable Tax and Invoicing" setting.
	 *
	 * @since 0.8.2
	 *
	 * @return bool $setting True if enabled, false otherwise.
	 */
	public function tax_is_enabled() {

		// Return early if already found.
		static $setting;
		if ( isset( $setting ) ) {
			return $setting;
		}

		// Get the setting from CiviCRM.
		$setting = false;
		$result  = $this->plugin->civi->get_setting( 'invoicing' );
		if ( ! empty( $result ) ) {
			$setting = $result;
		}

		return $setting;

	}

	/**
	 * Gets the CiviCRM Tax Rates.
	 *
	 * The array of Tax Rates has the form: [ <financial_type_id> => <tax_rate> ]
	 *
	 * @since 0.8.2
	 *
	 * @return array|bool The array of Tax Rates, or false on failure.
	 */
	public function tax_rates_get() {

		// Return early if already found.
		static $tax_rates;
		if ( isset( $tax_rates ) ) {
			return $tax_rates;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		$params = [
			'version'                        => 3,
			'return'                         => [
				'id',
				'entity_table',
				'entity_id',
				'account_relationship',
				'financial_account_id',
				'financial_account_id.financial_account_type_id',
				'financial_account_id.tax_rate',
			],
			'financial_account_id.is_active' => 1,
			'financial_account_id.is_tax'    => 1,
			'options'                        => [
				'limit' => 0,
			],
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'EntityFinancialAccount', 'get', $params );

		// Log and bail if something went wrong.
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
			return false;
		}

		// Return early if there's nothing to see.
		if ( 0 === (int) $result['count'] ) {
			return false;
		}

		// Build tax rates.
		$tax_rates = array_reduce(
			$result['values'],
			function( $tax_rates, $financial_account ) {
				$tax_rates[ $financial_account['entity_id'] ] = $financial_account['financial_account_id.tax_rate'];
				return $tax_rates;
			},
			[]
		);

		return $tax_rates;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the complete set of CiviCRM Payment Processors.
	 *
	 * @since 0.8.2
	 *
	 * @param bool $active False gets all Payment Processors, true gets just the active ones.
	 * @return array|bool $payment_processors The array of CiviCRM Payment Processors, or false on failure.
	 */
	public function processors_get_all( $active = true ) {

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Build common query.
			$query = \Civi\Api4\PaymentProcessor::get( false )
				->addSelect( '*' );

			// Maybe set weight.
			if ( ! empty( $active ) ) {
				$query->addWhere( 'is_active', '=', true );
			}

			// Call the API.
			$result = $query->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Return if nothing found.
		if ( 0 === $result->count() ) {
			return [];
		}

		// We only need the ArrayObject.
		$payment_processors = $result->getArrayCopy();

		/**
		 * Filters the Payment Processors retrieved from the CiviCRM API.
		 *
		 * @since 0.8.2
		 *
		 * @param array $payment_processors The array of CiviCRM Payment Processors.
		 */
		$payment_processors = apply_filters( 'ceo/civicrm/financial/processors_get_all', $payment_processors );

		// --<
		return $payment_processors;

	}

	/**
	 * Gets the CiviCRM Payment Processor names keyed by ID.
	 *
	 * @since 0.8.2
	 *
	 * @return array $financial_types The array of CiviCRM Payment Processors keyed by ID.
	 */
	public function processors_get_mapped() {

		// Get the full set of Payment Processors.
		$raw_payment_processors = $this->processors_get_all();
		if ( empty( $raw_payment_processors ) ) {
			return [];
		}

		// Build keyed array.
		$payment_processors = [];
		foreach ( $raw_payment_processors as $key => $value ) {
			$payment_processors[ (int) $value['id'] ] = $value['name'];
		}

		// --<
		return $payment_processors;

	}

	/**
	 * Gets the CiviCRM Currency names keyed by ID.
	 *
	 * @since 0.8.2
	 *
	 * @return array $currencies The array of CiviCRM Currencies keyed by ID.
	 */
	public function currencies_get_mapped() {

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return [];
		}

		// Get the full set of Payment Processors.
		$option_group = $this->plugin->civi->option_group_get( 'currencies_enabled' );
		if ( empty( $option_group ) ) {
			return [];
		}

		// Build keyed array.
		$currencies = CRM_Core_OptionGroup::valuesByID( $option_group['id'] );

		// --<
		return $currencies;

	}

	/**
	 * Gets the default CiviCRM Currency.
	 *
	 * @since 0.8.2
	 *
	 * @return array $default_currency The default CiviCRM Currency, or false on failure.
	 */
	public function currency_get_default() {

		// Return early if already calculated.
		static $default_currency;
		if ( isset( $default_currency ) ) {
			return $default_currency;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'name'       => 'defaultCurrency',
		];

		try {

			$result = civicrm_api3( 'Setting', 'getvalue', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = $e->getExtraParams();

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );

			return false;

		}

		// We must have a string.
		$default_currency = '';
		if ( is_string( $result ) ) {
			$default_currency = $result;
		}

		// --<
		return $default_currency;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the CiviCRM Decimal Separator.
	 *
	 * @since 0.8.2
	 *
	 * @return string|bool $decimal_separator The CiviCRM Decimal Separator, or false on failure.
	 */
	public function decimal_separator_get() {

		// Return early if already calculated.
		static $decimal_separator;
		if ( isset( $decimal_separator ) ) {
			return $decimal_separator;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'name'       => 'monetaryDecimalPoint',
		];

		try {

			$result = civicrm_api3( 'Setting', 'getvalue', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = $e->getExtraParams();

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );

			return false;

		}

		// We must have a string.
		$decimal_separator = '.';
		if ( is_string( $result ) ) {
			$decimal_separator = $result;
		}

		// --<
		return $decimal_separator;

	}

	/**
	 * Gets the CiviCRM Thousand Separator.
	 *
	 * @since 0.8.2
	 *
	 * @return string|bool $thousand_separator The CiviCRM Thousand Separator, or false on failure.
	 */
	public function thousand_separator_get() {

		// Return early if already calculated.
		static $thousand_separator;
		if ( isset( $thousand_separator ) ) {
			return $thousand_separator;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		$params = [
			'sequential' => 1,
			'name'       => 'monetaryThousandSeparator',
		];

		try {

			$result = civicrm_api3( 'Setting', 'getvalue', $params );

		} catch ( Exception $e ) {

			// Grab the error data.
			$message = $e->getMessage();
			$code    = $e->getErrorCode();
			$extra   = $e->getExtraParams();

			// Write to PHP log.
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'message'   => $message,
				'code'      => $code,
				'extra'     => $extra,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );

			return false;

		}

		// We must have a string.
		$thousand_separator = '';
		if ( is_string( $result ) ) {
			$thousand_separator = $result;
		}

		// --<
		return $thousand_separator;

	}

	/**
	 * Converts a number to CiviCRM-compliant number format.
	 *
	 * @since 0.8.2
	 *
	 * @param integer|float $number The number to convert.
	 * @return float $civicrm_number The CiviCRM-compliant number.
	 */
	public function civicrm_float( $number ) {

		// Return incoming value on error.
		$decimal_separator  = $this->decimal_separator_get();
		$thousand_separator = $this->thousand_separator_get();
		if ( false === $decimal_separator || false === $thousand_separator ) {
			return $number;
		}

		// Convert to CiviCRM-compliant number.
		$civicrm_number = number_format( $number, 2, $decimal_separator, $thousand_separator );

		// --<
		return $civicrm_number;

	}

	/**
	 * Calculate the percentage for a given amount.
	 *
	 * @since 0.8.2
	 *
	 * @param string $amount The amount.
	 * @param string $percentage The percentage.
	 * @return string $amount The calculated percentage amount.
	 */
	public function percentage( $amount, $percentage ) {
		// TODO: Check return format.
		return ( $percentage / 100 ) * $amount;
	}

}
