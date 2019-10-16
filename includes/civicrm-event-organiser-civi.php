<?php

/**
 * CiviCRM Event Organiser CiviCRM Class.
 *
 * A class that encapsulates interactions with CiviCRM.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_CiviCRM {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Flag for overriding sync process.
	 *
	 * @since 0.1
	 * @access public
	 * @var bool $do_not_sync True if overriding, false otherwise.
	 */
	public $do_not_sync = false;



	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Add CiviCRM hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', array( $this, 'register_hooks' ) );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.1
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
	 * @since 0.1
	 */
	public function register_hooks() {

		// Allow plugin to register php and template directories.
		//add_action( 'civicrm_config', array( $this, 'register_directories' ), 10, 1 );

		// Intercept CiviEvent create/update/delete actions.
		add_action( 'civicrm_post', array( $this, 'event_created' ), 10, 4 );
		add_action( 'civicrm_post', array( $this, 'event_updated' ), 10, 4 );
		add_action( 'civicrm_post', array( $this, 'event_deleted' ), 10, 4 );

	}



	/**
	 * Test if CiviCRM plugin is active.
	 *
	 * @since 0.1
	 *
	 * @return bool True if CiviCRM initialized, false otherwise.
	 */
	public function is_active() {

		// Bail if no CiviCRM init function.
		if ( ! function_exists( 'civi_wp' ) ) return false;

		// Try and init CiviCRM.
		return civi_wp()->initialize();

	}



	/**
	 * Register directories that CiviCRM searches for php and template files.
	 *
	 * @since 0.1
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_directories( &$config ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return;

		// Define our custom path.
		$custom_path = CIVICRM_WP_EVENT_ORGANISER_PATH . 'civicrm_custom_templates';

		// Get template instance.
		$template = CRM_Core_Smarty::singleton();

		// Add our custom template directory.
		$template->addTemplateDir( $custom_path );

		// Register template directories.
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );

	}



	/**
	 * Check a CiviCRM permission.
	 *
	 * @since 0.3
	 *
	 * @param str $permission The permission string.
	 * @return bool $permitted True if allowed, false otherwise.
	 */
	public function check_permission( $permission ) {

		// Always deny if CiviCRM is not active.
		if ( ! $this->is_active() ) return false;

		// Deny by default.
		$permitted = false;

		// Check CiviCRM permissions.
		if ( CRM_Core_Permission::check( $permission ) ) {
			$permitted = true;
		}

		/**
		 * Return permission but allow overrides.
		 *
		 * @since 0.3.4
		 *
		 * @param bool $permitted True if allowed, false otherwise.
		 * @param str $permission The CiviCRM permission string.
		 * @return bool $permitted True if allowed, false otherwise.
		 */
		return apply_filters( 'civicrm_event_organiser_permitted', $permitted, $permission );

	}



	//##########################################################################



	/**
	 * Create an EO event when a CiviEvent is created.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function event_created( $op, $objectName, $objectId, $objectRef ) {

		// Target our operation.
		if ( $op != 'create' ) return;

		// Target our object type.
		if ( $objectName != 'Event' ) return;

		// Kick out if not event object.
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) return;

		// Update a single EO event - or create if it doesn't exist.
		$event_id = $this->plugin->eo->update_event( (array) $objectRef );

		// Kick out if not event object.
		if ( is_wp_error( $event_id ) ) {

			// Log error.
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'error' => $event_id->get_error_message(),
				'backtrace' => $trace,
			), true ) );

			// Kick out.
			return;

		}

		// Get occurrences.
		$occurrences = eo_get_the_occurrences_of( $event_id );

		// In this context, a CiviEvent can only have an EO event with a
		// single occurrence associated with it, so use first item.
		$keys = array_keys( $occurrences );
		$occurrence_id = array_shift( $keys );

		// Store correspondences.
		$this->plugin->db->store_event_correspondences( $event_id, array( $occurrence_id => $objectRef->id ) );

	}



	/**
	 * Update an EO event when a CiviEvent is updated.
	 *
	 * Only CiviEvents that are in a one-to-one correspondence with an Event
	 * Organiser event can update that Event Organiser event. CiviEvents which
	 * are part of an Event Organiser sequence can be updated, but no data will
	 * be synced across to the Event Organiser event.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function event_updated( $op, $objectName, $objectId, $objectRef ) {

		// Target our operation.
		if ( $op != 'edit' ) return;

		// Target our object type.
		if ( $objectName != 'Event' ) return;

		// Kick out if not event object.
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) return;

		// Bail if this CiviEvent is part of an EO sequence.
		if ( $this->plugin->db->is_civi_event_in_eo_sequence( $objectId ) ) return;

		// Get full event data.
		$updated_event = $this->get_event_by_id( $objectId );

		// Bail if not found.
		if ( $updated_event === false ) return;

		// Update the EO event.
		$event_id = $this->plugin->eo->update_event( $updated_event );

		// Kick out if not event object.
		if ( is_wp_error( $event_id ) ) {

			// Log error first
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'error' => $event_id->get_error_message(),
				'backtrace' => $trace,
			), true ) );

			// Bail
			return;

		}

		// Get occurrences.
		$occurrences = eo_get_the_occurrences_of( $event_id );

		// In this context, a CiviEvent can only have an EO event with a
		// single occurrence associated with it, so use first item.
		$keys = array_keys( $occurrences );
		$occurrence_id = array_shift( $keys );

		// Store correspondences.
		$this->plugin->db->store_event_correspondences( $event_id, array( $occurrence_id => $objectId ) );

	}



	/**
	 * Delete an EO event when a CiviEvent is deleted.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function event_deleted( $op, $objectName, $objectId, $objectRef ) {

		// Target our operation.
		if ( $op != 'delete' ) return;

		// Target our object type.
		if ( $objectName != 'Event' ) return;

		// Kick out if not event object.
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) return;

	}



	//##########################################################################



	/**
	 * Prepare a CiviEvent with data from an EO Event.
	 *
	 * @since 0.1
	 *
	 * @param object $post The WordPress post object.
	 * @return array $civi_event The basic CiviEvent data.
	 */
	public function prepare_civi_event( $post ) {

		// Init CiviEvent array.
		$civi_event = array(
			'version' => 3,
		);

		// Add items that are common to all CiviEvents.
		$civi_event['title'] = $post->post_title;
		$civi_event['description'] = $post->post_content;
		$civi_event['summary'] = strip_tags( $post->post_excerpt );
		$civi_event['created_date'] = $post->post_date;
		$civi_event['is_public'] = 1;
		$civi_event['participant_listing_id'] = NULL;

		// If the event is in draft mode, set as 'inactive'.
		if ( $post->post_status == 'draft' ) {
			$civi_event['is_active'] = 0;
		} else {
			$civi_event['is_active'] = 1;
		}

		// Get venue for this event.
		$venue_id = eo_get_venue( $post->ID );

		// Get CiviEvent location.
		$location_id = $this->plugin->eo_venue->get_civi_location( $venue_id );

		// Did we get one?
		if ( is_numeric( $location_id ) ) {

			// Add to our params.
			$civi_event['loc_block_id'] = $location_id;

			// Set CiviCRM to add map.
			$civi_event['is_map'] = 1;

		}

		// Online registration off by default.
		$civi_event['is_online_registration'] = 0;

		// Get CiviEvent online registration value.
		$is_reg = $this->plugin->eo->get_event_registration( $post->ID );

		// Did we get one?
		if ( is_numeric( $is_reg ) AND $is_reg != 0 ) {

			// Add to our params.
			$civi_event['is_online_registration'] = 1;

		}

		// Participant_role default.
		$civi_event['default_role_id'] = 0;

		// Get existing role ID.
		$existing_id = $this->get_participant_role( $post );

		// Did we get one?
		if ( $existing_id !== false AND is_numeric( $existing_id ) AND $existing_id != 0 ) {

			// Add to our params.
			$civi_event['default_role_id'] = $existing_id;

		}

		// Get event type pseudo-ID (or value), because it is required in CiviCRM.
		$type_value = $this->plugin->taxonomy->get_default_event_type_value( $post );

		// Well?
		if ( $type_value === false ) {

			// Error.
			wp_die( __( 'You must have some CiviCRM event types defined', 'civicrm-event-organiser' ) );

		}

		// Assign event type value.
		$civi_event['event_type_id'] = $type_value;

		/**
		 * Filter prepared CiviEvent.
		 *
		 * @since 0.3.1
		 *
		 * @param array $civi_event The array of data for the CiviEvent.
		 * @param object $post The WP post object.
		 * @return array $civi_event The modified array of data for the CiviEvent.
		 */
		return apply_filters( 'civicrm_event_organiser_prepared_civi_event', $civi_event, $post );

	}



	/**
	 * Create CiviEvents for an EO event.
	 *
	 * @since 0.1
	 *
	 * @param object $post The WP post object.
	 * @param array $dates Array of properly formatted dates.
	 * @param array $civi_event_ids Array of new CiviEvent IDs.
	 * @return array $correspondences Array of correspondences, keyed by occurrence_id.
	 */
	public function create_civi_events( $post, $dates ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Just for safety, check we get some (though we must).
		if ( count( $dates ) === 0 ) return false;

		// Init links.
		$links = array();

		// Init correspondences.
		$correspondences = array();

		// Prepare CiviEvent.
		$civi_event = $this->prepare_civi_event( $post );

		// Now loop through dates and create CiviEvents per date.
		foreach ( $dates AS $date ) {

			// Overwrite dates.
			$civi_event['start_date'] = $date['start'];
			$civi_event['end_date'] = $date['end'];

			// Use API to create event.
			$result = civicrm_api( 'event', 'create', $civi_event );

			// Log failures and skip to next.
			if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {

				// Log error.
				$e = new Exception;
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'message' => $result['error_message'],
					'civi_event' => $civi_event,
					'backtrace' => $trace,
				), true ) );

				continue;

			}

			// Enable registration if selected.
			$this->enable_registration( array_pop( $result['values'] ), $post );

			// Add the new CiviEvent ID to array, keyed by occurrence_id.
			$correspondences[$date['occurrence_id']] = $result['id'];

		} // End dates loop.

		// Store these in post meta.
		$this->plugin->db->store_event_correspondences( $post->ID, $correspondences );

		// --<
		return $correspondences;

	}



	/**
	 * Update CiviEvents for an event.
	 *
	 * @since 0.1
	 *
	 * @param object $post The WP post object.
	 * @param array $dates Array of properly formatted dates.
	 * @return array $correspondences Array of correspondences, keyed by occurrence_id.
	 */
	public function update_civi_events( $post, $dates ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Just for safety, check we get some (though we must).
		if ( count( $dates ) === 0 ) return false;

		// Get existing CiviEvents from post meta.
		$correspondences = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post->ID );

		// If we have none yet.
		if ( count( $correspondences ) === 0 ) {

			// Create them.
			$correspondences = $this->create_civi_events( $post, $dates );

			// --<
			return $correspondences;

		}

		/*
		 * The logic for updating is as follows:
		 *
		 * Event sequences can only be generated from EO, so any CiviEvents that
		 * are part of a sequence must have been generated automatically.
		 *
		 * Since CiviEvents will only be generated when the "Create CiviEvents"
		 * checkbox is ticked (and only those with 'publish_posts' caps can see
		 * the checkbox) we assume that this is the definitive set of events.
		 *
		 * Any further changes work thusly:
		 *
		 * We already have the correspondence array, so retrieve the CiviEvents.
		 * The correspondence array is already sorted by start date, so the
		 * CiviEvents will be too.
		 *
		 * If the length of the two event arrays is identical, we assume the
		 * sequences correspond and update the CiviEvents with the details of
		 * the EO events.
		 *
		 * Next, we match by date and time. Any CiviEvents that match have their
		 * info updated since we assume their correspondence remains unaltered.
		 *
		 * Any additions to the EO event are treated as new CiviEvents and are
		 * added to CiviCRM. Any removals are treated as if the event has been
		 * cancelled and the CiviEvent is set to 'disabled' rather than deleted.
		 * This is to preserve any data that may have been collected for the
		 * removed event.
		 *
		 * The bottom line is: make sure your sequences are right before hitting
		 * the Publish button and be wary of making further changes.
		 *
		 * Things get a bit more complicated when a sequence is split, but it's
		 * not too bad. This functionality will eventually be handled by the EO
		 * 'occurrence' hooks when I get round to it.
		 *
		 * Also, note the inline comment discussing what to do with CiviEvents
		 * that have been "orphaned" from the sequence. The current need is to
		 * retain the CiviEvent, since there may be associated data.
		 */

		// Start with new correspondence array.
		$new_correspondences = array();

		// Sort existing correspondences by key, which will always be chronological.
		ksort( $correspondences );

		// Prepare CiviEvent.
		$civi_event = $this->prepare_civi_event( $post );

		// ---------------------------------------------------------------------
		// When arrays are equal in length
		// ---------------------------------------------------------------------

		// Do the arrays have the same length?
		if ( count( $dates ) === count( $correspondences ) ) {

			// Let's assume that the intention is simply to update the CiviEvents
			// and that each date corresponds to the sequential CiviEvent.

			// Loop through dates.
			foreach ( $dates AS $date ) {

				// Set ID, triggering update.
				$civi_event['id'] = array_shift( $correspondences );

				// Overwrite dates.
				$civi_event['start_date'] = $date['start'];
				$civi_event['end_date'] = $date['end'];

				// Use API to create event.
				$result = civicrm_api( 'event', 'create', $civi_event );

				// Log failures and skip to next.
				if ( $result['is_error'] == '1' ) {

					// Log error.
					$e = new Exception;
					$trace = $e->getTraceAsString();
					error_log( print_r( array(
						'method' => __METHOD__,
						'message' => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace' => $trace,
					), true ) );

					continue;

				}

				// Enable registration if selected.
				$this->enable_registration( array_pop( $result['values'] ), $post );

				// Add the CiviEvent ID to array, keyed by occurrence_id.
				$new_correspondences[$date['occurrence_id']] = $result['id'];

			}

			// Overwrite those stored in post meta.
			$this->plugin->db->store_event_correspondences( $post->ID, $new_correspondences );

			// --<
			return $new_correspondences;

		}

		// ---------------------------------------------------------------------
		// When arrays are NOT equal in length, we MUST have correspondences
		// ---------------------------------------------------------------------

		// Init CiviCRM events array.
		$civi_events = array();

		//  get CiviEvents by ID.
		foreach ( $correspondences AS $occurrence_id => $civi_event_id ) {

			// Get full CiviEvent.
			$full_civi_event = $this->get_event_by_id( $civi_event_id );

			// Continue if not found.
			if ( $full_civi_event === false ) continue;

			// Add CiviEvent to array.
			$civi_events[] = $full_civi_event;

		}

		// Init orphaned CiviEvent data.
		$orphaned_civi_events = array();

		// Get orphaned CiviEvents for this EO event.
		$orphaned = $this->plugin->db->get_orphaned_events_by_eo_event_id( $post->ID );

		// Did we get any?
		if ( count( $orphaned ) > 0 ) {

			//  get CiviEvents by ID.
			foreach ( $orphaned AS $civi_event_id ) {

				// Get full CiviEvent.
				$orphaned_civi_event = $this->get_event_by_id( $civi_event_id );

				// Continue if not found.
				if ( $orphaned_civi_event === false ) continue;

				// Add CiviEvent to array.
				$orphaned_civi_events[] = $orphaned_civi_event;

			}

		}

		// Get matches between EO events and CiviEvents.
		$matches = $this->get_event_matches( $dates, $civi_events, $orphaned_civi_events );

		// Amend the orphans array, removing on what has been "unorphaned".
		$orphans = array_diff( $orphaned, $matches['unorphaned'] );

		// Extract matched array.
		$matched = $matches['matched'];

		// Do we have any matched?
		if ( count( $matched ) > 0 ) {

			// Loop through matched dates and update CiviEvents.
			foreach ( $matched AS $occurrence_id => $civi_id ) {

				// Assign ID so we perform an update.
				$civi_event['id'] = $civi_id;

				// Use API to update event.
				$result = civicrm_api( 'event', 'create', $civi_event );

				// Log failures and skip to next.
				if ( $result['is_error'] == '1' ) {

					// Log error.
					$e = new Exception;
					$trace = $e->getTraceAsString();
					error_log( print_r( array(
						'method' => __METHOD__,
						'message' => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace' => $trace,
					), true ) );

					continue;

				}

				// Enable registration if selected.
				$this->enable_registration( array_pop( $result['values'] ), $post );

				// Add to new correspondence array.
				$new_correspondences[$occurrence_id] = $civi_id;

			}

		} // End check for empty array.

		// Extract unmatched EO events array.
		$unmatched_eo = $matches['unmatched_eo'];

		// Do we have any unmatched EO occurrences?
		if ( count( $unmatched_eo ) > 0 ) {

			// Now loop through unmatched EO dates and create CiviEvents.
			foreach ( $unmatched_eo AS $eo_date ) {

				// Make sure there's no ID.
				unset( $civi_event['id'] );

				// Overwrite dates.
				$civi_event['start_date'] = $eo_date['start'];
				$civi_event['end_date'] = $eo_date['end'];

				// Use API to create event.
				$result = civicrm_api( 'event', 'create', $civi_event );

				// Log failures and skip to next.
				if ( $result['is_error'] == '1' ) {

					// Log failures and skip to next.
					$e = new Exception;
					$trace = $e->getTraceAsString();
					error_log( print_r( array(
						'method' => __METHOD__,
						'message' => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace' => $trace,
					), true ) );

					continue;

				}

				// Enable registration if selected.
				$this->enable_registration( array_pop( $result['values'] ), $post );

				// Add the CiviEvent ID to array, keyed by occurrence_id.
				$new_correspondences[$eo_date['occurrence_id']] = $result['id'];

			}

		} // End check for empty array.

		// Extract unmatched CiviEvents array.
		$unmatched_civi = $matches['unmatched_civi'];

		// Do we have any unmatched CiviEvents?
		if ( count( $unmatched_civi ) > 0 ) {

			// Assume we're not deleting extra CiviEvents.
			$unmatched_delete = false;

			// Get "delete unused" checkbox value.
			if (
				isset( $_POST['civi_eo_event_delete_unused'] ) AND
				absint( $_POST['civi_eo_event_delete_unused'] ) === 1
			) {

				// Override - we ARE deleting.
				$unmatched_delete = true;

			}

			// Loop through unmatched CiviEvents.
			foreach ( $unmatched_civi AS $civi_id ) {

				// If deleting.
				if ( $unmatched_delete ) {

					// Delete CiviEvent.
					$result = $this->delete_civi_events( array( $civi_id ) );

					// Delete this ID from the orphans array?
					//$orphans = array_diff( $orphans, array( $civi_id ) );

				} else {

					// Set CiviEvent to disabled.
					$result = $this->disable_civi_event( $civi_id );

					// Add to orphans array.
					$orphans[] = $civi_id;

				}

			}

		} // End check for empty array.

		// Store new correspondences and orphans.
		$this->plugin->db->store_event_correspondences( $post->ID, $new_correspondences, $orphans );

	}



	/**
	 * Match EO Events and CiviEvents.
	 *
	 * @since 0.1
	 *
	 * @param array $dates An array of EO event occurrence data.
	 * @param array $civi_events An array of CiviEvent data.
	 * @param array $orphaned_civi_events An array of orphaned CiviEvent data.
	 * @return array $event_data A nested array of matched and unmatched events.
	 */
	public function get_event_matches( $dates, $civi_events, $orphaned_civi_events ) {

		// Init return array.
		$event_data = array(
			'matched' => array(),
			'unmatched_eo' => array(),
			'unmatched_civi' => array(),
			'unorphaned' => array(),
		);

		// Init matched.
		$matched = array();

		// Match EO dates to CiviEvents.
		foreach ( $dates AS $key => $date ) {

			// Run through CiviEvents.
			foreach( $civi_events AS $civi_event ) {

				// Does the start_date match?
				if ( $date['start'] == $civi_event['start_date'] ) {

					// Add to matched array.
					$matched[$date['occurrence_id']] = $civi_event['id'];

					// Found - break this loop.
					break;

				}

			}

		}

		// Init unorphaned.
		$unorphaned = array();

		// Check orphaned array.
		if ( count( $orphaned_civi_events ) > 0 ) {

			// Match EO dates to orphaned CiviEvents.
			foreach ( $dates AS $key => $date ) {

				// Run through orphaned CiviEvents.
				foreach( $orphaned_civi_events AS $orphaned_civi_event ) {

					// Does the start_date match?
					if ( $date['start'] == $orphaned_civi_event['start_date'] ) {

						// Add to matched array.
						$matched[$date['occurrence_id']] = $orphaned_civi_event['id'];

						// Add to "unorphaned" array.
						$unorphaned[] = $orphaned_civi_event['id'];

						// Found - break this loop.
						break;

					}

				}

			}

		}

		// Init EO unmatched.
		$unmatched_eo = array();

		// Find unmatched EO dates.
		foreach ( $dates AS $key => $date ) {

			// If the matched array has no entry.
			if ( ! isset( $matched[$date['occurrence_id']] ) ) {

				// Add to unmatched.
				$unmatched_eo[] = $date;

			}

		}

		// Init CiviCRM unmatched.
		$unmatched_civi = array();

		// Find unmatched EO dates.
		foreach( $civi_events AS $civi_event ) {

			// Does the matched array have an entry?
			if ( ! in_array( $civi_event['id'], $matched ) ) {

				// Add to unmatched.
				$unmatched_civi[] = $civi_event['id'];

			}

		}

		// Sort matched by key.
		ksort( $matched );

		// Construct return array.
		$event_data['matched'] = $matched;
		$event_data['unmatched_eo'] = $unmatched_eo;
		$event_data['unmatched_civi'] = $unmatched_civi;
		$event_data['unorphaned'] = $unorphaned;

		// --<
		return $event_data;

	}



	/**
	 * Get all CiviEvents.
	 *
	 * @since 0.1
	 *
	 * @return array $events The CiviEvents data.
	 */
	public function get_all_civi_events() {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Construct events array.
		$params = array(
			'version' => 3,
			'is_template' => 0,
			// Define stupidly high limit, because API defaults to 25.
			// TODO: we can set limit = 0.
			'options' => array(
				'limit' => '10000',
			),
		);

		// Call API.
		$events = civicrm_api( 'event', 'get', $params );

		// Log failures and return boolean false.
		if ( $events['is_error'] == '1' ) {

			// Log error.
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $events['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// --<
		return $events;

	}



	/**
	 * Delete all CiviEvents.
	 *
	 * WARNING: only for dev purposes really!
	 *
	 * @since 0.1
	 *
	 * @param array $civi_event_ids An array of CiviEvent IDs.
	 * @return array $results An array of CiviCRM results.
	 */
	public function delete_civi_events( $civi_event_ids ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Just for safety, check we get some.
		if ( count( $civi_event_ids ) == 0 ) return false;

		// Init return.
		$results = array();

		// One by one, it seems.
		foreach( $civi_event_ids AS $civi_event_id ) {

			// Construct "query".
			$params = array(
				'version' => 3,
				'id' => $civi_event_id,
			);

			// Okay, let's do it.
			$result = civicrm_api( 'event', 'delete', $params );

			// Log failures and skip to next.
			if ( $result['is_error'] == '1' ) {

				// Log failures and skip to next.
				$e = new Exception;
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'message' => $result['error_message'],
					'params' => $params,
					'backtrace' => $trace,
				), true ) );

				continue;

			}

			// Add to return array.
			$results[] = $result;

		}

		// --<
		return $results;

	}



	/**
	 * Disable a CiviEvent.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_event_id The numeric ID of the CiviEvent.
	 * @return array $result A CiviCRM result array.
	 */
	public function disable_civi_event( $civi_event_id ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Init event array.
		$civi_event = array(
			'version' => 3,
		);

		// Assign ID so we perform an update.
		$civi_event['id'] = $civi_event_id;

		// Set "disabled" flag - see below.
		$civi_event['is_active'] = 0;

		// Use API to update event.
		$result = civicrm_api( 'event', 'create', $civi_event );

		// Log failures and return boolean false.
		if ( $result['is_error'] == '1' ) {

			// Log error.
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $result['error_message'],
				'civi_event' => $civi_event,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// --<
		return $result;

	}



	/**
	 * Get a CiviEvent by ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_event_id The numeric ID of the CiviEvent.
	 * @return array|bool $event The CiviEvent location data, or false if not found.
	 */
	public function get_event_by_id( $civi_event_id ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Construct locations array.
		$params = array(
			'version' => 3,
			'id' => $civi_event_id,
		);

		// Call API.
		$event = civicrm_api( 'event', 'getsingle', $params );

		// Log failures and return boolean false.
		if ( isset( $event['is_error'] ) AND $event['is_error'] == '1' ) {

			// Log error.
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $event['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// --<
		return $event;

	}



	/**
	 * Get a CiviEvent's "Info & Settings" link.
	 *
	 * @since 0.3.6
	 *
	 * @param int $civi_event_id The numeric ID of the CiviEvent.
	 * @return string $link The URL of the CiviCRM "Info & Settings" page.
	 */
	public function get_settings_link( $civi_event_id ) {

		// Init link.
		$link = '';

		// Init CiviCRM or bail.
		if ( ! $this->is_active() ) return $link;

		// Use CiviCRM to construct link.
		$link = CRM_Utils_System::url(
			'civicrm/event/manage/settings',
			'reset=1&action=update&id=' . $civi_event_id,
			TRUE,
			NULL,
			FALSE,
			FALSE,
			TRUE
		);

		// --<
		return $link;

	}



	//##########################################################################



	/**
	 * Validate all CiviEvent data for an Event Organiser event.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @param object $post The WP post object.
	 * @return mixed True if success, otherwise WP error object.
	 */
	public function validate_civi_options( $post_id, $post ) {

		// Disabled.
		return true;

		// Check default event type.
		$result = $this->_validate_event_type();
		if ( is_wp_error( $result ) ) return $result;

		// Check participant_role.
		$result = $this->_validate_participant_role();
		if ( is_wp_error( $result ) ) return $result;

		// Check is_online_registration.
		$result = $this->_validate_is_online_registration();
		if ( is_wp_error( $result ) ) return $result;

		// Check loc_block_id.
		$result = $this->_validate_loc_block_id();
		if ( is_wp_error( $result ) ) return $result;

	}



	/**
	 * Updates a CiviEvent Location given an EO venue.
	 *
	 * @since 0.1
	 *
	 * @param array $venue The EO venue data.
	 * @param array $location The CiviEvent location data.
	 */
	public function update_location( $venue ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Get existing location.
		$location = $this->get_location( $venue );

		// If this venue already has a CiviEvent location.
		if ( $location !== false ) {

			// Is there a record on the EO side?
			if ( ! isset( $venue->venue_civi_id ) ) {

				// Use the result and fake the property.
				$venue->venue_civi_id = $location['id'];

			}

		} else {

			// Make sure the property is not set.
			$venue->venue_civi_id = 0;

		}

		// Update existing - or create one if it doesn't exist.
		$location = $this->create_civi_loc_block( $venue, $location );

		// --<
		return $location;

	}



	/**
	 * Delete a CiviEvent Location given an EO venue.
	 *
	 * @since 0.1
	 *
	 * @param array $venue The EO venue data.
	 * @return array $result CiviCRM API result data.
	 */
	public function delete_location( $venue ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Init return.
		$result = false;

		// Get existing location.
		$location = $this->get_location( $venue );

		// Did we do okay?
		if ( $location !== false ) {

			// Delete.
			$result = $this->delete_location_by_id( $location['id'] );

		}

		// --<
		return $result;

	}



	/**
	 * Delete a CiviEvent Location given a Location ID.
	 *
	 * Be aware that only the CiviCRM loc_block is deleted - not the items that
	 * constitute it. Email, phone and address will still exist but not be
	 * associated as a loc_block.
	 *
	 * The next iteration of this plugin should probably refine the loc_block
	 * sync process to take this into account.
	 *
	 * @since 0.1
	 *
	 * @param int $location_id The numeric ID of the CiviCRM location.
	 * @return array $result CiviCRM API result data.
	 */
	public function delete_location_by_id( $location_id ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Construct delete array.
		$params = array(
			'version' => 3,
			'id' => $location_id,
		);

		// Delete via API.
		$result = civicrm_api( 'loc_block', 'delete', $params );

		// Log failure and return boolean false.
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
	 * Gets a CiviEvent Location given an EO venue.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The EO venue data.
	 * @return bool|array $location The CiviEvent location data, or false if not found.
	 */
	public function get_location( $venue ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// ---------------------------------------------------------------------
		// Try by sync ID
		// ---------------------------------------------------------------------

		// Init a empty.
		$civi_id = 0;

		// If sync ID is present.
		if (
			isset( $venue->venue_civi_id )
			AND
			is_numeric( $venue->venue_civi_id )
			AND
			$venue->venue_civi_id > 0
		) {

			// Use it.
			$civi_id = $venue->venue_civi_id;

		}

		// Construct get-by-id array.
		$params = array(
			'version' => 3,
			'id' => $civi_id,
			'return' => 'all',
		);

		// Call API.
		$location = civicrm_api( 'loc_block', 'get', $params );

		// Log failure and return boolean false.
		if ( $location['is_error'] == '1' ) {

			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => __( 'Could not get CiviCRM Location by ID', 'civicrm-event-organiser' ),
				'civicrm' => $location['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// Return the result if we get one.
		if ( absint( $location['count'] ) > 0 AND is_array( $location['values'] ) ) {

			// Found by ID.
			return array_shift( $location['values'] );

		}

		// ---------------------------------------------------------------------
		// Now try by location
		// ---------------------------------------------------------------------

		/*
		// If we have a location.
		if ( ! empty( $venue->venue_lat ) AND ! empty( $venue->venue_lng ) ) {

			// Construct get-by-geolocation array.
			$params = array(
				'version' => 3,
				'address' => array(
					'geo_code_1' => $venue->venue_lat,
					'geo_code_2' => $venue->venue_lng,
				),
				'return' => 'all',
			);

			// Call API.
			$location = civicrm_api( 'loc_block', 'get', $params );

			// Log error and return boolean false.
			if ( isset( $location['is_error'] ) AND $location['is_error'] == '1' ) {

				// Log error.
				$e = new Exception;
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'message' => __( 'Could not get CiviCRM Location by Lat/Long', 'civicrm-event-organiser' ),
					'civicrm' => $location['error_message'],
					'params' => $params,
					'backtrace' => $trace,
				), true ) );

				// --<
				return false;

			}

			// Return the result if we get one.
			if ( absint( $location['count'] ) > 0 AND is_array( $location['values'] ) ) {

				$e = new Exception;
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'procedure' => 'found by location',
					'venue' => $venue,
					'params' => $params,
					'location' => $location,
					'backtrace' => $trace,
				), true ) );

				// Found by location.
				return array_shift( $location['values'] );

			}

		}
		*/

		// Fallback.
		return false;

	}



	/**
	 * Get all CiviEvent Locations.
	 *
	 * @since 0.1
	 *
	 * @return array $locations The array of CiviEvent location data.
	 */
	public function get_all_locations() {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Construct locations array.
		$params = array(

			// API v3 please.
			'version' => 3,

			// Return all data.
			'return' => 'all',

			// Define stupidly high limit, because API defaults to 25
			// TODO: use limit = 0.
			'options' => array(
				'limit' => '10000',
			),

		);

		// Call API.
		$locations = civicrm_api( 'loc_block', 'get', $params );

		// Log failure and return boolean false.
		if ( $locations['is_error'] == '1' ) {

			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $locations['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// --<
		return $locations;

	}



	/**
	 * WARNING: deletes all CiviEvent Locations.
	 *
	 * @since 0.1
	 */
	public function delete_all_locations() {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Get all locations.
		$locations = $this->get_all_locations();

		// Start again.
		foreach( $locations['values'] AS $location ) {

			// Construct delete array.
			$params = array(
				'version' => 3,
				'id' => $location['id'],
			);

			// Delete via API.
			$result = civicrm_api( 'loc_block', 'delete', $params );

		}

	}



	/**
	 * Gets a CiviEvent Location given an CiviEvent Location ID.
	 *
	 * @since 0.1
	 *
	 * @param int $loc_id The CiviEvent Location ID.
	 * @return array $location The CiviEvent Location data.
	 */
	public function get_location_by_id( $loc_id ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// Construct get-by-id array.
		$params = array(
			'version' => 3,
			'id' => $loc_id,
			'return' => 'all',
			// get country and state name
			'api.Address.getsingle' => ['sequential' => 1, 'id' => "\$value.address_id", 'return' => ["country_id.name", "state_province_id.name"]],
		);

		// Call API ('get' returns an array keyed by the item).
		$result = civicrm_api( 'loc_block', 'get', $params );

		// Log failure and return boolean false.
		if ( $result['is_error'] == '1' || $result['count'] != 1 ) {

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

		// Get location from nested array.
		$location = array_shift( $result['values'] );

		// --<
		return $location;

	}



	/**
	 * Creates (or updates) a CiviEvent Location given an EO venue.
	 *
	 * The only disadvantage to this method is that, for example, if we update
	 * the email and that email already exists in the DB, it will not be found
	 * and associated - but rather the existing email will be updated. Same goes
	 * for phone. This is not a deal-breaker, but not very DRY either.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The EO venue object.
	 * @param array $location The existing CiviCRM location data.
	 * @return array $location The CiviCRM location data.
	 */
	public function create_civi_loc_block( $venue, $location ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return array();

		// Init create/update flag.
		$op = 'create';

		// Update if our venue already has a location.
		if (
			isset( $venue->venue_civi_id ) AND
			is_numeric( $venue->venue_civi_id ) AND
			$venue->venue_civi_id > 0
		) {
			$op = 'update';
		}

		// Define initial params array.
		$params = array(
			'version' => 3,
		);

		/*
		 * First, see if the loc_block email, phone and address already exist.
		 *
		 * If they don't, we need params returned that trigger their creation on
		 * the CiviCRM side. If they do, then we may need to update or delete them
		 * before we include the data in the 'civicrm_api' call.
		 */

		// If we have an email.
		if ( isset( $venue->venue_civi_email ) AND ! empty( $venue->venue_civi_email ) ) {

			// Check email.
			$email = $this->maybe_update_email( $venue, $location, $op );

			// If we get a new email.
			if ( is_array( $email ) ) {

				// Add to params.
				$params['email'] = $email;

			} else {

				// Add existing ID to params.
				$params['email_id'] = $email;

			}

		}

		// If we have a phone number.
		if ( isset( $venue->venue_civi_phone ) AND ! empty( $venue->venue_civi_phone ) ) {

			// Check phone.
			$phone = $this->maybe_update_phone( $venue, $location, $op );

			// If we get a new phone.
			if ( is_array( $phone ) ) {

				// Add to params.
				$params['phone'] = $phone;

			} else {

				// Add existing ID to params.
				$params['phone_id'] = $phone;

			}

		}

		// Check address.
		$address = $this->maybe_update_address( $venue, $location, $op );

		// If we get a new address.
		if ( is_array( $address ) ) {

			// Add to params.
			$params['address'] = $address;

		} else {

			// Add existing ID to params.
			$params['address_id'] = $address;

		}

		// If our venue has a location, add it.
		if ( $op == 'update' ) {

			// Target our known location - this will trigger an update.
			$params['id'] = $venue->venue_civi_id;

		}

		// Call API.
		$location = civicrm_api( 'loc_block', 'create', $params );

		// Did we do okay?
		if ( isset( $location['is_error'] ) AND $location['is_error'] == '1' ) {

			// Log failed location.
			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $location['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			), true ) );

			// --<
			return false;

		}

		// We now need to create a dummy CiviEvent, or this venue will not show
		// up in CiviCRM...
		//$this->create_dummy_event( $location );

		// --<
		return $location;

	}



	//##########################################################################



	/**
	 * Get the existing participant role for a post, but fall back to the default
	 * as set on the admin screen. Fall back to false otherwise.
	 *
	 * @since 0.1
	 *
	 * @param object $post An EO event object.
	 * @return mixed $existing_id The numeric ID of the role, false if none exists.
	 */
	public function get_participant_role( $post = null ) {

		// Init with impossible ID.
		$existing_id = false;

		// Do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_role' );

		// Did we get one?
		if ( $default !== '' AND is_numeric( $default ) ) {

			// Override with default value.
			$existing_id = absint( $default );

		}

		// If we have a post.
		if ( isset( $post ) AND is_object( $post ) ) {

			// Get stored value.
			$stored_id = $this->plugin->eo->get_event_role( $post->ID );

			// Did we get one?
			if ( $stored_id !== '' AND is_numeric( $stored_id ) AND $stored_id > 0 ) {

				// Override with stored value.
				$existing_id = absint( $stored_id );

			}

		}

		// --<
		return $existing_id;

	}



	/**
	 * Get all participant roles.
	 *
	 * @since 0.1
	 *
	 * @param object $post An EO event object.
	 * @return array|bool $participant_roles Array of CiviCRM role data, or false if none exist.
	 */
	public function get_participant_roles( $post = null ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return false;

		// First, get participant_role option_group ID.
		$opt_group = array(
			'version' =>'3',
			'name' =>'participant_role'
		);
		$participant_role = civicrm_api( 'OptionGroup', 'getsingle', $opt_group );

		// Next, get option_values for that group.
		$opt_values = array(
			'version' =>'3',
			'is_active' => 1,
			'option_group_id' => $participant_role['id'],
			'options' => array(
				'sort' => 'weight ASC',
			),
		);
		$participant_roles = civicrm_api( 'OptionValue', 'get', $opt_values );

		// Did we get any?
		if ( $participant_roles['is_error'] == '0' AND count( $participant_roles['values'] ) > 0 ) {

			// --<
			return $participant_roles;

		}

		// --<
		return false;

	}



	/**
	 * Builds a form element for Participant Roles.
	 *
	 * @since 0.1
	 *
	 * @param object $post An EO event object.
	 * @return str $html Markup to display in the form.
	 */
	public function get_participant_roles_select( $post = null ) {

		// Init html.
		$html = '';

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) return $html;

		// First, get all participant_roles.
		$all_roles = $this->get_participant_roles();

		// Did we get any?
		if ( $all_roles['is_error'] == '0' AND count( $all_roles['values'] ) > 0 ) {

			// Get the values array.
			$roles = $all_roles['values'];

			// Init options.
			$options = array();

			// Get existing role ID.
			$existing_id = $this->get_participant_role( $post );

			// Loop.
			foreach( $roles AS $key => $role ) {

				// Get role.
				$role_id = absint( $role['value'] );

				// Init selected.
				$selected = '';

				// Is this value the same as in the post?
				if ( $existing_id === $role_id ) {

					// Override selected.
					$selected = ' selected="selected"';

				}

				// Construct option.
				$options[] = '<option value="' . $role_id . '"' . $selected . '>' . esc_html( $role['label'] ) . '</option>';

			}

			// Create html.
			$html = implode( "\n", $options );

		}

		// Return.
		return $html;

	}



	//##########################################################################



	/**
	 * Checks the status of a CiviEvent's Registration option.
	 *
	 * @since 0.1
	 *
	 * @param object $post The WP event object.
	 * @return str $default Checkbox checked or not.
	 */
	public function get_registration( $post ) {

		// Checkbox unticked by default.
		$default = '';

		// Sanity check.
		if ( ! is_object( $post ) ) return $default;

		// Get CiviEvents for this EO event.
		$civi_events = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post->ID );

		// Did we get any?
		if ( is_array( $civi_events ) AND count( $civi_events ) > 0 ) {

			// Get the first CiviEvent, though any would do as they all have the same value.
			$civi_event = $this->get_event_by_id( array_shift( $civi_events ) );

			// Did we do okay?
			if ( $civi_event !== false AND $civi_event['is_error'] == '0' AND $civi_event['is_online_registration'] == '1' ) {

				// Set checkbox to ticked.
				$default = ' checked="checked"';

			}

		}

		// --<
		return $default;

	}



	/**
	 * Get a CiviEvent's Registration link.
	 *
	 * @since 0.2.2
	 *
	 * @param array $civi_event An array of data for the CiviEvent.
	 * @return str $link The URL of the CiviCRM Registration page.
	 */
	public function get_registration_link( $civi_event ) {

		// Init link.
		$link = '';

		// If this event has registration enabled.
		if ( isset( $civi_event['is_online_registration'] ) AND $civi_event['is_online_registration'] == '1' ) {

			// Init CiviCRM or bail.
			if ( ! $this->is_active() ) return $link;

			// Use CiviCRM to construct link.
			$link = CRM_Utils_System::url(
				'civicrm/event/register', 'reset=1&id=' . $civi_event['id'],
				TRUE,
				NULL,
				FALSE,
				TRUE
			);

		}

		// --<
		return $link;

	}



	/**
	 * Check if Registration is closed for a given CiviEvent.
	 *
	 * How this works in CiviCRM is as follows: if a CiviEvent has "Registration
	 * Start Date" and "Registration End Date" set, then registration is open
	 * if now() is between those two datetimes. There is a special case to check
	 * for - when an event has ended but "Registration End Date" is specifically
	 * set to allow registration after the event has ended.
	 *
	 * @see CRM_Event_BAO_Event::validRegistrationDate()
	 *
	 * @since 0.3.4
	 *
	 * @param array $civi_event The array of data that represents a CiviEvent.
	 * @return bool $closed True if registration is closed, false otherwise.
	 */
	public function is_registration_closed( $civi_event ) {

		// Bail if online registration is not enabled.
		if ( ! isset( $civi_event['is_online_registration'] ) ) return true;
		if ( $civi_event['is_online_registration'] != 1 ) return true;

		// Gotta have a reference to now.
		$now = new DateTime( 'now', eo_get_blog_timezone() );

		// Init registration start.
		$reg_start = false;

		// Override with registration start date if set.
		if ( ! empty( $civi_event['registration_start_date'] ) ) {
			$reg_start = new DateTime( $civi_event['registration_start_date'], eo_get_blog_timezone() );
		}

		/**
		 * Filter the registration start date.
		 *
		 * @since 0.4
		 *
		 * @param obj $reg_start The starting DateTime object for registration.
		 * @param array $civi_event The array of data that represents a CiviEvent.
		 * @return obj $reg_start The modified starting DateTime object for registration.
		 */
		$reg_start = apply_filters( 'civicrm_event_organiser_registration_start_date', $reg_start, $civi_event );

		// Init registration end.
		$reg_end = false;

		// Override with registration end date if set.
		if ( ! empty( $civi_event['registration_end_date'] ) ) {
			$reg_end = new DateTime( $civi_event['registration_end_date'], eo_get_blog_timezone() );
		}

		/**
		 * Filter the registration end date.
		 *
		 * @since 0.4.2
		 *
		 * @param obj $reg_end The ending DateTime object for registration.
		 * @param array $civi_event The array of data that represents a CiviEvent.
		 * @return obj $reg_end The modified ending DateTime object for registration.
		 */
		$reg_end = apply_filters( 'civicrm_event_organiser_registration_end_date', $reg_end, $civi_event );

		// Init event end.
		$event_end = false;

		// Override with event end date if set.
		if ( ! empty( $civi_event['end_date'] ) ) {
			$event_end = new DateTime( $civi_event['end_date'], eo_get_blog_timezone() );
		}

		// Assume open.
		$open = true;

		// Check if started yet.
		if ( $reg_start AND $reg_start >= $now ) {
			$open = false;

		// Check if already ended.
		} elseif ( $reg_end AND $reg_end < $now ) {
			$open = false;

		// If the event has ended, registration may still be specifically open.
		} elseif ( $event_end AND $event_end < $now AND $reg_end === false ) {
			$open = false;

		}

		// Flip for appropriate value.
		$closed = ! $open;

		// --<
		return $closed;

	}



	/**
	 * Enable a CiviEvent's registration form.
	 *
	 * Just setting the 'is_online_registration' flag on an event is not enough
	 * to generate a valid Online Registration form in CiviCRM. There also needs
	 * to be a default "UF Group" associated with the event - for example the
	 * one that is supplied with a fresh installation of CiviCRM - it's called
	 * "Your Registration Info". This always seems to have ID = 12 but since it
	 * can be deleted that cannot be relied upon.
	 *
	 * We are only dealing with the profile included at the top of the page, so
	 * need to specify `weight = 1` to save that profile.
	 *
	 * @since 0.2.4
	 *
	 * @param array $civi_event An array of data representing a CiviEvent.
	 * @param object $post The WP post object.
	 */
	public function enable_registration( $civi_event, $post = null ) {

		// Does this event have online registration?
		if ( $civi_event['is_online_registration'] == 1 ) {

			// Get specified registration profile.
			$profile_id = $this->get_registration_profile( $post );

			// Construct profile params.
			$params = array(
				'version' => 3,
				'module' => 'CiviEvent',
				'entity_table' => 'civicrm_event',
				'entity_id' => $civi_event['id'],
				'uf_group_id' => $profile_id,
				'is_active' => 1,
				'weight' => 1,
				'sequential' => 1,
			);

			// Trigger update if this event already has a registration profile.
			$existing_profile = $this->has_registration_profile( $civi_event );
			if ( $existing_profile !== false ) {
				$params['id'] = $existing_profile['id'];
			}

			// Call API.
			$result = civicrm_api( 'uf_join', 'create', $params );

			// Test for errors.
			if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {

				// Log error.
				$e = new Exception;
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'message' => $result['error_message'],
					'civi_event' => $civi_event,
					'params' => $params,
					'backtrace' => $trace,
				), true ) );

			}

		}

	}



	/**
	 * Check if a CiviEvent has a registration form profile set.
	 *
	 * We are only dealing with the profile included at the top of the page, so
	 * need to specify `weight = 1` to retrieve just that profile.
	 *
	 * We also need to specify the "module" - because CiviEvent can specify an
	 * additional module called "CiviEvent_Additional" which refers to Profiles
	 * used for (surprise, surprise) registrations for additional people. At the
	 * moment, this plugin does not handle profiles used when "Register multiple
	 * participants" is enabled.
	 *
	 * @since 0.2.4
	 *
	 * @param array $civi_event An array of data representing a CiviEvent.
	 * @return array|bool $result The profile data if the CiviEvent has one, false otherwise.
	 */
	public function has_registration_profile( $civi_event ) {

		// Define query params.
		$params = array(
			'version' => 3,
			'entity_table' => 'civicrm_event',
			'module' => 'CiviEvent',
			'entity_id' => $civi_event['id'],
			'weight' => 1,
			'sequential' => 1,
		);

		// Query via API.
		$result = civicrm_api( 'uf_join', 'getsingle', $params );

		// Return false if we get an error.
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {
			return false;
		}

		// Return false if the event has no profile.
		if ( isset( $result['count'] ) AND $result['count'] == '0' ) {
			return false;
		}

		// --<
		return $result;

	}



	/**
	 * Get the default registration form profile for an EO event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.2.4
	 *
	 * @param object $post An EO event object.
	 * @return int|bool $profile_id The default registration form profile ID, false on failure.
	 */
	public function get_registration_profile( $post = null ) {

		// Init with impossible ID.
		$profile_id = false;

		// Do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_profile' );

		// Override with default value if we have one.
		if ( $default !== '' AND is_numeric( $default ) ) {
			$profile_id = absint( $default );
		}

		// If we have a post.
		if ( isset( $post ) AND is_object( $post ) ) {

			// Get stored value.
			$stored_id = $this->plugin->eo->get_event_registration_profile( $post->ID );

			// Did we get one?
			if ( $stored_id !== '' AND is_numeric( $stored_id ) AND $stored_id > 0 ) {

				// Override with stored value.
				$profile_id = absint( $stored_id );

			}

		}

		// --<
		return $profile_id;

	}



	/**
	 * Get all CiviEvent registration form profiles.
	 *
	 * @since 0.2.4
	 *
	 * @return array|bool $result CiviCRM API return array, or false on failure.
	 */
	public function get_registration_profiles() {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->is_active() ) return false;

		// Define params.
		$params = array(
			'version' => 3,
		);

		// Get them via API.
		$result = civicrm_api( 'uf_group', 'get', $params );

		// Error check.
		if ( isset( $result['is_error'] ) AND $result['is_error'] == '1' ) {

			$e = new Exception;
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => $result['error_message'],
				'result' => $result,
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
	 * Get all CiviEvent registration form profiles formatted as a dropdown list.
	 *
	 * @since 0.2.4
	 *
	 * @param object $post An EO event object.
	 * @return str $html Markup containing select options.
	 */
	public function get_registration_profiles_select( $post = null ) {

		// Init return.
		$html = '';

		// Init CiviCRM or bail.
		if ( ! $this->is_active() ) return $html;

		// Get all profiles.
		$result = $this->get_registration_profiles();

		// Did we get any?
		if (
			$result !== false AND
			$result['is_error'] == '0' AND
			count( $result['values'] ) > 0
		) {

			// Get the values array.
			$profiles = $result['values'];

			// Init options.
			$options = array();

			// Get existing profile ID.
			$existing_id = $this->get_registration_profile( $post );

			// Loop.
			foreach( $profiles AS $key => $profile ) {

				// Get profile value.
				$profile_id = absint( $profile['id'] );

				// Init selected.
				$selected = '';

				// Set selected if this value is the same as the default.
				if ( $existing_id === $profile_id ) {
					$selected = ' selected="selected"';
				}

				// Construct option.
				$options[] = '<option value="' . $profile_id . '"' . $selected . '>' .
								esc_html( $profile['title'] ) .
							 '</option>';

			}

			// Create html.
			$html = implode( "\n", $options );

		}

		// --<
		return $html;

	}



	//##########################################################################



	/**
	 * Query email via API and update if necessary.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The Event Organiser venue object.
	 * @param array $location The CiviCRM location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array $email_data Integer if found, array if not found.
	 */
	private function maybe_update_email( $venue, $location = null, $op = 'create' ) {

		// If the location has an existing email.
		if ( ! is_null( $location ) AND isset( $location['email']['id'] ) ) {

			// Check by ID.
			$email_params = array(
				'version' => 3,
				'id' => $location['email']['id'],
			);

		} else {

			// Check by email.
			$email_params = array(
				'version' => 3,
				'contact_id' => null,
				'is_primary' => 0,
				'location_type_id' => 1,
				'email' => $venue->venue_civi_email,
			);

		}

		// Query API.
		$existing_email_data = civicrm_api( 'email', 'get', $email_params );

		// Did we get one?
		if (
			$existing_email_data['is_error'] == 0 AND
			$existing_email_data['count'] > 0 AND
			is_array( $existing_email_data['values'] )
		) {

			// Get first one.
			$existing_email = array_shift( $existing_email_data['values'] );

			// Has it changed?
			if ( $op == 'update' AND $existing_email['email'] != $venue->venue_civi_email ) {

				// Add API version.
				$existing_email['version'] = 3;

				// Add null contact ID as this seems to be required.
				$existing_email['contact_id'] = null;

				// Replace with updated email.
				$existing_email['email'] = $venue->venue_civi_email;

				// Update it.
				$existing_email = civicrm_api( 'email', 'create', $existing_email );

			}

			// Get its ID.
			$email_data = $existing_email['id'];

		} else {

			// Define new email.
			$email_data = array(
				'location_type_id' => 1,
				'email' => $venue->venue_civi_email,
			);

		}

		// --<
		return $email_data;

	}



	/**
	 * Query phone via API and update if necessary.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The Event Organiser venue object.
	 * @param array $location The CiviCRM location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array $phone_data Integer if found, array if not found.
	 */
	private function maybe_update_phone( $venue, $location = null, $op = 'create' ) {

		// Create numeric version of phone number.
		$numeric = preg_replace( "/[^0-9]/", '', $venue->venue_civi_phone );

		// If the location has an existing email.
		if ( ! is_null( $location ) AND isset( $location['phone']['id'] ) ) {

			// Check by ID.
			$phone_params = array(
				'version' => 3,
				'id' => $location['phone']['id'],
			);

		} else {

			// Check phone by its numeric field.
			$phone_params = array(
				'version' => 3,
				'contact_id' => null,
				//'is_primary' => 0,
				'location_type_id' => 1,
				'phone_numeric' => $numeric,
			);

		}

		// Query API.
		$existing_phone_data = civicrm_api( 'phone', 'get', $phone_params );

		// Did we get one?
		if (
			$existing_phone_data['is_error'] == 0 AND
			$existing_phone_data['count'] > 0 AND
			is_array( $existing_phone_data['values'] )
		) {

			// Get first one.
			$existing_phone = array_shift( $existing_phone_data['values'] );

			// Has it changed?
			if ( $op == 'update' AND $existing_phone['phone'] != $venue->venue_civi_phone ) {

				// Add API version.
				$existing_phone['version'] = 3;

				// Add null contact ID as this seems to be required.
				$existing_phone['contact_id'] = null;

				// Replace with updated phone.
				$existing_phone['phone'] = $venue->venue_civi_phone;
				$existing_phone['phone_numeric'] = $numeric;

				// Update it.
				$existing_phone = civicrm_api( 'phone', 'create', $existing_phone );

			}

			// Get its ID.
			$phone_data = $existing_phone['id'];

		} else {

			// Define new phone.
			$phone_data = array(
				'location_type_id' => 1,
				'phone' => $venue->venue_civi_phone,
				'phone_numeric' => $numeric,
			);

		}

		// --<
		return $phone_data;

	}



	/**
	 * Query address via API and update if necessary.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The Event Organiser venue object.
	 * @param array $location The CiviCRM location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array $address_data Integer if found, array if not found.
	 */
	private function maybe_update_address( $venue, $location = null, $op = 'create' ) {

		// If the location has an existing address.
		if ( ! is_null( $location ) AND isset( $location['address']['id'] ) ) {

			// Check by ID.
			$address_params = array(
				'version' => 3,
				'id' => $location['address']['id'],
			);

		} else {

			// Check address.
			$address_params = array(
				'version' => 3,
				'contact_id' => null,
				//'is_primary' => 0,
				'location_type_id' => 1,
				//'county' => $venue->venue_state, // Can't do county in CiviCRM yet.
				//'country' => $venue->venue_country, // Can't do country in CiviCRM yet.
			);

			// Add street address if present.
			if ( ! empty( $venue->venue_address ) ) {
				$address_params['street_address'] = $venue->venue_address;
			}

			// Add city if present.
			if ( ! empty( $venue->venue_city ) ) {
				$address_params['city'] = $venue->venue_city;
			}

			// Add postcode if present.
			if ( ! empty( $venue->venue_postcode ) ) {
				$address_params['postal_code'] = $venue->venue_postcode;
			}

			// Add geocodes if present.
			if ( ! empty( $venue->venue_lat ) ) {
				$address_params['geo_code_1'] = $venue->venue_lat;
			}
			if ( ! empty( $venue->venue_lng ) ) {
				$address_params['geo_code_2'] = $venue->venue_lng;
			}

		}

		// Query API.
		$existing_address_data = civicrm_api( 'address', 'get', $address_params );

		// Did we get one?
		if ( $existing_address_data['is_error'] == 0 AND $existing_address_data['count'] > 0 ) {

			// Get first one.
			$existing_address = array_shift( $existing_address_data['values'] );

			// Has it changed?
			if ( $op == 'update' AND $this->is_address_changed( $venue, $existing_address ) ) {

				// Add API version.
				$existing_address['version'] = 3;

				// Add null contact ID as this seems to be required.
				$existing_address['contact_id'] = null;

				// Replace street address.
				$existing_address['street_address'] = $venue->venue_address;

				// Replace city.
				$existing_address['city'] = $venue->venue_city;

				// Replace postcode.
				$existing_address['postal_code'] = $venue->venue_postcode;

				// Replace geocodes.
				$existing_address['geo_code_1'] = $venue->venue_lat;
				$existing_address['geo_code_2'] = $venue->venue_lng;

				// Can't do county in CiviCRM yet.
				// Can't do country in CiviCRM yet.

				// Update it.
				$existing_address = civicrm_api( 'address', 'create', $existing_address );

			}

			// Get its ID.
			$address_data = $existing_address['id'];

		} else {

			// Define new address.
			$address_data = array(
				'location_type_id' => 1,
				//'county' => $venue->venue_state, // Can't do county in CiviCRM yet.
				//'country' => $venue->venue_country, // Can't do country in CiviCRM yet.
			);

			// Add street address if present.
			if ( ! empty( $venue->venue_address ) ) {
				$address_data['street_address'] = $venue->venue_address;
			}

			// Add city if present.
			if ( ! empty( $venue->venue_city ) ) {
				$address_data['city'] = $venue->venue_city;
			}

			// Add postcode if present.
			if ( ! empty( $venue->venue_postcode ) ) {
				$address_data['postal_code'] = $venue->venue_postcode;
			}

			// Add geocodes if present.
			if ( ! empty( $venue->venue_lat ) ) {
				$address_data['geo_code_1'] = $venue->venue_lat;
			}
			if ( ! empty( $venue->venue_lng ) ) {
				$address_data['geo_code_2'] = $venue->venue_lng;
			}

		}

		// --<
		return $address_data;

	}



	/**
	 * Has an address changed?
	 *
	 * It's worth noting that when there is no data for a property of a CiviCRM
	 * location, it will no exist as an entry in the data array. This is not
	 * the case for EO venues, whose objects always contain all properties,
	 * whether they have a value or not.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The EO venue object being updated.
	 * @param array $location The existing CiviCRM location data.
	 * @return bool $is_changed True if changed, false otherwise.
	 */
	private function is_address_changed( $venue, $location ) {

		// Check street address.
		if ( ! isset( $location['street_address'] ) ) $location['street_address'] = '';
		if ( $location['street_address'] != $venue->venue_address ) {
			return true;
		}

		// Check city.
		if ( ! isset( $location['city'] ) ) $location['city'] = '';
		if ( $location['city'] != $venue->venue_city ) {
			return true;
		}

		// Check postcode.
		if ( ! isset( $location['postal_code'] ) ) $location['postal_code'] = '';
		if ( $location['postal_code'] != $venue->venue_postcode ) {
			return true;
		}

		// Check geocodes.
		if ( ! isset( $location['geo_code_1'] ) ) $location['geo_code_1'] = '';
		if ( $location['geo_code_1'] != $venue->venue_lat ) {
			return true;
		}
		if ( ! isset( $location['geo_code_2'] ) ) $location['geo_code_2'] = '';
		if ( $location['geo_code_2'] != $venue->venue_lng ) {
			return true;
		}

		// --<
		return false;

	}



} // Class ends.



