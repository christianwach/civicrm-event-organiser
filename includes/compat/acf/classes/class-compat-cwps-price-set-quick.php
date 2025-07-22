<?php
/**
 * Quick Config Price Set Class.
 *
 * Handles CiviCRM "Quick Config Price Set" functionality.
 *
 * @package CiviCRM_WP_Profile_Sync
 * @since 0.8.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Quick Config Price Set Class.
 *
 * A class that encapsulates CiviCRM "Quick Config Price Set" functionality.
 *
 * @since 0.8.2
 */
class CEO_Compat_CWPS_Price_Set_Quick {

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
	public $compat;

	/**
	 * Fields which must be handled separately.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var array
	 */
	public $fields_handled = [
		'ceo_civicrm_price_set_quick',
	];

	/**
	 * An array of "Quick Config Price Set" Records prior to delete.
	 *
	 * There are situations where nested updates take place (e.g. via CiviRules)
	 * so we keep copies of the "Quick Config Price Set" Records in an array and try
	 * and match them up in the post delete hook.
	 *
	 * @since 0.8.2
	 * @access private
	 * @var array
	 */
	private $bridging_array = [];

	/**
	 * Constructor.
	 *
	 * @since 0.8.2
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store references to objects.
		$this->plugin = $parent->plugin;
		$this->compat = $parent;

		// Init when the ACF CiviCRM object is loaded.
		add_action( 'ceo/compat/cwps/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 0.8.2
	 */
	public function initialise() {

		// Bootstrap this class.
		$this->register_hooks();

		/**
		 * Fires when this class is loaded.
		 *
		 * @since 0.8.2
		 */
		do_action( 'ceo/compat/ceo/price_set_quick/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.8.2
	 */
	public function register_hooks() {

		// Listen for when a CiviCRM Event has been updated after ACF Fields were saved.
		add_action( 'ceo/acf/event/acf_fields_saved', [ $this, 'fields_handled_update' ], 10 );

		// Listen for when an Event Organiser Event has been synced from a CiviCRM Event.
		add_action( 'ceo/eo/event/updated', [ $this, 'event_synced_to_post' ], 10, 2 );

		// Maybe sync the Price Field Value ID Record "CiviCRM ID" to the ACF Subfields.
		add_action( 'ceo/acf/civicrm/price_field_value/created', [ $this, 'maybe_sync_price_field_value_id' ], 10, 2 );

		// Add any Event Fields attached to a Post.
		add_filter( 'cwps/acf/fields_get_for_post', [ $this, 'acf_fields_get_for_post' ], 10, 3 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Intercept when an Event Organiser Event has been updated from a CiviCRM Event.
	 *
	 * Sync any associated ACF Fields mapped to built-in Event Fields.
	 *
	 * @since 0.8.2
	 *
	 * @param int   $event_id The numeric ID of the Event Organiser Event.
	 * @param array $civicrm_event An array of data for the CiviCRM Event.
	 */
	public function event_synced_to_post( $event_id, $civicrm_event ) {

		// Cast Event ID as integer.
		$civicrm_event_id = (int) $civicrm_event['id'];

		// Get the current "Quick Config Price Set" Record.
		$current_price_set = $this->price_set_quick_config_get( $civicrm_event_id );

		// Bail if there is no "Quick Config Price Set" Record.
		if ( empty( $current_price_set ) ) {
			return;
		}

		// Get all ACF Fields for the Event.
		$acf_fields = $this->compat->cwps->acf->acf->field->fields_get_for_post( $event_id );

		// Bail if there are no "Quick Config Price Set" Record Fields.
		if ( empty( $acf_fields['price_set_quick'] ) ) {
			return;
		}

		// Let's look at each ACF Field in turn.
		foreach ( $acf_fields['price_set_quick'] as $selector => $field ) {

			// Init Field value.
			$value = [];

			// Grab the current Price Field ID and Price Field Values.
			$price_field_id = false;
			$current_pfvs   = [];
			foreach ( $current_price_set['price_fields'] as $price_field_id => $price_field ) {
				// This Price Field should only have one set of Price Field Values.
				$price_field_id = (int) $price_field['id'];
				$current_pfvs   = $price_field['price_field_values'];
				break;
			}

			// Let's look at each "Quick Config Price Set" in turn.
			foreach ( $current_pfvs as $current_pfv ) {

				// Convert to ACF "Quick Config Price Set" data.
				$acf_price_field_value = $this->prepare_from_civicrm( $current_pfv );

				// Add to Field value.
				$value[] = $acf_price_field_value;

			}

			// Now update Field.
			$this->compat->cwps->acf->acf->field->value_update( $selector, $value, $event_id );

		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Updates a CiviCRM Event's Fields with data from ACF Fields.
	 *
	 * @since 0.8.2
	 *
	 * @param array $args The array of WordPress params.
	 * @return bool $success True if updates were successful, or false on failure.
	 */
	public function fields_handled_update( $args ) {

		// Init success.
		$success = true;

		// Bail if we have no Field data to save.
		if ( empty( $args['fields'] ) ) {
			return $success;
		}

		// Loop through the Field data.
		foreach ( $args['fields'] as $field => $value ) {

			// Get the Field settings.
			$settings = get_field_object( $field, $args['post_id'] );
			if ( empty( $settings ) ) {
				continue;
			}

			// Skip if it's not an ACF Field Type that this class handles.
			if ( ! in_array( $settings['type'], $this->fields_handled, true ) ) {
				continue;
			}

			// Update the "Quick Config Price Set" Records.
			$success = $this->price_field_values_update( $value, $field, $settings, $args );

		}

		// --<
		return $success;

	}

	/**
	 * Updates a CiviCRM Event's "Quick Config Price Set" Record.
	 *
	 * @since 0.8.2
	 *
	 * @param array  $values The array of ACF data to update the CiviCRM Event with.
	 * @param string $selector The ACF Field selector.
	 * @param array  $settings The ACF Field settings.
	 * @param array  $args The array of process data.
	 * @return array|bool $price_set The array of "Quick Config Price Set" data, or false on failure.
	 */
	public function price_field_values_update( $values, $selector, $settings, $args = [] ) {

		// Init return.
		$price_set = [];

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Bail if there are no values to apply.
		if ( empty( $values ) ) {
			/*
			 * This probably isn't what we want to do... it could be that the intention
			 * is to *delete all* existing Price Field Values, or it could be that none
			 * are defined.
			 *
			 * This is a temporary measure until I figure out the logic.
			 */
			return $price_set;
		}

		// Cast Event ID as integer.
		$event_id = (int) $args['civi_event_id'];

		// Get the current "Quick Config Price Set" Record.
		$current_price_set = $this->price_set_quick_config_get( $event_id );

		// If there is no existing "Quick Config Price Set" Record.
		if ( empty( $current_price_set ) ) {

			/*
			 * There appears to be "initial price set support for event.create" in CiviCRM
			 * but I cannot find any documentation about it. It doesn't look like it
			 * gives us a quick way to build the Price Set anyway.
			 *
			 * @see https://issues.civicrm.org/jira/browse/CRM-14069
			 * @see https://github.com/civicrm/civicrm-core/pull/2326
			 */

			// Get the ID of the CiviEvent Component.
			$extends = $this->plugin->civi->component_get_id( 'CiviEvent' );

			// Get the full CiviCRM Event data.
			$civicrm_event = $this->plugin->civi->event->get_event_by_id( $event_id );

			// Create a Price Set for it.
			$price_set = $this->price_set_quick_config_create( $civicrm_event['title'], $settings['financial_type_id'], $extends );
			if ( empty( $price_set ) ) {
				return false;
			}

			// Cast Price Set ID as integer.
			$price_set_id = (int) $price_set['id'];

			// Clean up returned data.
			unset( $price_set['custom'] );
			unset( $price_set['check_permissions'] );

			// Create a Price Set Entity to link the Price Set to the Event.
			$price_set_entity = $this->compat->financial->price_set_entity_create( $price_set_id, $event_id, 'civicrm_event' );
			if ( empty( $price_set_entity ) ) {
				// Delete Price Set and bail.
				$this->compat->financial->price_set_delete( $price_set_id );
				return false;
			}

			// Clean up returned data.
			unset( $price_set_entity['custom'] );
			unset( $price_set_entity['check_permissions'] );

			// Add to Price Set.
			$price_set['entity'] = [
				$price_set_entity['entity_table'] => [
					$event_id,
				],
			];

			// Create a Price Field to hold the Price Field Values.
			$price_field = $this->price_field_create( $price_set_id, $civicrm_event );
			if ( empty( $price_field ) ) {
				// Delete Price Set and Price Set Entity and bail.
				$this->compat->financial->price_set_delete( $price_set_id );
				$this->compat->financial->price_set_entity_delete( $price_set_entity['id'] );
				return false;
			}

			// Clean up returned data.
			unset( $price_field['custom'] );
			unset( $price_field['check_permissions'] );

			// Cast Price Field ID as integer.
			$price_field_id = (int) $price_field['id'];

			// Add to Price Set.
			$price_set['price_fields'] = [
				$price_field_id => $price_field,
			];

			// Init array to collectPrice Field Values.
			$price_field_values = [];

			// Create a Price Field Value from each value.
			foreach ( $values as $key => $value ) {

				// Build required data from ACF Field.
				$data = $this->prepare_from_field( $value );

				// Add data from Price Field and ACF Field settings.
				$data['price_field_id']    = $price_field_id;
				$data['financial_type_id'] = $settings['financial_type_id'];

				// The weight is determined by the ACF row order.
				$weight = $key + 1;

				// Maybe set as default.
				$is_default = ! empty( $value['field_ceo_civicrm_default'] ) ? true : false;

				// Okay, let's create it.
				$price_field_value = $this->price_field_value_create( $data, $weight, $is_default );
				if ( empty( $price_field_value ) ) {
					// Try again later.
					continue;
				}

				// Clean up returned data.
				unset( $price_field_value['custom'] );
				unset( $price_field_value['check_permissions'] );

				// Cast Price Field Value ID as integer.
				$price_field_value_id = (int) $price_field_value['id'];

				// Add to array.
				$price_field_values[ $price_field_value_id ] = $price_field_value;

				// Make an array of our params.
				$params = [
					'key'                  => $key,
					'value'                => $value,
					'event_id'             => $event_id,
					'selector'             => $selector,
					'price_field_value'    => $price_field_value,
					'price_field_value_id' => $price_field_value_id,
				];

				/**
				 * Fires when a CiviCRM Price Field Value Record has been created.
				 *
				 * Used internally to update the ACF Field with the "CiviCRM ID" value.
				 *
				 * CiviCRM also has its own money format that has 8 decimal places. We
				 * could also sync that back to the ACF Field. Would we want to use
				 * the following code perhaps?
				 *
				 * `\Civi::format()->money( $amount, $currency, 'Full' );`
				 *
				 * @see self::maybe_sync_price_field_value_id()
				 *
				 * @since 0.8.2
				 *
				 * @param array $params The Price Field Value data.
				 * @param array $args The array of WordPress params.
				 */
				do_action( 'ceo/acf/civicrm/price_field_value/created', $params, $args );

			}

			// Add to Price Set.
			$price_set['price_fields'][ $price_field_id ]['price_field_values'] = $price_field_values;

			// Prevent recursion.
			remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ] );

			// Build fresh params.
			$event = [
				'id'                  => $event_id,
				'is_monetary'         => true,
				'currency'            => $settings['currency'],
				'financial_type_id'   => $settings['financial_type_id'],
				'payment_processor'   => $settings['payment_processor_id'],
				'pay_later_text'      => $settings['pay_later_label'],
				'pay_later_receipt'   => $settings['pay_later_instructions'],
				'is_billing_required' => $settings['pay_later_billing_required'],
			];

			// Maybe add Pay Later params.
			$event['is_pay_later'] = false;
			if ( ! empty( $settings['pay_later'] ) ) {
				$event['is_pay_later']        = true;
				$event['pay_later_text']      = $settings['pay_later_label'];
				$event['pay_later_receipt']   = $settings['pay_later_instructions'];
				$event['is_billing_required'] = false;
				if ( ! empty( $settings['pay_later_billing_required'] ) ) {
					$event['is_billing_required'] = true;
				}
			}

			// Now apply changes to the CiviCRM Event.
			$this->compat->event_update( $event );

			// Restore hook.
			add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10, 4 );

			/**
			 * Fires when a CiviCRM "Quick Config Price Set" Record has been created.
			 *
			 * @since 0.8.2
			 *
			 * @param array $price_set The Price Set data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'ceo/acf/civicrm/price_set_quick/created', $price_set, $args );

			// No need to go any further.
			return $price_set;

		}

		/*
		 * There seems to be some unfinished business when updating Price Field Values
		 * in CiviCRM (dating back to 2014) because comments in the code say:
		 *
		 * "@todo note that this removes the reference from existing participants -
		 * even where there is not change - redress?"
		 *
		 * I take this to mean that there are various scenarios here:
		 *
		 * 1. Updating the Price Field Values and make existing data inconsistent.
		 * 2. Deleting the existing Price Field Values removes links to existing Participants.
		 * 3. Deleting and recreating the existing Price Set Entity removes links to existing Participants.
		 *
		 * None seem ideal, but we'll follow what CiviCRM does:
		 *
		 * 1. Delete and recreate the existing Price Set Entity.
		 * 2. Deactivate any Price Field Values that have been deleted, i.e. `is_active = false`.
		 * 3. Update any Price Field Values that still exist.
		 * 4. Create any Price Field Values that do not yet exist.
		 *
		 * @see https://github.com/civicrm/civicrm-core/pull/2326
		 */

		// We have existing Price Field Value Records.
		$actions = [
			'create' => [],
			'update' => [],
			'delete' => [],
		];

		// Let's look at each ACF Record and check its Price Field Value ID.
		foreach ( $values as $key => $value ) {

			// New Records have no Price Field Value ID.
			if ( empty( $value['field_ceo_civicrm_pfv_id'] ) ) {
				$actions['create'][ $key ] = $value;
				continue;
			}

			// Records to update have a Price Field Value ID.
			if ( ! empty( $value['field_ceo_civicrm_pfv_id'] ) ) {
				$actions['update'][ $key ] = $value;
				continue;
			}

		}

		// Grab the ACF Price Field Value IDs.
		$acf_pfv_ids = wp_list_pluck( $values, 'field_ceo_civicrm_pfv_id' );

		// Sanitise array contents.
		array_walk(
			$acf_pfv_ids,
			function( &$item ) {
				$item = (int) trim( $item );
			}
		);

		// Grab the current Price Field ID and Price Field Values.
		$price_field_id = false;
		$current_pfvs   = [];
		foreach ( $current_price_set['price_fields'] as $price_field_id => $price_field ) {
			// This Price Field should only have one set of Price Field Values.
			$price_field_id = (int) $price_field['id'];
			$current_pfvs   = $price_field['price_field_values'];
			break;
		}

		// Records to delete are missing from the ACF data.
		foreach ( $current_pfvs as $current_pfv ) {
			if ( ! in_array( (int) $current_pfv['id'], $acf_pfv_ids, true ) ) {
				$actions['delete'][] = $current_pfv['id'];
				continue;
			}
		}

		// Track Price Field Values.
		$price_field_values = [];

		// Create CiviCRM Price Field Value Records.
		foreach ( $actions['create'] as $key => $value ) {

			// Build required data from ACF Field.
			$data = $this->prepare_from_field( $value );

			// Add data from Price Field and ACF Field settings.
			$data['price_field_id']    = $price_field_id;
			$data['financial_type_id'] = $settings['financial_type_id'];

			// Maybe set as default.
			$is_default = ! empty( $value['field_ceo_civicrm_default'] ) ? true : false;

			// The weight is determined by the ACF row order.
			$weight = $key + 1;

			// Okay, let's do it.
			$price_field_value = $this->price_field_value_create( $data, $weight, $is_default );
			if ( empty( $price_field_value ) ) {
				// Try again later.
				continue;
			}

			// Clean up returned data.
			unset( $price_field_value['custom'] );
			unset( $price_field_value['check_permissions'] );

			// Cast Price Field Value ID as integer.
			$price_field_value_id = (int) $price_field_value['id'];

			// Add to array.
			$price_field_values[ $price_field_value_id ] = $price_field_value;

			// Make an array of our params.
			$params = [
				'key'                  => $key,
				'value'                => $value,
				'event_id'             => $event_id,
				'selector'             => $selector,
				'price_field_value'    => $price_field_value,
				'price_field_value_id' => $price_field_value_id,
			];

			/**
			 * Fires when a CiviCRM Price Field Value Record has been created.
			 *
			 * Used internally to update the ACF Field with the "CiviCRM ID" value.
			 *
			 * @see self::maybe_sync_price_field_value_id()
			 *
			 * @since 0.8.2
			 *
			 * @param array $params The Price Field Value data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'ceo/acf/civicrm/price_field_value/created', $params, $args );

		}

		// Update CiviCRM Price Field Value Records.
		foreach ( $actions['update'] as $key => $value ) {

			// Build required data from ACF Field.
			$data = $this->prepare_from_field( $value );

			// Add data from Price Field and ACF Field settings.
			$data['price_field_id'] = $price_field_id;

			// Maybe set as default.
			$is_default = ! empty( $value['field_ceo_civicrm_default'] ) ? true : false;

			// The weight is determined by the ACF row order.
			$weight = $key + 1;

			// Okay, let's update it.
			$price_field_value = $this->price_field_value_update( $data, $weight, $is_default );
			if ( empty( $price_field_value ) ) {
				// Try again later.
				continue;
			}

			// Clean up returned data.
			unset( $price_field_value['custom'] );
			unset( $price_field_value['check_permissions'] );

			// Cast Price Field Value ID as integer.
			$price_field_value_id = (int) $price_field_value['id'];

			// Add to array.
			$price_field_values[ $price_field_value_id ] = $price_field_value;

			// Make an array of our params.
			$params = [
				'key'                  => $key,
				'value'                => $value,
				'event_id'             => $event_id,
				'selector'             => $selector,
				'price_field_value'    => $price_field_value,
				'price_field_value_id' => $price_field_value_id,
			];

			/**
			 * Fires when a CiviCRM Price Field Value Record has been updated.
			 *
			 * @since 0.8.2
			 *
			 * @param array $params The Price Field Value data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'ceo/acf/civicrm/price_field_value/updated', $params, $args );

		}

		// Delete (actually deactivate) CiviCRM Price Field Value Records.
		foreach ( $actions['delete'] as $price_field_value_id ) {

			// Disable the Price Field Value.
			$this->compat->financial->price_field_value_deactivate( $price_field_value_id );

			// Make an array of our params.
			$params = [
				'key'                  => $key,
				'value'                => $value,
				'event_id'             => $event_id,
				'selector'             => $selector,
				'price_field_value_id' => $price_field_value_id,
			];

			/**
			 * Fires when a CiviCRM Price Field Value Record has been disabled.
			 *
			 * @since 0.8.2
			 *
			 * @param array $params The Price Field Value data.
			 * @param array $args The array of WordPress params.
			 */
			do_action( 'ceo/acf/civicrm/price_field_value/disabled', $params, $args );

		}

		// --<
		return $price_set;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get the "Quick Config Price Set" Record for a given CiviCRM Event ID.
	 *
	 * @since 0.8.2
	 *
	 * @param integer $event_id The numeric ID of the CiviCRM Event.
	 * @return array $price_set The array of "Quick Config Price Set" Record data for the CiviCRM Event.
	 */
	public function price_set_quick_config_get( $event_id ) {

		// Get populated Price Sets.
		$price_sets = $this->compat->financial->price_sets_get_populated();

		// Find the matching Price Set.
		foreach ( $price_sets as $price_set ) {

			// Skip if not Quick Config.
			if ( empty( $price_set['is_quick_config'] ) ) {
				continue;
			}

			// Skip if not extending an Entity.
			if ( empty( $price_set['extends'] ) ) {
				continue;
			}

			// Skip if no Entity.
			if ( empty( $price_set['entity'] ) ) {
				continue;
			}

			// Skip if not linked to an Event.
			if ( ! array_key_exists( 'civicrm_event', $price_set['entity'] ) ) {
				continue;
			}

			// Sanitise Events array.
			$event_ids = array_map( 'intval', $price_set['entity']['civicrm_event'] );

			// Skip if not linked to this Event.
			if ( ! in_array( (int) $event_id, $event_ids, true ) ) {
				continue;
			}

			// Return the found Price Set.
			return $price_set;

		}

		// Not found.
		return [];

	}

	/**
	 * Creates a "Quick Config Price Set" for a given Event.
	 *
	 * The "extends" param is a bit confusing, but is basically the ID of the Component.
	 * Whether or not these are always the same is hard to tell.
	 *
	 * @see CRM_Core_Component::getComponentID()
	 *
	 * @since 0.8.2
	 *
	 * @param string $title The title of the Event for which to create the "Quick Config Price Set".
	 * @param int    $financial_type_id The ID of the CiviCRM Financial Type.
	 * @param int    $extends The ID of the CiviCRM Component.
	 * @return array $price_set The array of "Quick Config Price Set" data.
	 */
	public function price_set_quick_config_create( $title, $financial_type_id, $extends ) {

		// Sanity checks.
		if ( empty( $title ) || ! is_string( $title ) ) {
			return false;
		}
		if ( empty( $financial_type_id ) || ! is_numeric( $financial_type_id ) ) {
			return false;
		}
		if ( empty( $extends ) || ! is_numeric( $extends ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Follow the same logic as CiviCRM for the Price Set "name".
		$name = strtolower( CRM_Utils_String::munge( $title, '_', 245 ) );

		try {

			/*
			 * Some values are omitted because their defaults are fine:
			 *
			 * * `is_active`
			 * * `is_reserved`
			 * * `min_amount`
			 *
			 * TODO: "Minimum Amount" could be set in the ACF Field settings.
			 */

			// Call the API.
			$result = \Civi\Api4\PriceSet::create( false )
				->addValue( 'title', $title )
				->addValue( 'name', $name )
				->addValue( 'extends', [ (int) $extends ] )
				->addValue( 'financial_type_id', $financial_type_id )
				->addValue( 'is_quick_config', true )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'            => __METHOD__,
				'title'             => $title,
				'financial_type_id' => $financial_type_id,
				'error'             => $e->getMessage(),
				'backtrace'         => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Return if nothing found.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_set = $result->first();

		// --<
		return $price_set;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Creates a Price Field for a given Event.
	 *
	 * @since 0.8.2
	 *
	 * @param int   $price_set_id The ID of the CiviCRM Price Set.
	 * @param array $event The array of CiviCRM Event data.
	 * @return array $price_field The array of CiviCRM Price Field data.
	 */
	public function price_field_create( $price_set_id, $event ) {

		// Sanity checks.
		if ( empty( $price_set_id ) || ! is_numeric( $price_set_id ) ) {
			return false;
		}
		if ( empty( $event ) || ! is_array( $event ) ) {
			return false;
		}

		// Set the same default as CiviCRM if `fee_label` is empty.
		if ( empty( $event['fee_label'] ) ) {
			$label = __( 'Event Fee(s)', 'civicrm-event-organiser' );
		} else {
			$label = $event['fee_label'];
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Follow the same logic as CiviCRM for the Price Field "name".
		$name = strtolower( CRM_Utils_String::munge( $label, '_', 245 ) );

		try {

			/*
			 * Some values are omitted because their defaults are fine:
			 *
			 * * `is_enter_qty` - No.
			 * * `weight` - TODO: Might need to be set.
			 * * `is_display_amounts` - Yes.
			 * * `options_per_line` - 1.
			 * * `is_active` - Yes.
			 * * `is_required` - Yes.
			 * * `active_on` - Not set.
			 * * `expire_on` - Not set.
			 * * `visibility_id` - 1.
			 *
			 * TODO: Consider whether `active_on` and `expire_on` could be configured.
			 */

			// Call the API.
			$result = \Civi\Api4\PriceField::create( false )
				->addValue( 'price_set_id', $price_set_id )
				->addValue( 'label', $label )
				->addValue( 'name', $name )
				->addValue( 'html_type', 'Radio' )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'       => __METHOD__,
				'event'        => $event,
				'price_set_id' => $price_set_id,
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
		$price_field = $result->first();

		// --<
		return $price_field;

	}

	/**
	 * Creates a Price Field Value for a given Event.
	 *
	 * @since 0.8.2
	 *
	 * @param array $data {
	 *     The array of required data for the CiviCRM Price Field Value.
	 *
	 *     @type string $label The label for the Price Field Value.
	 *     @type string $amount The amount for the Price Field Value.
	 *     @type int    $financial_type_id The ID of the CiviCRM Financial Type.
	 * }
	 * @param int   $weight The weight for the Price Field Value.
	 * @param bool  $is_default True sets the Price Field Value as the default.
	 * @return array $price_field_value The array of CiviCRM Price Field data.
	 */
	public function price_field_value_create( $data, $weight = 1, $is_default = false ) {

		// Sanity checks.
		if ( empty( $data['price_field_id'] ) || ! is_numeric( $data['price_field_id'] ) ) {
			return false;
		}
		if ( empty( $data['financial_type_id'] ) || ! is_numeric( $data['financial_type_id'] ) ) {
			return false;
		}
		if ( empty( $data['label'] ) || ! is_string( $data['label'] ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			/*
			 * Some values are omitted because their defaults are fine:
			 *
			 * * `description` - Not set.
			 * * `help_pre` - Not set.
			 * * `help_post` - Not set.
			 * * `count` - Not set.
			 * * `non_deductible_amount` - Not set.
			 * * `visibility_id` - Not set.
			 *
			 * CiviCRM sets the `name` value to the value of the `label`.
			 */

			// Build common query.
			$query = \Civi\Api4\PriceFieldValue::create( false )
				->addValue( 'price_field_id', $data['price_field_id'] )
				->addValue( 'label', $data['label'] )
				->addValue( 'name', $data['label'] )
				->addValue( 'amount', $data['amount'] )
				->addValue( 'financial_type_id', $data['financial_type_id'] );

			// Maybe set weight.
			if ( 1 !== $weight ) {
				$query->addValue( 'weight', $weight );
			}

			// Maybe set as default.
			if ( ! empty( $is_default ) ) {
				$query->addValue( 'is_default', true );
			}

			// Call the API.
			$result = $query->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'data'      => $data,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Return if nothing found.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_field_value = $result->first();

		// --<
		return $price_field_value;

	}

	/**
	 * Updates a Price Field Value for a given Event.
	 *
	 * @since 0.8.2
	 *
	 * @param array $data {
	 *     The array of required data for the CiviCRM Price Field Value.
	 *
	 *     @type string $label The label for the Price Field Value.
	 *     @type string $amount The amount for the Price Field Value.
	 *     @type int    $financial_type_id The ID of the CiviCRM Financial Type.
	 * }
	 * @param int   $weight The weight for the Price Field Value.
	 * @param bool  $is_default True sets the Price Field Value as the default.
	 * @return array $price_field_value The array of CiviCRM Price Field data.
	 */
	public function price_field_value_update( $data, $weight = 1, $is_default = false ) {

		// Sanity checks.
		if ( empty( $data['label'] ) || ! is_string( $data['label'] ) ) {
			return false;
		}

		// Try and init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		try {

			// Build common query.
			$query = \Civi\Api4\PriceFieldValue::update( false )
				->addValue( 'price_field_id', $data['price_field_id'] )
				->addWhere( 'id', '=', $data['id'] )
				->addValue( 'label', $data['label'] )
				->addValue( 'amount', $data['amount'] )
				->addValue( 'weight', $weight );

			// Maybe set as default.
			if ( ! empty( $is_default ) ) {
				$query->addValue( 'is_default', true );
			}

			// Call the API.
			$result = $query->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'data'      => $data,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Return if nothing found.
		if ( 0 === $result->count() ) {
			return false;
		}

		// The first result is what we're after.
		$price_field_value = $result->first();

		// --<
		return $price_field_value;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Prepare the CiviCRM Price Field Value Record from an ACF Field.
	 *
	 * @since 0.8.2
	 *
	 * @param array   $value The array of Price Field Value data in the ACF Field.
	 * @param integer $price_field_value_id The numeric ID of the Price Field Value Record (or null if new).
	 * @return array $data The CiviCRM "Quick Config Price Set" Record data.
	 */
	public function prepare_from_field( $value, $price_field_value_id = null ) {

		// Init required data.
		$data = [];

		// Maybe add the "Quick Config Price Set" ID.
		if ( ! empty( $data ) ) {
			$data['id'] = (int) $price_field_value_id;
		}

		// Convert ACF data to CiviCRM data.
		$data['label']  = sanitize_text_field( $value['field_ceo_civicrm_fee_label'] );
		$data['amount'] = $value['field_ceo_civicrm_amount'];
		if ( ! empty( $value['field_ceo_civicrm_pfv_id'] ) ) {
			$data['id'] = (int) $value['field_ceo_civicrm_pfv_id'];
		}

		// --<
		return $data;

	}

	/**
	 * Prepare the ACF Field data from a CiviCRM "Quick Config Price Set" Record.
	 *
	 * @since 0.8.2
	 *
	 * @param array $value The array of "Quick Config Price Set" Record data in CiviCRM.
	 * @return array $data The ACF "Quick Config Price Set" data.
	 */
	public function prepare_from_civicrm( $value ) {

		// Init required data.
		$data = [];

		// Maybe cast as an object.
		if ( ! is_object( $value ) ) {
			$value = (object) $value;
		}

		// Convert CiviCRM data to ACF data.
		$data['field_ceo_civicrm_fee_label'] = trim( $value->label );
		$data['field_ceo_civicrm_amount']    = $value->amount;
		$data['field_ceo_civicrm_pfv_id']    = (int) $value->id;

		// --<
		return $data;

	}

	/**
	 * Add any "Quick Config Price Set" Fields that are attached to a Post.
	 *
	 * @since 0.8.2
	 *
	 * @param array   $acf_fields The existing ACF Fields array.
	 * @param array   $field The ACF Field.
	 * @param integer $post_id The numeric ID of the WordPress Post.
	 * @return array $acf_fields The modified ACF Fields array.
	 */
	public function acf_fields_get_for_post( $acf_fields, $field, $post_id ) {

		// Add if it has a reference to a "Quick Config Price Set" Field.
		if ( ! empty( $field['type'] ) && 'ceo_civicrm_price_set_quick' === $field['type'] ) {
			$acf_fields['price_set_quick'][ $field['name'] ] = $field['type'];
		}

		// --<
		return $acf_fields;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Sync the CiviCRM ""Quick Config Price Set" ID" to the ACF Fields on a WordPress Post.
	 *
	 * @since 0.8.2
	 *
	 * @param array $params The "Quick Config Price Set" data.
	 * @param array $args The array of WordPress params.
	 */
	public function maybe_sync_price_field_value_id( $params, $args ) {

		// Check permissions.
		if ( ! current_user_can( 'edit_event', $args['post_id'] ) ) {
			return;
		}

		// Get existing Field value.
		$existing = get_field( $params['selector'], $args['post_id'] );

		// Add Price Field Value ID and overwrite array element.
		if ( ! empty( $existing[ $params['key'] ] ) ) {
			/*
			// Do we want to sync this back in CiviCRM money format>
			$params['value']['field_ceo_civicrm_amount'] = $params['price_field_value']['amount'];
			*/
			$params['value']['field_ceo_civicrm_pfv_id'] = $params['price_field_value_id'];
			$existing[ $params['key'] ]                  = $params['value'];
		}

		// Now update Field.
		$this->compat->cwps->acf->acf->field->value_update( $params['selector'], $existing, $args['post_id'] );

	}

}
