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
	 * Initialises this object.
	 *
	 * @since 0.4.2
	 */
	public function __construct() {

		// add CiviCRM hooks when plugin is loaded
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

		// store
		$this->plugin = $parent;

	}



	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.4.2
	 */
	public function register_hooks() {

		// intercept new term creation
		add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );

		// intercept term updates
		add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 2 );
		add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );

		// intercept term deletion
		add_action( 'delete_term', array( $this, 'intercept_delete_term' ), 20, 4 );

		// filter Radio Buttons for Taxonomies null term insertion
		add_filter( 'radio-buttons-for-taxonomies-no-term-event-category', array( $this, 'skip_null_term' ), 20, 1 );
		add_filter( 'radio_buttons_for_taxonomies_no_term_event-category', array( $this, 'skip_null_term' ), 20, 1 );

		// hide "Parent Category" dropdown in event category metaboxes
		add_action( 'add_meta_boxes_event', array( $this, 'terms_dropdown_intercept' ), 3 );

		// ensure new events have the default term checked
		add_filter( 'wp_terms_checklist_args', array( $this, 'term_default_checked' ), 10, 2 );

		// intercept CiviCRM event type enable/disable
		//add_action( 'civicrm_enableDisable', array( $this, 'event_type_toggle' ), 10, 3 );

		// intercept CiviCRM event type form edits
		add_action( 'civicrm_postProcess', array( $this, 'event_type_process_form' ), 10, 2 );

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

		// if ID is false, get all terms
		if ( $post_id === false ) {

			// since WordPress 4.5.0, the category is specified in the arguments
			if ( function_exists( 'unregister_taxonomy' ) ) {

				// construct args
				$args = array(
					'taxonomy' => 'event-category',
					'orderby' => 'count',
					'hide_empty' => 0
				);

				// get all terms
				$terms = get_terms( $args );

			} else {

				// construct args
				$args = array(
					'orderby' => 'count',
					'hide_empty' => 0
				);

				// get all terms
				$terms = get_terms( 'event-category', $args );

			}

		} else {

			// get terms for the post
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

		// only look for terms in the EO taxonomy
		if ( $taxonomy != 'event-category' ) return;

		// get term object
		$term = get_term_by( 'id', $term_id, 'event-category' );

		// unhook CiviCRM - no need because we use hook_civicrm_postProcess

		// update CiviEvent term - or create if it doesn't exist
		$civi_event_type_id = $this->update_event_type( $term );

		// rehook CiviCRM?

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

		// did we get a taxonomy passed in?
		if ( is_null( $taxonomy ) ) {

			// no, get reference to term object
			$term = $this->get_term_by_id( $term_id );

		} else {

			// get term
			$term = get_term_by( 'id', $term_id, $taxonomy );

		}

		// error check
		if ( is_null( $term ) ) return;
		if ( is_wp_error( $term ) ) return;
		if ( ! is_object( $term ) ) return;

		// check taxonomy
		if ( $term->taxonomy != 'event-category' ) return;

		// store for reference in intercept_update_term()
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

		// only look for terms in the EO taxonomy
		if ( $taxonomy != 'event-category' ) return;

		// assume we have no edited term
		$old_term = null;

		// do we have the term stored?
		if ( isset( $this->term_edited ) ) {

			// use it
			$old_term = $this->term_edited;

		}

		// get current term object
		$new_term = get_term_by( 'id', $term_id, 'event-category' );

		// unhook CiviCRM - no need because we use hook_civicrm_postProcess

		// update CiviEvent term - or create if it doesn't exist
		$civi_event_type_id = $this->update_event_type( $new_term, $old_term );

		// rehook CiviCRM?

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

		// only look for terms in the EO taxonomy
		if ( $taxonomy != 'event-category' ) return;

		// unhook CiviCRM - no need because there is no hook to catch event type deletes

		// delete CiviEvent term if it exists
		$civi_event_type_id = $this->delete_event_type( $deleted_term );

		// rehook CiviCRM?

	}



	/**
	 * Create an EO event category term.
	 *
	 * @since 0.1
	 *
	 * @param int $type A CiviEvent event type.
	 * @return array $result Array containing EO event category term data.
	 */
	public function create_term( $type ) {

		// sanity check
		if ( ! is_array( $type ) ) return false;

		// define description if present
		$description = isset( $type['description'] ) ? $type['description'] : '';

		// construct args
		$args = array(
			'slug' => sanitize_title( $type['name'] ),
			'description'=> $description,
		);

		// unhook listener
		remove_action( 'created_term', array( $this, 'intercept_create_term' ), 20 );

		// insert it
		$result = wp_insert_term( $type['label'], 'event-category', $args );

		// rehook listener
		add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );

		// if all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// if something goes wrong, we get a WP_Error object
		if ( is_wp_error( $result ) ) return false;

		// --<
		return $result;

	}



	/**
	 * Update an EO event category term.
	 *
	 * @since 0.1
	 *
	 * @param array $new_type A CiviEvent event type.
	 * @param array $old_type A CiviEvent event type prior to the update.
	 * @return int|bool $term_id The ID of the updated EO event category term.
	 */
	public function update_term( $new_type, $old_type = null ) {

		// sanity check
		if ( ! is_array( $new_type ) ) return false;

		// if we're updating a term
		if ( ! is_null( $old_type ) ) {

			// does this type have an existing term?
			$term_id = $this->get_term_id( $old_type );

		} else {

			// get matching event term ID
			$term_id = $this->get_term_id( $new_type );

		}

		// if we don't get one...
		if ( $term_id === false ) {

			// create term
			$result = $this->create_term( $new_type );

			// how did we do?
			if ( $result === false ) return $result;

			// --<
			return $result['term_id'];

		}

		// define description if present
		$description = isset( $new_type['description'] ) ? $new_type['description'] : '';

		// construct term
		$args = array(
			'name' => $new_type['label'],
			'slug' => sanitize_title( $new_type['name'] ),
			'description'=> $description,
		);

		// unhook listeners
		remove_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20 );
		remove_action( 'edited_term', array( $this, 'intercept_update_term' ), 20 );

		// update term
		$result = wp_update_term( $term_id, 'event-category', $args );

		// rehook listeners
		add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 2 );
		add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );

		// if all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// if something goes wrong, we get a WP_Error object
		if ( is_wp_error( $result ) ) return false;

		// --<
		return $result['term_id'];

	}



	/**
	 * Get an EO event category term by CiviEvent event type.
	 *
	 * @since 0.1
	 *
	 * @param int $type A CiviEvent event type.
	 * @return int|bool $term_id The ID of the updated EO event category term.
	 */
	public function get_term_id( $type ) {

		// sanity check
		if ( ! is_array( $type ) ) return false;

		// init return
		$term_id = false;

		// try and match by term name <-> type label
		$term = get_term_by( 'name', $type['label'], 'event-category' );

		// how did we do?
		if ( $term !== false ) return $term->term_id;

		// try and match by term slug <-> type label
		$term = get_term_by( 'slug', sanitize_title( $type['label'] ), 'event-category' );

		// how did we do?
		if ( $term !== false ) return $term->term_id;

		// try and match by term slug <-> type name
		$term = get_term_by( 'slug', sanitize_title( $type['name'] ), 'event-category' );

		// how did we do?
		if ( $term !== false ) return $term->term_id;

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

		// access db
		global $wpdb;

		// init failure
		$null = null;

		// sanity check
		if ( empty( $term_id ) ) {
			$error = new WP_Error( 'invalid_term', __( 'Empty Term', 'civicrm-event-organiser' ) );
			return $error;
		}

		// get directly from DB
		$tax = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.* FROM $wpdb->term_taxonomy AS t WHERE t.term_id = %s LIMIT 1",
				$term_id
			)
		);

		// error check
		if ( ! $tax ) return $null;

		// get taxonomy name
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

		// a class property is passed in, so set that
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

		// trigger emptying of dropdown
		add_filter( 'wp_dropdown_cats', array( $this, 'terms_dropdown_clear' ), 20, 2 );

	}



	/**
	 * Always hide "Parent Category" dropdown in metaboxes.
	 *
	 * @since 0.3.5
	 */
	public function terms_dropdown_clear( $output, $r ) {

		// only clear Event Organiser category
		if ( $r['taxonomy'] != 'event-category' ) return $output;

		// only once please, in case further dropdowns are rendered
		remove_filter( 'wp_dropdown_cats', array( $this, 'terms_dropdown_clear' ), 20 );

		// clear
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

		// only modify Event Organiser category
		if ( $args['taxonomy'] != 'event-category' ) return $args;

		// if this is a post
		if ( $post_id ) {

			// get existing terms
			$args['selected_cats'] = wp_get_object_terms(
				$post_id,
				$args['taxonomy'],
				array_merge( $args, array( 'fields' => 'ids' ) )
			);

			// bail if a category is already set
			if ( ! empty( $args['selected_cats'] ) ) return $args;

		}

		// get the default event type value
		$type_value = $this->get_default_event_type_value();

		// bail if something went wrong
		if ( $type_value === false ) return $args;

		// get the event type data
		$type = $this->get_event_type_by_value( $type_value );

		// bail if something went wrong
		if ( $type === false ) return $args;

		// get corresponding term ID
		$term_id = $this->get_term_id( $type );

		// bail if something went wrong
		if ( $term_id === false ) return $args;

		// set argument
		$args['selected_cats'] = array( $term_id );

		// --<
		return $args;

	}



	//##########################################################################



	/**
	 * Intercept when a CiviCRM event type is updated.
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

		// target our operation
		if ( $op != 'edit' ) return;

		// target our object type
		if ( $objectName != 'Email' ) return;

	}



	/**
	 * Intercept when a CiviCRM event type is toggled.
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
	 * Update an EO 'event-category' term when a CiviCRM event type is updated.
	 *
	 * @since 0.4.2
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function event_type_process_form( $formName, &$form ) {

		// kick out if not options form
		if ( ! ( $form instanceof CRM_Admin_Form_Options ) ) return;

		// kick out if not event type form
		if ( 'event_type' != $form->getVar( '_gName' ) ) return;

		// inspect all values
		$type = $form->getVar( '_values' );

		// inspect submitted values
		$submitted_values = $form->getVar( '_submitValues' );

		// NOTE: we still need to address the 'is_active' option

		// if our type is populated
		if ( isset( $type['id'] ) ) {

			// it's an update

			// define description if present
			$description = isset( $submitted_values['description'] ) ? $submitted_values['description'] : '';

			// copy existing event type
			$new_type = $type;

			// assemble new event type
			$new_type['label'] = $submitted_values['label'];
			$new_type['name'] = $submitted_values['label'];
			$new_type['description'] = $submitted_values['description'];

			// unhook listeners
			remove_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20 );
			remove_action( 'edited_term', array( $this, 'intercept_update_term' ), 20 );

			// update EO term - or create if it doesn't exist
			$result = $this->update_term( $new_type, $type );

			// rehook listeners
			add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 2 );
			add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );

		} else {

			// it's an insert

			// define description if present
			$description = isset( $submitted_values['description'] ) ? $submitted_values['description'] : '';

			// construct event type
			$new_type = array(
				'label' => $submitted_values['label'],
				'name' => $submitted_values['label'],
				'description' => $description,
			);

			// unhook listener
			remove_action( 'created_term', array( $this, 'intercept_create_term' ), 20 );

			// create EO term
			$result = $this->create_term( $new_type );

			// rehook listener
			add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );

		}

	}



	/**
	 * Update a CiviEvent event type.
	 *
	 * @since 0.4.2
	 *
	 * @param object $new_term The new EO event category term.
	 * @param object $old_term The EO event category term as it was before update.
	 * @return int|bool $type_id The CiviCRM Event Type ID, or false on failure.
	 */
	public function update_event_type( $new_term, $old_term = null ) {

		// sanity check
		if ( ! is_object( $new_term ) ) return false;

		// init CiviCRM or die
		if ( ! $this->plugin->civi->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define event type
		$params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'label' => $new_term->name,
			'name' => $new_term->name,
		);

		// do we have a description?
		if ( $new_term->description != '' ) {

			// add it
			$params['description'] = $new_term->description;

		}

		// if we're updating a term
		if ( ! is_null( $old_term ) ) {

			// get existing event type ID
			$type_id = $this->get_event_type_id( $old_term );

		} else {

			// get matching event type ID
			$type_id = $this->get_event_type_id( $new_term );

		}

		// trigger update if we don't find an existing type
		if ( $type_id !== false ) {
			$params['id'] = $type_id;
		}

		// create (or update) the event type
		$type = civicrm_api( 'option_value', 'create', $params );

		// error check
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

			// success, grab type ID
			if ( isset( $type['id'] ) AND is_numeric( $type['id'] ) AND $type['id'] > 0 ) {
				$type_id = intval( $type['id'] );
			}

		}

		// --<
		return $type_id;

	}



	/**
	 * Delete a CiviEvent event type.
	 *
	 * @since 0.4.2
	 *
	 * @param object $term The EO event category term.
	 * @return array|bool CiviCRM API data array on success, false on failure.
	 */
	public function delete_event_type( $term ) {

		// sanity check
		if ( ! is_object( $term ) ) return false;

		// init CiviCRM or die
		if ( ! $this->plugin->civi->is_active() ) return false;

		// get ID of event type to delete
		$type_id = $this->get_event_type_id( $term );

		// error check
		if ( $type_id === false ) return false;

		// define event type
		$params = array(
			'version' => 3,
			'id' => $type_id,
		);

		// delete the event type
		$result = civicrm_api( 'option_value', 'delete', $params );

		// error check
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
	 * Get a CiviEvent event type by term.
	 *
	 * @since 0.4.2
	 *
	 * @param object $term The EO event category term.
	 * @return int|bool $type_id The numeric ID of the CiviEvent event type, or false on failure.
	 */
	public function get_event_type_id( $term ) {

		// if we fail to init CiviCRM...
		if ( ! $this->plugin->civi->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get item
		$types_params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'label' => $term->name,
			'options' => array(
				'sort' => 'weight ASC',
			),
		);

		// get the item
		$type = civicrm_api( 'option_value', 'getsingle', $types_params );

		// bail if we get an error
		if ( isset( $type['is_error'] ) AND $type['is_error'] == '1' ) {

			/*
			 * Sometimes we want to log failures, but not usually. When no type
			 * is found, it's not an error as such; it can just mean there's no
			 * existing event type. Uncomment the error logging code to see
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

		// sanity check ID and return if valid
		if ( isset( $type['id'] ) AND is_numeric( $type['id'] ) AND $type['id'] > 0 ) return $type['id'];

		// if all the above fails
		return false;

	}



	/**
	 * Get a CiviEvent event type value by type ID.
	 *
	 * @since 0.4.2
	 *
	 * @param int $type_id The numeric ID of the CiviEvent event type.
	 * @return int|bool $value The value of the CiviEvent event type (or false on failure)
	 */
	public function get_event_type_value( $type_id ) {

		// if we fail to init CiviCRM...
		if ( ! $this->plugin->civi->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get item
		$types_params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'id' => $type_id,
		);

		// get the item
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

		// error check
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

		// sanity check
		if ( isset( $type['value'] ) AND is_numeric( $type['value'] ) AND $type['value'] > 0 ) return $type['value'];

		// if all the above fails
		return false;

	}



	/**
	 * Get all CiviEvent event types.
	 *
	 * @since 0.4.2
	 *
	 * @return array|bool $types CiviCRM API return array, or false on failure.
	 */
	public function get_event_types() {

		// if we fail to init CiviCRM...
		if ( ! $this->plugin->civi->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get items sorted by weight
		$types_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'options' => array(
				'sort' => 'weight ASC',
			),
		);

		// get them (descriptions will be present if not null)
		$types = civicrm_api( 'option_value', 'get', $types_params );

		// error check
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
	 * Get all CiviEvent event types formatted as a dropdown list. The pseudo-ID
	 * is actually the event type "value" rather than the event type ID.
	 *
	 * @since 0.4.2
	 *
	 * @return str $html Markup containing select options.
	 */
	public function get_event_types_select() {

		// init return
		$html = '';

		// bail if we fail to init CiviCRM
		if ( ! $this->plugin->civi->is_active() ) return $html;

		// get all event types
		$result = $this->get_event_types();

		// did we get any?
		if (
			$result !== false AND
			$result['is_error'] == '0' AND
			count( $result['values'] ) > 0
		) {

			// get the values array
			$types = $result['values'];

			// init options
			$options = array();

			// get existing type value
			$existing_value = $this->get_default_event_type_value();

			// loop
			foreach( $types AS $key => $type ) {

				// get type value
				$type_value = absint( $type['value'] );

				// init selected
				$selected = '';

				// is this value the same as in the post?
				if ( $existing_value === $type_value ) {

					// override selected
					$selected = ' selected="selected"';

				}

				// construct option
				$options[] = '<option value="' . $type_value . '"' . $selected . '>' . esc_html( $type['label'] ) . '</option>';

			}

			// create html
			$html = implode( "\n", $options );

		}

		// return
		return $html;

	}



	/**
	 * Get the default event type value for a post, but fall back to the default as set
	 * on the admin screen, Fall back to false otherwise.
	 *
	 * @since 0.4.2
	 *
	 * @param object $post The WP event object.
	 * @return int|bool $existing_id The numeric ID of the CiviEvent event type, or false if none exists.
	 */
	public function get_default_event_type_value( $post = null ) {

		// init with impossible ID
		$existing_value = false;

		// do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_type' );

		// did we get one?
		if ( $default !== '' AND is_numeric( $default ) ) {

			// override with default value
			$existing_value = absint( $default );

		}

		// if we have a post
		if ( isset( $post ) AND is_object( $post ) ) {

			// get the terms for this post - there should only be one
			$cat = get_the_terms( $post->ID, 'event-category' );

			// error check
			if ( is_wp_error( $cat ) ) return false;

			// did we get any?
			if ( is_array( $cat ) AND count( $cat ) > 0 ) {

				// get first term object (keyed by term ID)
				$term = array_shift( $cat );

				// get type ID for this term
				$existing_id = $this->get_event_type_id( $term );

				// convert to value
				$existing_value = $this->get_event_type_value( $existing_id );

			}

		}

		// --<
		return $existing_value;

	}



	/**
	 * Get a CiviEvent event type by ID.
	 *
	 * @since 0.4.2
	 *
	 * @param int $type_id The numeric ID of a CiviEvent event type.
	 * @return array $type CiviEvent event type data.
	 */
	public function get_event_type_by_id( $type_id ) {

		// if we fail to init CiviCRM...
		if ( ! $this->plugin->civi->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get item
		$type_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'id' => $type_id,
		);

		// get them (descriptions will be present if not null)
		$type = civicrm_api( 'option_value', 'getsingle', $type_params );

		// --<
		return $type;

	}



	/**
	 * Get a CiviEvent event type by "value" pseudo-ID.
	 *
	 * @since 0.4.2
	 *
	 * @param int $type_value The numeric value of a CiviEvent event type.
	 * @return array $type CiviEvent event type data.
	 */
	public function get_event_type_by_value( $type_value ) {

		// if we fail to init CiviCRM...
		if ( ! $this->plugin->civi->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get item
		$type_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'value' => $type_value,
		);

		// get them (descriptions will be present if not null)
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

		// init
		static $optgroup_id;

		// do we have it?
		if ( ! isset( $optgroup_id ) ) {

			// if we fail to init CiviCRM...
			if ( ! $this->plugin->civi->is_active() ) {

				// set flag to false for future reference
				$optgroup_id = false;

				// --<
				return $optgroup_id;

			}

			// define params to get event type option group
			$opt_group_params = array(
				'name' => 'event_type',
				'version' => 3,
			);

			// get it
			$opt_group = civicrm_api( 'option_group', 'getsingle', $opt_group_params );

			// error check
			if ( isset( $opt_group['id'] ) AND is_numeric( $opt_group['id'] ) AND $opt_group['id'] > 0 ) {

				// set flag to false for future reference
				$optgroup_id = $opt_group['id'];

				// --<
				return $optgroup_id;

			}

		}

		// --<
		return $optgroup_id;

	}



} // class ends



