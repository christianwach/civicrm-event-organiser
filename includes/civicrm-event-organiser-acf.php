<?php

/**
 * CiviCRM Event Organiser ACF Class.
 *
 * This class provides compatibility with the CiviCRM ACF Integration plugin.
 *
 * @since 0.4.4
 */
class CiviCRM_WP_Event_Organiser_ACF {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM ACF Integration reference.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var object $acf The CiviCRM ACF Integration plugin reference.
	 */
	public $cacf = false;



	/**
	 * Initialises this object.
	 *
	 * @since 0.4.4
	 */
	public function __construct() {

		// Add CiviCRM hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.4.4
	 *
	 * @param object $parent The parent object.
	 */
	public function set_references( $parent ) {

		// Store reference.
		$this->plugin = $parent;

	}



	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.4.4
	 */
	public function initialise() {

		// Maybe store reference to CiviCRM ACF Integration.
		if ( function_exists( 'civicrm_acf_integration' ) ) {
			$this->cacf = civicrm_acf_integration();
		}

		// Bail if CiviCRM ACF Integration isn't detected.
		if ( $this->cacf === false ) {
			return;
		}

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.4.4
	 */
	public function register_hooks() {

		// Listen for events from the Mapper that require Event updates.
		add_action( 'civicrm_acf_integration_mapper_acf_fields_saved', [ $this, 'acf_fields_saved' ], 10, 1 );

		// Listen for queries from our Field Group class.
		add_action( 'civicrm_acf_integration_query_field_group_mapped', [ $this, 'query_field_group_mapped' ], 10, 2 );

		// Listen for queries from our Custom Field class.
		add_action( 'civicrm_acf_integration_query_custom_fields', [ $this, 'query_custom_fields' ], 10, 2 );

		// Listen for queries from the Custom Field class.
		add_action( 'civicrm_acf_integration_query_post_id', [ $this, 'query_post_id' ], 10, 2 );

		// Exclude "Event" from being mapped to a Contact Type.
		add_filter( 'civicrm_acf_integration_post_types_get_all', [ $this, 'post_types_filter' ], 10, 1 );

		// Listen for a CiviEvent being synced to an EO Event.
		add_action( 'civicrm_event_organiser_admin_civi_to_eo_sync', [ $this, 'sync_to_eo' ], 10, 1 );

		// Listen for an EO Event being synced to a CiviEvent.
		add_action( 'civicrm_event_organiser_admin_eo_to_civi_sync', [ $this, 'sync_to_civi' ], 10, 1 );

	}



	//##########################################################################



	/**
	 * Update the CiviCRM Events when the ACF Fields on an EO Event have been updated.
	 *
	 * @since 0.4.4
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function acf_fields_saved( $args ) {

		// We need the Post itself.
		$post = get_post( $args['post_id'] );

		// Bail if this is not an EO Event.
		if ( $post->post_type != 'event' ) {
			return;
		}

		// Get existing CiviEvents from post meta.
		$correspondences = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $args['post_id'] );

		// Bail if we have no correspondences.
		if ( count( $correspondences ) === 0 ) {
			return;
		}

		/*
		 * Get existing field values.
		 *
		 * These are actually the *new* values because we are hooking in *after*
		 * the fields have been saved.
		 */
		$fields = get_fields( $args['post_id'] );

		// We only ever update a CiviCRM Event via ACF.
		remove_action( 'civicrm_post', [ $this->plugin->civi, 'event_updated' ], 10 );

		// Loop through the CiviCRM Events and update.
		foreach( $correspondences AS $event_id ) {
			$this->update_from_fields( $event_id, $fields );
		}

		// Restore hook.
		add_action( 'civicrm_post', [ $this->plugin->civi, 'event_updated' ], 10, 4 );

	}



	/**
	 * Prepare the required CiviCRM Event data from a set of ACF Fields.
	 *
	 * The CiviCRM API will update Custom Fields as long as they are passed to
	 * ( 'Event', 'create' ) in the correct format. This is of the form:
	 * 'custom_N' where N is the ID of the Custom Field.
	 *
	 * Some Fields have to be handled elsewhere (e.g. 'email') because they are
	 * not included in these API calls.
	 *
	 * @see CiviCRM_ACF_Integration_CiviCRM_Base
	 *
	 * @since 0.2
	 *
	 * @param array $fields The ACF Field data.
	 * @return array|bool $event_data The CiviCRM Event data.
	 */
	public function prepare_from_fields( $fields ) {

		// Init data for fields.
		$event_data = [];

		// Bail if we have no field data to save.
		if ( empty( $fields ) ) {
			return $event_data;
		}

		// Loop through the field data.
		foreach( $fields AS $field => $value ) {

			// Get the Field settings.
			$settings = get_field_object( $field );

			// Get the CiviCRM Custom Field.
			$custom_field_id = $this->cacf->civicrm->contact->custom_field_id_get( $settings );

			// Build Custom Field code.
			if ( ! empty( $custom_field_id ) ) {
				$code = 'custom_' . $custom_field_id;
			}

			// Parse value by field type.
			$value = $this->cacf->acf->field->value_get_for_civicrm( $settings['type'], $value );

			// Add it to the field data.
			$event_data[$code] = $value;

		}

		// --<
		return $event_data;

	}



	/**
	 * Update a CiviCRM Event with data from ACF Fields.
	 *
	 * @since 0.3
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param array $fields The ACF Field data.
	 * @return array|bool $event The CiviCRM Event data, or false on failure.
	 */
	public function update_from_fields( $event_id, $fields ) {

		// Build required data.
		$event_data = $this->prepare_from_fields( $fields );

		// Add the Event ID.
		$event_data['id'] = $event_id;

		// Update the Event.
		$event = $this->event_update( $event_data );

		// --<
		return $event;

	}



	/**
	 * Update a CiviCRM Event with a given set of data.
	 *
	 * @since 0.4.4
	 *
	 * @param array $event The CiviCRM Event data.
	 * @return array|bool $event_data The array Event data from the CiviCRM API, or false on failure.
	 */
	public function event_update( $event ) {

		// Init Event data.
		$event_data = false;

		// Log and bail if there's no Event ID.
		if ( empty( $event['id'] ) ) {
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'A numerical ID must be present to update an Event.', 'civicrm-event_organiser' ),
				'event' => $event,
				'backtrace' => $trace,
			], true ) );
			return $event_data;
		}

		// Build params to update an Event.
		$params = [
			'version' => 3,
		] + $event;

		// Call the API.
		$result = civicrm_api( 'Event', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) AND $result['is_error'] == 1 ) {
			return $event_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $event_data;
		}

		// The result set should contain only one item.
		$event_data = array_pop( $result['values'] );

		// Pass through.
		return $event_data;

	}



	//##########################################################################



	/**
	 * Listen for queries from the Field Group class.
	 *
	 * This method responds with a Boolean if it detects that this Field Group
	 * maps to the EO Event Post Type.
	 *
	 * @since 0.4.4
	 *
	 * @param bool $mapped The existing mapping flag.
	 * @param array $field_group The array of ACF Field Group data.
	 * @param bool $mapped True if the Field Group is mapped, or pass through if not mapped.
	 */
	public function query_field_group_mapped( $mapped, $field_group ) {

		// Bail if a Mapping has already been found.
		if ( $mapped !== false ) {
			return $mapped;
		}

		// Bail if this is not an Event Field Group.
		$is_event_field_group = $this->is_event_field_group( $field_group );
		if ( $is_event_field_group === false ) {
			return $mapped;
		}

		// --<
		return true;

	}



	/**
	 * Listen for queries from the Custom Field class.
	 *
	 * @since 0.4.4
	 *
	 * @param array $custom_fields The existing Custom Fields.
	 * @param array $field_group The array of ACF Field Group data.
	 * @param array $custom_fields The populated array of CiviCRM Custom Fields params.
	 */
	public function query_custom_fields( $custom_fields, $field_group ) {

		// Bail if this is not an Event Field Group.
		$is_visible = $this->is_event_field_group( $field_group );
		if ( $is_visible === false ) {
			return $custom_fields;
		}

		// Get the Custom Fields for CiviCRM Events.
		$custom_fields = $this->cacf->civicrm->custom_field->get_for_entity_type( 'Event', '' );

		// --<
		return $custom_fields;

	}



	/**
	 * Listen for queries from the Custom Field class.
	 *
	 * This method responds with a Post ID if it detects that the set of Custom
	 * Fields maps to an Event.
	 *
	 * @since 0.4.4
	 *
	 * @param bool $post_id False, since we're asking for a Post ID.
	 * @param array $args The array of CiviCRM Custom Fields params.
	 * @param bool|int $post_id The mapped Post ID, or false if not mapped.
	 */
	public function query_post_id( $post_id, $args ) {

		// Bail early if a Post ID has been found.
		if ( $post_id !== false ) {
			return $post_id;
		}

		// Let's tease out the context from the Custom Field data.
		foreach( $args['custom_fields'] AS $field ) {

			// Skip if it is not attached to a Event.
			if ( $field['entity_table'] != 'civicrm_event' ) {
				continue;
			}

			// Get the Post ID that this Event is mapped to.
			$post_id = $this->plugin->db->get_eo_event_id_by_civi_event_id( $field['entity_id'] );

			// We can bail now that we know.
			break;

		}

		// --<
		return $post_id;

	}



	//##########################################################################



	/**
	 * Check if this Field Group has been mapped to the Event Post Type.
	 *
	 * @since 0.4.4
	 *
	 * @param array $field_group The Field Group to check.
	 * @return bool True if the Field Group has been mapped to the Event Post Type, or false otherwise.
	 */
	public function is_event_field_group( $field_group ) {

		// Only do this once per Field Group.
		static $pseudocache;
		if ( isset( $pseudocache[$field_group['ID']] ) ) {
			return $pseudocache[$field_group['ID']];
		}

		// Assume not visible.
		$is_visible = false;

		// Bail if no location rules exist.
		if ( ! empty( $field_group['location'] ) ) {

			// Define params to test for Event location.
			$params = [
				'post_type' => 'event',
			];

			// Do the check.
			$is_visible = $this->cacf->acf->field_group->is_visible( $field_group, $params );

		}

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$field_group['ID']] ) ) {
			$pseudocache[$field_group['ID']] = $is_visible;
		}

		// --<
		return $is_visible;

	}



	/**
	 * Exclude "Event" from being mapped to a Contact Type.
	 *
	 * @since 0.4.4
	 *
	 * @param array $post_types The array of WordPress Post Types.
	 * @return array $post_types The modified array of WordPress Post Types.
	 */
	public function post_types_filter( $post_types ) {

		// Exclude "Event" if it's there.
		if ( isset( $post_types['event'] ) ) {
			unset( $post_types['event'] );
		}

		// --<
		return $post_types;

	}



	//##########################################################################



	/**
	 * Intercept when a CiviEvent has been synced to an EO Event.
	 *
	 * Update any associated ACF Fields with their Custom Field values.
	 *
	 * @since 0.5.2
	 *
	 * @param array $args The array of CiviCRM Event and EO Event params.
	 */
	public function sync_to_eo( $args ) {

		// Grab Event ID.
		$event_id = $args['event_id'];

		// Get all ACF Fields for the Event.
		$acf_fields = $this->cacf->acf->field->fields_get_for_post( $event_id );

		// Bail if we don't have any Custom Fields in ACF.
		if ( empty( $acf_fields['custom'] ) ) {
			return;
		}

		// Get all the Custom Fields for CiviCRM Events.
		$civicrm_custom_fields = $this->cacf->civicrm->custom_field->get_for_entity_type( 'Event', '' );

		// Bail if there are none.
		if ( empty( $civicrm_custom_fields ) ) {
			return;
		}

		// Flatten the array since we don't need labels.
		$custom_fields = [];
		foreach( $civicrm_custom_fields AS $key => $field_group ) {
			foreach( $field_group AS $custom_field ) {
				$custom_field['type'] = $custom_field['data_type'];
				$custom_fields[] = $custom_field;
			}
		}

		// CiviEvent data contains the associated Custom Field data! *smile*
		$custom_field_data = [];
		foreach( $args['civi_event'] AS $key => $value ) {
			// CiviCRM only appends populated Custom Fields.
			if ( substr( $key, 0, 7 ) == 'custom_' ) {
				$index = str_replace( 'custom_', '', $key );
				$custom_field_data[$index] = $value;
			}
		}

		// Let's run through each Custom Field in turn.
		foreach( $acf_fields['custom'] AS $selector => $custom_field_ref ) {

			// Prime with an empty string.
			$value = '';

			// Safely get the value from the Custom Field values.
			if ( isset( $custom_field_data[$custom_field_ref] ) ) {
				$value = $custom_field_data[$custom_field_ref];
			}

			// Grab the CiviCRM field definition.
			$filtered = wp_list_filter( $custom_fields, [ 'id' => $custom_field_ref ] );
			$field = array_pop( $filtered );

			// Contact Reference fields return the Contact's "sort_name".
			if ( $field['type'] == 'ContactReference' ) {

				// Overwrite value if the raw value exists.
				$key = $field['id'] . '_id';
				if ( isset( $custom_field_data[$key] ) ) {
					$value = $custom_field_data[$key];
				}

			}

			// Parse the value for ACF.
			$value = $this->cacf->civicrm->custom_field->value_get_for_acf( $value, $field, $selector, $event_id );

			// Update the value of the ACF Field.
			$this->cacf->acf->field->value_update( $selector, $value, $event_id );

		}

	}



	/**
	 * Intercept when an EO Event has been synced to a CiviEvent.
	 *
	 * Update any associated Custom Fields with their ACF Field values.
	 *
	 * @since 0.5.2
	 *
	 * @param array $args The array of CiviCRM Event and EO Event params.
	 */
	public function sync_to_civi( $args ) {

		// Set Post ID to be compatible with CAI.
		$args['post_id'] = $args['event_id'];

		// Pass on.
		$this->acf_fields_saved( $args );

	}



} // Class ends.



