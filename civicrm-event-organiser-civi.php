<?php /*
--------------------------------------------------------------------------------
CiviCRM_WP_Event_Organiser_CiviCRM Class
--------------------------------------------------------------------------------
*/

class CiviCRM_WP_Event_Organiser_CiviCRM {
	
	/** 
	 * properties
	 */
	
	// parent object
	public $plugin;
	
	// flag for overriding sync process
	public $do_not_sync = false;
	
	
	
	/** 
	 * @description: initialises this object
	 * @return object
	 */
	function __construct() {
		
		// add actions for plugin init on CiviCRM init
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
	 * @description: register hooks on plugin init
	 * @return nothing
	 */
	public function register_hooks() {
		
		// allow plugins to register php and template directories
		//add_action( 'civicrm_config', array( $this, 'register_directories' ), 10, 1 );
		
		// intercept event type enable/disable
		//add_action( 'civicrm_enableDisable', array( $this, 'event_type_toggle' ), 10, 3 );
		
		// intercept event type form edits
		add_action( 'civicrm_postProcess', array( $this, 'event_type_process_form' ), 10, 2 );
		
	}
	
	
	
	/**
	 * @description: test if CiviCRM plugin is active
	 * @return bool
	 */
	public function is_active() {
		
		// bail if no CiviCRM init function
		if ( ! function_exists( 'civi_wp' ) ) return false;
		
		// try and init CiviCRM
		return civi_wp()->initialize();
		
	}
	
	
	
	/**
	 * @description: register directories that CiviCRM searches for php and template files
	 * @param object $config the CiviCRM config object
	 */
	public function register_directories( &$config ) {
		
		/*
		print_r( array(
			'config' => $config
		) ); die();
		*/
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return;
		
		// define our custom path
		$custom_path = BP_GROUPS_CIVICRM_SYNC_PATH . 'civicrm_custom_templates';
		
		// get template instance
		$template = CRM_Core_Smarty::singleton();
		
		// add our custom template directory
		$template->addTemplateDir( $custom_path );
		
		// register template directories
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: create CiviEvents for an EO event
	 * @param object $post the WP post object
	 * @param array $dates array of properly formatted dates
	 * @param array $civi_event_ids array of new CiviEvent IDs
	 */
	public function create_civi_events( $post, $dates ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// just for safety, check we get some (though we must)
		if ( count( $dates ) === 0 ) return false;
		
		// init links
		$links = array();
		
		// init CiviEvent array
		$civi_event = array(
			'version' => 3,
		);
		
		// add items that are common to all CiviEvents
		$civi_event['title'] = $post->post_title;
		$civi_event['description'] = $post->post_content;
		$civi_event['summary'] = strip_tags( $post->post_excerpt );
		$civi_event['created_date'] = $post->post_date;
		$civi_event['is_public'] = 1;
		$civi_event['is_active'] = 1;
		$civi_event['participant_listing_id'] = NULL;
		
		
		
		// get venue for this event
		$venue_id = eo_get_venue( $post->ID );
		
		// get CiviEvent location
		$location_id = $this->plugin->eo_venue->get_civi_location( $venue_id );
		
		// did we get one?
		if ( is_numeric( $location_id ) ) {
			
			// add to our params
			$civi_event['loc_block_id'] = $location_id;
			
			// set CiviCRM to add map
			$civi_event['is_map'] = 1;
			
		}
		
		
		
		// online registration off by default
		$civi_event['is_online_registration'] = 0;
		
		// get CiviEvent online registration value
		$is_reg = $this->plugin->eo->get_event_registration( $post->ID );
		
		// did we get one?
		if ( is_numeric( $is_reg ) AND $is_reg != 0 ) {
			
			// add to our params
			$civi_event['is_online_registration'] = 1;
			
		}
		
		
		
		// participant_role default
		$civi_event['default_role_id'] = 0;
		
		// get existing role ID
		$existing_id = $this->get_participant_role( $post );
		
		// did we get one?
		if ( $existing_id !== false AND is_numeric( $existing_id ) AND $existing_id != 0 ) {
			
			// add to our params
			$civi_event['default_role_id'] = $existing_id;
			
		}
		
		
		
		// get event type id, because it is required in CiviCRM
		$type_id = $this->get_default_event_type( $post );
		
		// well?
		if ( $type_id === false ) {
			
			// error
			wp_die( __( 'You must have some CiviCRM event types defined', 'civicrm-event-organiser' ) );
			
		}
		
		// we need the event type 'value'
		$type_value = $this->get_event_type_value( $type_id );
		
		// assign event type value
		$civi_event['event_type_id'] = $type_value;
		
		
		
		// now loop through dates and create CiviEvents per date
		foreach ( $dates AS $date ) {
			
			// overwrite dates
			$civi_event['start_date'] = $date['start'];
			$civi_event['end_date'] = $date['end'];
			
			// use API to create event
			$result = civicrm_api( 'event', 'create', $civi_event );
			
			// did we do okay?
			if ( $result['is_error'] == '1' ) {
				
				// not much else we can do here if we get an error...
				wp_die( $result['error_message'] );
				
			}
			
			// let's retrieve our CiviEvent ID
			$civi_event_id = $result['id'];
			
			// add the CiviEvent ID to array
			$civi_event_ids[] = $civi_event_id;
			
			/*
			// construct Drupal link
			$links[] = l(
				'CiviCRM Event ('.$date['human'].')', 
				'civicrm/event/manage/eventInfo', 
				array(
					'query' => 'reset=1&action=update&id='. $civi_event_id
				)
			);
			*/
			
			/*
			print_r( array(
				'post' => $post,
				'dates' => $dates,
				'civi_event' => $civi_event,
			) );
			*/
			
		} // end dates loop
		
		
		
		/*
		// inform the admin user
		if ( user_access( 'access CiviEvent' ) ) {
			
			// where there is only one CiviEvent...
			if ( count( $dates ) == 1 ) {
				
				// contruct link
				$link = l(
					'CiviCRM Event page', 
					'civicrm/event/manage/eventInfo', 
					array('query' => 'reset=1&action=update&id='. $civi_event_id)
				);
				
				// feedback
				drupal_set_message(
					'The corresponding CiviCRM Event has been updated. '.
					'You can add further details to it on the '.$link
				);
			
			}
		
			// where there are repeating CiviEvents...
			if ( count( $dates ) > 1 ) {
				
				// construct list of links
				$link_list = implode( '<br/>', $links );
				
				// feedback
				drupal_set_message(
					'The following CiviCRM Events have been created. '.
					'If you need to, visit the following links to configure the events further:<br/>'.$link_list
				);
			
			}
		
		}
		*/
		
		
		
		// store these in post meta
		$this->plugin->db->store_event_correspondences( $post->ID, $civi_event_ids );
		
		
		
		// --<
		return $civi_event_ids;
	
	}



	/**
	 * @description: update CiviEvents for an event
	 * @param object $post the WP post object
	 * @param array $dates array of properly formatted dates
	 * @return nothing
	 */
	public function update_civi_events( $post, $dates ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// just for safety, check we get some (though we must)
		if ( count( $dates ) == 0 ) return false;
		
		
		
		// get existing CiviEvents from post meta
		$civi_event_ids = $this->plugin->db->get_eo_to_civi_correspondences( $post->ID );
		
		// if we have none yet
		if ( count( $civi_event_ids ) === 0 ) {
			
			// create them
			$civi_event_ids = $this->create_civi_events( $post, $dates );
			
			// --<
			return $civi_event_ids;
			
		}
		
		
		
		/*
		------------------------------------------------------------------------
		
		The logic for updating is as follows:
		
		Event sequences can only be generated from EO, so any CiviEvents that are
		part of a sequence must have been generated automatically.
		
		Since CiviEvents will only be generated when the "Create CiviEvents"
		checkbox is ticked (and only those with 'publish_posts' caps can see the
		checkbox) we assume that this is the definitive set of events.
		
		Any further changes work thusly:
		
		We get the correspondences and match by date and time. Any CiviEvents
		that match have their info updated since their correspondence remains
		unaltered.
		
		Any additions to the EO event are treated as new CiviEvents and are added
		to CiviCRM. Any removals are treated as if the event has been cancelled
		and the CiviEvent is set to 'disabled' rather than deleted. This is to
		preserve any data that may have been collected for the removed event.
		
		The bottom line is: make sure your sequences are right before hitting
		the Publish button and be wary of making further changes.
		
		Things get a bit more complicated when a sequence is split, but it's not
		too bad. THis functionality will be handled by the EO 'occurrence' hooks
		when I get round to it!
		
		------------------------------------------------------------------------
		*/
		
		
		
		// TODO event update logic
		// go no further
		return false;
		
		
		
		// init links
		$links = array();
		
		// init CiviEvent array
		$civi_event = array(
			'version' => 3,
		);
		
		// add items that are common to all CiviEvents
		$civi_event['title'] = $post->post_title;
		$civi_event['description'] = $post->post_content;
		$civi_event['summary'] = strip_tags( $post->post_excerpt );
		$civi_event['created_date'] = $post->post_date;
		$civi_event['is_public'] = 1;
		$civi_event['is_active'] = 1;
		$civi_event['participant_listing_id'] = NULL;
		
		/*
		// add participant_role
		$civi_event['default_role_id'] = $post->participant_role;
		
		// add location
		$civi_event['loc_block_id'] = $post->loc_block_id;
		
		// if we have a location, set CiviCRM to add map
		if ( is_numeric( $post->loc_block_id ) ) {
			$civi_event['is_map'] = 1;
		}
		
		// add registration
		$civi_event['is_online_registration'] = $post->is_online_registration;
		
		// set Civi event type according to taxonomy term
		$civi_event['event_type_id'] = _wpa_civievent_sync_get_event_type_id(
			$post->taxonomy[_wpa_civievent_sync_get_vocab_id()]
		);
		*/
		
		// now loop through dates and create CiviEvents per date
		foreach ( $dates AS $date ) {
			
			// see what our Drupal node date looks like
			//print_r( $date ); die();
			
			// overwrite dates
			$civi_event['start_date'] = $date['start'];
			$civi_event['end_date'] = $date['end'];
			
			// use API to create event
			//$result = civicrm_api( 'event', 'create', $civi_event );
			
			/*
			// did we do okay?
			if ( $result['is_error'] == '1' ) {
				
				// not much else we can do here if we get an error...
				wp_die( $result['error_message'] );
				
			}
			
			// let's retrieve our CiviEvent ID
			$civi_event_id = $result['id'];
			*/
			
			/*
			// store the Drupal Node -> CiviEvent relationship in our table
			db_query(
				"INSERT {wpa_civievent_sync} SET nid = '%d', civi_eid = %d", 
				$post->nid, 
				$civi_event_id
			);
			*/
			
			/*
			// construct link
			$links[] = l(
				'CiviCRM Event ('.$date['human'].')', 
				'civicrm/event/manage/eventInfo', 
				array(
					'query' => 'reset=1&action=update&id='. $civi_event_id
				)
			);
			*/
			
			print_r( array(
				'civi_event' => $civi_event,
			) );
			
		} // end check for empty array
		
		die();
		
	}
	
	
	
	/**
	 * @description: get all CiviEvents
	 * @return array $events the CiviEvents data
	 */
	public function get_all_events() {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// construct locations array
		$params = array( 
			'version' => 3,
		);
		
		// call API
		$events = civicrm_api( 'event', 'get', $params );
		
		// --<
		return $events;
		
	}
	
	
	
	/**
	 * @description: delete all CiviEvents WARNING only for dev purposes really!
	 * @return array $results an array of CiviCRM results
	 */
	public function delete_all_events( $civi_event_ids ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// just for safety, check we get some
		if ( count( $civi_event_ids ) == 0 ) return false;
		
		// init return
		$results = array();
		
		// one by one, it seems
		foreach( $civi_event_ids AS $civi_event_id ) {
			
			// construct "query"
			$params = array( 
				'version' => 3,
				'id' => $civi_event_id,
			);
			
			// okay, let's do it
			$results[] = civicrm_api( 'event', 'delete', $params );
		
		}
		
		// --<
		return $results;
	
	}
	
	
	
	/**
	 * @description: get a CiviEvent by ID
	 * @param array $location the CiviEvent location data
	 */
	public function get_event_by_id( $civi_event_id ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// construct locations array
		$params = array( 
			'version' => 3,
			'id' => $civi_event_id,
		);
		
		// call API
		$event = civicrm_api( 'event', 'getsingle', $params );
		
		// --<
		return $event;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: validate all CiviEvent data for an Event Organiser event
	 * @param int $post_id the numeric ID of the WP post
	 * @param object $post the WP post object
	 * @return mixed true if success, otherwise WP error object
	 */
	public function validate_civi_options( $post_id, $post ) {
		
		// disable
		return true;
		
		// check default event type
		$result = $this->_validate_event_type();
		if ( is_wp_error( $result ) ) return $result;
		
		// check participant_role
		$result = $this->_validate_participant_role();
		if ( is_wp_error( $result ) ) return $result;
		
		// check is_online_registration
		$result = $this->_validate_is_online_registration();
		if ( is_wp_error( $result ) ) return $result;
		
		// check loc_block_id
		$result = $this->_validate_loc_block_id();
		if ( is_wp_error( $result ) ) return $result;
		
	}
	
	
	
	/**
	 * @description: updates a CiviEvent Location given an EO venue
	 * @param array $venue the EO venue data
	 * @param array $location the CiviEvent location data
	 */
	public function update_location( $venue ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// get existing location
		$location = $this->get_location( $venue );
		
		// if this venue already has a CiviEvent location
		if ( 
			$location 
			AND 
			$location['is_error'] == '0' 
			AND
			isset( $location['id'] )
			AND
			is_numeric( $location['id'] )
		) {
			
			// is there a record on the EO side?
			if ( !isset( $venue->venue_civi_id ) ) {
				
				// use the result and fake the property
				$venue->venue_civi_id = $location['id'];
				
			}
			
		} else {
			
			// make sure the property is not set
			$venue->venue_civi_id = 0;
			
		}
		
		/*
		print_r( array(
			'venue' => $venue,
			'location' => $location,
		) ); die();
		*/
		
		// update existing - or create one if it doesn't exist
		$location = $this->create_civi_loc_block( $venue );
		
		// --<
		return $location;
		
	}
	
	
	
	/**
	 * @description: delete a CiviEvent Location given an EO venue.
	 * @param array $venue the EO venue data
	 * @return nothing
	 */
	public function delete_location( $venue ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// init return
		$result = false;
		
		// get existing location
		$location = $this->get_location( $venue );
		
		// did we do okay?
		if ( 
			$location 
			AND 
			$location['is_error'] == '0' 
			AND
			isset( $location['id'] )
			AND
			is_numeric( $location['id'] )
		) {
			
			// delete
			$result = $this->delete_location_by_id( $location['id'] );
			
		}

		// --<
		return $result;
		
	}
	
	
	
	/**
	 * @description: delete a CiviEvent Location given a Location ID. Be aware that
	 * only the CiviCRM loc_block is deleted - not the items that constitute it.
	 * Email, phone and address will still exist but not be associated as a loc_block
	 * The next iteration of this plugin should probably refine the loc_block 
	 * sync process to take this into account.
	 * @param array $venue the EO venue data
	 * @return nothing
	 */
	public function delete_location_by_id( $location_id ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// construct delete array
		$params = array( 
			'version' => 3,
			'id' => $location_id,
		);
		
		// delete via API
		$result = civicrm_api( 'loc_block', 'delete', $params );
		
		// did we do okay?
		if ( $result['is_error'] == '1' ) {
			
			// not much else we can do here if we get an error...
			trigger_error( print_r( array( 
				'method' => 'delete_location_by_id',
				'params' => $params, 
				'result' => $result,
			), true ), E_USER_ERROR ); die();
			
		}
		
		// --<
		return $result;
		
	}
	
	
	
	/**
	 * @description: gets a CiviEvent Location given an EO venue
	 * @param array $venue the EO venue data
	 * @param array $location the CiviEvent location data
	 */
	public function get_location( $venue ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// ---------------------------------------------------------------------
		// try by sync ID
		// ---------------------------------------------------------------------
		
		// use it
		$civi_id = 0;
	
		// if sync ID is present
		if ( 
			isset( $venue->venue_civi_id ) 
			AND
			is_numeric( $venue->venue_civi_id ) 
			AND
			$venue->venue_civi_id > 0
		) {
			
			// use it
			$civi_id = $venue->venue_civi_id;
			
		} 
		
		// construct get-by-id array
		$params = array( 
			'version' => 3,
			'id' => $venue->venue_civi_id,
			'return' => 'all',
		);
		
		// call API
		$location = civicrm_api( 'loc_block', 'get', $params );
		
		// did we do okay?
		if ( $location['is_error'] == '1' ) {
			
			// not much else we can do here if we get an error...
			wp_die( 'get_location 1: ' . $location['error_message'] );
			
		}
		
		/*
		print_r( array(
			'venue' => $venue,
			'params' => $params,
			'location' => $location,
		) ); die();
		*/
		
		// return the result if we get one
		if ( absint( $location['count'] ) > 0 ) return $location;
		
		// ---------------------------------------------------------------------
		// now try by location
		// ---------------------------------------------------------------------
		
		// construct get-by-geolocation array
		$params = array( 
			'version' => 3,
			'address' => array( 
				'geo_code_1' => $venue->venue_lat,
				'geo_code_2' => $venue->venue_lng,
			),
			'return' => 'all',
		);
		
		// call API
		$location = civicrm_api( 'loc_block', 'get', $params );
		
		// did we do okay?
		if ( $location['is_error'] == '1' ) {
			
			// not much else we can do here if we get an error...
			wp_die( 'get_location 2: ' . $location['error_message'] );
			
		}
		
		/*
		print_r( array(
			'venue' => $venue,
			'params' => $params,
			'location' => $location,
		) ); die();
		*/
		
		// --<
		return $location;
		
	}



	/**
	 * @description: get all CiviEvent Locations
	 * @param array $location the CiviEvent location data
	 */
	public function get_all_locations() {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// construct locations array
		$params = array(
		
			// API v3 please
			'version' => 3,
			
			// return all data
			'return' => 'all',

			// define stupidly high limit, because API defaults to 25
			'options' => array( 
				'limit' => '10000',
			),
			
		);
		
		// call API
		$locations = civicrm_api( 'loc_block', 'get', $params );
		
		// --<
		return $locations;
		
	}



	/**
	 * @description: WARNING: deletes all CiviEvent Locations
	 * @return nothing
	 */
	public function delete_all_locations() {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// construct locations array
		$params = array( 
			'version' => 3,
			'return' => 'all',
		);
		
		// call API
		$locations = civicrm_api( 'loc_block', 'get', $params );
		
		// start again
		foreach( $locations['values'] AS $location ) {
			
			// construct delete array
			$params = array( 
				'version' => 3,
				'id' => $location['id'],
			);
			
			// delete via API
			$result = civicrm_api( 'loc_block', 'delete', $params );
			
		}
		
	}
	
	
	
	/**
	 * @description: creates (or updates) a CiviEvent Location given an EO venue
	 * The only disadvantage to this method is that, for example, if we update 
	 * the email and that email already exists in the DB, it will not be found
	 * and associated - but rather the existing email will be updated. Same goes
	 * for phone. This is not a deal-breaker, but not very DRY either.
	 * @param array $venue the EO venue data
	 * @return array $location the CiviCRM location data
	 */
	public function create_civi_loc_block( $venue ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return array();
		
		// define create array
		$params = array(
			
			// API version
			'version' => 3,
			
			// contact email
			'email' => array( 
				'location_type_id' => 1,
				'email' => $venue->venue_civi_email,
			),
			
			// contact phone
			'phone' => array( 
				'location_type_id' => 1,
				'phone' => $venue->venue_civi_phone,
			),
			
			// address
			'address' => array( 
				'location_type_id' => 1,
				'street_address' => $venue->venue_address,
				'city' => $venue->venue_city,
				//'county' => $venue->venue_state, // can't do county in CiviCRM yet
				'postal_code' => $venue->venue_postcode,
				//'country' => $venue->venue_country, // can't do country in CiviCRM yet
				'geo_code_1' => $venue->venue_lat,
				'geo_code_2' => $venue->venue_lng,
			),
			
		);
		
		// if our venue has a location, add it
		if ( 
			isset( $venue->venue_civi_id ) 
			AND
			is_numeric( $venue->venue_civi_id ) 
			AND
			$venue->venue_civi_id > 0
		) {
			
			// target our known location - this will trigger an update
			$params['id'] = $venue->venue_civi_id;
			
		}
		
		// call API
		$location = civicrm_api( 'loc_block', 'create', $params );
		
		// did we do okay?
		if ( $location['is_error'] == '1' ) {
			
			// not much else we can do here if we get an error...
			wp_die( 'create_civi_loc_block 1: ' . $location['error_message'] );
			
		}
		
		// --<
		return $location;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: get the existing participant role for a post, but fall back
	 * to the default as set on the admin screen. fall back to false otherwise
	 * @param array $msg
	 * @return string
	 */
	public function get_participant_role( $post = null ) {
		
		// init with impossible ID
		$existing_id = false;
		
		// do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_role' );
		
		// did we get one?
		if ( $default !== '' AND is_numeric( $default ) ) {
			
			// override with default value
			$existing_id = absint( $default );
			
		}
		
		// if we have a post
		if ( isset( $post ) AND is_object( $post ) ) {
			
			// get stored value
			$stored_id = $this->plugin->eo->get_event_role( $post->ID );
			
			// did we get one?
			if ( $stored_id !== '' AND is_numeric( $stored_id ) AND $stored_id > 0 ) {
			
				// override with stored value
				$existing_id = absint( $stored_id );
			
			}
			
		}
		
		// --<
		return $existing_id;
		
	}
	
	
	
	/**
	 * @description: get all participant roles
	 * @param array $msg
	 * @return string
	 */
	public function get_participant_roles( $post = null ) {
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// first, get participant_role option_group ID
		$opt_group = array(
			'version' =>'3', 
			'name' =>'participant_role'
		);
		$participant_role = civicrm_api( 'OptionGroup', 'getsingle', $opt_group );
		
		// next, get option_values for that group
		$opt_values = array(
			'version' =>'3',
			'is_active' => 1,
			'option_group_id' => $participant_role['id']
		);
		$participant_roles = civicrm_api( 'OptionValue', 'get', $opt_values );
		
		// did we get any?
		if ( $participant_roles['is_error'] == '0' AND count( $participant_roles['values'] ) > 0 ) {
			
			// --<
			return $participant_roles;
			
		}
		
		// --<
		return false;
		
	}
	
	
	
	/**
	 * @description: builds a form element for Participant Roles
	 * @param object $user the WP user object
	 * @param object $civi_contact the Civi Contact object
	 * @return array $opts
	 */
	public function get_participant_roles_select( $post = null ) {
	
		// init html
		$html = '';
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return $html;
		
		// first, get all participant_roles
		$all_roles = $this->get_participant_roles();
		
		// init an array
		$opts = array();
		
		// did we get any?
		if ( $all_roles['is_error'] == '0' AND count( $all_roles['values'] ) > 0 ) {
			
			// get the values array
			$roles = $all_roles['values'];
			
			// init options
			$options = array();
			
			// get existing role ID
			$existing_id = $this->get_participant_role( $post );
			
			// loop
			foreach( $roles AS $key => $role ) {
				
				// get role
				$role_id = absint( $role['value'] );
				
				// init selected
				$selected = '';
				
				// is this value the same as in the post?
				if ( $existing_id === $role_id ) {
					
					// override selected
					$selected = ' selected="selected"';
					
				}
				
				// construct option
				$options[] = '<option value="'.$role_id.'"'.$selected.'>'.esc_html( $role['label'] ).'</option>';
				
			}
			
			// create html
			$html = implode( "\n", $options );
			
		}
		
		/*
		print_r( array(
			'all_roles' => $all_roles,
			'opts' => $opts,
			'options' => $options,
			'html' => $html,
		) ); die();
		*/
		
		// return
		return $html;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: builds a form element for CiviEvent Registration
	 * @param object $post the WP event object
	 * @param string $default checkbox checked or not
	 * @return nothing
	 */
	public function get_registration( $post ) {
		
		// checkbox unticked by default
		$default = '';
		
		// sanity check
		if ( !is_object( $post ) ) return $default;
		
		// get CiviEvents for this EO event
		$civi_events = $this->plugin->db->get_eo_to_civi_correspondences( $post->ID );
		
		// did we get any?
		if ( is_array( $civi_events ) AND count( $civi_events ) > 0 ) {
			
			// get the CiviEvent
			$civi_event = $this->get_event_by_id( $civi_events[0] );
			
			//print_r( $civi_events ); die();
			
			// did we do okay?
			if ( $civi_event['is_error'] == '0' AND $civi_event['is_online_registration'] == '1' ) {
				
				// set checkbox to ticked
				$default = ' checked="checked"';
				
			}
			
		}
		
		// --<
		return $default;
	
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: update a WP user when a CiviCRM contact is updated
	 * @param string $op the type of database operation
	 * @param string $objectName the type of object
	 * @param integer $objectId the ID of the object
	 * @param object $objectRef the object
	 * @return nothing
	 */
	public function event_type_pre( $op, $objectName, $objectId, $objectRef ) {
		
		/*
		print_r( array( 
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		)); die();
		*/
		
		// target our operation
		if ( $op != 'edit' ) return;
		
		// target our object type
		if ( $objectName != 'Email' ) return;
		
	}
	
	
	
	/**
	 * @description: intercept when a CiviCRM event type is toggled
	 * @param string $op the type of database operation
	 * @param string $objectName the type of object
	 * @param integer $objectId the ID of the object
	 * @param object $objectRef the object
	 * @return nothing
	 */
	public function event_type_toggle( $recordBAO, $recordID, $isActive ) {
		
		/*
		
		[recordBAO] => CRM_Core_BAO_OptionValue
		[recordID] => 734
		[isActive] => 1
		
		trigger_error( print_r( array( 
			'recordBAO' => $recordBAO,
			'recordID' => $recordID,
			'isActive' => $isActive,
		), true ), E_USER_ERROR ); die();
		*/
		
	}
	
	
	
	/**
	 * @description: update a WP user when a CiviCRM contact is updated
	 * @param string $formName the CiviCRM form name
	 * @param object $form the CiviCRM form object
	 * @return nothing
	 */
	public function event_type_process_form( $formName, &$form ) {
		
		/*
		print_r( array(
			'formName' => $formName,
			'form' => $form,
		) ); die();
		*/
		
		// kick out if not options form
		if ( ! is_a( $form, 'CRM_Admin_Form_Options' ) ) return;
		
		// kick out if not event type form
		if ( 'event_type' != $form->getVar( '_gName' ) ) return;
		
		// inspect all values
		$type = $form->getVar( '_values' );
		
		// inspect submitted values
		$submitted_values = $form->getVar( '_submitValues' );
		
		/*
		print_r( array(
			'formName' => $formName,
			//'form' => $form,
			'type' => $type,
			'submitted_values' => $submitted_values,
		) ); die();
		*/
		
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
			
			/*
			print_r( array(
				'new_type' => $new_type,
				'old_type' => $type,
			) ); die();
			*/
			
			// unhook EO action
			remove_action( 'edit_terms', array( $this->plugin->eo, 'intercept_pre_update_term' ), 20 );
			remove_action( 'edited_term', array( $this->plugin->eo, 'intercept_pre_update_term' ), 20 );
			
			// update EO term - or create if it doesn't exist
			$result = $this->plugin->eo->update_term( $new_type, $type );
			
			// rehook EO actions
			add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 1 );
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
			
			/*
			print_r( array(
				'new_type' => $new_type,
			) ); die();
			*/
			
			// unhook EO action
			remove_action( 'created_term', array( $this->plugin->eo, 'intercept_create_term' ), 20 );
			
			// create EO term
			$result = $this->plugin->eo->create_term( $new_type );
			
			// rehook EO actions
			add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );
			
		}
		
	}
	
	
	
	/*
	 * @description: update a CiviEvent event type
	 * @param object $new_term the new EO event category term
	 * @param object $old_term the EO event category term as it was before update
	 * @return ID $type_id the CiviCRM Event Type ID (or false on failure)
	 */
	public function update_event_type( $new_term, $old_term = null ) {
		
		// sanity check
		if ( !is_object( $new_term ) ) return false;
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
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
			
			// trigger update
			$params['description'] = $new_term->description;
		
		}
		
		// if we're updating a term
		if ( !is_null( $old_term ) ) {
			
			// get existing event type ID
			$type_id = $this->get_event_type_id( $old_term );
			
		} else {
			
			// get matching event type ID
			$type_id = $this->get_event_type_id( $new_term );
			
		}
		
		// error check
		if ( $type_id !== false ) {
			
			// trigger update
			$params['id'] = $type_id;
			
		}
		
		// create the event type
		$type = civicrm_api( 'option_value', 'create', $params );
		
		// error check
		if ( $type['is_error'] == '1' ) {
			
			// --<
			return false;
			
			/*
			trigger_error( print_r( array( 
				'method' => 'update_event_type',
				//'params' => $params,
				'type' => $type,
			), true ), E_USER_ERROR ); die();
			*/
			
		}
		
		// --<
		return $type_id;
		
	}
	
	
	
	/*
	 * @description: delete a CiviEvent event type
	 * @param object $term the EO event category term
	 * @return bool true on success, false on failure
	 */
	public function delete_event_type( $term ) {
		
		// sanity check
		if ( !is_object( $term ) ) return false;
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;
		
		// get ID of event type to delete
		$type_id = $this->get_event_type_id( $term );
		
		// error check
		if ( $type_id === false ) return false;
		
		// define event type
		$params = array( 
			'version' => 3,
			'id' => $type_id,
		);
		
		// create the event type
		$result = civicrm_api( 'option_value', 'delete', $params );
		
		//print_r( array( 'result' => $result ) ); die();
		
		// error check
		if ( $result['is_error'] == '1' ) return false;
		
		// --<
		return $result;
	
	}
	
	
	
	/*
	 * @description: get a CiviEvent event type by term
	 * @return array $types CiviCRM API return array (or false on failure)
	 */
	public function get_event_type_id( $term ) {
		
		// if we fail to init CiviCRM...
		if ( ! $this->is_active() ) return false;
		
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
		
		// error check
		if ( $type['is_error'] == '1' ) {
			
			// --<
			return false;
			
			/*
			trigger_error( print_r( array( 
				'method' => 'get_event_type_id',
				'params' => $types_params,
				'type' => $type,
			), true ), E_USER_ERROR ); die();
			*/
			
		}
		
		// sanity check
		if ( isset( $type['id'] ) AND is_numeric( $type['id'] ) AND $type['id'] > 0 ) return $type['id'];
		
		// if all the above fails
		return false;
		
	}
	
	
	
	/*
	 * @description: get a CiviEvent event type value by type ID
	 * @return array $types CiviCRM API return array (or false on failure)
	 */
	public function get_event_type_value( $type_id ) {
		
		// if we fail to init CiviCRM...
		if ( ! $this->is_active() ) return false;
		
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
		
		// error check
		if ( $type['is_error'] == '1' ) {
			
			trigger_error( print_r( array( 
				'method' => 'get_event_type_value',
				'params' => $types_params,
				'type' => $type,
			), true ), E_USER_ERROR ); die();
			
		}
		
		// sanity check
		if ( isset( $type['value'] ) AND is_numeric( $type['value'] ) AND $type['value'] > 0 ) return $type['value'];
		
		// if all the above fails
		return false;
		
	}
	
	
	
	/*
	 * @description: get all CiviEvent event types.
	 * @return array $types CiviCRM API return array (or false on failure)
	 */
	public function get_event_types() {
		
		// if we fail to init CiviCRM...
		if ( ! $this->is_active() ) return false;
		
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
		if ( $types['is_error'] == '1' ) return false;
		
		// --<
		return $types;
		
	}
	
	
	
	/*
	 * @description: get all CiviEvent event types formatted as a dropdown list
	 * @return array $types CiviCRM API return array (or false on failure)
	 */
	public function get_event_types_select() {
		
		// init return
		$html = '';
		
		// init CiviCRM or die
		if ( ! $this->is_active() ) return $html;
		
		// init an array
		$opts = array();
		
		// get all event types
		$result = $this->get_event_types();
		
		// did we get any?
		if ( $result['is_error'] == '0' AND count( $result['values'] ) > 0 ) {
			
			// get the values array
			$types = $result['values'];
			
			// init options
			$options = array();
			
			// get existing type ID
			$existing_id = $this->get_default_event_type( $post );
			
			// loop
			foreach( $types AS $key => $type ) {
				
				// get type
				$type_id = absint( $type['value'] );
				
				// init selected
				$selected = '';
				
				// is this value the same as in the post?
				if ( $existing_id === $type_id ) {
					
					// override selected
					$selected = ' selected="selected"';
					
				}
				
				// construct option
				$options[] = '<option value="'.$type_id.'"'.$selected.'>'.esc_html( $type['label'] ).'</option>';
				
			}
			
			// create html
			$html = implode( "\n", $options );
			
		}
		
		/*
		print_r( array(
			'result' => $result,
			'opts' => $opts,
			'options' => $options,
			'html' => $html,
		) ); die();
		*/
		
		// return
		return $html;
		
	}
	
	
	
	/**
	 * @description: get the default event type for a post, but fall back
	 * to the default as set on the admin screen, fall back to false otherwise
	 * @param array $msg
	 * @return string
	 */
	public function get_default_event_type( $post = null ) {
		
		// init with impossible ID
		$existing_id = false;
		
		// do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_type' );
		
		// did we get one?
		if ( $default !== '' AND is_numeric( $default ) ) {
			
			// override with default value
			$existing_id = absint( $default );
			
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
				
			}
		
		}
		
		// --<
		return $existing_id;
		
	}
	
	
	
	/*
	 * @description: get the CiviEvent event_types option group ID. Multiple calls 
	 * to the db are avoided by setting the static variable.
	 * @return array $types CiviCRM API return array (or false on failure)
	 */
	public function get_event_types_optgroup_id() {
		
		// init
		static $optgroup_id;
		
		// do we have it?
		if ( !isset( $optgroup_id ) ) {
			
			// if we fail to init CiviCRM...
			if ( ! $this->is_active() ) {
				
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






