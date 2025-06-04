<?php
/**
 * CiviCRM Profile Sync compatibility Class.
 *
 * Handles compatibility with the CiviCRM Profile Sync plugin.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Profile Sync compatibility Class.
 *
 * This class provides compatibility with the CiviCRM Profile Sync plugin.
 *
 * @since 0.6.2
 */
class CEO_Compat_CWPS {

	/**
	 * Plugin object.
	 *
	 * @since 0.6.2
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
	 * CiviCRM Profile Sync plugin reference.
	 *
	 * @since 0.6.2
	 * @access public
	 * @var CiviCRM_WP_Profile_Sync
	 */
	public $cwps = false;

	/**
	 * Constructor.
	 *
	 * @since 0.6.2
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin = $parent->plugin;
		$this->compat = $parent;

		// Add CiviCRM hooks when plugin is loaded.
		add_action( 'ceo/compat/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.6.2
	 */
	public function initialise() {

		// Bail if there's no ACF plugin present.
		if ( ! function_exists( 'acf' ) ) {
			return;
		}

		// Prefer CiviCRM ACF Integration if present.
		if ( function_exists( 'civicrm_acf_integration' ) ) {
			return;
		}

		// Bail if there's no CiviCRM Profile Sync plugin present.
		if ( ! defined( 'CIVICRM_WP_PROFILE_SYNC_VERSION' ) ) {
			return;
		}

		// Bail if CiviCRM Profile Sync is not version 0.4 or greater.
		if ( version_compare( CIVICRM_WP_PROFILE_SYNC_VERSION, '0.4', '<' ) ) {
			return;
		}

		// Wait for next action to finish set up.
		add_action( 'sanitize_comment_cookies', [ $this, 'setup_instance' ] );

	}

	/**
	 * Wait until "plugins_loaded" has finished to set up instance.
	 *
	 * This is necessary because the order in which plugins load cannot be
	 * guaranteed and we need to find out if CiviCRM Profile Sync has fully
	 * loaded its ACF classes.
	 *
	 * @since 0.6.2
	 */
	public function setup_instance() {

		// Grab reference to CiviCRM Profile Sync.
		$plugin = civicrm_wp_profile_sync();

		// Bail if CiviCRM Profile Sync hasn't loaded ACF.
		if ( ! $plugin->acf->is_loaded() ) {
			return;
		}

		// Store reference.
		$this->cwps = $plugin;

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.6.2
	 */
	public function register_hooks() {

		// Include any Field Types that we have defined after ACFE does.
		add_action( 'acf/include_field_types', [ $this, 'register_field_types' ], 100 );

		// Listen for events from the Mapper that require Event updates.
		add_action( 'cwps/acf/mapper/acf_fields/saved', [ $this, 'acf_fields_saved' ], 10, 1 );

		// Listen for queries from our Field Group class.
		add_filter( 'cwps/acf/query_field_group_mapped', [ $this, 'query_field_group_mapped' ], 10, 2 );

		// Listen for queries from the ACF Field class.
		add_filter( 'cwps/acf/field/query_setting_choices', [ $this, 'query_setting_choices' ], 50, 3 );

		// Listen for queries from our Custom Field class.
		add_filter( 'cwps/acf/query_custom_fields', [ $this, 'query_custom_fields' ], 10, 2 );

		// Listen for queries from the Custom Field class.
		add_filter( 'cwps/acf/query_post_id', [ $this, 'query_post_id' ], 10, 2 );

		// Listen for queries from the Attachment class.
		add_filter( 'cwps/acf/query_entity_table', [ $this, 'query_entity_table' ], 10, 2 );

		// Exclude "Event" from being mapped to a CiviCRM Entity Type.
		add_filter( 'cwps/acf/post_types/get_all', [ $this, 'post_types_filter' ], 10, 1 );

		// Listen for a CiviCRM Event being synced to an Event Organiser Event.
		add_action( 'ceo/admin/manual_sync/civi_to_eo/sync/after', [ $this, 'sync_to_eo' ], 10, 1 );

		// Listen for an Event Organiser Event being synced to a CiviCRM Event.
		add_action( 'ceo/admin/manual_sync/eo_to_civi/sync', [ $this, 'sync_to_civi' ], 10, 1 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Registers our Field Types for ACF.
	 *
	 * @since 0.7.3
	 *
	 * @param string $version The installed version of ACF.
	 */
	public function register_field_types( $version ) {

		// Include Field Types.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/compat/acf/fields/class-acf-field-civicrm-event-id.php';

		// Init Field Types.
		new CEO_ACF_Custom_CiviCRM_Event_ID_Field( $this );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Update the CiviCRM Events when the ACF Fields on an Event Organiser Event have been updated.
	 *
	 * @since 0.6.2
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function acf_fields_saved( $args ) {

		// Bail early if this Field Group is not attached to a Post Type.
		if ( ! is_numeric( $args['post_id'] ) ) {
			return;
		}

		// We need the Post itself.
		$post = get_post( $args['post_id'] );

		// Bail if this is not an Event Organiser Event.
		if ( 'event' !== $post->post_type ) {
			return;
		}

		// Get existing CiviCRM Events from post meta.
		$correspondences = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $args['post_id'] );

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

		// Add our data to the params.
		$args['fields'] = $fields;

		// We only ever update a CiviCRM Event via ACF.
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10 );

		// Loop through the CiviCRM Events.
		foreach ( $correspondences as $occurrence_id => $civi_event_id ) {

			// Update each CiviCRM Event.
			$civi_event = $this->update_from_fields( $civi_event_id, $fields );

			// Add our data to the params.
			$args['civi_event_id'] = $civi_event_id;
			$args['civi_event']    = $civi_event;
			$args['post_id']       = $post->ID;
			$args['post']          = $post;

			/**
			 * Broadcast that an Event has been updated when ACF Fields were saved.
			 *
			 * @since 0.7.2
			 *
			 * @param array $args The updated array of WordPress params.
			 */
			do_action( 'ceo/acf/event/acf_fields_saved', $args );

		}

		// Restore hook.
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10, 4 );

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
	 * @param int   $event_id The numeric ID of the CiviCRM Event.
	 * @param array $fields The ACF Field data.
	 * @param int   $post_id The numeric ID of the WordPress Post.
	 * @return array|bool $event_data The CiviCRM Event data.
	 */
	public function prepare_from_fields( $event_id, $fields, $post_id = null ) {

		// Init data for fields.
		$event_data = [];

		// Bail if we have no field data to save.
		if ( empty( $fields ) ) {
			return $event_data;
		}

		// Loop through the field data.
		foreach ( $fields as $selector => $value ) {

			// Get the Field settings.
			$settings = get_field_object( $selector, $post_id );

			// Get the CiviCRM Custom Field.
			$custom_field_id = $this->cwps->acf->civicrm->custom_field->custom_field_id_get( $settings );

			// Skip if there's no corresponding CiviCRM Custom Field.
			if ( empty( $custom_field_id ) ) {
				continue;
			}

			// Build Custom Field code.
			$code = 'custom_' . $custom_field_id;

			// Build args for value conversion.
			$args = [
				'identifier'      => 'event',
				'entity_id'       => $event_id,
				'custom_field_id' => $custom_field_id,
				'field_name'      => '',
				'selector'        => $selector,
				'post_id'         => $post_id,
			];

			$value = $this->cwps->acf->acf->field->value_get_for_civicrm( $value, $settings['type'], $settings, $args );

			// Add it to the field data.
			$event_data[ $code ] = $value;

		}

		// --<
		return $event_data;

	}

	/**
	 * Update a CiviCRM Event with data from ACF Fields.
	 *
	 * @since 0.3
	 *
	 * @param int   $event_id The numeric ID of the CiviCRM Event.
	 * @param array $fields The ACF Field data.
	 * @param int   $post_id The numeric ID of the WordPress Post.
	 * @return array|bool $event The CiviCRM Event data, or false on failure.
	 */
	public function update_from_fields( $event_id, $fields, $post_id = null ) {

		// Build required data.
		$event_data = $this->prepare_from_fields( $event_id, $fields, $post_id );

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
	 * @since 0.6.2
	 *
	 * @param array $event The CiviCRM Event data.
	 * @return array|bool $event_data The array Event data from the CiviCRM API, or false on failure.
	 */
	public function event_update( $event ) {

		// Init Event data.
		$event_data = false;

		// Log and bail if there's no Event ID.
		if ( empty( $event['id'] ) ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'A numeric ID must be present to update an Event.', 'civicrm-event-organiser' ),
				'event'     => $event,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $event_data;
		}

		// Build params to update an Event.
		$params = [
			'version' => 3,
		] + $event;

		// Call the API.
		$result = civicrm_api( 'Event', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
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

	// -----------------------------------------------------------------------------------

	/**
	 * Returns the choices for a Setting Field from this Entity when found.
	 *
	 * @since 0.6.4
	 *
	 * @param array $choices The existing array of choices for the Setting Field.
	 * @param array $field The ACF Field data array.
	 * @param array $field_group The ACF Field Group data array.
	 * @param bool  $skip_check True if the check for Field Group should be skipped. Default false.
	 * @return array $choices The modified array of choices for the Setting Field.
	 */
	public function query_setting_choices( $choices, $field, $field_group, $skip_check = false ) {

		// Pass if this is not an Event Field Group.
		$is_event_field_group = $this->is_event_field_group( $field_group );
		if ( false === $is_event_field_group ) {
			return $choices;
		}

		// Get the Custom Fields for CiviCRM Events.
		if ( method_exists( $this->cwps->civicrm->custom_field, 'get_for_entity_type' ) ) {
			$custom_fields = $this->cwps->civicrm->custom_field->get_for_entity_type( 'Event', '' );
		} else {
			$custom_fields = $this->cwps->acf->civicrm->custom_field->get_for_entity_type( 'Event', '' );
		}

		/**
		 * Filter the Custom Fields.
		 *
		 * @since 0.6.4
		 *
		 * @param array The initially empty array of filtered Custom Fields.
		 * @param array $custom_fields The CiviCRM Custom Fields array.
		 * @param array $field The ACF Field data array.
		 */
		$filtered_fields = apply_filters( 'cwps/acf/query_settings/custom_fields_filter', [], $custom_fields, $field );

		// Pass if not populated.
		if ( empty( $filtered_fields ) ) {
			return $choices;
		}

		// Build Custom Field choices array for dropdown.
		$custom_field_prefix = $this->cwps->acf->civicrm->custom_field_prefix();
		foreach ( $filtered_fields as $custom_group_name => $custom_group ) {
			$custom_fields_label = esc_attr( $custom_group_name );
			foreach ( $custom_group as $custom_field ) {
				$choices[ $custom_fields_label ][ $custom_field_prefix . $custom_field['id'] ] = $custom_field['label'];
			}
		}

		// Return populated array.
		return $choices;

	}

	/**
	 * Listen for queries from the Field Group class.
	 *
	 * This method responds with a Boolean if it detects that this Field Group
	 * maps to the Event Organiser Event Post Type.
	 *
	 * @since 0.6.2
	 *
	 * @param bool  $mapped The existing mapping flag.
	 * @param array $field_group The array of ACF Field Group data.
	 * @return bool $mapped True if the Field Group is mapped, or pass through if not mapped.
	 */
	public function query_field_group_mapped( $mapped, $field_group ) {

		// Bail if a Mapping has already been found.
		if ( false !== $mapped ) {
			return $mapped;
		}

		// Bail if this is not an Event Field Group.
		$is_event_field_group = $this->is_event_field_group( $field_group );
		if ( false === $is_event_field_group ) {
			return $mapped;
		}

		// --<
		return true;

	}

	/**
	 * Listen for queries from the Custom Field class.
	 *
	 * @since 0.6.2
	 *
	 * @param array $custom_fields The existing Custom Fields.
	 * @param array $field_group The array of ACF Field Group data.
	 * @return array $custom_fields The populated array of CiviCRM Custom Fields params.
	 */
	public function query_custom_fields( $custom_fields, $field_group ) {

		// Bail if this is not an Event Field Group.
		$is_visible = $this->is_event_field_group( $field_group );
		if ( false === $is_visible ) {
			return $custom_fields;
		}

		// Get the Custom Fields for CiviCRM Events.
		if ( method_exists( $this->cwps->civicrm->custom_field, 'get_for_entity_type' ) ) {
			$event_custom_fields = $this->cwps->civicrm->custom_field->get_for_entity_type( 'Event', '' );
		} else {
			$event_custom_fields = $this->cwps->acf->civicrm->custom_field->get_for_entity_type( 'Event', '' );
		}

		// Maybe merge with passed in array.
		if ( ! empty( $event_custom_fields ) ) {
			$custom_fields = array_merge( $custom_fields, $event_custom_fields );
		}

		// --<
		return $custom_fields;

	}

	/**
	 * Listen for queries from the Custom Field class.
	 *
	 * This method responds with a "Post ID" if it detects that the set of Custom
	 * Fields maps to an Event.
	 *
	 * @since 0.6.2
	 *
	 * @param array|bool $post_ids The existing "Post IDs".
	 * @param array      $args The array of CiviCRM Custom Fields params.
	 * @return array|bool $post_ids The mapped "Post IDs", or false if not mapped.
	 */
	public function query_post_id( $post_ids, $args ) {

		// Init Event Post IDs.
		$event_post_ids = [];

		// Let's tease out the context from the Custom Field data.
		foreach ( $args['custom_fields'] as $field ) {

			// Skip if it is not attached to a Event.
			if ( 'civicrm_event' !== $field['entity_table'] ) {
				continue;
			}

			// Get the "Post ID" that this Event is mapped to.
			$post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $field['entity_id'] );

			// Skip to next if not found.
			if ( false === $post_id ) {
				continue;
			}

			// Cast as an array.
			$event_post_ids = [ $post_id ];

			// We can bail now that we know.
			break;

		}

		// Bail if we didn't find any Event "Post IDs".
		if ( empty( $event_post_ids ) ) {
			return $post_ids;
		}

		// Add found "Post IDs" to return array.
		if ( is_array( $post_ids ) ) {
			$post_ids = array_merge( $post_ids, $event_post_ids );
		} else {
			$post_ids = $event_post_ids;
		}

		// --<
		return $post_ids;

	}

	/**
	 * Listen for queries from the Attachment class.
	 *
	 * This method responds with an "Entity Table" if it detects that the ACF
	 * Field Group maps to an Event.
	 *
	 * @since 0.6.6
	 *
	 * @param array $entity_tables The existing "Entity Tables".
	 * @param array $field_group The array of ACF Field Group params.
	 * @return array $entity_tables The mapped "Entity Tables".
	 */
	public function query_entity_table( $entity_tables, $field_group ) {

		// Bail if this is not an Event Field Group.
		$is_visible = $this->is_event_field_group( $field_group );
		if ( false === $is_visible ) {
			return $entity_tables;
		}

		// Append our "Entity Table" if not already present.
		if ( ! in_array( 'civicrm_event', $entity_tables ) ) {
			$entity_tables['civicrm_event'] = __( 'Event', 'civicrm-event-organiser' );
		}

		// --<
		return $entity_tables;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Check if this Field Group has been mapped to the Event Post Type.
	 *
	 * @since 0.6.2
	 *
	 * @param array $field_group The Field Group to check.
	 * @return bool True if the Field Group has been mapped to the Event Post Type, or false otherwise.
	 */
	public function is_event_field_group( $field_group ) {

		// Bail if there's no Field Group ID.
		if ( empty( $field_group['ID'] ) ) {
			return false;
		}

		// Only do this once per Field Group.
		static $pseudocache;
		if ( isset( $pseudocache[ $field_group['ID'] ] ) ) {
			return $pseudocache[ $field_group['ID'] ];
		}

		// Assume not visible.
		$is_visible = false;

		// Bail if no Location Rules exist.
		if ( ! empty( $field_group['location'] ) ) {

			// Define params to test for Event Location.
			$params = [
				'post_type' => 'event',
			];

			// Do the check.
			$is_visible = $this->cwps->acf->acf->field_group->is_visible( $field_group, $params );

		}

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[ $field_group['ID'] ] ) ) {
			$pseudocache[ $field_group['ID'] ] = $is_visible;
		}

		// --<
		return $is_visible;

	}

	/**
	 * Exclude "Event" from being mapped to a Contact Type.
	 *
	 * @since 0.6.2
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

	// -----------------------------------------------------------------------------------

	/**
	 * Intercept when a CiviCRM Event has been synced to an Event Organiser Event.
	 *
	 * Update any associated ACF Fields with their Custom Field values.
	 *
	 * @since 0.5.2
	 *
	 * @param array $args The array of CiviCRM Event and Event Organiser Event params.
	 */
	public function sync_to_eo( $args ) {

		// Grab Event ID.
		$event_id = $args['event_id'];

		// Get all ACF Fields for the Event.
		$acf_fields = $this->cwps->acf->acf->field->fields_get_for_post( $event_id );

		// Bail if we don't have any Custom Fields in ACF.
		if ( empty( $acf_fields['custom'] ) ) {
			return;
		}

		// Get all the Custom Fields for CiviCRM Events.
		if ( method_exists( $this->cwps->civicrm->custom_field, 'get_for_entity_type' ) ) {
			$civicrm_custom_fields = $this->cwps->civicrm->custom_field->get_for_entity_type( 'Event', '' );
		} else {
			$civicrm_custom_fields = $this->cwps->acf->civicrm->custom_field->get_for_entity_type( 'Event', '' );
		}

		// Bail if there are none.
		if ( empty( $civicrm_custom_fields ) ) {
			return;
		}

		// Flatten the array since we don't need labels.
		$custom_fields = [];
		foreach ( $civicrm_custom_fields as $key => $field_group ) {
			foreach ( $field_group as $custom_field ) {
				$custom_field['type'] = $custom_field['data_type'];
				$custom_fields[]      = $custom_field;
			}
		}

		// CiviCRM Event data contains the associated Custom Field data.
		$custom_field_data = [];
		foreach ( $args['civi_event'] as $key => $value ) {
			// CiviCRM only appends populated Custom Fields.
			if ( substr( $key, 0, 7 ) === 'custom_' ) {
				$index                       = str_replace( 'custom_', '', $key );
				$custom_field_data[ $index ] = $value;
			}
		}

		// Let's run through each Custom Field in turn.
		foreach ( $acf_fields['custom'] as $selector => $custom_field_ref ) {

			// Prime with an empty string.
			$value = '';

			// Safely get the value from the Custom Field values.
			if ( isset( $custom_field_data[ $custom_field_ref ] ) ) {
				$value = $custom_field_data[ $custom_field_ref ];
			}

			// Grab the CiviCRM field definition.
			$filtered = wp_list_filter( $custom_fields, [ 'id' => $custom_field_ref ] );
			$field    = array_pop( $filtered );

			// Contact Reference fields return the Contact's "sort_name".
			if ( 'ContactReference' === $field['type'] ) {

				// Overwrite value if the raw value exists.
				$key = $field['id'] . '_id';
				if ( isset( $custom_field_data[ $key ] ) ) {
					$value = $custom_field_data[ $key ];
				}

			}

			// Parse the value for ACF.
			$value = $this->cwps->acf->civicrm->custom_field->value_get_for_acf( $value, $field, $selector, $event_id );

			// Update the value of the ACF Field.
			$this->cwps->acf->acf->field->value_update( $selector, $value, $event_id );

		}

	}

	/**
	 * Intercept when an Event Organiser Event has been synced to a CiviCRM Event.
	 *
	 * Update any associated Custom Fields with their ACF Field values.
	 *
	 * @since 0.5.2
	 *
	 * @param array $args The array of CiviCRM Event and Event Organiser Event params.
	 */
	public function sync_to_civi( $args ) {

		// Pass on.
		$this->acf_fields_saved( $args );

	}

}
