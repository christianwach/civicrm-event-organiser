<?php
/**
 * Taxonomy Class.
 *
 * Handles sync between the Event Organiser custom Taxonomy and CiviCRM Event Types.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Taxonomy Class.
 *
 * This class keeps the Event Organiser custom Taxonomy in sync with the CiviCRM
 * Event Types.
 *
 * @since 0.4.2
 */
class CEO_WordPress_Taxonomy {

	/**
	 * Plugin object.
	 *
	 * @since 0.4.2
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * WordPress object.
	 *
	 * @since 0.8.0
	 * @access public
	 * @var CEO_WordPress
	 */
	public $wordpress;

	/**
	 * Term Meta key.
	 *
	 * @since 0.4.5
	 * @access public
	 * @var string
	 */
	public $term_meta_key = '_ceo_civi_event_type_id';

	/**
	 * Constructor.
	 *
	 * @since 0.4.2
	 *
	 * @param CEO_WordPress $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin    = $parent->plugin;
		$this->wordpress = $parent;

		// Add Event Organiser hooks when WordPress class is loaded.
		add_action( 'ceo/wordpress/loaded', [ $this, 'register_hooks' ] );

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.4.2
	 */
	public function register_hooks() {

		// Intercept WordPress Term operations.
		$this->hooks_wordpress_add();

		// Add CiviCRM listeners once CiviCRM is available.
		add_action( 'civicrm_config', [ $this, 'civicrm_config' ] );

		// Filter Radio Buttons for Taxonomies null Term insertion.
		add_filter( 'radio-buttons-for-taxonomies-no-term-event-category', [ $this, 'skip_null_term' ], 20, 1 );
		add_filter( 'radio_buttons_for_taxonomies_no_term_event-category', [ $this, 'skip_null_term' ], 20, 1 );

		// Override "No Category" option.
		add_filter( 'radio-buttons-for-taxonomies-no-term-event-category', [ $this, 'force_taxonomy' ], 30 );

		// Hide "Parent Category" dropdown in Event Category metaboxes.
		add_action( 'add_meta_boxes_event', [ $this, 'terms_dropdown_intercept' ], 3 );

		// Ensure new Events have the default Term checked.
		add_filter( 'wp_terms_checklist_args', [ $this, 'term_default_checked' ], 10, 2 );

		// Create custom filters that mirror 'the_content'.
		add_filter( 'ceo/taxonomy/term/the_content', 'wptexturize' );
		add_filter( 'ceo/taxonomy/term/the_content', 'convert_smilies' );
		add_filter( 'ceo/taxonomy/term/the_content', 'convert_chars' );
		add_filter( 'ceo/taxonomy/term/the_content', 'wpautop' );
		add_filter( 'ceo/taxonomy/term/the_content', 'shortcode_unautop' );

	}

	/**
	 * Callback for "civicrm_config".
	 *
	 * @since 0.4.6
	 *
	 * @param CRM_Core_Config $config The CiviCRM config object.
	 */
	public function civicrm_config( &$config ) {

		// Add CiviCRM listeners once CiviCRM is available.
		$this->hooks_civicrm_add();

	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.4.6
	 */
	public function hooks_wordpress_add() {

		// Intercept new Term creation.
		add_action( 'created_term', [ $this, 'intercept_create_term' ], 20, 3 );

		// Intercept Term updates.
		add_action( 'edit_terms', [ $this, 'intercept_pre_update_term' ], 20, 2 );
		add_action( 'edited_term', [ $this, 'intercept_update_term' ], 20, 3 );

		// Intercept Term deletion.
		add_action( 'delete_term', [ $this, 'intercept_delete_term' ], 20, 4 );

	}


	/**
	 * Remove WordPress hooks.
	 *
	 * @since 0.4.6
	 */
	public function hooks_wordpress_remove() {

		// Remove all previously added callbacks.
		remove_action( 'created_term', [ $this, 'intercept_create_term' ], 20 );
		remove_action( 'edit_terms', [ $this, 'intercept_pre_update_term' ], 20 );
		remove_action( 'edited_term', [ $this, 'intercept_update_term' ], 20 );
		remove_action( 'delete_term', [ $this, 'intercept_delete_term' ], 20 );

	}

	/**
	 * Add listeners for CiviCRM Event Type operations.
	 *
	 * @since 0.4.6
	 */
	public function hooks_civicrm_add() {

		// Add callback for CiviCRM "postInsert" hook.
		Civi::service( 'dispatcher' )->addListener(
			'civi.dao.postInsert',
			[ $this, 'event_type_created' ],
			-100 // Default priority.
		);

		/*
		// Add callback for CiviCRM "preUpdate" hook.
		// @see https://lab.civicrm.org/dev/core/issues/1638
		Civi::service( 'dispatcher' )->addListener(
			'civi.dao.preUpdate',
			[ $this, 'event_type_pre_update' ],
			-100 // Default priority.
		);
		*/

		// Add callback for CiviCRM "postUpdate" hook.
		Civi::service( 'dispatcher' )->addListener(
			'civi.dao.postUpdate',
			[ $this, 'event_type_updated' ],
			-100 // Default priority.
		);

		// Add callback for CiviCRM "preDelete" hook.
		Civi::service( 'dispatcher' )->addListener(
			'civi.dao.preDelete',
			[ $this, 'event_type_pre_delete' ],
			-100 // Default priority.
		);

	}

	/**
	 * Remove listeners from CiviCRM Event Type operations.
	 *
	 * @since 0.4.6
	 */
	public function hooks_civicrm_remove() {

		// Remove callback for CiviCRM "postInsert" hook.
		Civi::service( 'dispatcher' )->removeListener(
			'civi.dao.postInsert',
			[ $this, 'event_type_created' ]
		);

		/*
		// Remove callback for CiviCRM "preUpdate" hook.
		Civi::service( 'dispatcher' )->removeListener(
			'civi.dao.preUpdate',
			[ $this, 'event_type_pre_update' ]
		);
		*/

		// Remove callback for CiviCRM "postUpdate" hook.
		Civi::service( 'dispatcher' )->removeListener(
			'civi.dao.postUpdate',
			[ $this, 'event_type_updated' ]
		);

		// Remove callback for CiviCRM "preDelete" hook.
		Civi::service( 'dispatcher' )->removeListener(
			'civi.dao.preDelete',
			[ $this, 'event_type_pre_delete' ]
		);

	}

	/**
	 * Upgrade all synced Terms to store linkage in "term_meta".
	 *
	 * @since 0.4.5
	 */
	public function upgrade() {

		// Delay until "admin_init" hook.
		add_action( 'admin_init', [ $this, 'upgrade_terms' ] );

	}

	/**
	 * Upgrade all synced Terms to store linkage in "term_meta".
	 *
	 * @since 0.4.5
	 */
	public function upgrade_terms() {

		// Get all Terms in the Event Category Taxonomy.
		$terms = $this->get_event_categories();

		// Bail if we don't have any.
		if ( empty( $terms ) ) {
			return;
		}

		// Step through the Terms.
		foreach ( $terms as $term ) {

			// Get the corresponding CiviCRM Event Type.
			$event_type_id = $this->get_event_type_id( $term );

			// Skip if something went wrong.
			if ( false === $event_type_id ) {
				continue;
			}

			// Add the Event Type ID to the Term's meta.
			$this->add_term_meta( $term->term_id, $event_type_id );

		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get Event Category Terms.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return array $terms The Event Organiser Event Category Terms.
	 */
	public function get_event_categories( $post_id = false ) {

		// If ID is false, get all Terms.
		if ( false === $post_id ) {

			// Construct args.
			$args = [
				'taxonomy'   => 'event-category',
				'orderby'    => 'count',
				'hide_empty' => 0,
			];

			// Get all Terms.
			$terms = get_terms( $args );

		} else {

			// Get Terms for the Post.
			$terms = get_the_terms( $post_id, 'event-category' );

		}

		// --<
		return $terms;

	}

	/**
	 * Hook into the creation of an Event Organiser Event Category Term.
	 *
	 * @since 0.1
	 *
	 * @param array  $term_id The numeric ID of the new Term.
	 * @param array  $tt_id The numeric ID of the new Term.
	 * @param string $taxonomy Should be (an array containing) 'event-category'.
	 */
	public function intercept_create_term( $term_id, $tt_id, $taxonomy ) {

		// Only look for Terms in the Event Organiser Taxonomy.
		if ( 'event-category' !== $taxonomy ) {
			return;
		}

		// Get Term object.
		$term = get_term_by( 'id', $term_id, 'event-category' );

		// Unhook CiviCRM.
		$this->hooks_civicrm_remove();

		// Update CiviCRM Event Type - or create if it doesn't exist.
		$event_type_id = $this->update_event_type( $term );

		// Rehook CiviCRM.
		$this->hooks_civicrm_add();

		// Add the Event Type ID to the Term's meta.
		$this->add_term_meta( $term_id, (int) $event_type_id );

	}

	/**
	 * Hook into updates to an Event Organiser Event Category Term before the Term
	 * is updated because we need to get the corresponding CiviCRM Event Type
	 * before the WP Term is updated.
	 *
	 * @since 0.1
	 *
	 * @param int    $term_id The numeric ID of the new Term.
	 * @param string $taxonomy The Taxonomy containing the Term.
	 */
	public function intercept_pre_update_term( $term_id, $taxonomy = null ) {

		// Did we get a Taxonomy passed in?
		if ( is_null( $taxonomy ) ) {

			// No, get reference to Term object.
			$term = $this->get_term_by_id( $term_id );

		} else {

			// Get Term.
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

		// Check Taxonomy.
		if ( 'event-category' !== $term->taxonomy ) {
			return;
		}

		// Store for reference in intercept_update_term().
		$this->term_edited = clone $term;

	}

	/**
	 * Hook into updates to an Event Organiser Event Category Term.
	 *
	 * @since 0.1
	 *
	 * @param int    $term_id The numeric ID of the edited Term.
	 * @param array  $tt_id The numeric ID of the edited Term Taxonomy.
	 * @param string $taxonomy Should be (an array containing) 'event-category'.
	 */
	public function intercept_update_term( $term_id, $tt_id, $taxonomy ) {

		// Only look for Terms in the Event Organiser Taxonomy.
		if ( 'event-category' !== $taxonomy ) {
			return;
		}

		// Assume we have no edited Term.
		$old_term = null;

		// Use it if we have the Term stored.
		if ( isset( $this->term_edited ) ) {
			$old_term = $this->term_edited;
		}

		// Get current Term object.
		$new_term = get_term_by( 'id', $term_id, 'event-category' );

		// Unhook CiviCRM.
		$this->hooks_civicrm_remove();

		// Update CiviCRM Event Type - or create if it doesn't exist.
		$event_type_id = $this->update_event_type( $new_term, $old_term );

		// Rehook CiviCRM.
		$this->hooks_civicrm_add();

	}

	/**
	 * Hook into deletion of an Event Organiser Event Category Term - requires
	 * WordPress 3.5+ because of the 4th parameter.
	 *
	 * @since 0.1
	 *
	 * @param int     $term_id The numeric ID of the deleted Term.
	 * @param array   $tt_id The numeric ID of the deleted Term Taxonomy.
	 * @param string  $taxonomy Name of the Taxonomy.
	 * @param WP_Term $deleted_term The deleted Term object.
	 */
	public function intercept_delete_term( $term_id, $tt_id, $taxonomy, $deleted_term ) {

		// Only look for Terms in the Event Organiser Taxonomy.
		if ( 'event-category' !== $taxonomy ) {
			return;
		}

		// Unhook CiviCRM.
		$this->hooks_civicrm_remove();

		// Delete the CiviCRM Event Type if it exists.
		$event_type_id = $this->delete_event_type( $deleted_term );

		// Rehook CiviCRM.
		$this->hooks_civicrm_add();

	}

	/**
	 * Create an Event Organiser Event Category Term.
	 *
	 * @since 0.1
	 *
	 * @param array|object $event_type The CiviCRM Event Type.
	 * @return array $result Array containing Event Organiser Event Category Term data.
	 */
	public function create_term( $event_type ) {

		// Sanity check.
		if ( empty( $event_type ) ) {
			return false;
		}

		// Cast as array.
		if ( ! is_array( $event_type ) ) {
			$event_type = (array) $event_type;
		}

		// Define description if present.
		$description = isset( $event_type['description'] ) ? $event_type['description'] : '';

		// Construct args.
		$args = [
			'slug'        => sanitize_title( $event_type['name'] ),
			'description' => $description,
		];

		// Unhook listeners.
		$this->hooks_wordpress_remove();

		// Insert it.
		$result = wp_insert_term( $event_type['label'], 'event-category', $args );

		// Rehook listeners.
		$this->hooks_wordpress_add();

		// If all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// If something goes wrong, we get a WP_Error object.
		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Add the Event Type ID to the Term's meta.
		$this->add_term_meta( $result['term_id'], (int) $event_type['id'] );

		/*
		 * WordPress does not have an "Active/Inactive" Term state by default,
		 * but we can add a "term meta" value to hold this.
		 *
		 * @see eventorganiser_add_tax_meta()
		 * @see eventorganiser_edit_tax_meta()
		 */

		// TODO: Use "term meta" to save "Active/Inactive" state.

		// --<
		return $result;

	}

	/**
	 * Update an Event Organiser Event Category Term.
	 *
	 * @since 0.1
	 *
	 * @param array $new_type The CiviCRM Event Type.
	 * @param array $old_type The CiviCRM Event Type prior to the update.
	 * @return int|bool $term_id The ID of the updated Event Organiser Event Category Term.
	 */
	public function update_term( $new_type, $old_type = null ) {

		// Sanity check.
		if ( ! is_array( $new_type ) ) {
			return false;
		}

		// First, query "term meta".
		$term = $this->get_term_by_meta( $new_type );

		// If the new query produces a result.
		if ( $term instanceof WP_Term ) {

			// Grab the found Term ID.
			$term_id = $term->term_id;

			// Fall back to old behaviour if none found.
		} else {

			// If we're updating a Term.
			if ( ! is_null( $old_type ) ) {

				// Does this Event Type have an existing Term?
				$term_id = $this->get_term_id( $old_type );

			} else {

				// Get matching Event Term ID.
				$term_id = $this->get_term_id( $new_type );

			}

		}

		// If we don't get one.
		if ( false === $term_id ) {

			// Create Term.
			$result = $this->create_term( $new_type );

			// How did we do?
			if ( false === $result ) {
				return $result;
			}

			// --<
			return $result['term_id'];

		}

		// Define description if present.
		$description = isset( $new_type['description'] ) ? $new_type['description'] : '';

		// Construct Term.
		$args = [
			'name'        => $new_type['label'],
			'slug'        => sanitize_title( $new_type['name'] ),
			'description' => $description,
		];

		// Unhook listeners.
		$this->hooks_wordpress_remove();

		// Update Term.
		$result = wp_update_term( $term_id, 'event-category', $args );

		// Rehook listeners.
		$this->hooks_wordpress_add();

		// If all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// If something goes wrong, we get a WP_Error object.
		if ( is_wp_error( $result ) ) {
			return false;
		}

		/*
		 * WordPress does not have an "Active/Inactive" Term state by default,
		 * but we can add a "term meta" value to hold this.
		 *
		 * @see eventorganiser_add_tax_meta()
		 * @see eventorganiser_edit_tax_meta()
		 */

		// TODO: Use "term meta" to save "Active/Inactive" state.

		// --<
		return $result['term_id'];

	}

	/**
	 * Delete an Event Organiser Event Category Term.
	 *
	 * @since 0.4.6
	 *
	 * @param int $term_id The Term to delete.
	 * @return int|bool $term_id The ID of the updated Event Organiser Event Category Term.
	 */
	public function delete_term( $term_id ) {

		// Unhook listeners.
		$this->hooks_wordpress_remove();

		// Delete the Term.
		$result = wp_delete_term( $term_id, 'event-category' );

		// Rehook listeners.
		$this->hooks_wordpress_add();

		// True on success, false if Term does not exist. Zero on attempted
		// deletion of default Category. WP_Error if the Taxonomy does not exist.
		return $result;

	}

	/**
	 * Get the Event Organiser Event Category Term for a given CiviCRM Event Type.
	 *
	 * @since 0.1
	 *
	 * @param array|object $event_type The CiviCRM Event Type.
	 * @return int|bool $term_id The ID of the updated Event Organiser Event Category Term.
	 */
	public function get_term_id( $event_type ) {

		// Sanity check.
		if ( empty( $event_type ) ) {
			return false;
		}

		// Cast as array.
		if ( ! is_array( $event_type ) ) {
			$event_type = (array) $event_type;
		}

		// Init return.
		$term_id = false;

		// First, query "term meta".
		$term = $this->get_term_by_meta( $event_type );

		// How did we do?
		if ( false !== $term ) {
			return $term->term_id;
		}

		// Try and match by Term name <-> Type label.
		$term = get_term_by( 'name', $event_type['label'], 'event-category' );

		// How did we do?
		if ( false !== $term ) {
			return $term->term_id;
		}

		// Try and match by Term slug <-> Type label.
		$term = get_term_by( 'slug', sanitize_title( $event_type['label'] ), 'event-category' );

		// How did we do?
		if ( false !== $term ) {
			return $term->term_id;
		}

		// Try and match by Term slug <-> Type name.
		$term = get_term_by( 'slug', sanitize_title( $event_type['name'] ), 'event-category' );

		// How did we do?
		if ( false !== $term ) {
			return $term->term_id;
		}

		// --<
		return $term_id;

	}

	/**
	 * Get a Term without knowing its Taxonomy - this was necessary before WordPress
	 * passed $taxonomy to the 'edit_terms' action in WP 3.7:
	 *
	 * @see http://core.trac.wordpress.org/ticket/22542
	 * @see https://core.trac.wordpress.org/changeset/24829
	 *
	 * @since 0.1
	 *
	 * @param int    $term_id The ID of the Term whose Taxonomy we want.
	 * @param string $output Passed to get_term.
	 * @param string $filter Passed to get_term.
	 * @return WP_Term $term The WP Term object passed by reference.
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

		// Get Taxonomy name.
		$taxonomy = $tax->taxonomy;

		// --<
		return get_term( $term_id, $taxonomy, $output, $filter );

	}

	/**
	 * Never let Radio Buttons for Taxonomies filter get_terms() to add a null
	 * Term because CiviCRM requires an Event to have a Term.
	 *
	 * @since 0.3.5
	 *
	 * @param bool $set True if null Term is to be set, false otherwise.
	 * @return bool $set True if null Term is to be set, false otherwise.
	 */
	public function skip_null_term( $set ) {

		// A class property is passed in, so set that.
		$set = 0;

		// --<
		return $set;

	}

	/**
	 * Disallow "No Category" in Event Organiser Event Category box.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @return bool false
	 */
	public function force_taxonomy() {

		// Disable.
		return false;

	}

	/**
	 * Trigger hiding of "Parent Category" dropdown in metaboxes.
	 *
	 * @since 0.3.5
	 */
	public function terms_dropdown_intercept() {

		// Trigger emptying of dropdown.
		add_filter( 'wp_dropdown_cats', [ $this, 'terms_dropdown_clear' ], 20, 2 );

	}

	/**
	 * Always hide "Parent Category" dropdown in metaboxes.
	 *
	 * @since 0.3.5
	 *
	 * @param string $output The existing output.
	 * @param array  $parsed_args The arguments used to build the drop-down.
	 * @return string $output The modified output.
	 */
	public function terms_dropdown_clear( $output, $parsed_args ) {

		// Only clear Event Organiser Category.
		if ( 'event-category' !== $parsed_args['taxonomy'] ) {
			return $output;
		}

		// Only once please, in case further dropdowns are rendered.
		remove_filter( 'wp_dropdown_cats', [ $this, 'terms_dropdown_clear' ], 20 );

		// Clear.
		return '';

	}

	/**
	 * Make sure new Event Organiser Events have the default Term checked if no Term
	 * has been chosen - e.g. on the "Add New Event" screen.
	 *
	 * @since 0.4.2
	 *
	 * @param array $args An array of arguments.
	 * @param int   $post_id The Post ID.
	 */
	public function term_default_checked( $args, $post_id ) {

		// Only modify Event Organiser Category.
		if ( 'event-category' !== $args['taxonomy'] ) {
			return $args;
		}

		// If this is a Post.
		if ( $post_id ) {

			// Get existing Terms.
			$args['selected_cats'] = wp_get_object_terms(
				$post_id,
				$args['taxonomy'],
				array_merge( $args, [ 'fields' => 'ids' ] )
			);

			// Bail if a Category is already set.
			if ( ! empty( $args['selected_cats'] ) ) {
				return $args;
			}

		}

		// Get the default Event Type value.
		$event_type_value = $this->get_default_event_type_value();

		// Bail if something went wrong.
		if ( false === $event_type_value ) {
			return $args;
		}

		// Get the Event Type data.
		$event_type = $this->get_event_type_by_value( $event_type_value );

		// Bail if something went wrong.
		if ( false === $event_type ) {
			return $args;
		}

		// Get corresponding Term ID.
		$term_id = $this->get_term_id( $event_type );

		// Bail if something went wrong.
		if ( false === $term_id ) {
			return $args;
		}

		// Set argument.
		$args['selected_cats'] = [ $term_id ];

		// --<
		return $args;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get the Event Organiser Event Category Term for a given CiviCRM Event Type.
	 *
	 * @since 0.4.5
	 *
	 * @param array|object $event_type The array of CiviCRM Event Type data.
	 * @return WP_Term|bool $term The Term object, or false on failure.
	 */
	public function get_term_by_meta( $event_type ) {

		// Extract value.
		if ( is_array( $event_type ) ) {
			$value = $event_type['id'];
		} elseif ( is_object( $event_type ) ) {
			$value = $event_type->id;
		} else {
			return false;
		}

		// Query Terms for the Term with the ID of the Event Type in meta data.
		$args = [
			'taxonomy'   => 'event-category',
			'hide_empty' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => [
				[
					'key'     => $this->term_meta_key,
					'value'   => $value,
					'compare' => '=',
				],
			],
		];

		// Get what should only be a single Term.
		$terms = get_terms( $args );

		// Bail if there are no results.
		if ( empty( $terms ) ) {
			return false;
		}

		// Log a message and bail if there's an error.
		if ( is_wp_error( $terms ) ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'     => __METHOD__,
				'message'    => $terms->get_error_message(),
				'term'       => $term,
				'event_type' => $event_type,
				'backtrace'  => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// If we get more than one, WTF?
		if ( count( $terms ) > 1 ) {
			return false;
		}

		// Init return.
		$term = false;

		// Grab Term data.
		if ( count( $terms ) === 1 ) {
			$term = array_pop( $terms );
		}

		// --<
		return $term;

	}

	/**
	 * Get CiviCRM Event Type for an Event Organiser Event Category Term.
	 *
	 * @since 0.4.5
	 *
	 * @param int $term_id The numeric ID of the Term.
	 * @return int|bool $event_type_id The ID of the CiviCRM Event Type, or false on failure.
	 */
	public function get_term_meta( $term_id ) {

		// Get the Event Type ID from the Term's meta.
		$event_type_id = get_term_meta( $term_id, $this->term_meta_key, true );

		// Bail if there is no result.
		if ( empty( $event_type_id ) ) {
			return false;
		}

		// --<
		return $event_type_id;

	}

	/**
	 * Add meta data to an Event Organiser Event Category Term.
	 *
	 * @since 0.4.5
	 *
	 * @param int $term_id The numeric ID of the Term.
	 * @param int $event_type_id The numeric ID of the CiviCRM Event Type.
	 * @return int|bool $meta_id The ID of the meta, or false on failure.
	 */
	public function add_term_meta( $term_id, $event_type_id ) {

		// Add the Event Type ID to the Term's meta.
		$meta_id = add_term_meta( $term_id, $this->term_meta_key, (int) $event_type_id, true );

		// Log something if there's an error.
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
		if ( false === $meta_id ) {

			/*
			 * This probably means that the Term already has its "term meta" set.
			 * Uncomment the following to debug if you need to.
			 */

			/*
			$e = new Exception;
			$trace = $e->getTraceAsString();
			$log   = [
				'method' => __METHOD__,
				'message' => __( 'Could not add term_meta', 'civicrm-event-organiser' ),
				'term_id' => $term_id,
				'event_type_id' => $event_type_id,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			*/

		}

		// Log a message if the term_id is ambiguous between Taxonomies.
		if ( is_wp_error( $meta_id ) ) {

			// Log error message.
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'        => __METHOD__,
				'message'       => $meta_id->get_error_message(),
				'term_id'       => $term_id,
				'event_type_id' => $event_type_id,
				'backtrace'     => $trace,
			];
			$this->plugin->log_error( $log );

			// Also overwrite return.
			$meta_id = false;

		}

		// --<
		return $meta_id;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Callback for the CiviCRM 'civi.dao.postInsert' hook.
	 *
	 * @since 0.4.6
	 *
	 * @param object $event The Event object.
	 * @param string $hook The hook name.
	 */
	public function event_type_created( $event, $hook ) {

		// Extract Event Type for this hook.
		$event_type =& $event->object;

		// Bail if this isn't the type of object we're after.
		if ( ! ( $event_type instanceof CRM_Core_DAO_OptionValue ) ) {
			return;
		}

		// Bail if it's not an Event Type.
		$opt_group_id = $this->get_event_types_optgroup_id();
		if ( false === $opt_group_id || (int) $opt_group_id !== (int) $event_type->option_group_id ) {
			return;
		}

		// Add description if present.
		$description = '';
		if ( ! empty( $event_type->description ) && 'null' !== $event_type->description ) {
			$description = $event_type->description;
		}

		// Construct Term data.
		$term_data = [
			'id'          => $event_type->id,
			'label'       => $event_type->label,
			'name'        => $event_type->label,
			'description' => $description,
		];

		// Unhook listeners.
		$this->hooks_wordpress_remove();

		// Create Event Organiser Term.
		$result = $this->create_term( $term_data );

		// Rehook listeners.
		$this->hooks_wordpress_add();

	}

	/**
	 * Callback for the CiviCRM 'civi.dao.preUpdate' hook.
	 *
	 * This is unused since the hook doesn't exist yet.
	 *
	 * @see https://lab.civicrm.org/dev/core/issues/1638
	 *
	 * @since 0.4.6
	 *
	 * @param object $event The Event object.
	 * @param string $hook The hook name.
	 */
	public function event_type_pre_update( $event, $hook ) {

		// Extract Event Type for this hook.
		$event_type =& $event->object;

		// Bail if this isn't the type of object we're after.
		if ( ! ( $event_type instanceof CRM_Core_DAO_OptionValue ) ) {
			return;
		}

		// Get the full Event Type before it is updated.
		$this->event_type_pre = $this->get_event_type_by_id( $event_type->id );

		/*
		$e = new Exception;
		$trace = $e->getTraceAsString();
		$log   = [
			'method' => __METHOD__,
			'hook' => $hook,
			//'event' => $event,
			'event_type' => $event_type,
			'event_type_pre' => $this->event_type_pre,
			//'backtrace' => $trace,
		];
		$this->plugin->log_error( $log );
		*/

	}

	/**
	 * Callback for the CiviCRM 'civi.dao.postUpdate' hook.
	 *
	 * @since 0.4.6
	 *
	 * @param object $event The Event object.
	 * @param string $hook The hook name.
	 */
	public function event_type_updated( $event, $hook ) {

		// Extract Event Type for this hook.
		$event_type =& $event->object;

		// Bail if this isn't the type of object we're after.
		if ( ! ( $event_type instanceof CRM_Core_DAO_OptionValue ) ) {
			return;
		}

		// Bail if it's not an Event Type.
		$opt_group_id = $this->get_event_types_optgroup_id();
		if ( false === $opt_group_id || (int) $opt_group_id !== (int) $event_type->option_group_id ) {
			return;
		}

		// Get the full data for the updated Event Type.
		$event_type_full = $this->get_event_type_by_id( $event_type->id );
		if ( false === $event_type_full ) {
			return;
		}

		// Update description if present.
		$description = '';
		if ( ! empty( $event_type_full['description'] ) && 'null' !== $event_type_full['description'] ) {
			$description = $event_type_full['description'];
		}

		// Construct Event Type data.
		$event_type = [
			'id'          => $event_type_full['id'],
			'label'       => $event_type_full['label'],
			'name'        => $event_type_full['name'],
			'description' => $description,
		];

		// Unhook listeners.
		$this->hooks_wordpress_remove();

		// Update Event Organiser Term - or create if it doesn't exist.
		$result = $this->update_term( $event_type );

		// Rehook listeners.
		$this->hooks_wordpress_add();

	}

	/**
	 * Callback for the CiviCRM 'civi.dao.preDelete' hook.
	 *
	 * @since 0.4.6
	 *
	 * @param object $event The Event object.
	 * @param string $hook The hook name.
	 */
	public function event_type_pre_delete( $event, $hook ) {

		// Extract Event Type for this hook.
		$event_type =& $event->object;

		// Bail if this isn't the type of object we're after.
		if ( ! ( $event_type instanceof CRM_Core_DAO_OptionValue ) ) {
			return;
		}

		// Get the actual Event Type being deleted.
		$event_type_full = $this->get_event_type_by_id( $event_type->id );
		if ( false === $event_type_full ) {
			return;
		}

		// Bail if there's no Term ID.
		$term_id = $this->get_term_id( $event_type_full );
		if ( false === $term_id ) {
			return;
		}

		// Unhook listeners.
		$this->hooks_wordpress_remove();

		// Delete Term.
		$success = $this->delete_term( $term_id );

		// Rehook listeners.
		$this->hooks_wordpress_add();

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Update a CiviCRM Event Type.
	 *
	 * @since 0.4.2
	 *
	 * @param WP_Term $new_term The new Event Organiser Event Category Term.
	 * @param WP_Term $old_term The Event Organiser Event Category Term as it was before update.
	 * @return int|bool $event_type_id The CiviCRM Event Type ID, or false on failure.
	 */
	public function update_event_type( $new_term, $old_term = null ) {

		// Sanity check.
		if ( ! is_object( $new_term ) ) {
			return false;
		}

		// Bail if CiviCRM is not active.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check.
		if ( false === $opt_group_id ) {
			return false;
		}

		// Define Event Type.
		$params = [
			'version'         => 3,
			'option_group_id' => $opt_group_id,
			'label'           => $new_term->name,
			// 'name' => $new_term->name,
		];

		// If there is a description, apply content filters and add to params.
		if ( ! empty( $new_term->description ) ) {

			/**
			 * Apply content filters to the Term description.
			 *
			 * For internal use only.
			 *
			 * @since 0.4.2
			 * @since 0.7 Moved to this class.
			 * @since 0.8.0 Renamed from "civicrm_eo_term_content".
			 *
			 * @param string $description The Term description.
			 */
			$params['description'] = apply_filters( 'ceo/taxonomy/term/the_content', $new_term->description );

		}

		// First check if the Term has the ID in its "term meta".
		$event_type_id = $this->get_term_meta( $new_term->term_id );

		// Short-circuit if we found it.
		if ( false === $event_type_id ) {

			// If we're updating a Term.
			if ( ! is_null( $old_term ) ) {

				// Get existing Event Type ID.
				$event_type_id = $this->get_event_type_id( $old_term );

			} else {

				// Get matching Event Type ID.
				$event_type_id = $this->get_event_type_id( $new_term );

			}

		}

		// Trigger update if we find a synced Event Type.
		if ( false !== $event_type_id ) {
			$params['id'] = $event_type_id;
		}

		// Create (or update) the Event Type.
		$result = civicrm_api( 'OptionValue', 'create', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => $result['error_message'],
				'params'    => $params,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Success, grab Event Type ID.
		if ( isset( $result['id'] ) && is_numeric( $result['id'] ) && $result['id'] > 0 ) {
			$event_type_id = (int) $result['id'];
		}

		// --<
		return $event_type_id;

	}

	/**
	 * Delete a CiviCRM Event Type.
	 *
	 * @since 0.4.2
	 *
	 * @param WP_Term $term The Event Organiser Event Category Term.
	 * @return array|bool CiviCRM API data array on success, false on failure.
	 */
	public function delete_event_type( $term ) {

		// Sanity check.
		if ( ! is_object( $term ) ) {
			return false;
		}

		// Bail if CiviCRM is not active.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get ID of Event Type to delete.
		$event_type_id = $this->get_event_type_id( $term );

		// Error check.
		if ( false === $event_type_id ) {
			return false;
		}

		// Define Event Type.
		$params = [
			'version' => 3,
			'id'      => $event_type_id,
		];

		// Delete the Event Type.
		$result = civicrm_api( 'OptionValue', 'delete', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => $result['error_message'],
				'params'    => $params,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// --<
		return $result;

	}

	/**
	 * Get a CiviCRM Event Type ID for a given Term.
	 *
	 * @since 0.4.2
	 *
	 * @param object $term The Event Organiser Event Category Term.
	 * @return int|bool $event_type_id The numeric ID of the CiviCRM Event Type, or false on failure.
	 */
	public function get_event_type_id( $term ) {

		// Return early if the Term has the ID in its Term meta.
		$event_type_id = $this->get_term_meta( $term->term_id );
		if ( false !== $event_type_id ) {
			return $event_type_id;
		}

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get Option Group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();
		if ( false === $opt_group_id ) {
			return false;
		}

		// Define params to get item.
		$params = [
			'version'         => 3,
			'option_group_id' => $opt_group_id,
			'label'           => $term->name,
			'options'         => [
				'sort'  => 'weight ASC',
				'limit' => 1,
			],
		];

		// Get the item.
		$result = civicrm_api( 'OptionValue', 'get', $params );

		// Log and bail if we get an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
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

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// The result set should contain only one item.
		$event_type = array_pop( $result['values'] );

		// Sanity check ID and return if valid.
		if ( ! empty( $event_type['id'] ) && is_numeric( $event_type['id'] ) ) {
			return (int) $event_type['id'];
		}

		// If all the above fails.
		return false;

	}

	/**
	 * Get a CiviCRM Event Type value by type ID.
	 *
	 * @since 0.4.2
	 *
	 * @param int $event_type_id The numeric ID of the CiviCRM Event Type.
	 * @return int|bool $value The value of the CiviCRM Event Type, or false on failure.
	 */
	public function get_event_type_value( $event_type_id ) {

		// Init return.
		$value = false;

		// Get the full Event Type.
		$event_type = $this->get_event_type_by_id( $event_type_id );
		if ( false === $event_type ) {
			return $value;
		}

		// Overwrite return if we get a value.
		if ( ! empty( $event_type['value'] ) && is_numeric( $event_type['value'] ) ) {
			$value = (int) $event_type['value'];
		}

		// --<
		return $value;

	}

	/**
	 * Get all CiviCRM Event Types.
	 *
	 * @since 0.4.2
	 *
	 * @return array|bool $event_types CiviCRM API return array, or false on failure.
	 */
	public function get_event_types() {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check.
		if ( false === $opt_group_id ) {
			return false;
		}

		// Define params to get items sorted by weight.
		$params = [
			'version'         => 3,
			'option_group_id' => $opt_group_id,
			'options'         => [
				'limit' => 0, // Get all Event Types.
				'sort'  => 'weight ASC',
			],
		];

		// Get them (descriptions will be present if not null).
		$result = civicrm_api( 'OptionValue', 'get', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => $result['error_message'],
				'result'    => $result,
				'params'    => $params,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// --<
		return $result;

	}

	/**
	 * Gets all CiviCRM Event Types keyed by pseudo-ID.
	 *
	 * The pseudo-ID is actually the Event Type "value" rather than the Event Type ID.
	 *
	 * @since 0.8.2
	 *
	 * @return array $event_types The array of CiviCRM Event Types keyed by pseudo-ID.
	 */
	public function get_event_types_mapped() {

		// Get all Event Types.
		$result = $this->get_event_types();

		// Bail on error or no results.
		if ( false === $result || empty( $result['values'] ) ) {
			return [];
		}

		// Build mapped array.
		$event_types = [];
		foreach ( $result['values'] as $key => $event_type ) {
			$event_types[ (int) $event_type['value'] ] = $event_type['label'];
		}

		// --<
		return $event_types;

	}

	/**
	 * Gets the default Event Type value for a Post, but fall back to the default as set
	 * on the admin screen, Fall back to false otherwise.
	 *
	 * @since 0.4.2
	 *
	 * @param WP_Post $post The WP Event object.
	 * @return int|bool $existing_value The numeric ID of the CiviCRM Event Type, or false if none exists.
	 */
	public function get_default_event_type_value( $post = null ) {

		// Init with impossible ID.
		$existing_value = false;

		// Do we have a default set?
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_type' );

		// Override with default value if we get one.
		if ( '' !== $default && is_numeric( $default ) ) {
			$existing_value = (int) $default;
		}

		// If we have a Post.
		if ( isset( $post ) && is_object( $post ) ) {

			// Get the Terms for this Post - there should only be one.
			$cat = get_the_terms( $post->ID, 'event-category' );

			// Error check.
			if ( is_wp_error( $cat ) ) {
				return false;
			}

			// Did we get any?
			if ( is_array( $cat ) && count( $cat ) > 0 ) {

				// Get first Term object (keyed by Term ID).
				$term = array_shift( $cat );

				// Get type ID for this Term.
				$existing_id = $this->get_event_type_id( $term );

				// Convert to value.
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
	 * @param int $event_type_id The numeric ID of a CiviCRM Event Type.
	 * @return array|bool $event_type CiviCRM Event Type data, or false on failure.
	 */
	public function get_event_type_by_id( $event_type_id ) {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();
		if ( false === $opt_group_id ) {
			return false;
		}

		// Define params to get item.
		$params = [
			'version'         => 3,
			'option_group_id' => $opt_group_id,
			'id'              => $event_type_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionValue', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return false;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// The result set should contain only one item.
		$event_type = array_pop( $result['values'] );

		// --<
		return $event_type;

	}

	/**
	 * Get a CiviCRM Event Type by "value" pseudo-ID.
	 *
	 * @since 0.4.2
	 *
	 * @param int $event_type_value The numeric value of a CiviCRM Event Type.
	 * @return array|bool $event_type CiviCRM Event Type data, or false on failure.
	 */
	public function get_event_type_by_value( $event_type_value ) {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->plugin->civi->is_active() ) {
			return false;
		}

		// Get option group ID.
		$opt_group_id = $this->get_event_types_optgroup_id();

		// Error check.
		if ( false === $opt_group_id ) {
			return false;
		}

		// Define params to get item.
		$params = [
			'version'         => 3,
			'option_group_id' => $opt_group_id,
			'value'           => $event_type_value,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionValue', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return false;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// The result set should contain only one item.
		$event_type = array_pop( $result['values'] );

		// --<
		return $event_type;

	}

	/**
	 * Get the CiviCRM Event Types option group ID.
	 *
	 * Multiple calls to the database are avoided by setting a static variable.
	 *
	 * @since 0.4.2
	 *
	 * @return array|bool $optgroup_id The CiviCRM API return array, or false on failure.
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
			$params = [
				'version' => 3,
				'name'    => 'event_type',
			];

			// Get it.
			$result = civicrm_api( 'OptionGroup', 'getsingle', $params );

			// Error check.
			if ( isset( $result['id'] ) && is_numeric( $result['id'] ) && $result['id'] > 0 ) {

				// Set flag to false for future reference.
				$optgroup_id = (int) $result['id'];

				// --<
				return $optgroup_id;

			}

		}

		// --<
		return $optgroup_id;

	}

}
