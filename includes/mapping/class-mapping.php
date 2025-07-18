<?php
/**
 * Mapping Class.
 *
 * Handles mapping between Event Organiser Events and CiviCRM Events.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Mapping Class.
 *
 * A class that encapsulates mapping between Event Organiser Events and CiviCRM Events.
 *
 * Correspondences are stored using existing data structures. This imposes some
 * limitations on us. Ideally, I suppose, this plugin would define its own table
 * for the correspondences, but the existing tables will work.
 *
 * (a) A CiviCRM Event needs to know which Post ID and which Occurrence ID it is synced with.
 * (b) An Event Organiser Event (post) needs to know the CiviCRM Events which are synced with it.
 * (c) An Event Organiser Occurrence needs to know which CiviCRM Event it is synced with.
 *
 * So, given that CiviCRM seems to have no meta storage for CiviCRM Events, use a
 * WordPress option to store this data. We can now query the data by CiviCRM Event ID
 * and retrieve Post ID and Occurrence ID. The array looks like:
 *
 * array(
 *   $civi_event_id => array(
 *     'post_id' => $post_id,
 *     'occurrence_id' => $occurrence_id,
 *   ),
 *   $civi_event_id => array(
 *     'post_id' => $post_id,
 *     'occurrence_id' => $occurrence_id,
 *   ),
 *   ...
 * )
 *
 * In the reverse situation, we store an array of correspondences as Post meta.
 * We will need to know the Post ID to get it. The array looks like:
 *
 * array(
 *   $occurrence_id => $civi_event_id,
 *   $occurrence_id => $civi_event_id,
 *   $occurrence_id => $civi_event_id,
 *   ...
 * )
 *
 * In practice, however, if the sequence changes, then Event Organiser regenerates the
 * Occurrences anyway, so our correspondences need to be rebuilt when that
 * happens. This makes the occurrence_id linkage useful only when sequences are
 * broken.
 *
 * There is an additional "orphans" array, so that when Occurrences are added
 * (or added back) to a sequence, the corresponding CiviCRM Event may be reconnected
 * as long as none of its date and time data has changed.
 *
 * @since 0.7
 */
class CEO_Mapping {

	/**
	 * Plugin object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * Constructor.
	 *
	 * @since 0.7
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin = $parent;

		// Initialise.
		add_action( 'ceo/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Perform initialisation tasks.
	 *
	 * @since 0.7
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.7
	 */
	public function register_hooks() {

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Clears all CiviCRM Events <-> Event Organiser Event data.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 */
	public function clear_all_correspondences() {

		// Construct args for all Event Posts.
		$args = [
			'post_type'   => 'event',
			'numberposts' => -1,
		];

		// Get all Event Posts.
		$all_events = get_posts( $args );

		// Delete post meta for all Events that we get.
		if ( count( $all_events ) > 0 ) {
			foreach ( $all_events as $event ) {
				delete_post_meta( $post_id, '_civi_eo_civicrm_events' );
				delete_post_meta( $post_id, '_civi_eo_civicrm_events_disabled' );
			}
		}

		// Overwrite event_disabled array.
		$this->plugin->admin->option_save( 'civi_eo_civi_event_disabled', [] );

		// Overwrite Event Organiser to CiviCRM data.
		$this->plugin->admin->option_save( 'civi_eo_civi_event_data', [] );

	}

	/**
	 * Store CiviCRM Events <-> Event Organiser Event data.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int   $post_id The numeric ID of the WP Post.
	 * @param array $correspondences CiviCRM Event IDs, keyed by Event Organiser Occurrence ID.
	 * @param array $unlinked CiviCRM Event IDs that have been orphaned from an Event Organiser Event.
	 */
	public function store_event_correspondences( $post_id, $correspondences, $unlinked = [] ) {

		/*
		 * An Event Organiser Event needs to know the IDs of all the CiviCRM Events,
		 * keyed by Event Organiser Occurrence ID.
		 */
		update_post_meta( $post_id, '_civi_eo_civicrm_events', $correspondences );

		// Init array with stored value (or empty array).
		$civi_event_data = $this->plugin->admin->option_get( 'civi_eo_civi_event_data', [] );

		/*
		 * Each CiviCRM Event needs to know the IDs of the Event Organiser Post
		 * and the Event Organiser Occurrence.
		 */
		if ( count( $correspondences ) > 0 ) {

			// Add Post ID and Occurrence ID, keyed by CiviCRM Event ID.
			foreach ( $correspondences as $occurrence_id => $civi_event_id ) {
				$civi_event_data[ $civi_event_id ] = [
					'post_id'       => $post_id,
					'occurrence_id' => $occurrence_id,
				];
			}

		}

		// Store updated array as option.
		$this->plugin->admin->option_save( 'civi_eo_civi_event_data', $civi_event_data );

		// Finally, store orphaned CiviCRM Events.
		$this->store_orphaned_events( $post_id, $unlinked );

		/**
		 * Fires when the Event correspondences have been saved.
		 *
		 * @since 0.8.2
		 *
		 * @param int   $post_id The numeric ID of the WP Post.
		 * @param array $correspondences CiviCRM Event IDs, keyed by Event Organiser Occurrence ID.
		 * @param array $unlinked CiviCRM Event IDs that have been orphaned from an Event Organiser Event.
		 * @param array $civi_event_data The array of linkages between CiviCRM Events and Event Organiser Events.
		 */
		do_action( 'ceo/mapping/correspondences/saved', $post_id, $correspondences, $unlinked, $civi_event_data );

	}

	/**
	 * Get all Event correspondences.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @return array $correspondences All CiviCRM Event - Event Organiser correspondences.
	 */
	public function get_all_event_correspondences() {

		// Init return.
		$correspondences = [];

		// Add "CiviCRM to Event Organiser".
		$correspondences['civi_to_eo'] = $this->get_all_civi_to_eo_correspondences();

		// Add "Event Organiser to CiviCRM".
		$correspondences['eo_to_civi'] = $this->get_all_eo_to_civi_correspondences();

		// --<
		return $correspondences;

	}

	/**
	 * Get all Event Organiser Events for all CiviCRM Events.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @return array $eo_event_data The array of all Event Organiser Event IDs.
	 */
	public function get_all_civi_to_eo_correspondences() {

		// Get option.
		$eo_event_data = $this->plugin->admin->option_get( 'civi_eo_civi_event_data', [] );

		// --<
		return $eo_event_data;

	}

	/**
	 * Get all CiviCRM Events for all Event Organiser Events.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @return array $eo_event_data The array of all CiviCRM Event IDs.
	 */
	public function get_all_eo_to_civi_correspondences() {

		// Init civi data.
		$civi_event_data = [];

		// Construct args for all Event Posts.
		$args = [
			'post_type'   => 'event',
			'numberposts' => -1,
		];

		// Get all Event Posts.
		$all_events = get_posts( $args );

		// Get post meta and add to return array if we get some.
		if ( count( $all_events ) > 0 ) {
			foreach ( $all_events as $event ) {
				$civi_event_data[ $event->ID ] = $this->get_civi_event_ids_by_eo_event_id( $event->ID );
			}
		}

		// --<
		return $civi_event_data;

	}

	/**
	 * Delete the correspondence between an Event Organiser Occurrence and a CiviCRM Event.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int      $post_id The numeric ID of the WP Post.
	 * @param int      $occurrence_id The numeric ID of the Event Organiser Event Occurrence.
	 * @param int|bool $civi_event_id The numeric ID of the CiviCRM Event.
	 */
	public function clear_event_correspondence( $post_id, $occurrence_id, $civi_event_id = false ) {

		// Get CiviCRM Event ID if not passed in.
		if ( empty( $civi_event_id ) ) {
			$civi_event_id = $this->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );
		}

		// Get all CiviCRM Event data held in option.
		$civi_event_data = $this->get_all_civi_to_eo_correspondences();

		// If we have a CiviCRM Event ID for this Event Organiser Occurrence.
		if ( false === $civi_event_id ) {

			// Unset the item with this key in the option array.
			if ( isset( $civi_event_data[ $civi_event_id ] ) ) {
				unset( $civi_event_data[ $civi_event_id ] );
			}

			// Store updated array.
			$this->plugin->admin->option_save( 'civi_eo_civi_event_data', $civi_event_data );

		}

		// Bail if an Event is being deleted.
		if ( doing_action( 'delete_post' ) ) {
			return;
		}

		// Get existing "live".
		$correspondences = $this->get_civi_event_ids_by_eo_event_id( $post_id );

		// Is the CiviCRM Event in the "live" array?
		if ( is_array( $correspondences ) && in_array( $civi_event_id, $correspondences ) ) {

			// Ditch the current CiviCRM Event ID.
			$correspondences = array_diff( $correspondences, [ $civi_event_id ] );

			// Update the meta value.
			update_post_meta( $post_id, '_civi_eo_civicrm_events', $correspondences );

			// No need to go further.
			return;

		}

		// Get existing "orphans".
		$orphans = $this->get_orphaned_events_by_eo_event_id( $post_id );

		// Is the CiviCRM Event in the "orphans" array?
		if ( is_array( $orphans ) && in_array( $civi_event_id, $orphans ) ) {

			// Ditch the current CiviCRM Event ID.
			$orphans = array_diff( $orphans, [ $civi_event_id ] );

			// Update the meta value.
			update_post_meta( $post_id, '_civi_eo_civicrm_events_disabled', $orphans );

		}

	}

	/**
	 * Delete all correspondences between an Event Organiser Event and CiviCRM Events.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int   $post_id The numeric ID of the WP Post.
	 * @param array $civi_event_ids The array of CiviCRM Event IDs.
	 */
	public function clear_event_correspondences( $post_id, $civi_event_ids = [] ) {

		// Maybe get CiviCRM Event IDs from post meta.
		if ( empty( $civi_event_ids ) ) {
			$civi_event_ids = $this->get_civi_event_ids_by_eo_event_id( $post_id );
		}

		// Get CiviCRM Event data held in option.
		$civi_event_data = $this->get_all_civi_to_eo_correspondences();

		// If we have some CiviCRM Event IDs for this Event Organiser Event.
		if ( count( $civi_event_ids ) > 0 ) {

			// Unset items with the relevant key in the option array.
			foreach ( $civi_event_ids as $civi_event_id ) {
				if ( isset( $civi_event_data[ $civi_event_id ] ) ) {
					unset( $civi_event_data[ $civi_event_id ] );
				}
			}

			// Store updated array.
			$this->plugin->admin->option_save( 'civi_eo_civi_event_data', $civi_event_data );

		}

		// Now we can delete the array held in post meta.
		delete_post_meta( $post_id, '_civi_eo_civicrm_events' );

		// Also delete the array of orphans held in post meta.
		delete_post_meta( $post_id, '_civi_eo_civicrm_events_disabled' );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get Event Organiser Event ID for a CiviCRM Event Event ID.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $civi_event_id The numeric ID of a CiviCRM Event Event.
	 * @return int|bool $eo_event_id The numeric ID of the Event Organiser Event, or false on failure.
	 */
	public function get_eo_event_id_by_civi_event_id( $civi_event_id ) {

		// Init return.
		$eo_event_id = false;

		// Get all correspondences.
		$eo_event_data = $this->get_all_civi_to_eo_correspondences();

		// Get keyed value if we have one.
		if ( count( $eo_event_data ) > 0 ) {
			if ( isset( $eo_event_data[ $civi_event_id ] ) ) {
				$eo_event_id = $eo_event_data[ $civi_event_id ]['post_id'];
			}
		}

		// --<
		return $eo_event_id;

	}

	/**
	 * Get Event Organiser Occurrence ID for a CiviCRM Event ID.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $civi_event_id The numeric ID of a CiviCRM Event.
	 * @return int|bool $eo_occurrence_id The numeric ID of the Event Organiser Occurrence, or false on failure.
	 */
	public function get_eo_occurrence_id_by_civi_event_id( $civi_event_id ) {

		// Init return.
		$eo_occurrence_id = false;

		// Get all correspondences.
		$eo_event_data = $this->get_all_civi_to_eo_correspondences();

		// Get keyed value if we have one.
		if ( count( $eo_event_data ) > 0 ) {
			if ( isset( $eo_event_data[ $civi_event_id ] ) ) {
				$eo_occurrence_id = $eo_event_data[ $civi_event_id ]['occurrence_id'];
			}
		}

		// --<
		return $eo_occurrence_id;

	}

	/**
	 * Get CiviCRM Event IDs (keyed by Occurrence ID) for an Event Organiser Event ID.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return array $civi_event_ids All CiviCRM Event IDs for the Post, keyed by Occurrence ID.
	 */
	public function get_civi_event_ids_by_eo_event_id( $post_id ) {

		// Get the meta value.
		$civi_event_ids = get_post_meta( $post_id, '_civi_eo_civicrm_events', true );

		// If it's not yet set it will be an empty string, so cast as array.
		if ( '' === $civi_event_ids ) {
			$civi_event_ids = [];
		}

		// --<
		return $civi_event_ids;

	}

	/**
	 * Get CiviCRM Event ID for an Event Organiser Event Occurrence.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @param int $occurrence_id The numeric ID of the Event Organiser Event Occurrence.
	 * @return mixed $civi_event_id The CiviCRM Event ID, or false otherwise.
	 */
	public function get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id ) {

		// Get the meta value.
		$civi_event_ids = $this->get_civi_event_ids_by_eo_event_id( $post_id );

		// Return false if none present.
		if ( count( $civi_event_ids ) === 0 ) {
			return false;
		}

		// Get value.
		$civi_event_id = isset( $civi_event_ids[ $occurrence_id ] ) ? $civi_event_ids[ $occurrence_id ] : false;

		// --<
		return $civi_event_id;

	}

	/**
	 * Check if a CiviCRM Event is part of an Event Organiser Event sequence.
	 *
	 * @since 0.3
	 *
	 * @param int $civi_event_id The CiviCRM Event ID.
	 * @return bool True if CiviCRM Event is part of an Event Organiser Event sequence, false otherwise.
	 */
	public function is_civi_event_in_eo_sequence( $civi_event_id ) {

		// Get the Event Organiser Event ID for this CiviCRM Event.
		$eo_post_id = $this->get_eo_event_id_by_civi_event_id( $civi_event_id );

		// If there is one.
		if ( false !== $eo_post_id ) {

			// Get the corresponding CiviCRM Events.
			$civi_event_ids = $this->get_civi_event_ids_by_eo_event_id( $eo_post_id );

			// Does the Event Organiser Event have multiple CiviCRM Events?
			if ( count( $civi_event_ids ) > 1 ) {

				// Yes, this CiviCRM Event is part of a series.
				return true;

			}

		}

		// Fallback.
		return false;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Store orphaned CiviCRM Events.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int   $post_id The numeric ID of the WP Post.
	 * @param array $orphans The CiviCRM Event IDs that have been orphaned from an Event Organiser Event.
	 */
	public function store_orphaned_events( $post_id, $orphans ) {

		// Get existing orphans before we update.
		$existing = $this->get_orphaned_events_by_eo_event_id( $post_id );

		// An Event Organiser Event needs to know the IDs of all the orphaned CiviCRM Events.
		update_post_meta( $post_id, '_civi_eo_civicrm_events_disabled', $orphans );

		// Get the values that are not present in new orphans.
		$to_remove = array_diff( $existing, $orphans );

		// Get the values that are not present in existing.
		$to_add = array_diff( $orphans, $existing );

		// Init array with stored value (or empty array).
		$civi_event_disabled = $this->plugin->admin->option_get( 'civi_eo_civi_event_disabled', [] );

		// Do we have any orphans to add?
		if ( count( $to_add ) > 0 ) {

			// Add Post IDs, keyed by CiviCRM Event ID.
			foreach ( $to_add as $civi_event_id ) {
				$civi_event_disabled[ $civi_event_id ] = $post_id;
			}

		}

		// Do we have any orphans to remove?
		if ( count( $to_remove ) > 0 ) {

			// Delete them from the data array.
			foreach ( $to_remove as $civi_event_id ) {
				unset( $civi_event_disabled[ $civi_event_id ] );
			}

		}

		// Store updated array as option.
		$this->plugin->admin->option_save( 'civi_eo_civi_event_disabled', $civi_event_disabled );

	}

	/**
	 * Make a single Occurrence orphaned.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $post_id The numeric ID of the WP Post = Event Organiser Event.
	 * @param int $occurrence_id The numeric ID of the Event Organiser Event Occurrence.
	 * @param int $civi_event_id The numeric ID of the orphaned CiviCRM Event.
	 */
	public function occurrence_orphaned( $post_id, $occurrence_id, $civi_event_id ) {

		// Get existing orphans for this Post.
		$existing_orphans = $this->get_orphaned_events_by_eo_event_id( $post_id );

		// Get existing "live" correspondences.
		$correspondences = $this->get_civi_event_ids_by_eo_event_id( $post_id );

		// Add the current orphan.
		$existing_orphans[] = $civi_event_id;

		// Safely remove it from live.
		if ( isset( $correspondences[ $occurrence_id ] ) ) {
			unset( $correspondences[ $occurrence_id ] );
		}

		// Store updated correspondences and orphans.
		$this->store_event_correspondences( $post_id, $correspondences, $existing_orphans );

	}

	/**
	 * Get orphaned CiviCRM Events by Event Organiser Event ID.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $post_id The numeric ID of the WP Post = Event Organiser Event.
	 * @return array $civi_event_ids Array of orphaned CiviCRM Event IDs.
	 */
	public function get_orphaned_events_by_eo_event_id( $post_id ) {

		// Get the meta value.
		$civi_event_ids = get_post_meta( $post_id, '_civi_eo_civicrm_events_disabled', true );

		// If it's not yet set it will be an empty string, so cast as array.
		if ( '' === $civi_event_ids ) {
			$civi_event_ids = [];
		}

		// --<
		return $civi_event_ids;

	}

	/**
	 * Get all Event Organiser Event IDs for all orphaned CiviCRM Events.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @return array $civi_event_disabled All CiviCRM Event IDs.
	 */
	public function get_eo_event_ids_for_orphans() {

		// Return option.
		return $this->plugin->admin->option_get( 'civi_eo_civi_event_disabled', [] );

	}

	/**
	 * Get Event Organiser Event ID by orphaned CiviCRM Event ID.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
	 * @return int $eo_event_id The numeric ID of the WP Post = Event Organiser Event.
	 */
	public function get_eo_event_id_by_orphaned_event_id( $civi_event_id ) {

		// Init return.
		$eo_event_id = false;

		// Get all orphan data.
		$eo_event_data = $this->get_eo_event_ids_for_orphans();

		// Get keyed value if there is one.
		if ( count( $eo_event_data ) > 0 ) {
			if ( isset( $eo_event_data[ $civi_event_id ] ) ) {
				$eo_event_id = $eo_event_data[ $civi_event_id ];
			}
		}

		// --<
		return $eo_event_id;

	}

	/**
	 * Gets the array of CiviCRM Event sync settings keyed by value.
	 *
	 * @since 0.8.2
	 *
	 * @return array $settings The array of CiviCRM Event sync settings keyed by value.
	 */
	public function get_civicrm_event_sync_mapped() {

		// Init settings.
		$settings = [
			0 => __( 'Sync all CiviEvents to WordPress', 'civicrm-event-organiser' ),
			1 => __( 'Show checkbox on each CiviEvent', 'civicrm-event-organiser' ),
		];

		// --<
		return $settings;

	}

	/**
	 * Gets the array of Status Sync settings keyed by ID.
	 *
	 * @since 0.8.2
	 *
	 * @return array $settings The array of Status Sync settings keyed by ID.
	 */
	public function get_status_sync_mapped() {

		// Init settings.
		$settings = [
			0 => __( 'Sync in both directions', 'civicrm-event-organiser' ),
			1 => __( 'One-way sync: EO &rarr; CiviCRM', 'civicrm-event-organiser' ),
			2 => __( 'One-way sync: CiviCRM &rarr; EO', 'civicrm-event-organiser' ),
			3 => __( 'Do not sync', 'civicrm-event-organiser' ),
		];

		// --<
		return $settings;

	}

}
