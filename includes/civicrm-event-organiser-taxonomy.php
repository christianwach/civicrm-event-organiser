<?php

/**
 * CiviCRM Event Organiser Taxonomy Class.
 *
 * This class keeps the Event Organiser custom taxonomy in sync with the CiviCRM
 * Event Types.
 *
 * @since 0.4.2
 */
class CiviCRM_WP_Event_Organiser_Taxonomy {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.4.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Term Meta key.
	 *
	 * @since 0.4.5
	 * @access public
	 * @var object $term_meta_key The Term Meta key.
	 */
	public $term_meta_key = '_ceo_civi_event_type_id';



	/**
	 * Initialises this object.
	 *
	 * @since 0.4.2
	 */
	public function __construct() {

		// Add CiviCRM hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', array( $this, 'register_hooks' ) );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.4.2
	 *
	 * @param object $parent The parent object.
	 */
	public function set_references( $parent ) {

		// Store reference.
		$this->plugin = $parent;

	}



	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.4.2
	 */
	public function register_hooks() {

		// Intercept new term creation.
		add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );

		// Intercept term updates.
		add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 2 );
		add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );

		// Intercept term deletion.
		add_action( 'delete_term', array( $this, 'intercept_delete_term' ), 20, 4 );

		// Filter Radio Buttons for Taxonomies null term insertion.
		add_filter( 'radio-buttons-for-taxonomies-no-term-event-category', array( $this, 'skip_null_term' ), 20, 1 );
		add_filter( 'radio_buttons_for_taxonomies_no_term_event-category', array( $this, 'skip_null_term' ), 20, 1 );

		// Hide "Parent Category" dropdown in event category metaboxes.
		add_action( 'add_meta_boxes_event', array( $this, 'terms_dropdown_intercept' ), 3 );

		// Ensure new events have the default term checked.
		add_filter( 'wp_terms_checklist_args', array( $this, 'term_default_checked' ), 10, 2 );

		// Intercept CiviCRM Event Type enable/disable.
		//add_action( 'civicrm_enableDisable', array( $this, 'event_type_toggle' ), 10, 3 );

		// Intercept CiviCRM Event Type form edits.
		add_action( 'civicrm_postProcess', array( $this, 'event_type_process_form' ), 10, 2 );

		// Create custom filters that mirror 'the_content'.
		add_filter( 'civicrm_eo_term_content', 'wptexturize' );
		add_filter( 'civicrm_eo_term_content', 'convert_smilies' );
		add_filter( 'civicrm_eo_term_content', 'convert_chars' );
		add_filter( 'civicrm_eo_term_content', 'wpautop' );
		add_filter( 'civicrm_eo_term_content', 'shortcode_unautop' );

	}



	/**
	 * Can this version of WordPress perform "term meta" queries?
	 *
	 * The `unregister_meta_key()` function was introduced in WordPress 4.6,
	 * so we look for that rather than potentially triggering autoloaders by
	 * using a `class_exists( 'WP_Term_Query' )` lookup.

	 * @since 0.4.5
	 *
	 * @return bool True if terms can be queried by their "term meta", false otherwise.
	 */
	public function can_query_by_term_meta() {

		// Bail if this version of WordPress doesn't support "term meta" queries.
		if ( ! function_exists( 'unregister_meta_key' ) ) {
			return false;
		}

		// --<
		return true;

	}



	/**
	 * Upgrade all synced terms to store linkage in "term_meta".
	 *
	 * @since 0.4.5
	 */
	public function upgrade() {

		// Bail if term meta queries aren't available.
		if ( ! $this->can_query_by_term_meta() ) {
			return;
		}

		// Delay until "admin_init" hook.
		add_action( 'admin_init', array( $this, 'upgrade_terms' ) );

	}



	/**
	 * Upgrade all synced terms to store linkage in "term_meta".
	 *
	 * @since 0.4.5
	 */
	public function upgrade_terms() {

		// Get all terms in the Event Category taxonomy.
		$terms = $this->get_event_categories();

		// Bail if we don't have any.
		if ( empty( $terms ) ) {
			return;
		}

		// Step through the terms.
		foreach( $terms AS $term ) {

			// Get the corresponding CiviCRM Event Type.
			$event_type_id = $this->get_event_type_id( $term );

			// Skip if something went wrong.
			if ( $event_type_id === false ) {
				continue;
			}

			// Add the Event Type ID to the term's meta.
			$this->add_term_meta( $term->term_id, $event_type_id );

		}

	}



	//##########################################################################



	/**
	 * Get event category terms.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @return array $terms The EO event category terms.
	 */
	public function get_event_categories( $post_id = false ) {

		// If ID is false, get all terms.
		if ( $post_id === false ) {

			// Since WordPress 4.5.0, the category is specified in the arguments.
			if ( function_exists( 'unregister_taxonomy' ) ) {

				// Construct args.
				$args = array(
					'taxonomy' => 'event-category',
					'orderby' => 'count',
					'hide_empty' => 0
				);

				// Get all terms.
				$terms = get_terms( $args );

			} else {

				// Construct args.
				$args = array(
					'orderby' => 'count',
					'hide_empty' => 0
				);

				// Get all terms.
				$terms = get_terms( 'event-category', $args );

			}

		} else {

			// Get terms for the post.
			$terms = get_the_terms( $post_id, 'event-category' );

		}

		// --<
		return $terms;

	}



	/**
	 * Hook into the creation of an EO event category term.
	 *
	 * @since 0.1
	 *
	 * @param array $term_id The numeric ID of the new term.
	 * @param array $tt_id The numeric ID of the new term.
	 * @param string $taxonomy Should be (an array containing) 'event-category'.
	 */
	public function intercept_create_term( $term_id, $tt_id, $taxonomy ) {

		// Only look for terms in the EO taxonomy.
		if ( $taxonomy != 'event-category' ) {
			return;
		}

		// Get term object.
		$term = get_term_by( 'id', $term_id, 'event-category' );

		// Unhook CiviCRM - no need because we use hook_civicrm_postProcess.

		// Update CiviEvent term - or create if it doesn't exist.
		$civi_event_type_id = $this->update_event_type( $term );

		// Rehook CiviCRM?

	}



	/**
	 * Hook into updates to an EO event category term before the term is updated
	 * because we need to get the corresponding CiviEvent type before the WP term
	 * is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $term_id The numeric ID of the new term.
	 * @param string $taxonomy The taxonomy containing the term.
	 */
	public function intercept_pre_update_term( $term_id, $taxonomy = null ) {

		// Did we get a taxonomy passed in?
		if ( is_null( $taxonomy ) ) {

			// No, get reference to term object.
			$term = $this->get_term_by_id( $term_id );

		} else {

			// Get term.
			$term = get_term_by( 'id', $term_id, $taxonomy );

		}

		// Error check.
		if ( is_null( $term ) ) {
			return;
		}
		if ( is_wp_error( $term ) ) {
			return;
		}
		if ( ! is_object( $term ) ) {
			return;
		}

		// Check taxonomy.
		if ( $term->taxonomy != 'event-category' ) {
			return;
		}

		// Store for reference in intercept_update_term().
		$this->term_edited = clone $term;

	}



	/**
	 * Hook into updates to an EO event category term.
	 *
	 * @since 0.1
	 *
	 * @param int $term_id The numeric ID of the edited term.
	 * @param array $tt_id The numeric ID of the edited term taxonomy.
	 * @param string $taxonomy Should be (an array containing) 'event-category'.
	 */
	public function intercept_update_term( $term_id, $tt_id, $taxonomy ) {

		// Only look for terms in the EO taxonomy.
		if ( $taxonomy != 'event-category' ) {
			return;
		}

		// Assume we have no edited term.
		$old_term = null;

		// Use it if we have the term stored.
		if ( isset( $this->term_edited ) ) {
			$old_term = $this->term_edited;
		}

		// Get current term object.
		$new_term = get_term_by( 'id', $term_id, 'event-category' );

		// Unhook CiviCRM - no need because we use hook_civicrm_postProcess.

		// Update CiviEvent term - or create if it doesn't exist.
		$civi_event_type_id = $this->update_event_type( $new_term, $old_term );

		// Rehook CiviCRM?

	}



	/**
	 * Hook into deletion of an EO event category term - requires WordPress 3.5+
	 * because of the 4th parameter.
	 *
	 * @since 0.1
	 *
	 * @param int $term_id The numeric ID of the deleted term.
	 * @param array $tt_id The numeric ID of the deleted term taxonomy.
	 * @param string $taxonomy Name of the taxonomy.
	 * @param object $deleted_term The deleted term object.
	 */
	public function intercept_delete_term( $term, $tt_id, $taxonomy, $deleted_term ) {

		// Only look for terms in the EO taxonomy.
		if ( $taxonomy != 'event-category' ) {
			return;
		}

		// Unhook CiviCRM - no need because there is no hook to catch Event Type deletes.

		// Delete CiviEvent term if it exists.
		$civi_event_type_id = $this->delete_event_type( $deleted_term );

		// Rehook CiviCRM?

	}



	/**
	 * Create an EO event category term.
	 *
	 * @since 0.1
	 *
	 * @param int $type The CiviCRM Event Type.
	 * @return array $result Array containing EO event category term data.
	 */
	public function create_term( $type ) {

		// Sanity check.
		if ( ! is_array( $type ) ) {
			return false;
		}

		// Define description if present.
		$description = isset( $type['description'] ) ? $type['description'] : '';

		// Construct args.
		$args = array(
			'slug' => sanitize_title( $type['name'] ),
			'description'=> $description,
		);

		// Unhook listener.
		remove_action( 'created_term', array( $this, 'intercept_create_term' ), 20 );

		// Insert it.
		$result = wp_insert_term( $type['label'], 'event-category', $args );

		// Rehook listener.
		add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );

		// If all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// If something goes wrong, we get a WP_Error object.
		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Add the Event Type ID to the term's meta.
		$this->add_term_meta( $result['term_id'], intval( $type['id'] ) );

		// --<
		return $result;

	}



	/**
	 * Update an EO event category term.
	 *
	 * @since 0.1
	 *
	 * @param array $new_type The CiviCRM Event Type.
	 * @param array $old_type The CiviCRM Event Type prior to the update.
	 * @return int|bool $term_id The ID of the updated EO event category term.
	 */
	public function update_term( $new_type, $old_type = null ) {

		// Sanity check.
		if ( ! is_array( $new_type ) ) {
			return false;
		}

		// If we're updating a term.
		if ( ! is_null( $old_type ) ) {

			// Does this type have an existing term?
			$term_id = $this->get_term_id( $old_type );

		} else {

			// Get matching event term ID.
			$term_id = $this->get_term_id( $new_type );

		}

		// If we don't get one.
		if ( $term_id === false ) {

			// Create term,
			$result = $this->create_term( $new_type );

			// How did we do?
			if ( $result === false ) {
				return $result;
			}

			// --<
			return $result['term_id'];

		}

		// Define description if present.
		$description = isset( $new_type['description'] ) ? $new_type['description'] : '';

		// Construct term.
		$args = array(
			'name' => $new_type['label'],
			'slug' => sanitize_title( $new_type['name'] ),
			'description'=> $description,
		);

		// Unhook listeners.
		remove_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20 );
		remove_action( 'edited_term', array( $this, 'intercept_update_term' ), 20 );

		// Update term.
		$result = wp_update_term( $term_id, 'event-category', $args );

		// Rehook listeners.
		add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 2 );
		add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );

		// If all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// If something goes wrong, we get a WP_Error object.
		if ( is_wp_error( $result ) ) {
			return false;
		}

		// --<
		return $result['term_id'];

	}



	/**
	 * Get an EO event category term by CiviCRM Event Type.
	 *
	 * @since 0.1
	 *
	 * @param int $type The CiviCRM Event Type.
	 * @return int|bool $term_id The ID of the updated EO event category term.
	 */
	public function get_term_id( $type ) {

		// Sanity check.
		if ( ! is_array( $type ) ) {
			return false;
		}

		// Init return.
		$term_id = false;

		// First, query "term meta".
		$term = $this->get_term_by_meta( $type );

		// How did we do?
		if ( $term !== false ) {
			return $term->term_id;
		}

		// Try and match by term name <-> type label.
		$term = get_term_by( 'name', $type['label'], 'event-category' );

		// How did we do?
		if ( $term !== false ) {
			return $term->term_id;
		}

		// Try and match by term slug <-> type label.
		$term = get_term_by( 'slug', sanitize_title( $type['label'] ), 'event-category' );

		// How did we do?
		if ( $term !== false ) {
			return $term->term_id;
		}

		// Try and match by term slug <-> type name.
		$term = get_term_by( 'slug', sanitize_title( $type['name'] ), 'event-category' );

		// How did we do?
		if ( $term !== false ) {
			return $term->term_id;
		}

		// --<
		return $term_id;

	}



	/**
	 * Get a term without knowing its taxonomy - this was necessary before WordPress
	 * passed $taxonomy to the 'edit_terms' action in WP 3.7:
	 *
	 * @see http://core.trac.wordpress.org/ticket/22542
	 * @see https://core.trac.wordpress.org/changeset/24829
	 *
	 * @since 0.1
	 *
	 * @param int $term_id The ID of the term whose taxonomy we want.
	 * @param string $output Passed to get_term.
	 * @param string $filter Passed to get_term.
	 * @return object $term The WP term object passed by reference.
	 */
	public function &get_term_by_id( $term_id, $output = OBJECT, $filter = 'raw' ) {

		// Access db.
		global $wpdb;

		// Init failure.
		$null = null;

		// Sanity check.
		if ( empty( $term_id ) ) {
			$error = new WP_Error( 'invalid_term', __( 'Empty Term', 'civicrm-event-organiser' ) );
			return $error;
		}

		// Get directly from DB.
		$tax = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.* FROM $wpdb->term_taxonomy AS t WHERE t.term_id = %s LIMIT 1",
				$term_id
			)
		);

		// Error check.
		if ( ! $tax ) {
			return $null;
		}

		// Get taxonomy name.
		$taxonomy = $tax->taxonomy;

		// --<
		return get_term( $term_id, $taxonomy, $output, $filter );

	}



	/**
	 * Never let Radio Buttons for Taxonomies filter get_terms() to add a null
	 * term because CiviCRM requires an event to have a term.
	 *
	 * @since 0.3.5
	 *
	 * @param bool $set True if null term is to be set, false otherwise.
	 * @return bool $set True if null term is to be set, false otherwise.
	 */
	public function skip_null_term( $set ) {

		// A class property is passed in, so set that.
		$set = 0;

		// --<
		return $set;

	}



	/**
	 * Trigger hiding of "Parent Category" dropdown in metaboxes.
	 *
	 * @since 0.3.5
	 */
	public function terms_dropdown_intercept() {

		// Trigger emptying of dropdown.
		add_filter( 'wp_dropdown_cats', array( $this, 'terms_dropdown_clear' ), 20, 2 );

	}



	/**
	 * Always hide "Parent Category" dropdown in metaboxes.
	 *
	 * @since 0.3.5
	 */
	public function terms_dropdown_clear( $output, $r ) {

		// Only clear Event Organiser category.
		if ( $r['taxonomy'] != 'event-category' ) {
			return $output;
		}

		// Only once please, in case further dropdowns are rendered.
		remove_filter( 'wp_dropdown_cats', array( $this, 'terms_dropdown_clear' ), 20 );

		// Clear.
		return '';

	}



	/**
	 * Make sure new EO Events have the default term checked if no term has been
	 * chosen - e.g. on the "Add New Event" screen.
	 *
	 * @since 0.4.2
	 *
	 * @param array $args An array of arguments.
	 * @param int $post_id The post ID.
	 */
	public function term_default_checked( $args, $post_id ) {

		// Only modify Event Organiser category.
		if ( $args['taxonomy'] != 'event-category' ) {
			return $args;
		}

		// If this is a post.
		if ( $post_id ) {

			// Get existing terms.
			$args['selected_cats'] = wp_get_object_terms(
				$post_id,
				$args['taxonomy'],
				array_merge( $args, array( 'fields' => 'ids' ) )
			);

			// Bail if a category is already set.
			if ( ! empty( $args['selected_cats'] ) ) {
				return $args;
			}

		}

		// Get the default Event Type value.
		$type_value = $this->get_default_event_type_value();

		// Bail if something went wrong.
		if ( $type_value === false ) {
			return $args;
		}

		// Get the Event Type data.
		$type = $this->get_event_type_by_value( $type_value );

		// Bail if something went wrong.
		if ( $type === false ) {
			return $args;
		}

		// Get corresponding term ID.
		$term_id = $this->get_term_id( $type );

		// Bail if something went wrong.
		if ( $term_id === false ) {
			return $args;
		}

		// Set argument.
		$args['selected_cats'] = array( $term_id );

		// --<
		return $args;

	}



	//##########################################################################



	/**
	 * Add meta data to an EO event category term.
	 *
	 * @since 0.4.5
	 *
	 * @param int $event_type The array of CiviCRM Event Type data.
	 * @param int|bool $term_id The numeric ID of the term, or false on failure.
	 */
	public function get_term_by_meta( $event_type ) {

		// Bail if this version of WordPress doesn't support "term meta" queries.
		if ( ! $this->can_query_by_term_meta() ) {
			return false;
		}

		// Query terms for the term with the ID of the Event Type in meta data.
		$args = array(
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key' => $this->term_meta_key,
					'value' => $event_type['id'],
					'compare' => '='
				),
			),
		);

		// Get what should only be a single term.
		$terms = get_terms( 'event-category', $args );

		// Bail if there are no results.
		if ( empty( $terms ) ) {
			return false;
		}

		// Log a message and bail if there's an error.
		if ( is_wp_error( $terms ) ) {

			// Write error message.
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $terms->get_error_message(),
				'term' => $term,
				'event_type' => $event_type,
				'backtrace' => $trace,
			), true ) );
			return false;

		}

		// If we get more than one, WTF?
		if ( count( $terms ) > 1 ) {
			return false;
		}

		// Init return.
		$term = false;

		// Grab term data.
		if ( count( $terms ) === 1 ) {
			$term = array_pop( $terms );
		}

		// --<
		return $term;

	}



	/**
	 * Get CiviCRM Event Type for an EO event category term.
	 *
	 * @since 0.4.5
	 *
	 * @param int $term_id The numeric ID of the term.
	 * @return int|bool $event_type_id The ID of the CiviCRM Event Type, or false on failure.
	 */
	public function get_term_meta( $term_id ) {

		// Bail if this version of WordPress doesn't support "term meta" queries.
		if ( ! $this->can_query_by_term_meta() ) {
			return false;
		}

		// Get the Event Type ID from the term's meta.
		$event_type_id = get_term_meta( $term_id, $this->term_meta_key, true );

		// Bail if there is no result.
		if ( empty( $event_type_id ) ) {
			return false;
		}

		// --<
		return $event_type_id;

	}



	/**
	 * Add meta data to an EO event category term.
	 *
	 * @since 0.4.5
	 *
	 * @param int $term_id The numeric ID of the term.
	 * @param int $event_type_id The  numeric ID of the CiviCRM Event Type.
	 * @return int|bool $meta_id The ID of the meta, or false on failure.
	 */
	public function add_term_meta( $term_id, $event_type_id ) {

		// Add the Event Type ID to the term's meta.
		$meta_id = add_term_meta( $term_id, $this->term_meta_key, intval( $event_type_id ), true );

		// Log something if there's an error.
		if ( $meta_id === false ) {

			/*
			 * This probably means that the term already has its term meta set.
			 * Uncomment the following to debug if you need to.
			 */

			/*
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => __( 'Could not add term_meta', 'civicrm-event-organiser' ),
				'term_id' => $term_id,
				'event_type_id' => $event_type_id,
				'backtrace' => $trace,
			), true ) );
			*/

		}

		// Log a message if the term_id is ambiguous between taxonomies.
		if ( is_wp_error( $meta_id ) ) {

			// Log error message.
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $meta_id->get_error_message(),
				'term' => $term,
				'event_type_id' => $event_type_id,
				'backtrace' => $trace,
			), true ) );

			// Also overwrite return.
			$meta_id = false;

		}

		// --<
		return $meta_id;

	}



	//##########################################################################



	/**
	 * Intercept when a CiviCRM Event Type is updated.
	 *
	 * Unfortunately, this doesn't work because Civi does not fire hook_civicrm_pre
	 * for this entity type. Sad face.
	 *
	 * @since 0.4.2
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function event_type_pre( $op, $objectName, $objectId, $objectRef ) {

		// Target our operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'Email' ) {
			return;
		}

	}



	/**
	 * Intercept when a CiviCRM Event Type is toggled.
	 *
	 * @since 0.4.2
	 *
	 * @param object $recordBAO The CiviCRM option object.
	 * @param integer $recordID The ID of the object.
	 * @param bool $isActive Whether the item is active or not.
	 */
	public function event_type_toggle( $recordBAO, $recordID, $isActive ) {

		/**
		 * Example data:
		 *
		 * [recordBAO] => CRM_Core_BAO_OptionValue
		 * [recordID] => 734
		 * [isActive] => 1
		 *
		 * However, WordPress does not have an "Inactive" term state...
		 */

	}



	/**
	 * Update an EO 'event-category' term when a CiviCRM Event Type is updated.
	 *
	 * @since 0.4.2
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function event_type_process_form( $formName, &$form ) {

		// Kick out if not options form.
		if ( ! ( $form instanceof CRM_Admin_Form_Options ) ) {
			return;
		}

		// Kick out if not Event Type form.
		if ( 'event_type' != $form->getVar( '_gName' ) ) {
			return;
		}

		// Inspect all values.
		$type = $form->getVar( '_values' );

		// Inspect submitted values.
		$submitted_values = $form->getVar( '_submitValues' );

		// NOTE: we still need to address the 'is_active' option.

		// If our type is populated.
		if ( isset( $type['id'] ) ) {

			// It's an update.

			// Define description if present.
			$description = isset( $submitted_values['description'] ) ? $submitted_values['description'] : '';

			// Copy existing Event Type.
			$new_type = $type;

			// Assemble new Event Type.
			$new_type['label'] = $submitted_values['label'];
			$new_type['name'] = $submitted_values['label'];
			$new_type['description'] = $submitted_values['description'];

			// Unhook listeners.
			remove_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20 );
			remove_action( 'edited_term', array( $this, 'intercept_update_term' ), 20 );

			// Update EO term - or create if it doesn't exist.
			$result = $this->update_term( $new_type, $type );

			// Rehook listeners.
			add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 2 );
			add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );

		} else {

			// It's an insert.

			// Define description if present.
			$description = isset( $submitted_values['description'] ) ? $submitted_values['description'] : '';

			// Construct Event Type.
			$new_type = array(
				'label' => $submitted_values['label'],
				'name' => $submitted_values['label'],
				'description' => $description,
			);

			// Unhook listener.
			remove_action( 'created_term', array( $this, 'intercept_create_term' ), 20 );

			// Create EO term.
			$result = $this->create_term( $new_type );

			// Rehook listener.
			add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );

		}

	}



	/**
	 * Update a CiviCRM Event Type.
	 *
	 * @since 0.4.2
	 *
	 * @param object $new_term The new EO event category term.
	 * @param object $old_term The EO event category term as it was before update.
	 * @return int|bool $type_id The CiviCRM Event Type ID, or false on failure.
	 */
	public function update_event_type( $new_term, $old_term = null ) {

		// Sanity check.
		if ( ! is_object( $new_term ) ) {
			return false;
		}

		// Init CiviCRM or die.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check.
		if ( $opt_group_id === false ) {
			return false;
		}

		// Define Event Type.
		$params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'label' => $new_term->name,
			'name' => $new_term->name,
		);

		// If there is a description, apply content filters and add to params.
		if ( ! empty( $new_term->description ) ) {
			$params['description'] = apply_filters( 'civicrm_eo_term_content', $new_term->description );
		}

		// If we're updating a term.
		if ( ! is_null( $old_term ) ) {

			// Get existing Event Type ID.
			$type_id = $this->get_event_type_id( $old_term );

		} else {

			// Get matching Event Type ID.
			$type_id = $this->get_event_type_id( $new_term );

		}

		// Trigger update if we don't find an existing type.
		if ( $type_id !== false ) {
			$params['id'] = $type_id;
		}

		// Create (or update) the Event Type.
		$type = civicrm_api( 'option_value', 'create', $params );

		// Error check.
		if ( $type['is_error'] == '1' ) {

			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $type['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		} else {

			// Success, grab type ID.
			if ( isset( $type['id'] ) AND is_numeric( $type['id'] ) AND $type['id'] > 0 ) {
				$type_id = intval( $type['id'] );
			}

		}

		// --<
		return $type_id;

	}



	/**
	 * Delete a CiviCRM Event Type.
	 *
	 * @since 0.4.2
	 *
	 * @param object $term The EO event category term.
	 * @return array|bool CiviCRM API data array on success, false on failure.
	 */
	public function delete_event_type( $term ) {

		// Sanity check.
		if ( ! is_object( $term ) ) {
			return false;
		}

		// Init CiviCRM or die.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get ID of Event Type to delete.
		$type_id = $this->get_event_type_id( $term );

		// Error check.
		if ( $type_id === false ) {
			return false;
		}

		// Define Event Type.
		$params = array(
			'version' => 3,
			'id' => $type_id,
		);

		// Delete the Event Type.
		$result = civicrm_api( 'option_value', 'delete', $params );

		// Error check.
		if ( $result['is_error'] == '1' ) {

			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $result['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// --<
		return $result;

	}



	/**
	 * Get a CiviCRM Event Type by term.
	 *
	 * @since 0.4.2
	 *
	 * @param object $term The EO event category term.
	 * @return int|bool $type_id The numeric ID of the CiviCRM Event Type, or false on failure.
	 */
	public function get_event_type_id( $term ) {

		// First check if the term has the ID in its "term meta".
		$type_id = $this->get_term_meta( $term->term_id );

		// Short-circuit if we found it.
		if ( $type_id !== false ) {
			return $type_id;
		}

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check.
		if ( $opt_group_id === false ) {
			return false;
		}

		// Define params to get item.
		$types_params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'label' => $term->name,
			'options' => array(
				'sort' => 'weight ASC',
			),
		);

		// Get the item.
		$type = civicrm_api( 'option_value', 'getsingle', $types_params );

		// Bail if we get an error.
		if ( isset( $type['is_error'] ) AND $type['is_error'] == '1' ) {

			/*
			 * Sometimes we want to log failures, but not usually. When no type
			 * is found, it's not an error as such; it can just mean there's no
			 * existing Event Type. Uncomment the error logging code to see
			 * what's going on.
			 */

			/*
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $type['error_message'],
				'params' => $types_params,
				'backtrace' => $trace,
			), true ) );
			*/

			// --<
			return false;

		}

		// Sanity check ID and return if valid.
		if ( isset( $type['id'] ) AND is_numeric( $type['id'] ) AND $type['id'] > 0 ) {
			return $type['id'];
		}

		// If all the above fails.
		return false;

	}



	/**
	 * Get a CiviCRM Event Type value by type ID.
	 *
	 * @since 0.4.2
	 *
	 * @param int $type_id The numeric ID of the CiviCRM Event Type.
	 * @return int|bool $value The value of the CiviCRM Event Type (or false on failure)
	 */
	public function get_event_type_value( $type_id ) {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check.
		if ( $opt_group_id === false ) {
			return false;
		}

		// Define params to get item.
		$types_params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'id' => $type_id,
		);

		// Get the item.
		$type = civicrm_api( 'option_value', 'getsingle', $types_params );

		/*
		[type] => Array
			(
				[id] => 115
				[option_group_id] => 15
				[label] => Meeting
				[value] => 4
				[name] => Meeting
				[filter] => 0
				[weight] => 4
				[is_optgroup] => 0
				[is_reserved] => 0
				[is_active] => 1
			)
		*/

		// Error check.
		if ( isset( $type['is_error'] ) AND $type['is_error'] == '1' ) {

			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $type['error_message'],
				'type' => $type,
				'params' => $types_params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// Sanity check.
		if ( isset( $type['value'] ) AND is_numeric( $type['value'] ) AND $type['value'] > 0 ) {
			return $type['value'];
		}

		// If all the above fails.
		return false;

	}



	/**
	 * Get all CiviCRM Event Types.
	 *
	 * @since 0.4.2
	 *
	 * @return array|bool $types CiviCRM API return array, or false on failure.
	 */
	public function get_event_types() {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID,
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check,
		if ( $opt_group_id === false ) {
			return false;
		}

		// Define params to get items sorted by weight,
		$types_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'options' => array(
				'sort' => 'weight ASC',
			),
		);

		// Get them (descriptions will be present if not null),
		$types = civicrm_api( 'option_value', 'get', $types_params );

		// Error check,
		if ( $types['is_error'] == '1' ) {

			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $types['error_message'],
				'types' => $types,
				'params' => $types_params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// --<
		return $types;

	}



	/**
	 * Get all CiviCRM Event Types formatted as a dropdown list. The pseudo-ID
	 * is actually the Event Type "value" rather than the Event Type ID.
	 *
	 * @since 0.4.2
	 *
	 * @return str $html Markup containing select options.
	 */
	public function get_event_types_select() {

		// Init return,
		$html = '';

		// Bail if we fail to init CiviCRM,
		if ( ! $this->plugin->civi->is_active() ) {
			return $html;
		}

		// Get all Event Types,
		$result = $this->get_event_types();

		// Did we get any?
		if (
			$result !== false AND
			$result['is_error'] == '0' AND
			count( $result['values'] ) > 0
		) {

			// Get the values array,
			$types = $result['values'];

			// Init options,
			$options = array();

			// Get existing type value,
			$existing_value = $this->get_default_event_type_value();

			// Loop,
			foreach( $types AS $key => $type ) {

				// Get type value,
				$type_value = absint( $type['value'] );

				// Init selected,
				$selected = '';

				// Override selected if this value is the same as in the post.
				if ( $existing_value === $type_value ) {
					$selected = ' selected="selected"';
				}

				// Construct option,
				$options[] = '<option value="' . $type_value . '"' . $selected . '>' . esc_html( $type['label'] ) . '</option>';

			}

			// Create html,
			$html = implode( "\n", $options );

		}

		// Return,
		return $html;

	}



	/**
	 * Get the default Event Type value for a post, but fall back to the default as set
	 * on the admin screen, Fall back to false otherwise.
	 *
	 * @since 0.4.2
	 *
	 * @param object $post The WP event object.
	 * @return int|bool $existing_id The numeric ID of the CiviCRM Event Type, or false if none exists.
	 */
	public function get_default_event_type_value( $post = null ) {

		// Init with impossible ID,
		$existing_value = false;

		// Do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_type' );

		// Override with default value if we get one.
		if ( $default !== '' AND is_numeric( $default ) ) {
			$existing_value = absint( $default );
		}

		// If we have a post,
		if ( isset( $post ) AND is_object( $post ) ) {

			// Get the terms for this post - there should only be one,
			$cat = get_the_terms( $post->ID, 'event-category' );

			// Error check,
			if ( is_wp_error( $cat ) ) {
				return false;
			}

			// Did we get any?
			if ( is_array( $cat ) AND count( $cat ) > 0 ) {

				// Get first term object (keyed by term ID),
				$term = array_shift( $cat );

				// Get type ID for this term,
				$existing_id = $this->get_event_type_id( $term );

				// Convert to value,
				$existing_value = $this->get_event_type_value( $existing_id );

			}

		}

		// --<
		return $existing_value;

	}



	/**
	 * Get a CiviCRM Event Type by ID.
	 *
	 * @since 0.4.2
	 *
	 * @param int $type_id The numeric ID of a CiviCRM Event Type.
	 * @return array $type CiviCRM Event Type data.
	 */
	public function get_event_type_by_id( $type_id ) {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check.
		if ( $opt_group_id === false ) {
			return false;
		}

		// Define params to get item.
		$type_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'id' => $type_id,
		);

		// Get them (descriptions will be present if not null).
		$type = civicrm_api( 'option_value', 'getsingle', $type_params );

		// --<
		return $type;

	}



	/**
	 * Get a CiviCRM Event Type by "value" pseudo-ID.
	 *
	 * @since 0.4.2
	 *
	 * @param int $type_value The numeric value of a CiviCRM Event Type.
	 * @return array $type CiviCRM Event Type data.
	 */
	public function get_event_type_by_value( $type_value ) {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check.
		if ( $opt_group_id === false ) {
			return false;
		}

		// Define params to get item.
		$type_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'value' => $type_value,
		);

		// Get them (descriptions will be present if not null).
		$type = civicrm_api( 'option_value', 'getsingle', $type_params );

		// --<
		return $type;

	}



	/**
	 * Get the CiviEvent event_types option group ID.
	 *
	 * Multiple calls to the database are avoided by setting the static variable.
	 *
	 * @since 0.4.2
	 *
	 * @return array|bool $types CiviCRM API return array, or false on failure.
	 */
	public function get_event_types_optgroup_id() {

		// Init.
		static $optgroup_id;

		// Do we have it?
		if ( ! isset( $optgroup_id ) ) {

			// If we fail to init CiviCRM.
			if ( ! $this->plugin->civi->is_active() ) {

				// Set flag to false for future reference.
				$optgroup_id = false;

				// --<
				return $optgroup_id;

			}

			// Define params to get Event Type option group.
			$opt_group_params = array(
				'name' => 'event_type',
				'version' => 3,
			);

			// Get it.
			$opt_group = civicrm_api( 'option_group', 'getsingle', $opt_group_params );

			// Error check.
			if ( isset( $opt_group['id'] ) AND is_numeric( $opt_group['id'] ) AND $opt_group['id'] > 0 ) {

				// Set flag to false for future reference.
				$optgroup_id = $opt_group['id'];

				// --<
				return $optgroup_id;

			}

		}

		// --<
		return $optgroup_id;

	}



} // Class ends.



