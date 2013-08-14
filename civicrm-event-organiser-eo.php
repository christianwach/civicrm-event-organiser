<?php /*
--------------------------------------------------------------------------------
CiviCRM_WP_Event_Organiser_EO Class
--------------------------------------------------------------------------------
*/

class CiviCRM_WP_Event_Organiser_EO {
	
	/** 
	 * properties
	 */
	
	// parent object
	public $plugin;
	
	// assume we're updating an event
	public $insert_event = false;
	
	
	
	/** 
	 * @description: initialises this object
	 * @return object
	 */
	function __construct() {
		
		// register hooks
		$this->register_hooks();
		
		// --<
		return $this;
		
	}
	
	
	
	/**
	 * @description: set references to other objects
	 * @return nothing
	 */
	public function set_references( $parent ) {
	
		// store
		$this->plugin = $parent;
		
	}
	
	
	
	/**
	 * @description: register hooks on BuddyPress loaded
	 * @return nothing
	 */
	public function register_hooks() {
		
		// check for Event Organiser
		if ( !$this->is_active() ) return;
		
		// intercept insert post
		add_action( 'wp_insert_post', array( $this, 'insert_post' ), 10, 2 );
		
		// intercept save event
		add_action( 'eventorganiser_save_event', array( $this, 'save_event' ), 10, 1 );
		
		// intercept delete event occurrences
		add_action( 'eventorganiser_delete_event_occurrences', array( $this, 'delete_event_occurrences' ), 10, 1 );
		
		// intercept before break occurrence
		//add_action( 'eventorganiser_pre_break_occurrence', array( $this, 'pre_break_occurrence' ), 10, 2 );
		
		// there's no hook for 'eventorganiser_delete_event_occurrence', which moves the occurrence
		// to the 'exclude' array of the date sequence
		
		// intercept after break occurrence
		//add_action( 'eventorganiser_occurrence_broken', array( $this, 'occurrence_broken' ), 10, 3 );
		
		// debug
		//add_filter( 'eventorganiser_pre_event_content', array( $this, 'pre_event_content' ), 10, 2 );
		
		// add our event meta box
		add_action( 'add_meta_boxes', array( $this, 'event_meta_box' ) );
		
		// intercept new term creation
		add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );
		
		// intercept term updates
		add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 1 );
		add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );
		
		// intercept term deletion
		add_action( 'delete_term', array( $this, 'intercept_delete_term' ), 20, 4 );
		
	}
	
	
	
	/**
	 * @description: utility to check if Event Organiser is present and active
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
			wp_die( '<p>Event Organiser plugin is required</p>' );
		}
		
		// we need version 2 at least
		if ( $installed_version < '2' ) {
			wp_die( '<p>Event Organiser version 2 or higher is required</p>' );
		}
		
		// set flag
		$eo_active = true;
		
		// --<
		return $eo_active;
		
	}
	
	
	
 	//##########################################################################
	
	
	
	/**
	 * @description: intercept insert post and check if we're inserting an event
	 * @param int $post_id the numeric ID of the WP post
	 * @param object $post the WP post object
	 * @return nothing
	 */
	public function insert_post( $post_id, $post ) {
		
		/*
		print_r( array(
			'post_id' => $post_id,
			'post' => $post,
		) ); die();
		*/
		
		// kick out if not event
		if ( $post->post_type != 'event' ) return;
		
		// set flag
		$this->insert_event = true;
		
		// check the validity of our CiviCRM options
		$success = $this->plugin->civi->validate_civi_options( $post_id, $post );
		
	}
	
	
	
	/**
	 * @description: intercept save event
	 * @param int $post_id the numeric ID of the WP post
	 * @return nothing
	 */
	public function save_event( $post_id ) {
		
		// get post data
		$post = get_post( $post_id );
		
		// save custom EO event components
		$this->_save_event_components( $post_id );
		
		// get all dates
		$dates = $this->get_all_dates( $post_id );
		
		// get event data
		//$schedule = eo_get_event_schedule( $post_id );
		
		/*
		print_r( array(
			'post_id' => $post_id,
			'post' => $post,
			//'schedule' => $schedule,
			'dates' => $dates,
		) ); die();
		*/
		
		// are we creating an event for the first time?
		if ( $this->insert_event ) {
			
			// create our CiviCRM events
			$this->plugin->civi->create_civi_events( $post, $dates );
			
		} else {
		
			// update our CiviCRM events
			$this->plugin->civi->update_civi_events( $post, $dates );
			
		}
		
	}
	
	
	
	/**
	 * @description: intercept delete event
	 * @param int $post_id the numeric ID of the WP post
	 * @return nothing
	 */
	public function delete_event_occurrences( $post_id ) {
		
		/*
		print_r( array(
			'method' => 'delete_event_occurrences',
			'post_id' => $post_id,
		) ); die();
		*/
		
		// TODO
		return;
		
		// get IDs from post meta
		$civi_event_ids = $this->plugin->db->get_eo_to_civi_correspondences( $post_id );
		
		// delete those CiviCRM events
		$this->plugin->civi->delete_all_events( $civi_event_ids );
		
		// delete our stored CiviCRM event IDs
		$this->plugin->db->clear_event_correspondences( $post_id );
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: intercept before break occurrence
	 * @param int $post_id the numeric ID of the WP post
	 * @param int $occurrence_id the numeric ID of the occurrence
	 * @return nothing
	 */
	public function pre_break_occurrence( $post_id, $occurrence_id ) {
		
		// eg ( [post_id] => 31 [occurrence_id] => 2 )
		print_r( array(
			'method' => 'pre_break_occurrence',
			'post_id' => $post_id,
			'occurrence_id' => $occurrence_id,
		) ); die();
		
		// init or die
		if ( ! $this->is_active() ) return;
		
	}
	
	
	
	/**
	 * @description: intercept after break occurrence
	 * @param int $post_id the numeric ID of the WP post
	 * @param int $occurrence_id the numeric ID of the occurrence
	 * @param int $new_event_id the numeric ID of the new WP post
	 * @return nothing
	 */
	public function occurrence_broken( $post_id, $occurrence_id, $new_event_id ) {
		
		print_r( array(
			'method' => 'occurrence_broken',
			'post_id' => $post_id,
			'occurrence_id' => $occurrence_id,
			'new_event_id' => $new_event_id,
		) ); die();
		
		// init or die
		if ( ! $this->is_active() ) return;
		
	}
	
	
	
	/**
	 * @description: intercept before event content
	 * @param int $post_id the numeric ID of the WP post
	 * @return nothing
	 */
	public function pre_event_content( $event_content, $content ) {
		
		// init or die
		if ( ! $this->is_active() ) return $event_content;
		
		// let's see
		//$this->get_participant_roles();
		
		/*
		print_r( array(
			'post_id' => $post_id,
		) ); die();
		*/
		
		return $event_content;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: register event meta box
	 * @return nothing 
	 */
	public function event_meta_box() {
		
		// create it
		add_meta_box( 
			'civi_eo_venue_metabox', 
			'CiviCRM Settings', 
			array( $this, 'event_meta_box_render' ), 
			'event', 
			'side', //'normal', 
			'core' //'high' 
		);
		
	}
	
	
	
	/**
	 * @description: define venue meta box
	 * @return nothing 
	 */
	public function event_meta_box_render( $event ) {
		
		// add nonce
		wp_nonce_field( 'civi_eo_event_meta_save', 'civi_eo_event_nonce_field' );
		
		//print_r( $event ); die();
		
		// get online registration
		$reg_checked = $this->plugin->civi->get_registration( $event );
		
		// get participant roles
		$roles = $this->plugin->civi->get_participant_roles_select( $event );
		
		// show meta box
		echo '
		
		<p class="civi_eo_event_desc">Choose whether or not the events will allow online registration. WARNING changing this will set the event registration for all events.</p>
		
		<p>
		<label for="civi_eo_event_reg">Online Registration:</label>
		<input type="checkbox" id="civi_eo_event_reg" name="civi_eo_event_reg" value="1"'.$reg_checked.' />
		</p>
		
		<p class="civi_eo_event_desc">The role you select here is automatically assigned to people when they register online for this event (usually the default <em>Attendee</em> role).</p>
		
		<p>
		<label for="civi_eo_event_role">Participant Role:</label>
		<select id="civi_eo_event_role" name="civi_eo_event_role">
			'.$roles.'
		</select>
		</p>
		
		';
		
	}



	//##########################################################################
	
	
	
	/**
	 * @description: get all Event Organiser dates for a given post ID
	 * @param int $post_id the numeric ID of the WP post
	 * @return array $all_dates all dates for the post
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
				
				// init
				$date = array();
				
				// add to our array, formatted for CiviCRM
				$date['start'] = eo_get_the_start( 'Y-m-d H:i:s' );
				$date['end'] = eo_get_the_end( 'Y-m-d H:i:s' );
				$date['human'] = eo_get_the_start( 'M j, Y, g:i a' );
				
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
	 * @description: get all Event Organiser date objects for a given post ID
	 * @param int $post_id the numeric ID of the WP post
	 * @return array $all_dates all dates for the post, keyed by ID
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
	 * @description: save custom components that sync with CiviCRM
	 * @param int $event_id the numeric ID of the event
	 * @return nothing
	 */
	private function _save_event_components( $event_id ) {
		
		// save online registration
		$this->update_event_registration( $event_id );
		
		// save participant role
		$this->update_event_role( $event_id );
		
	}
	
	
	
	/**
	 * @description: delete custom components that sync with CiviCRM
	 * @param int $event_id the numeric ID of the event
	 * @return nothing
	 */
	private function _delete_event_components( $event_id ) {
		
		// EO garbage-collects when it deletes a event, so no need
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: update event online registration value
	 * @param int $event_id the numeric ID of the event
	 * @return nothing
	 */
	public function update_event_registration( $event_id ) {
		
		// init as off
		$value = 0;
		
		// kick out if not set
		if ( isset( $_POST['civi_eo_event_reg'] ) ) {
			
			// retrieve meta value
			$value = absint( $_POST['civi_eo_event_reg'] );
			
		}
		
		// update event meta
		update_post_meta( $event_id,  '_civi_reg', $value );
		
	}
	
	
	
	/**
	 * @description: get all event registration value
	 * @param int $post_id the numeric ID of the WP post
	 * @return bool $civi_reg the event registration value for the CiviEvent
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
	 * @description: delete event registration value for a CiviEvent
	 * @param int $post_id the numeric ID of the WP post
	 * @return nothing
	 */
	public function clear_event_registration( $post_id ) {
		
		// delete the meta value
		delete_post_meta( $post_id, '_civi_reg' );
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: update event participant role value
	 * @param int $event_id the numeric ID of the event
	 * @return nothing
	 */
	public function update_event_role( $event_id ) {
		
		// kick out if not set
		if ( isset( $_POST['civi_eo_event_role'] ) ) return;
		
		// retrieve meta value
		$value = $_POST['civi_eo_event_role'];
		
		// retrieve meta value
		$value = absint( $_POST['civi_eo_event_role'] );
		
		// update event meta
		update_post_meta( $event_id,  '_civi_role', $value );
		
	}
	
	
	
	/**
	 * @description: get event participant role value
	 * @param int $post_id the numeric ID of the WP post
	 * @return bool $civi_role the event participant role value for the CiviEvent
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
	 * @description: delete event participant role value for a CiviEvent
	 * @param int $post_id the numeric ID of the WP post
	 * @return nothing
	 */
	public function clear_event_role( $post_id ) {
		
		// delete the meta value
		delete_post_meta( $post_id, '_civi_role' );
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: get event category terms
	 * @param int $post_id the numeric ID of the WP post
	 * @return array $terms the EO event category terms
	 */
	public function get_event_categories( $post_id = false ) {
		
		// if ID is false, get all terms
		if ( $post_id === false ) {
			
			// construct args
			$args = array(
				'orderby' => 'count',
				'hide_empty' => 0
			);
			
			// get all terms
			$terms = get_terms( 'event-category', $args );
			
		} else {
			
			// get terms for the post
			$terms = get_the_terms( $post_id, 'event-category' );
			
		}
		
		// --<
		return $terms;
		
	}
	
	
	
	/**
	 * @description: hook into the creation of an EO event category term
	 * @param array $term_id the numeric ID of the new term
	 * @param array $tt_id the numeric ID of the new term
	 * @param string $taxonomy should be (an array containing) 'event-category'
	 * @return nothing
	 */
	public function intercept_create_term( $term_id, $tt_id, $taxonomy ) {
		
		/*
		print_r( array( 
			'term_id' => $term_id, 
			'tt_id' => $tt_id, 
			'taxonomy' => $taxonomy 
		) ); die();
		*/
		
		// only look for terms in the EO taxonomy
		if ( $taxonomy != 'event-category' ) return;
		
		// get term object
		$term = get_term_by( 'id', $term_id, 'event-category' );
		
		// unhook Civi - no need because we use hook_civicrm_postProcess
		
		// update CiviEvent term - or create if it doesn't exist
		$civi_event_type_id = $this->plugin->civi->update_event_type( $term );
		
		// rehook Civi?
		
	}
	
	
	
	/**
	 * @description: hook into updates to an EO event category term before the
	 * term is updated - because we need to get the corresponding CiviEvent type
	 * before the WP term is updated.
	 * @param array $term_id the numeric ID of the new term
	 * @return nothing
	 */
	public function intercept_pre_update_term( $term_id ) {
		
		// get reference to term object
		$term = $this->get_term_by_id( $term_id );
		
		/*
		print_r( array( 
			'term_id' => $term_id, 
			'term' => $term 
		) ); die();
		*/
		
		// error check
		if ( is_null( $term ) ) return;
		if ( is_wp_error( $term ) ) return;
		if ( !is_object( $term ) ) return;
		
		// check taxonomy
		if ( $term->taxonomy != 'event-category' ) return;
		
		// store for reference in intercept_update_term()
		$this->term_edited = clone $term;
		
	}
	
	
	
	/**
	 * @description: hook into updates to an EO event category term
	 * @param array $term_id the numeric ID of the new term
	 * @param array $tt_id the numeric ID of the new term
	 * @param string $taxonomy should be (an array containing) 'event-category'
	 * @return nothing
	 */
	public function intercept_update_term( $term_id, $tt_id, $taxonomy ) {
		
		/*
		print_r( array( 
			'term_id' => $term_id, 
			'tt_id' => $tt_id, 
			'taxonomy' => $taxonomy,
		) ); die();
		*/
		
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
		
		// unhook Civi - no need because we use hook_civicrm_postProcess
		
		/*
		print_r( array( 
			'new_term' => $new_term, 
			'old_term' => $old_term, 
		) ); die();
		*/
		
		// update CiviEvent term - or create if it doesn't exist
		$civi_event_type_id = $this->plugin->civi->update_event_type( $new_term, $old_term );
		
		// rehook Civi?
		
	}
	
	
	
	/**
	 * @description: hook into deletion of an EO event category term - requires
	 * WordPress 3.5+ because of the 4th parameter
	 * @param array $term_id the numeric ID of the new term
	 * @param array $tt_id the numeric ID of the new term
	 * @param string $taxonomy name of the taxonomy
	 * @param object $deleted_term the deleted term object
	 * @return nothing
	 */
	public function intercept_delete_term( $term, $tt_id, $taxonomy, $deleted_term ) {
		
		/*
		print_r( array( 
			'term_id' => $term_id, 
			'tt_id' => $tt_id, 
			'taxonomy' => $taxonomy,
			'deleted_term' => $deleted_term,
		) ); die();
		*/
		
		// only look for terms in the EO taxonomy
		if ( $taxonomy != 'event-category' ) return;
		
		// unhook Civi - no need because there is no hook to catch event type deletes
		
		// delete CiviEvent term if it exists
		$civi_event_type_id = $this->plugin->civi->delete_event_type( $deleted_term );
		
		// rehook Civi?
		
	}
	
	
	
	/**
	 * @description: create an EO event category term
	 * @param int $type a CiviEvent event type
	 * @return int $term_id the ID of the created EO event category term
	 */
	public function create_term( $type ) {
	
		// sanity check
		if ( !is_array( $type ) ) return false;
		
		// define description if present
		$description = isset( $type['description'] ) ? $type['description'] : '';
		
		// construst args
		$args = array(
			'slug' => sanitize_title( $type['name'] ),
			'description'=> $description,
		);
		
		// unhook Civi - no need because we use hook_civicrm_postProcess
		
		// insert it
		$result = wp_insert_term( $type['label'], 'event-category', $args );
		
		// rehook Civi?
		
		// if all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// if something goes wrong, we get a WP_Error object
		if ( is_wp_error( $result ) ) return false;
		
		// --<
		return $result;
		
	}
	
	
	
	/**
	 * @description: update an EO event category term
	 * @param array $new_type a CiviEvent event type
	 * @param array $old_type a CiviEvent event type prior to the update
	 * @return int $term_id the ID of the updated EO event category term
	 */
	public function update_term( $new_type, $old_type = null ) {
		
		// sanity check
		if ( !is_array( $new_type ) ) return false;
		
		// if we're updating a term
		if ( !is_null( $old_type ) ) {
			
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
		
		// unhook Civi - no need because we use hook_civicrm_postProcess
		
		// update term
		$result = wp_update_term( $term_id, 'event-category', $args );
		
		// rehook Civi?
		
		// if all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// if something goes wrong, we get a WP_Error object
		if ( is_wp_error( $result ) ) return false;
		
		// --<
		return $result['term_id'];
		
	}
	
	
	
	/**
	 * @description: get an EO event category term by CiviEvent event type
	 * @param int $type a CiviEvent event type
	 * @return int $term_id the ID of the updated EO event category term
	 */
	public function get_term_id( $type ) {
		
		// sanity check
		if ( !is_array( $type ) ) return false;
		
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
	 * @description get a term without knowing its taxonomy - this is necessary 
	 * until WordPress passes $taxonomy to the 'edit_terms' action, due in WP 3.7:
	 * @see http://core.trac.wordpress.org/ticket/22542
	 * @param int $term_id the ID of the term whose taxonomy we want
	 * @param string $output passed to get_term
	 * @param string $filter passed to get_term
	 * @return object $term the WP term object passed by reference
	 */
	public function &get_term_by_id( $term_id, $output = OBJECT, $filter = 'raw' ) {
		
		// access db
		global $wpdb;
		
		// init failure
		$null = null;
		
		// sanity check
		if ( empty( $term_id ) ) {
			$error = new WP_Error('invalid_term', __('Empty Term'));
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
	 * @description: debugging
	 * @param array $msg
	 * @return string
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



