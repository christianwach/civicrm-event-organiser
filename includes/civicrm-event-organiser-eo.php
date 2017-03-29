<?php

/**
 * CiviCRM Event Organiser EO General Class.
 *
 * A class that encapsulates functionality generally related to Event Organiser.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_EO {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object
	 */
	public $plugin;

	/**
	 * Insert event flag.
	 *
	 * @since 0.1
	 * @access public
	 * @var bool $insert_event True if inserting an event, false otherwise
	 */
	public $insert_event = false;



	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// register hooks
		$this->register_hooks();

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object
	 */
	public function set_references( $parent ) {

		// store
		$this->plugin = $parent;

	}



	/**
	 * Register hooks if Event Organiser is present.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// check for Event Organiser
		if ( ! $this->is_active() ) return;

		// intercept insert post
		add_action( 'wp_insert_post', array( $this, 'insert_post' ), 10, 2 );

		// intercept save event
		add_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10, 1 );

		// intercept update event (though by misuse of a filter)
		add_filter( 'eventorganiser_update_event_event_data', array( $this, 'intercept_update_event' ), 10, 4 );

		// intercept delete event occurrences (which is the preferred way to hook into event deletion)
		add_action( 'eventorganiser_delete_event_occurrences', array( $this, 'delete_event_occurrences' ), 10, 1 );

		// intercept before break occurrence
		add_action( 'eventorganiser_pre_break_occurrence', array( $this, 'pre_break_occurrence' ), 10, 2 );

		// there's no hook for 'eventorganiser_delete_event_occurrence', which moves the occurrence
		// to the 'exclude' array of the date sequence

		// intercept after break occurrence
		add_action( 'eventorganiser_occurrence_broken', array( $this, 'occurrence_broken' ), 10, 3 );

		// intercept "Delete Occurrence" in admin calendar
		add_action( 'eventorganiser_admin_calendar_occurrence_deleted', array( $this, 'occurrence_deleted' ), 10, 2 );

		// debug
		//add_filter( 'eventorganiser_pre_event_content', array( $this, 'pre_event_content' ), 10, 2 );

		// add our event meta box
		add_action( 'add_meta_boxes', array( $this, 'event_meta_box' ) );

		// intercept new term creation
		add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );

		// intercept term updates
		add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 2 );
		add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );

		// intercept term deletion
		add_action( 'delete_term', array( $this, 'intercept_delete_term' ), 20, 4 );

	}



	/**
	 * Utility to check if Event Organiser is present and active.
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	public function is_active() {

		// only check once
		static $eo_active = false;
		if ( $eo_active ) { return true; }

		// access Event Organiser option
		$installed_version = get_option( 'eventorganiser_version' );

		// this plugin will not work without EO
		if ( $installed_version === false ) {
			wp_die( '<p>' . __( 'Event Organiser plugin is required', 'civicrm-event-organiser' ) . '</p>' );
		}

		// we need version 2 at least
		if ( $installed_version < '2' ) {
			wp_die( '<p>' . __( 'Event Organiser version 2 or higher is required', 'civicrm-event-organiser' ) . '</p>' );
		}

		// set flag
		$eo_active = true;

		// --<
		return $eo_active;

	}



 	//##########################################################################



	/**
	 * Intercept insert post and check if we're inserting an event.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param object $post The WP post object
	 */
	public function insert_post( $post_id, $post ) {

		// kick out if not event
		if ( $post->post_type != 'event' ) return;

		// set flag
		$this->insert_event = true;

		// check the validity of our CiviCRM options
		$success = $this->plugin->civi->validate_civi_options( $post_id, $post );

	}



	/**
	 * Intercept save event.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 */
	public function intercept_save_event( $post_id ) {

		// save custom EO event components
		$this->_save_event_components( $post_id );

		// sync checkbox is only shown to people who can publish posts
		if ( ! current_user_can( 'publish_posts' ) ) return;

		// is our checkbox checked?
		if ( ! isset( $_POST['civi_eo_event_sync'] ) ) return;
		if ( $_POST['civi_eo_event_sync'] != 1 ) return;

		// get post data
		$post = get_post( $post_id );

		// get all dates
		$dates = $this->get_all_dates( $post_id );

		// get event data
		//$schedule = eo_get_event_schedule( $post_id );

		// prevent recursion
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_created' ), 10 );
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_updated' ), 10 );
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_deleted' ), 10 );

		// update our CiviCRM events (or create new if none exist)
		$this->plugin->civi->update_civi_events( $post, $dates );

		// restore hooks
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_created' ), 10, 4 );
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_updated' ), 10, 4 );
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_deleted' ), 10, 4 );

	}



	/**
	 * Intercept update event.
	 *
	 * @since 0.1
	 *
	 * @param array $event_data The new event data
	 * @param int $post_id The numeric ID of the WP post
	 * @param array $post_data The updated post data
	 * @param array $event_data The updated event data
	 * @return array $event_data Always pass back the updated event data
	 */
	public function intercept_update_event( $event_data, $post_id, $post_data, $event_data ) {

		// --<
		return $event_data;

	}



	/**
	 * Intercept delete event occurrences.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 */
	public function delete_event_occurrences( $post_id ) {

		/**
		 * Once again, the question arises as to whether we should actually delete
		 * the CiviEvents or set them to "disabled"... I guess this behaviour could
		 * be set as a plugin option.
		 *
		 * Also whether we should delete the correspondences or transfer them to an
		 * "inactive" array of some kind.
		 */

		// get IDs from post meta
		$correspondences = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post_id );

		// loop through them
		foreach ( $correspondences AS $civi_event_id ) {

			// disable CiviEvent
			$return = $this->plugin->civi->disable_civi_event( $civi_event_id );

		}

		// TODO - decide if we delete CiviEvents...
		return;

		// delete those CiviCRM events - not used at present
		//$this->plugin->civi->delete_civi_events( $correspondences );

		// delete our stored CiviCRM event IDs
		$this->plugin->db->clear_event_correspondences( $post_id );

	}



	/**
	 * Intercept delete occurrence.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param int $occurrence_id The numeric ID of the EO event occurrence
	 */
	public function occurrence_deleted( $post_id, $occurrence_id ) {

		// init or die
		if ( ! $this->is_active() ) return;

		// get CiviEvent ID from post meta
		$civi_event_id = $this->plugin->db->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );

		// disable CiviEvent
		$return = $this->plugin->civi->disable_civi_event( $civi_event_id );

		// convert occurrence to orphaned
		$this->plugin->db->occurrence_orphaned( $post_id, $occurrence_id, $civi_event_id );

	}



	//##########################################################################



	/**
	 * Update an EO event, given a CiviEvent.
	 *
	 * If no EO event exists then create one. This will NOT create sequences and
	 * is intended for the initial migration of CiviEvents to WordPress.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_event An array of data for the CiviEvent
	 * @return int $event_id The numeric ID of the event
	 */
	public function update_event( $civi_event ) {

		// define schedule
		$event_data = array(

			// start date
			'start' => new DateTime( $civi_event['start_date'], eo_get_blog_timezone() ),

			// end date and end of schedule are the same
			'end' => new DateTime( $civi_event['end_date'], eo_get_blog_timezone() ),
			'schedule_last' => new DateTime( $civi_event['end_date'], eo_get_blog_timezone() ),

			// we can't tell if a CiviEvent is repeating, so only once
			'frequency' => 1,

			// CiviCRM does not have "all day"
			'all_day' => 0,

			// we can't tell if a CiviEvent is repeating
			'schedule' => 'once',

		);

		// define post
		$post_data = array(

			// standard post data
			'post_title' => $civi_event['title'],
			'post_content' => isset( $civi_event['description'] ) ? $civi_event['description'] : '',
			'post_excerpt' => isset( $civi_event['summary']) ? $civi_event['summary'] : '',

			// quick fixes for Windows which need to be present
			'to_ping' => '',
			'pinged' => '',
			'post_content_filtered' => '',

		);

		// test for created date, which may be absent
		if ( isset( $civi_event['created_date'] ) AND ! empty( $civi_event['created_date'] ) ) {

			// create DateTime object
			$datetime = new DateTime( $civi_event['created_date'], eo_get_blog_timezone() );

			// add it, but format it first since CiviCRM seems to send data in the form 20150916135435
			$post_data['post_date'] = $datetime->format( 'Y-m-d H:i:s' );

		}

		// assume the CiviEvent is live
		$post_data['post_status'] = 'publish';

		// is the event active?
		if ( $civi_event['is_active'] == 0 ) {

			// make the CiviEvent unpublished
			$post_data['post_status'] = 'draft';

		}

		// init venue as undefined
		$venue_id = 0;

		// get location ID
		if ( isset( $civi_event['loc_block_id'] ) ) {

			// we have a location...

			// get location data
			$location = $this->plugin->civi->get_location_by_id( $civi_event['loc_block_id'] );

			// get corresponding EO venue ID
			$venue_id = $this->plugin->eo_venue->get_venue_id( $location );

			// did we get one?
			if ( $venue_id === false ) {

				// no, let's create one
				$venue_id = $this->plugin->eo_venue->create_venue( $location );

			} else {

				// yes, update it
				$venue_id = $this->plugin->eo_venue->update_venue( $location );

			}

		}

		// init category as undefined
		$terms = array();

		// get location ID
		if ( isset( $civi_event['event_type_id'] ) ) {

			// we have a category...

			// get event type data for this pseudo-ID (actually "value")
			$type = $this->plugin->civi->get_event_type_by_value( $civi_event['event_type_id'] );

			// does this type have an existing term?
			$term_id = $this->get_term_id( $type );

			// if not...
			if ( $term_id === false ) {

				// create one
				$term = $this->create_term( $type );

				// assign term ID
				$term_id = $term['term_id'];

			}

			// define as array
			$terms = array( absint( $term_id ) );

		}

		// add to post data
		$post_data['tax_input'] = array(
			'event-venue' => array( absint( $venue_id ) ),
			'event-category' => $terms,
		);

		// do we have a post ID for this event?
		$eo_post_id = $this->plugin->db->get_eo_event_id_by_civi_event_id( $civi_event['id'] );

		// remove hooks
		remove_action( 'wp_insert_post', array( $this, 'insert_post' ), 10 );
		remove_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10 );

		// did we get a post ID?
		if ( $eo_post_id === false ) {

			// use EO's API to create event
			$event_id = eo_insert_event( $post_data, $event_data );

		} else {

			// use EO's API to update event
			$event_id = eo_update_event( $eo_post_id, $post_data, $event_data );

		}

		// re-add hooks
		add_action( 'wp_insert_post', array( $this, 'insert_post' ), 10, 2 );
		add_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10, 1 );

		// if the event has online registration enabled
		if (
			isset( $civi_event['is_online_registration'] ) AND
			$civi_event['is_online_registration'] == 1
		) {

			// save specified online registration value
			$this->set_event_registration( $event_id, $civi_event['is_online_registration'] );

		} else {

			// save empty online registration value
			$this->set_event_registration( $event_id );

		}

		// if the event has a participant role specified
		if (
			isset( $civi_event['default_role_id'] ) AND
			! empty( $civi_event['default_role_id'] )
		) {

			// save specified participant role
			$this->set_event_role( $event_id, $civi_event['default_role_id'] );

		} else {

			// set default participant role
			$this->set_event_role( $event_id );

		}

		// --<
		return $event_id;

	}



	//##########################################################################



	/**
	 * Intercept before break occurrence.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param int $occurrence_id The numeric ID of the occurrence
	 */
	public function pre_break_occurrence( $post_id, $occurrence_id ) {

		// init or die
		if ( ! $this->is_active() ) return;

		/**
		 * At minimum, we need to prevent our '_civi_eo_civicrm_events' post meta
		 * from being copied as is to the new EO event. We need to rebuild the data
		 * for both EO events, excluding from the broken and adding to the new EO event.
		 * We get the excluded CiviEvent before the break, remove it from the event,
		 * then rebuild after - see occurrence_broken() below.
		 */

		// unhook eventorganiser_save_event, because that relies on $_POST
		remove_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10 );

		// get the CiviEvent that this occurrence is synced with
		$this->temp_civi_event_id = $this->plugin->db->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );

		// remove it from the correspondences for this post
		$this->plugin->db->clear_event_correspondence( $post_id, $occurrence_id );

	}



	/**
	 * Intercept after break occurrence.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param int $occurrence_id The numeric ID of the occurrence
	 * @param int $new_event_id The numeric ID of the new WP post
	 */
	public function occurrence_broken( $post_id, $occurrence_id, $new_event_id ) {

		/**
		 * EO transfers across all existing post meta, so we don't need to update
		 * registration or event_role values.
		 *
		 * However, because the correspondence data is also copied over, we have to
		 * delete and rebuild it.
		 */

		// clear existing correspondences
		$this->plugin->db->clear_event_correspondences( $new_event_id );

		// build new correspondences array
		$correspondences = array( $occurrence_id => $this->temp_civi_event_id );

		// store new correspondences
		$this->plugin->db->store_event_correspondences( $new_event_id, $correspondences );

	}



	/**
	 * Intercept before event content.
	 *
	 * @since 0.1
	 *
	 * @param str $event_content The EO event content
	 * @param str $content The content of the WP post
	 * @return str $event_content The modified EO event content
	 */
	public function pre_event_content( $event_content, $content ) {

		// init or die
		if ( ! $this->is_active() ) return $event_content;

		// let's see
		//$this->get_participant_roles();

		// --<
		return $event_content;

	}



	//##########################################################################



	/**
	 * Register event meta box.
	 *
	 * @since 0.1
	 */
	public function event_meta_box() {

		// check permission
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) return;

		// create it
		add_meta_box(
			'civi_eo_event_metabox',
			__( 'CiviCRM Settings', 'civicrm-event-organiser' ),
			array( $this, 'event_meta_box_render' ),
			'event',
			'side', //'normal',
			'core' //'high'
		);

	}



	/**
	 * Render a meta box on event edit screens.
	 *
	 * @since 0.1
	 *
	 * @param object $event The EO event
	 */
	public function event_meta_box_render( $event ) {

		// add nonce
		wp_nonce_field( 'civi_eo_event_meta_save', 'civi_eo_event_nonce_field' );

		// get online registration
		$is_reg_checked = $this->get_event_registration( $event->ID );

		// construct checkbox status
		$reg_checked = '';
		if ( $is_reg_checked == 1 ) {
			// override if registration is allowed
			$reg_checked = ' checked="checked"';
		}

		// get participant roles
		$roles = $this->plugin->civi->get_participant_roles_select( $event );

		// init sync options
		$sync_options = '';

		// show checkbox to people who can publish posts
		if ( current_user_can( 'publish_posts' ) ) {

			// do not allow sync by default
			$can_be_synced = false;

			// does this event have a category set?
			if ( has_term( '', $taxonomy = 'event-category', $event ) ) {

				// allow sync
				$can_be_synced = true;

			}

			// does the EO event have enough data for us to sync?
			if ( $can_be_synced ) {

				// define sync options
				$sync_options = '
				<h4>' . __( 'CiviCRM Sync Options', 'civicrm-event-organiser' ) . '</h4>

				<p class="civi_eo_event_desc">' . __( 'Choose whether or not to sync this event and (if the sequence has changed) whether or not to delete the unused corresponding CiviEvents. If you do not delete them, they will be set to "disabled".', 'civicrm-event-organiser' ) . '</p>

				<p>
				<label for="civi_eo_event_sync">' . __( 'Sync this event with CiviCRM:', 'civicrm-event-organiser' ) . '</label>
				<input type="checkbox" id="civi_eo_event_sync" name="civi_eo_event_sync" value="1" />
				</p>

				<p>
				<label for="civi_eo_event_delete_unused">' . __( 'Delete unused CiviEvents:', 'civicrm-event-organiser' ) . '</label>
				<input type="checkbox" id="civi_eo_event_delete_unused" name="civi_eo_event_delete_unused" value="1" />
				</p>

				';

			}

		}

		// show sync options, if allowed
		echo $sync_options;

		// show meta box
		echo '
		<h4>' . __( 'CiviEvent Options', 'civicrm-event-organiser' ) . '</h4>

		<p class="civi_eo_event_desc">' . __( 'Choose whether or not this event allows online registration. <em>NOTE</em> changing this will set the event registration for all corresponding CiviEvents.', 'civicrm-event-organiser' ) . '</p>

		<p>
		<label for="civi_eo_event_reg">' . __( 'Online Registration:', 'civicrm-event-organiser' ) . '</label>
		<input type="checkbox" id="civi_eo_event_reg" name="civi_eo_event_reg" value="1"' . $reg_checked . ' />
		</p>

		<p class="civi_eo_event_desc">' . __( 'The role you select here is automatically assigned to people when they register online for this event (usually the default <em>Attendee</em> role).', 'civicrm-event-organiser' ) . '</p>

		<p>
		<label for="civi_eo_event_role">' . __( 'Participant Role:', 'civicrm-event-organiser' ) . '</label>
		<select id="civi_eo_event_role" name="civi_eo_event_role">
			' . $roles . '
		</select>
		</p>

		';

		/**
		 * Broadcast end of metabox.
		 *
		 * @since 0.3
		 */
		do_action( 'civicrm_event_organiser_event_meta_box_after' );

	}



	//##########################################################################



	/**
	 * Get all Event Organiser dates for a given post ID.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @return array $all_dates All dates for the post
	 */
	public function get_all_dates( $post_id ) {

		// init dates
		$all_dates = array();

		// get all dates
		$all_event_dates = new WP_Query( array(
			'post_type' => 'event',
			'posts_per_page' => -1,
			'event_series' => $post_id,
			'group_events_by' => 'occurrence'
		));

		// if we have some
		if( $all_event_dates->have_posts() ) {

			// loop through them
			while( $all_event_dates->have_posts() ) {

				// get the post
				$all_event_dates->the_post();

				// access post
				global $post;

				// init
				$date = array();

				// add to our array, formatted for CiviCRM
				$date['occurrence_id'] = $post->occurrence_id;
				$date['start'] = eo_get_the_start( 'Y-m-d H:i:s' );
				$date['end'] = eo_get_the_end( 'Y-m-d H:i:s' );
				$date['human'] = eo_get_the_start( 'g:ia, M jS, Y' );

				// add to our array
				$all_dates[] = $date;

			}

			// reset post data
			wp_reset_postdata();

		}

		// --<
		return $all_dates;

	}



	/**
	 * Get all Event Organiser date objects for a given post ID.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @return array $all_dates All dates for the post, keyed by ID
	 */
	public function get_date_objects( $post_id ) {

		// get schedule
		$schedule = eo_get_event_schedule( $post_id );

		// if we have some dates
		if( isset( $schedule['_occurrences'] ) AND count( $schedule['_occurrences'] ) > 0 ) {

			// --<
			return $schedule['_occurrences'];

		}

		// --<
		return array();

	}



	//##########################################################################



	/**
	 * Save custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event
	 */
	private function _save_event_components( $event_id ) {

		// save online registration
		$this->update_event_registration( $event_id );

		// save participant role
		$this->update_event_role( $event_id );

	}



	/**
	 * Delete custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event
	 */
	private function _delete_event_components( $event_id ) {

		// EO garbage-collects when it deletes a event, so no need

	}



	//##########################################################################



	/**
	 * Update event online registration value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event
	 * @param bool $value Whether registration is enabled or not
	 */
	public function update_event_registration( $event_id, $value = 0 ) {

		// if the checkbox is ticked
		if ( isset( $_POST['civi_eo_event_reg'] ) ) {

			// override the meta value with the checkbox state
			$value = absint( $_POST['civi_eo_event_reg'] );

		}

		// go ahead and set the value
		$this->set_event_registration( $event_id, $value );

	}



	/**
	 * Get event registration value.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @return bool $civi_reg The event registration value for the CiviEvent
	 */
	public function get_event_registration( $post_id ) {

		// get the meta value
		$civi_reg = get_post_meta( $post_id, '_civi_reg', true );

		// if it's not yet set it will be an empty string, so cast as boolean
		if ( $civi_reg === '' ) { $civi_reg = 0; }

		// --<
		return absint( $civi_reg );

	}



	/**
	 * Set event registration value.
	 *
	 * @since 0.2.3
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param bool $value Whether registration is enabled or not
	 */
	public function set_event_registration( $post_id, $value = 0 ) {

		// update event meta
		update_post_meta( $post_id,  '_civi_reg', $value );

	}



	/**
	 * Delete event registration value for a CiviEvent.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 */
	public function clear_event_registration( $post_id ) {

		// delete the meta value
		delete_post_meta( $post_id, '_civi_reg' );

	}



	//##########################################################################



	/**
	 * Update event participant role value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event
	 */
	public function update_event_role( $event_id ) {

		// kick out if not set
		if ( ! isset( $_POST['civi_eo_event_role'] ) ) return;

		// retrieve meta value
		$value = absint( $_POST['civi_eo_event_role'] );

		// update event meta
		update_post_meta( $event_id,  '_civi_role', $value );

	}



	/**
	 * Update event participant role value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event
	 * @param int $value The event participant role value for the CiviEvent
	 */
	public function set_event_role( $event_id, $value = null ) {

		// if not set
		if ( is_null( $value ) ) {

			// do we have a default set?
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_role' );

			// did we get one?
			if ( $default !== '' AND is_numeric( $default ) ) {

				// override with default value
				$value = absint( $default );

			}

		}

		// update event meta
		update_post_meta( $event_id,  '_civi_role', $value );

	}



	/**
	 * Get event participant role value.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @return int $civi_role The event participant role value for the CiviEvent
	 */
	public function get_event_role( $post_id ) {

		// get the meta value
		$civi_role = get_post_meta( $post_id, '_civi_role', true );

		// if it's not yet set it will be an empty string, so cast as number
		if ( $civi_role === '' ) { $civi_role = 0; }

		// --<
		return absint( $civi_role );

	}



	/**
	 * Delete event participant role value for a CiviEvent.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 */
	public function clear_event_role( $post_id ) {

		// delete the meta value
		delete_post_meta( $post_id, '_civi_role' );

	}



	//##########################################################################



	/**
	 * Get event category terms.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @return array $terms The EO event category terms
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
	 * @param array $term_id The numeric ID of the new term
	 * @param array $tt_id The numeric ID of the new term
	 * @param string $taxonomy Should be (an array containing) 'event-category'
	 */
	public function intercept_create_term( $term_id, $tt_id, $taxonomy ) {

		// only look for terms in the EO taxonomy
		if ( $taxonomy != 'event-category' ) return;

		// get term object
		$term = get_term_by( 'id', $term_id, 'event-category' );

		// unhook CiviCRM - no need because we use hook_civicrm_postProcess

		// update CiviEvent term - or create if it doesn't exist
		$civi_event_type_id = $this->plugin->civi->update_event_type( $term );

		// rehook CiviCRM?

	}



	/**
	 * Hook into updates to an EO event category term before the term is updated
	 * because we need to get the corresponding CiviEvent type before the WP term
	 * is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $term_id The numeric ID of the new term
	 * @param string $taxonomy The taxonomy containing the term (param introduced in WP 3.7)
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
	 * @param int $term_id The numeric ID of the edited term
	 * @param array $tt_id The numeric ID of the edited term taxonomy
	 * @param string $taxonomy Should be (an array containing) 'event-category'
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
		$civi_event_type_id = $this->plugin->civi->update_event_type( $new_term, $old_term );

		// rehook CiviCRM?

	}



	/**
	 * Hook into deletion of an EO event category term - requires WordPress 3.5+
	 * because of the 4th parameter.
	 *
	 * @since 0.1
	 *
	 * @param int $term_id The numeric ID of the deleted term
	 * @param array $tt_id The numeric ID of the deleted term taxonomy
	 * @param string $taxonomy Name of the taxonomy
	 * @param object $deleted_term The deleted term object
	 */
	public function intercept_delete_term( $term, $tt_id, $taxonomy, $deleted_term ) {

		// only look for terms in the EO taxonomy
		if ( $taxonomy != 'event-category' ) return;

		// unhook CiviCRM - no need because there is no hook to catch event type deletes

		// delete CiviEvent term if it exists
		$civi_event_type_id = $this->plugin->civi->delete_event_type( $deleted_term );

		// rehook CiviCRM?

	}



	/**
	 * Create an EO event category term.
	 *
	 * @since 0.1
	 *
	 * @param int $type A CiviEvent event type
	 * @return array $result Array containing EO event category term data
	 */
	public function create_term( $type ) {

		// sanity check
		if ( ! is_array( $type ) ) return false;

		// define description if present
		$description = isset( $type['description'] ) ? $type['description'] : '';

		// construst args
		$args = array(
			'slug' => sanitize_title( $type['name'] ),
			'description'=> $description,
		);

		// unhook CiviCRM - no need because we use hook_civicrm_postProcess

		// insert it
		$result = wp_insert_term( $type['label'], 'event-category', $args );

		// rehook CiviCRM?

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
	 * @param array $new_type A CiviEvent event type
	 * @param array $old_type A CiviEvent event type prior to the update
	 * @return int $term_id The ID of the updated EO event category term
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

		// unhook CiviCRM - no need because we use hook_civicrm_postProcess

		// update term
		$result = wp_update_term( $term_id, 'event-category', $args );

		// rehook CiviCRM?

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
	 * @param int $type A CiviEvent event type
	 * @return int $term_id The ID of the updated EO event category term
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
	 * @param int $term_id The ID of the term whose taxonomy we want
	 * @param string $output Passed to get_term
	 * @param string $filter Passed to get_term
	 * @return object $term The WP term object passed by reference
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



	//##########################################################################



	/**
	 * Debugging.
	 *
	 * @since 0.1
	 *
	 * @param array $msg
	 */
	private function _debug( $msg ) {

		// add to internal array
		$this->messages[] = $msg;

		// do we want output?
		if ( CIVICRM_WP_EVENT_ORGANISER_DEBUG ) print_r( $msg );

	}



} // class ends



/*

// useful theme snippets

// for public display
if ( eo_is_all_day( $post->ID ) ) {
	$date_format = 'j F Y';
} else {
	$date_format = 'j F Y ' . get_option('time_format');
}



// the main Event Organiser filters

apply_filters( 'eventorganiser_event_meta_list', $html, $post_id);
apply_filters( 'eventorganiser_event_color',$color,$post_id);
apply_filters( 'eventorganiser_event_classes', $event_classes, $post_id, $occurrence_id );

apply_filters( 'eventorganiser_get_event_schedule', $event_details, $post_id );
apply_filters( 'eventorganiser_generate_occurrences', $_event_data, $event_data );

apply_filters( 'eventorganiser_is_event_query', $bool, $query, $exclusive );
apply_filters( 'eventorganiser_events_expire_time', 24*60*60 );
apply_filters( 'eventorganiser_menu_position', 5 );

apply_filters( 'eventorganiser_event_properties', $args );

apply_filters( 'eventorganiser_update_event_event_data', $event_data, $post_id, $post_data, $event_data );
apply_filters( 'eventorganiser_update_event_post_data', $post_data, $post_id, $post_data, $event_data );
apply_filters( 'eventorganiser_insert_event_event_data', $event_data, $post_data, $event_data );
apply_filters( 'eventorganiser_insert_event_post_data', $post_data, $post_data, $event_data );

apply_filters( 'eventorganiser_venue_tooltip', $tooltip_content, $venue_id, $args );
apply_filters( 'eventorganiser_venue_marker', null, $venue_id, $args );
apply_filters( 'eventorganiser_venue_address_fields', $address_fields);

apply_filters( 'eventorganiser_format_datetime', $formatted_datetime , $format, $datetime);

apply_filters( 'eventorganiser_pre_event_content', $event_content, $content);

apply_filters( 'eventorganiser_get_the_start', eo_format_datetime( $start, $format ), $start, $format, $post_id, $occurrence_id );
apply_filters( 'eventorganiser_get_the_end', eo_format_datetime( $end, $format ), $end, $format, $post_id, $occurrence_id );
apply_filters( 'eventorganiser_get_next_occurrence', eo_format_datetime( $next, $format ), $next, $format, $post_id );
apply_filters( 'eventorganiser_get_schedule_start', eo_format_datetime( $schedule_start, $format ), $schedule_start, $format, $post_id );
apply_filters( 'eventorganiser_get_schedule_last', eo_format_datetime( $schedule_last, $format ), $schedule_last, $format, $post_id );
apply_filters( 'eventorganiser_get_the_future_occurrences_of', $occurrences, $post_id );
apply_filters( 'eventorganiser_get_the_occurrences_of', $occurrences, $post_id );

apply_filters( 'eventorganiser_calendar_event_link',$link,$post->ID,$post->occurrence_id);
apply_filters( 'eventorganiser_fullcalendar_event',$event, $post->ID,$post->occurrence_id);
apply_filters( 'eventorganiser_admin_cal_summary',$summary,$post->ID,$post->occurrence_id,$post);
apply_filters( 'eventorganiser_admin_calendar',$event, $post);
apply_filters( 'eventorganiser_admin_fullcalendar_event', $event, $post->ID, $post->occurrence_id );

apply_filters( 'eventorganiser_event_metabox_notice', $notices, $post )
apply_filters( 'eventorganiser_calendar_dialog_tabs', array( 'summary' => __( 'Event Details', 'eventorganiser' ) ) )

apply_filters( 'list_cats', $category->name, $category);

*/



