<?php /*
--------------------------------------------------------------------------------
CiviCRM_WP_Event_Organiser_EO_Venue Class
--------------------------------------------------------------------------------
*/

class CiviCRM_WP_Event_Organiser_EO_Venue {
	
	/** 
	 * properties
	 */
	
	// parent object
	public $plugin;
	
	
	
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
		
		// intercept create venue
		add_action( 'eventorganiser_insert_venue', array( $this, 'insert_venue' ), 10, 1 );
		
		// intercept save venue
		add_action( 'eventorganiser_save_venue', array( $this, 'save_venue' ), 10, 1 );
		
		// intercept term deletion (before delete venue)
		add_action( 'delete_term', array( $this, 'delete_venue_term' ), 20, 4 );
		add_action( 'delete_event-venue', array( $this, 'delete_venue' ), 20, 3 );
		
		// intercept after delete venue
		add_action( 'eventorganiser_delete_venue', array( $this, 'deleted_venue' ), 10, 1 );
		
		// add our venue meta box
		add_action( 'add_meta_boxes', array( $this, 'venue_meta_box' ) );
		
		// filter terms after EO does
		add_filter( 'wp_get_object_terms', array( $this, 'update_venue_meta' ), 20, 4 );
		
		// intercept terms after EO does
		add_filter( 'get_terms', array( $this, 'update_venue_meta_cache' ), 20, 2 );
		add_filter( 'get_event-venue', array( $this, 'update_venue_meta_cache' ), 20, 2 );
		
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
	 * @description: intercept insert venue
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	public function insert_venue( $venue_id ) {
		
		// check permissions
		if ( !$this->_allow_venue_edit() ) return;
		
		// check nonce
		check_admin_referer( 'civi_eo_venue_meta_save', 'civi_eo_nonce_field' );
		
		// save custom EO components
		$this->_save_venue_components( $venue_id );
		
		// get full venue
		$venue = eo_get_venue_by( 'id', $venue_id );
		
		// create CiviEvent location
		$location = $this->plugin->civi->update_location( $venue );
		
		// store loc_block ID
		$this->store_civi_location( $venue_id, $location );
		
	}
	
	
	
	/**
	 * @description: intercept save venue
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	public function save_venue( $venue_id ) {
		
		// check permissions
		if ( !$this->_allow_venue_edit() ) return;
	
		// check nonce
		check_admin_referer( 'civi_eo_venue_meta_save', 'civi_eo_nonce_field' );
		
		// save custom EO components
		$this->_save_venue_components( $venue_id );
		
		// get full venue
		$venue = eo_get_venue_by( 'id', $venue_id );
		
		// update CiviEvent location
		$location = $this->plugin->civi->update_location( $venue );
		
	}
	
	
	
	/**
	 * @description: intercept before delete venue term
	 * @param object $term the term object of the venue
	 * @param int $tt_id the numeric ID of the venue term taxonomy
	 * @param string $taxonomy the deleted term's taxonomy name
	 * @param object $deleted_term the deleted term object of the venue
	 * @return nothing
	 */
	public function delete_venue_term( $term, $tt_id, $taxonomy, $deleted_term ) {
		
		// sanity checks
		if ( !is_object( $deleted_term ) ) return;
		if ( is_array( $taxonomy ) && !in_array( 'event-venue', $taxonomy ) ) return;
		if ( !is_array( $taxonomy ) && $taxonomy != 'event-venue' ) return;
		
		// delete anything associated with this venue
		$this->delete_venue_meta( $deleted_term );
		
	}
	
	
	
	/**
	 * @description: intercept before delete venue term by 'delete_$taxonomy' hook
	 * @param object $term the term object of the venue
	 * @param int $tt_id the numeric ID of the venue term taxonomy
	 * @param object $deleted_term the deleted term object of the venue
	 * @return nothing , $term, $tt_id, $taxonomy, $deleted_term );
	 */
	public function delete_venue( $term, $tt_id, $deleted_term ) {
		
		// sanity check
		if ( !is_object( $deleted_term ) ) return;
		
		// delete anything associated with this venue
		$this->delete_venue_meta( $deleted_term );
		
	}
	
	
	
	/**
	 * @description: delete anything associated with this venue
	 * @param object $deleted_term the term object of the venue
	 * @return nothing
	 */
	public function delete_venue_meta( $deleted_term ) {
		
		// only do this once
		static $term_deleted;
		if ( isset( $term_deleted ) AND $term_deleted === $deleted_term->term_id ) return;
		
		// sanity check
		if ( !is_object( $deleted_term ) ) return;
		
		// get venue ID
		$venue_id = $deleted_term->term_id;
		
		// get all remaining venue meta
		$venue_meta = eo_get_venue_meta( $venue_id, '', false );
		
		/*
		print_r( array(
			'venue_id' => $venue_id,
			'venue_meta' => $venue_meta,
		) ); die();
		*/
		
		// did we get a Civi location ID?
		if ( 
			isset( $venue_meta['_civi_loc_id'] ) 
			AND
			is_array( $venue_meta['_civi_loc_id'] ) 
			AND
			count( $venue_meta['_civi_loc_id'] ) === 1
		) {
			
			// delete CiviEvent location
			$this->plugin->civi->delete_location_by_id( $venue_meta['_civi_loc_id'][0] );
			
		}
		
		// delete components
		$this->_delete_venue_components( $venue_id );
		
		// set flag
		$term_deleted = $deleted_term->term_id;
		
	}
	
	
	
	/**
	 * @description: intercept after delete venue
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	public function deleted_venue( $venue_id ) {
		
		// check permissions
		if ( !$this->_allow_venue_edit() ) return;
		
		// delete components
		$this->_delete_venue_components( $venue_id );
		
		/*
		$venue_meta = eo_get_venue_meta( $venue_id, '', false );
		
		print_r( array(
			'venue_id' => $venue_id,
			'venue_meta' => $venue_meta,
		) ); die();
		*/
		
	}
	
	
	
	/**
	 * @description: create an EO venue given a CiviEvent location
	 * @param array $location the CiviEvent location data
	 * @return nothing
	 */
	public function create_venue( $location ) {
		
		// check permissions
		if ( !$this->_allow_venue_edit() ) return false;
		
		// construct name
		$name = !empty( $location['address']['street_address'] ) ? 
				$location['address']['street_address'] : 
				'Untitled venue';
		
		// construct args
		$args = array(
			//'description' => $location['description'], // can't do county yet
			'address' => $location['address']['street_address'],
			'city' => $location['address']['city'],
			//'state' => $location['address']['county'], // can't do county yet
			'postcode' => $location['address']['postal_code'],
			//'country' => $location['address']['country'], // can't do country yet
			'latitude' => $location['address']['geo_code_1'],
			'longtitude' => $location['address']['geo_code_2'],
		);
		
		// remove actions to prevent recursion
		remove_action( 'eventorganiser_insert_venue', array( $this, 'insert_venue' ), 10 );
		remove_action( 'eventorganiser_save_venue', array( $this, 'save_venue' ), 10 );
		
		// insert venue
		$result = eo_insert_venue( $name, $args );
		
		// add actions again
		add_action( 'eventorganiser_insert_venue', array( $this, 'insert_venue' ), 10, 1 );
		add_action( 'eventorganiser_save_venue', array( $this, 'save_venue' ), 10, 1 );
		
		// if not an error
		if ( is_wp_error( $result ) OR !isset( $result['term_id'] ) ) {
			
			print_r( array(
				'method' => 'create_venue',
				'result' => $result,
			) ); die();
			
		}
		
		// create venue meta data
		
		// init email to site default
		$email = get_option( 'admin_email' );
		
		// do we have an alternative in CiviCRM?
		if ( isset( $location['email']['email'] ) ) {
			
			// override
			$email = $location['email']['email'];
			
		}
		
		// store email in meta
		eo_update_venue_meta( $result['term_id'],  '_civi_email', esc_sql( $email ) );
		
		// do we have an alternative in CiviCRM?
		if ( isset( $location['phone']['phone'] ) ) {
			
			// store phone in meta
			eo_update_venue_meta( $result['term_id'],  '_civi_phone', esc_sql( $location['phone']['phone'] ) );
			
		}
		
		// store location ID
		eo_update_venue_meta( $result['term_id'],  '_civi_loc_id', $location['id'] );
		
		/*
		print_r( array(
			'action' => 'create_venue',
			'location' => $location,
			'result' => $result,
		) ); die();
		*/
		
		// --<
		return $result['term_id'];
	
	}
	
	
	
	/**
	 * @description: update an EO venue given a CiviEvent location
	 * @param array $location the CiviEvent location data
	 * @return nothing
	 */
	public function update_venue( $location ) {
		
		// check permissions
		if ( !$this->_allow_venue_edit() ) return;
		
		// does this location have an existing venue?
		$venue_id = $this->get_venue_id( $location );
		
		print_r( array(
			'venue_id' => $venue_id,
		) );
		
		// if we get one
		if ( $venue_id === false ) {
			
			// create venue
			$result = $this->create_venue( $location );
			
			// --<
			return $result['term_id'];
			
		} else {
			
			// get full venue
			$venue = eo_get_venue_by( 'id', $venue_id );
			
			// did we get one?
			if ( ! is_object( $venue ) ) {
				
				// create venue
				$result = $this->create_venue( $location );
				
				// --<
				return $result['term_id'];
				
			}
			
			// construct args
			$args = array(
				'name' => $venue->name, // can't update name yet
				//'description' => $location['description'], // can't do county yet
				'address' => $location['address']['street_address'],
				'city' => $location['address']['city'],
				//'state' => $location['address']['county'], // can't do county yet
				'postcode' => $location['address']['postal_code'],
				//'country' => $location['address']['country'], // can't do country yet
				'latitude' => $location['address']['geo_code_1'],
				'longtitude' => $location['address']['geo_code_2'],
			);
			
			// remove actions to prevent recursion
			remove_action( 'eventorganiser_save_venue', array( $this, 'save_venue' ), 10 );
			
			// insert venue
			$result = eo_update_venue( $venue_id, $args );
			
			/*
			print_r( array( 
				'venue_id' => $venue_id, 
				'venue' => $venue, 
				'result' => $result, 
				'args' => $args 
			) ); //die();
			*/
			
			// add actions again
			add_action( 'eventorganiser_save_venue', array( $this, 'save_venue' ), 10, 1 );
			
			// if not an error
			if ( is_wp_error( $result ) OR !isset( $result['term_id'] ) ) return false;
			
			// create venue meta data
			eo_update_venue_meta( $result['term_id'],  '_civi_email', esc_sql( $location['email']['email'] ) );
			eo_update_venue_meta( $result['term_id'],  '_civi_phone', esc_sql( $location['phone']['phone'] ) );
			eo_update_venue_meta( $result['term_id'],  '_civi_loc_id', $location['id'] );
			
		}
		
		/*
		print_r( array(
			'action' => 'update_venue',
			'location' => $location,
			'result' => $result,
		) ); die();
		*/
		
		// --<
		return $result['term_id'];
		
	}
	
	
	
	/**
	 * @description: get an EO venue ID given a CiviEvent location
	 * @param array $location the CiviEvent location data
	 * @return nothing
	 */
	public function get_venue_id( $location ) {
		
		// ---------------------------------------------------------------------
		// first, see if we have a matching ID in the venue's meta table
		// ---------------------------------------------------------------------
		
		// access db
		global $wpdb;
		
		// to avoid the pro plugin, hit the db directly
		$sql = $wpdb->prepare(
			"SELECT eo_venue_id FROM $wpdb->eo_venuemeta WHERE 
			meta_key = '_civi_loc_id' AND 
			meta_value = %d", 
			$location['id'] 
		);
		
		// this should return a value
		$venue_id = $wpdb->get_var( $sql );
		
		///*
		print_r( array(
			//'location' => $location,
			'sql' => $sql,
			'venue_id' => $venue_id,
		) ); //die();
		//*/
		
		// if we get one, return it
		if ( isset( $venue_id ) AND !is_null( $venue_id ) AND $venue_id > 0 ) return $venue_id;
		
		
		
		// ---------------------------------------------------------------------
		// next, see if we have an identical location
		// ---------------------------------------------------------------------
		
		// do we have geo data?
		if (
			isset( $location['address']['geo_code_1'] )
			AND
			isset( $location['address']['geo_code_2'] )
		) {
			
			// to avoid the pro plugin, hit the db directly...
			// this could use some refinement from someone better at SQL than me
			$sql = $wpdb->prepare(
				"SELECT eo_venue_id FROM $wpdb->eo_venuemeta 
				WHERE 
					( meta_key = '_lat' AND meta_value = '%f' ) 
				AND
				eo_venue_id = ( 
					SELECT eo_venue_id FROM $wpdb->eo_venuemeta WHERE 
					( meta_key = '_lng' AND meta_value = '%f' ) 
				)",
				floatval( $location['address']['geo_code_1'] ),
				floatval( $location['address']['geo_code_2'] )
			);
			
			// this should return a value
			$venue_id = $wpdb->get_var( $sql );
			
			///*
			print_r( array(
				//'location' => $location,
				'sql' => $sql,
				'venue_id' => $venue_id,
			) ); //die();
			//*/
			
			// if we get one, return it
			if ( isset( $venue_id ) AND !is_null( $venue_id ) AND $venue_id > 0 ) return $venue_id;
			
		}
		
		
		
		// ---------------------------------------------------------------------
		// lastly, see if we have an identical street address
		// ---------------------------------------------------------------------
		
		// do we have street data?
		if ( isset( $location['address']['street_address'] ) ) {
		
			$sql = $wpdb->prepare(
				"SELECT eo_venue_id FROM $wpdb->eo_venuemeta WHERE 
				meta_key = '_address' AND 
				meta_value = %s", 
				$location['address']['street_address'] 
			);
			
			// get value
			$venue_id = $wpdb->get_var( $sql );
			
			///*
			print_r( array(
				//'location' => $location,
				'sql' => $sql,
				'venue_id' => $venue_id,
			) ); //die();
			//*/
			
			// if we get one, return it
			if ( isset( $venue_id ) AND !is_null( $venue_id ) AND $venue_id > 0 ) return $venue_id;
			
		}
		
		
		
		// --<
		return false;
		
	}
	
	
	
	/**
	 * @description: register venue meta box
	 * @return nothing 
	 */
	function venue_meta_box() {
		
		// create it
		add_meta_box( 
			'civi_eo_venue_metabox', 
			'CiviCRM Settings', 
			array( $this, 'venue_meta_box_render' ), 
			'event_page_venues', 
			'side', 
			'high' 
		);
		
	}
	
	
	
	/**
	 * @description: define venue meta box
	 * @return nothing 
	 */
	function venue_meta_box_render( $venue ) {
		
		// get meta box data
		$email = eo_get_venue_meta( $venue->term_id, '_civi_email', true );
		$phone = eo_get_venue_meta( $venue->term_id, '_civi_phone', true );
		
		// add nonce
		wp_nonce_field( 'civi_eo_venue_meta_save', 'civi_eo_nonce_field' );
		
		echo '
		<p>
		<label for="civi_eo_venue_email">Email contact:</label> 
		<input type="text" id="civi_eo_venue_email" name="civi_eo_venue_email" value="'.esc_attr( $email ).'" />
		</p>
		
		<p>
		<label for="civi_eo_venue_phone">Phone contact:</label> 
		<input type="text" id="civi_eo_venue_phone" name="civi_eo_venue_phone" value="'.esc_attr( $phone ).'" />
		</p>
		';
		
	}



	/*
	----------------------------------------------------------------------------
	The next two methods have been adapted from Event Organiser so we can add 
	our custom data to the venue object when standard EO functions are called
	----------------------------------------------------------------------------
	*/
	
	/**
	 * @description updates venue meta cache when an event's venue is retrieved
	 * @param array $terms array of terms
	 * @param array $post_ids array of post ids
	 * @param string $taxonomies should be (an array containing) 'event-venue'
	 */
	function update_venue_meta( $terms, $post_ids, $taxonomies, $args ) {
		
		// passes taxonomies as a string inside quotes...
		$taxonomies = explode( ',', trim( $taxonomies, "\x22\x27" ) );
		return $this->update_venue_meta_cache( $terms, $taxonomies );
		
	}
	
	
	
	/**
	 * @description updates venue meta cache when event venues are retrieved
	 * @param array $terms array of terms
	 * @param string $tax ahould be (an array containing) 'event-venue'
	 * @param array array of event-venue terms,
	 */
	function update_venue_meta_cache( $terms, $tax ) {

		if ( is_array( $tax ) && !in_array( 'event-venue', $tax ) ) {
			return $terms;
		}
		if ( !is_array( $tax ) && $tax != 'event-venue' ) {
			return $terms;
		}
		
		$single = false;
		if ( ! is_array($terms) ) {
			$single = true;
			$terms = array( $terms );
		}
		
		if( empty( $terms ) )
			   return $terms;
		
		// check if its array of terms or term IDs
		$first_element = reset( $terms );
		if ( is_object( $first_element ) ){
			$term_ids = wp_list_pluck( $terms, 'term_id' );
		} else {
			$term_ids = $terms;
		}
		
		update_meta_cache( 'eo_venue', $term_ids );
		
		// loop through
		foreach( $terms AS $term ) {
			
			// skip if not useful
			if( !is_object( $term ) ) continue;
			
			// get id
			$term_id = (int) $term->term_id;
			
			if( !isset( $term->venue_civi_email ) ) {
				$term->venue_civi_email = eo_get_venue_meta( $term_id, '_civi_email', true );
			}
			
			if( !isset( $term->venue_civi_phone ) ) {
				$term->venue_civi_phone = eo_get_venue_meta( $term_id, '_civi_phone', true );
			}
			
			if( !isset( $term->venue_civi_id ) ) {
				$term->venue_civi_id = eo_get_venue_meta( $term_id, '_civi_loc_id', true );
			}
			
		}
		
		if( $single ) return $terms[0];
		
		return $terms;
	}
	
	
	
	/**
	 * @description: store a CiviEvent loc_block ID for a given EO venue ID
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	public function store_civi_location( $venue_id, $civi_loc_block ) {
		
		// $civi_loc_block comes in standard API return format
		if ( absint( $civi_loc_block['count'] ) == 1 ) {
			
			// update venue meta
			eo_update_venue_meta( $venue_id,  '_civi_loc_id', absint( $civi_loc_block['id'] ) );
			
		}
		
	}
	
	
	
	/**
	 * @description: get a CiviEvent loc_block ID for a given EO venue ID
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	public function get_civi_location( $venue_id ) {
		
		// get venue meta data
		$loc_block_id = eo_get_venue_meta( $venue_id, '_civi_loc_id', true );
		
		// --< 
		return $loc_block_id;
		
	}
	
	
	
	/**
	 * @description: clear a CiviEvent loc_block ID for a given EO venue ID
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	public function clear_civi_location( $venue_id ) {
		
		// update venue meta
		eo_delete_venue_meta( $venue_id,  '_civi_loc_id' );
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: intercept save venue
	 * @return bool
	 */
	public function _allow_venue_edit() {
		
		// check permissions
		$tax = get_taxonomy( 'event-venue' );
		
		// return permission
		return current_user_can( $tax->cap->edit_terms );
		
	}
	
	

	/**
	 * @description: save custom components that sync with CiviCRM
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	private function _save_venue_components( $venue_id ) {
		
		// save email
		$this->_update_venue_email( $venue_id );
		
		// save phone
		$this->_update_venue_phone( $venue_id );
		
	}
	
	
	
	/**
	 * @description: delete custom components that sync with CiviCRM
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	private function _delete_venue_components( $venue_id ) {
		
		// EO garbage-collects when it deletes a venue, so no need
		
	}
	
	
	
	/**
	 * @description: update venue email value
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	public function _update_venue_email( $venue_id ) {
		
		// kick out if not set
		if ( !isset( $_POST['civi_eo_venue_email'] ) ) return;
	
		// retrieve meta value
		$value = sanitize_email( $_POST['civi_eo_venue_email'] );
		
		// validate as email address
		if ( !is_email( $value ) ) return;
		
		// update venue meta
		eo_update_venue_meta( $venue_id,  '_civi_email', esc_sql( $value ) );
		
	}
	
	
	
	/**
	 * @description: update venue phone value
	 * @param int $venue_id the numeric ID of the venue
	 * @return nothing
	 */
	public function _update_venue_phone( $venue_id ) {
		
		// retrieve meta value
		$value = $_POST['civi_eo_venue_phone'];
		
		// use CiviCRM to validate?
		
		// update venue meta
		eo_update_venue_meta( $venue_id,  '_civi_phone', esc_sql( $value ) );
		
	}
	
	
	
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






