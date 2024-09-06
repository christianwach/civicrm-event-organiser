<?php
/**
 * CiviCRM Location Class.
 *
 * Handles interactions with CiviCRM Locations.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Location Class.
 *
 * A class that encapsulates interactions with CiviCRM Locations.
 *
 * @since 0.7
 */
class CEO_CiviCRM_Location {

	/**
	 * Plugin object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CEO_CiviCRM
	 */
	public $civicrm;

	/**
	 * Constructor.
	 *
	 * @since 0.7
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin  = $parent->plugin;
		$this->civicrm = $parent;

		// Add CiviCRM hooks when parent is loaded.
		add_action( 'ceo/civicrm/loaded', [ $this, 'register_hooks' ] );

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.7
	 */
	public function register_hooks() {

		/*
		// Add callbacks for LocBlock updates.
		add_action( 'civicrm_post', [ $this, 'locblock_edited' ], 10, 4 );
		*/

	}

	/**
	 * Act when a LocBlock has been updated.
	 *
	 * Disabled for now.
	 *
	 * @since 0.7.1
	 *
	 * @param string  $op The type of database operation.
	 * @param string  $object_name The type of object.
	 * @param integer $object_id The ID of the object.
	 * @param object  $object_ref The object.
	 */
	public function locblock_edited( $op, $object_name, $object_id, $object_ref ) {

		// Target our operation.
		if ( 'edit' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'LocBlock' !== $object_name ) {
			return;
		}

		// Kick out if not LocBlock object.
		if ( ! ( $object_ref instanceof CRM_Core_DAO_LocBlock ) ) {
			return;
		}

		// Get the full LocBlock data.
		$location = $this->get_location_by_id( $object_id );

		// Get corresponding Event Organiser Venue ID.
		$venue_id = $this->plugin->wordpress->eo_venue->get_venue_id( $location );
		if ( empty( $venue_id ) ) {
			return;
		}

		// Build params to get the CiviCRM Events with this LocBlock.
		$params = [
			'version'      => 3,
			'sequential'   => 1,
			'loc_block_id' => $location['id'],
		];

		// Call the API.
		$result = civicrm_api( 'Event', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return;
		}

		// Bail if any of the Events are not part of a sequence.
		foreach ( $result['values'] as $event ) {
			if ( ! $this->plugin->mapping->is_civi_event_in_eo_sequence( $event['id'] ) ) {
				return;
			}
		}

		// Update the Venue.
		$venue_id = $this->plugin->wordpress->eo_venue->update_venue( $location );

	}

	/**
	 * Updates a CiviCRM Event Location given an Event Organiser Venue.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $venue The Event Organiser Venue data.
	 * @return array|bool $location The CiviCRM Event Location data, or false on failure.
	 */
	public function update_location( $venue ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Get existing Location.
		$location = $this->get_location( $venue );

		// If this Venue already has a CiviCRM Event Location.
		if ( false !== $location ) {

			// Use it if there's a record on the Event Organiser side.
			if ( ! isset( $venue->venue_civi_id ) ) {
				$venue->venue_civi_id = (int) $location['id'];
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
	 * Delete a CiviCRM Event Location given an Event Organiser Venue.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $venue The Event Organiser Venue data.
	 * @return array $result CiviCRM API result data.
	 */
	public function delete_location( $venue ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Init return.
		$result = false;

		// Get existing Location.
		$location = $this->get_location( $venue );

		// Delete Location if we get one.
		if ( false !== $location ) {
			$result = $this->delete_location_by_id( $location['id'] );
		}

		// --<
		return $result;

	}

	/**
	 * Delete a CiviCRM Event Location given a Location ID.
	 *
	 * Be aware that only the CiviCRM LocBlock is deleted - not the items that
	 * constitute it. Email, Phone and Address will still exist but not be
	 * associated as a LocBlock.
	 *
	 * The next iteration of this plugin should probably refine the LocBlock
	 * sync process to take this into account.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $location_id The numeric ID of the CiviCRM Location.
	 * @return array $result CiviCRM API result data.
	 */
	public function delete_location_by_id( $location_id ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Construct delete array.
		$params = [
			'version' => 3,
			'id'      => $location_id,
		];

		// Delete via API.
		$result = civicrm_api( 'LocBlock', 'delete', $params );

		// Log failure and return boolean false.
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
	 * Gets a CiviCRM Event Location given an Event Organiser Venue.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue data.
	 * @return bool|array $location The CiviCRM Event Location data, or false if not found.
	 */
	public function get_location( $venue ) {

		// Init return.
		$location = false;

		// Bail if no CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $location;
		}

		// ---------------------------------------------------------------------
		// Try by LocBlock ID stored in Venue Term meta.
		// ---------------------------------------------------------------------
		$loc_block_id = 0;
		if ( ! empty( $venue->venue_civi_id ) && is_numeric( $venue->venue_civi_id ) ) {
			$loc_block_id = $venue->venue_civi_id;
		}

		// Construct get-by-id array.
		$params = [
			'version' => 3,
			'id'      => $loc_block_id,
			'return'  => 'all',
		];

		// Call API.
		$result = civicrm_api( 'LocBlock', 'get', $params );

		// Log on failure and bail.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'Could not get CiviCRM Location by ID', 'civicrm-event-organiser' ),
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $location;
		}

		// Return the result if we get one.
		if ( (int) $result['count'] > 0 && is_array( $result['values'] ) ) {
			$location = array_shift( $result['values'] );
			return $location;
		}

		// ---------------------------------------------------------------------
		// Now try by Location.
		// ---------------------------------------------------------------------

		/*
		// If we have a Location.
		if ( ! empty( $venue->venue_lat ) && ! empty( $venue->venue_lng ) ) {

			// Construct get-by-geolocation array.
			$params = [
				'version' => 3,
				'address' => [
					'geo_code_1' => $venue->venue_lat,
					'geo_code_2' => $venue->venue_lng,
				],
				'return' => 'all',
			];

			// Call API.
			$location = civicrm_api( 'LocBlock', 'get', $params );

			// Log error and return boolean false.
			if ( ! empty( $location['is_error'] ) && 1 === (int) $location['is_error'] ) {
				$e = new Exception;
				$trace = $e->getTraceAsString();
				$log   = [
					'method' => __METHOD__,
					'message' => __( 'Could not get CiviCRM Location by Lat/Long', 'civicrm-event-organiser' ),
					'civicrm' => $location['error_message'],
					'params' => $params,
					'backtrace' => $trace,
				];
				$this->plugin->log_error( $log );
				return false;
			}

			// Return the result if we get one.
			if ( (int) $location['count'] > 0 && is_array( $location['values'] ) ) {

				$e = new Exception;
				$trace = $e->getTraceAsString();
				$log   = [
					'method' => __METHOD__,
					'procedure' => 'found by location',
					'venue' => $venue,
					'params' => $params,
					'location' => $location,
					'backtrace' => $trace,
				];
				$this->plugin->log_error( $log );

				// Found by Location.
				return array_shift( $location['values'] );

			}

		}
		*/

		// Fallback.
		return false;

	}

	/**
	 * Get all CiviCRM Event Locations.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @return array $locations The array of CiviCRM Event Location data.
	 */
	public function get_all_locations() {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Construct Locations array.
		$params = [
			'version' => 3,
			'return'  => 'all', // Return all data.
			'options' => [
				'limit' => 0, // Get all Locations.
			],
		];

		// Call API.
		$locations = civicrm_api( 'LocBlock', 'get', $params );

		// Log failure and return boolean false.
		if ( ! empty( $locations['is_error'] ) && 1 === (int) $locations['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => $locations['error_message'],
				'params'    => $params,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// --<
		return $locations;

	}

	/**
	 * WARNING: deletes all CiviCRM Event Locations.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 */
	public function delete_all_locations() {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Get all Locations.
		$locations = $this->get_all_locations();

		// Start again.
		foreach ( $locations['values'] as $location ) {

			// Construct delete array.
			$params = [
				'version' => 3,
				'id'      => $location['id'],
			];

			// Delete via API.
			$result = civicrm_api( 'LocBlock', 'delete', $params );

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
			}

		}

	}

	/**
	 * Gets a CiviCRM Event Location given an CiviCRM Event Location ID.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $loc_id The CiviCRM Event Location ID.
	 * @return array $location The CiviCRM Event Location data.
	 */
	public function get_location_by_id( $loc_id ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Construct get-by-id array.
		$params = [
			'version'               => 3,
			'id'                    => $loc_id,
			'return'                => 'all',
			// Get Country and State name.
			'api.Address.getsingle' => [
				'sequential' => 1,
				'id'         => '$value.address_id',
				'return'     => [
					'country_id.name',
					'state_province_id.name',
				],
			],
		];

		// Call API ('get' returns an array keyed by the item).
		$result = civicrm_api( 'LocBlock', 'get', $params );

		// Log failure and return boolean false.
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

		// Get Location from nested array.
		$location = array_shift( $result['values'] );

		// --<
		return $location;

	}

	/**
	 * Creates (or updates) a CiviCRM Event Location given an Event Organiser Venue.
	 *
	 * The only disadvantage to this method is that, for example, if we update
	 * the Email and that Email already exists in the DB, it will not be found
	 * and associated - but rather the existing Email will be updated. Same goes
	 * for Phone. This is not a deal-breaker, but not very DRY either.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array  $location The existing CiviCRM Location data.
	 * @return array|bool $location The CiviCRM Location data, or false on failure.
	 */
	public function create_civi_loc_block( $venue, $location ) {

		// Bail if no CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Init create/update flag.
		$op = 'create';

		// Update if our Venue already has a Location.
		if ( ! empty( $venue->venue_civi_id ) && is_numeric( $venue->venue_civi_id ) ) {
			$op = 'update';
		}

		// Define initial params array.
		$params = [
			'version' => 3,
		];

		/*
		 * First, see if the LocBlock Email, Phone and Address already exist.
		 *
		 * If they don't, we need params returned that trigger their creation on
		 * the CiviCRM side. If they do, then we may need to update or delete them
		 * before we include the data in the CiviCRM API call.
		 */

		// If we have a Venue Email.
		if ( ! empty( $venue->venue_civi_email ) ) {

			// Check Email.
			$email = $this->maybe_update_email( $venue, $location, $op );

			// Skip if there's an error.
			if ( false !== $email ) {

				// If we get a new Email.
				if ( is_array( $email ) ) {

					// Add Email to params.
					$params['email'] = $email;

				} else {

					// Add existing Email ID to params.
					$params['email_id'] = $email;

				}

			}

		}

		// If we have a Phone Number.
		if ( ! empty( $venue->venue_civi_phone ) ) {

			// Check Phone record.
			$phone = $this->maybe_update_phone( $venue, $location, $op );

			// Skip if there's an error.
			if ( false !== $phone ) {

				// If we get a new Phone record.
				if ( is_array( $phone ) ) {

					// Add Phone to params.
					$params['phone'] = $phone;

				} else {

					// Add existing Phone ID to params.
					$params['phone_id'] = $phone;

				}

			}

		}

		// Check Address.
		$address = $this->maybe_update_address( $venue, $location, $op );

		// Skip if there's an error.
		if ( false !== $address ) {

			// If we get a new Address record.
			if ( is_array( $address ) ) {

				// Add Address to params.
				$params['address'] = $address;

			} else {

				// Add existing Address ID to params.
				$params['address_id'] = $address;

			}

		}

		// If our Venue has a Location, add it.
		if ( 'update' === $op ) {

			// Target our known Location - this will trigger an update.
			$params['id'] = $venue->venue_civi_id;

		}

		// Call CiviCRM API.
		$result = civicrm_api( 'LocBlock', 'create', $params );

		// Log and bail if there's an error.
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

		// Bail if there's no result array.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// The new Location is the entry in the values array.
		$location = array_pop( $result['values'] );

		/*
		 * We now need to create a dummy CiviCRM Event, or this Venue will not show
		 * up in CiviCRM...
		 */
		// $this->create_dummy_event( $location );

		// --<
		return $location;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Query Email via API and update if necessary.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array  $location The CiviCRM Location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array|bool $email_data Integer if found, array if not found, false on failure.
	 */
	private function maybe_update_email( $venue, $location = null, $op = 'create' ) {

		// Init return.
		$email_data = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $email_data;
		}

		// If the Location has an existing Email.
		if ( ! is_null( $location ) && isset( $location['email']['id'] ) ) {

			// Check by ID.
			$email_params = [
				'version' => 3,
				'id'      => (int) $location['email']['id'],
			];

		} else {

			// Check by Email values.
			$email_params = [
				'version'          => 3,
				'contact_id'       => null,
				'is_primary'       => 0,
				'location_type_id' => 1,
				'email'            => $venue->venue_civi_email,
			];

		}

		// Query CiviCRM API.
		$existing_email_data = civicrm_api( 'Email', 'get', $email_params );

		// Bail if there's an error.
		if ( ! empty( $existing_email_data['is_error'] ) && 1 === (int) $existing_email_data['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'Could not fetch CiviCRM Email.', 'civicrm-event-organiser' ),
				'params'    => $email_params,
				'result'    => $existing_email_data,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $email_data;
		}

		// Did we get one?
		if ( ! empty( $existing_email_data['values'] ) ) {

			// Get first one.
			$existing_email = array_shift( $existing_email_data['values'] );

			// Has it changed?
			if ( 'update' === $op && $existing_email['email'] !== $venue->venue_civi_email ) {

				// Add API version.
				$existing_email['version'] = 3;

				// Add "null" Contact ID as this seems to be required.
				$existing_email['contact_id'] = null;

				// Replace with updated Email.
				$existing_email['email'] = $venue->venue_civi_email;

				// Update the Email record in CiviCRM.
				$result = civicrm_api( 'Email', 'create', $existing_email );

				// Log something if there's an error.
				if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
					$e     = new Exception();
					$trace = $e->getTraceAsString();
					$log   = [
						'method'    => __METHOD__,
						'message'   => __( 'Could not update CiviCRM Email.', 'civicrm-event-organiser' ),
						'params'    => $existing_email,
						'result'    => $result,
						'backtrace' => $trace,
					];
					$this->plugin->log_error( $log );
				}

			}

			// Get its ID.
			$email_data = (int) $existing_email['id'];

			// --<
			return $email_data;

		}

		// Define new Email.
		$email_data = [
			'location_type_id' => 1,
			'email'            => $venue->venue_civi_email,
		];

		// --<
		return $email_data;

	}

	/**
	 * Query Phone via API and update if necessary.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array  $location The CiviCRM Location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array|bool $phone_data Integer if found, array if not found, or false on failure.
	 */
	private function maybe_update_phone( $venue, $location = null, $op = 'create' ) {

		// Init return.
		$phone_data = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $phone_data;
		}

		// Create numeric version of Phone Number.
		$numeric = preg_replace( '/[^0-9]/', '', $venue->venue_civi_phone );

		// If the Location has an existing Email.
		if ( ! is_null( $location ) && isset( $location['phone']['id'] ) ) {

			// Check by existing Phone ID.
			$phone_params = [
				'version' => 3,
				'id'      => (int) $location['phone']['id'],
			];

		} else {

			// Check Phone by its numeric field.
			$phone_params = [
				'version'          => 3,
				'contact_id'       => null,
				'location_type_id' => 1,
				'phone_numeric'    => $numeric,
			];

		}

		// Query API.
		$existing_phone_data = civicrm_api( 'Phone', 'get', $phone_params );

		// Bail if there's an error.
		if ( ! empty( $existing_phone_data['is_error'] ) && 1 === (int) $existing_phone_data['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'Could not fetch CiviCRM Phone.', 'civicrm-event-organiser' ),
				'params'    => $phone_params,
				'result'    => $existing_phone_data,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $phone_data;
		}

		// Did we get one?
		if ( ! empty( $existing_phone_data['values'] ) ) {

			// Get first one.
			$existing_phone = array_shift( $existing_phone_data['values'] );

			// Has it changed?
			if ( 'update' === $op && $existing_phone['phone'] !== $venue->venue_civi_phone ) {

				// Add API version.
				$existing_phone['version'] = 3;

				// Add "null" Contact ID as this seems to be required.
				$existing_phone['contact_id'] = null;

				// Replace with updated Phone Number.
				$existing_phone['phone']         = $venue->venue_civi_phone;
				$existing_phone['phone_numeric'] = $numeric;

				// Update the Phone record in CiviCRM.
				$result = civicrm_api( 'Phone', 'create', $existing_phone );

				// Log something if there's an error.
				if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
					$e     = new Exception();
					$trace = $e->getTraceAsString();
					$log   = [
						'method'    => __METHOD__,
						'message'   => __( 'Could not update CiviCRM Phone.', 'civicrm-event-organiser' ),
						'params'    => $existing_phone,
						'result'    => $result,
						'backtrace' => $trace,
					];
					$this->plugin->log_error( $log );
				}

			}

			// Get its ID.
			$phone_data = (int) $existing_phone['id'];

			// --<
			return $phone_data;

		}

		// Define new Phone.
		$phone_data = [
			'location_type_id' => 1,
			'phone'            => $venue->venue_civi_phone,
			'phone_numeric'    => $numeric,
		];

		// --<
		return $phone_data;

	}

	/**
	 * Query Address via API and update if necessary.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array  $location The CiviCRM Location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array $address_data Integer if found, array if not found, or false on failure.
	 */
	private function maybe_update_address( $venue, $location = null, $op = 'create' ) {

		// Init return.
		$address_data = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $address_data;
		}

		// If the Location has an existing Address.
		if ( ! is_null( $location ) && isset( $location['address']['id'] ) ) {

			// Check by Address ID.
			$address_params = [
				'version' => 3,
				'id'      => (int) $location['address']['id'],
			];

		} else {

			// Check Address by values.
			$address_params = [
				'version'          => 3,
				'location_type_id' => 1,
				'contact_id'       => null,
			];

			// Add Street Address if present.
			if ( ! empty( $venue->venue_address ) ) {
				$address_params['street_address'] = $venue->venue_address;
			}

			// Add City if present.
			if ( ! empty( $venue->venue_city ) ) {
				$address_params['city'] = $venue->venue_city;
			}

			// Add Postcode if present.
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

		// Query CiviCRM API.
		$existing_address_data = civicrm_api( 'Address', 'get', $address_params );

		// Bail if there's an error.
		if ( ! empty( $existing_address_data['is_error'] ) && 1 === (int) $existing_address_data['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'Could not fetch CiviCRM Address.', 'civicrm-event-organiser' ),
				'params'    => $address_params,
				'result'    => $existing_address_data,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $address_data;
		}

		// Did we get one?
		if ( ! empty( $existing_address_data['values'] ) ) {

			// Get first one.
			$existing_address = array_shift( $existing_address_data['values'] );

			// Has it changed?
			if ( 'update' === $op && $this->is_address_changed( $venue, $existing_address ) ) {

				// Add API version.
				$existing_address['version'] = 3;

				// Add "null" Contact ID as this seems to be required.
				$existing_address['contact_id'] = null;

				// Replace or clear Street Address.
				if ( ! empty( $venue->venue_address ) ) {
					$existing_address['street_address'] = $venue->venue_address;
				} else {
					$existing_address['street_address'] = '';
				}

				// Replace Address Name when it is different to Street Address.
				if ( ! empty( $venue->name ) && $existing_address['street_address'] !== $venue->name ) {
					$existing_address['name'] = $venue->name;
				}

				// Replace or clear City.
				if ( ! empty( $venue->venue_city ) ) {
					$existing_address['city'] = $venue->venue_city;
				} else {
					$existing_address['city'] = '';
				}

				// Replace or clear Postcode.
				if ( ! empty( $venue->venue_postcode ) ) {
					$existing_address['postal_code'] = $venue->venue_postcode;
				} else {
					$existing_address['postal_code'] = '';
				}

				// When we have a Country.
				if ( ! empty( $venue->venue_country ) ) {

					// Replace Country.
					$country = $this->country_get_by_name( $venue->venue_country );
					if ( ! empty( $country ) ) {
						$existing_address['country_id'] = $country['id'];
					}

					// Replace State/Province.
					if ( ! empty( $venue->venue_state ) && ! empty( $country ) ) {
						$state_province = $this->state_province_get_by_name( $venue->venue_state, $country['id'] );
						if ( ! empty( $state_province ) ) {
							$existing_address['state_province_id'] = $state_province['id'];
						}
					}

				}

				// Replace or clear geocodes.
				if ( ! empty( $venue->venue_lat ) ) {
					$existing_address['geo_code_1'] = $venue->venue_lat;
				} else {
					$existing_address['geo_code_1'] = '';
				}
				if ( ! empty( $venue->venue_lng ) ) {
					$existing_address['geo_code_2'] = $venue->venue_lng;
				} else {
					$existing_address['geo_code_2'] = '';
				}

				// Update the Address in CiviCRM.
				$result = civicrm_api( 'Address', 'create', $existing_address );

				// Log something if there's an error.
				if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
					$e     = new Exception();
					$trace = $e->getTraceAsString();
					$log   = [
						'method'    => __METHOD__,
						'message'   => __( 'Could not update CiviCRM Address.', 'civicrm-event-organiser' ),
						'params'    => $existing_address,
						'result'    => $result,
						'backtrace' => $trace,
					];
					$this->plugin->log_error( $log );
				}

			}

			// Return the existing Address ID.
			$address_data = (int) $existing_address['id'];

			// --<
			return $address_data;

		}

		// Define new Address.
		$address_data = [
			'location_type_id' => 1,
		];

		// Get Street Address if present.
		$street_address = '';
		if ( ! empty( $venue->venue_address ) ) {
			$street_address = $venue->venue_address;
		}

		// Add Address Name when it is different to Street Address.
		if ( ! empty( $venue->name ) && $street_address !== $venue->name ) {
			$address_data['name'] = $venue->name;
		}

		// Add Street Address.
		$address_data['street_address'] = $street_address;

		// Add City if present.
		if ( ! empty( $venue->venue_city ) ) {
			$address_data['city'] = $venue->venue_city;
		}

		// Add Postcode if present.
		if ( ! empty( $venue->venue_postcode ) ) {
			$address_data['postal_code'] = $venue->venue_postcode;
		}

		// When we have a Country.
		if ( ! empty( $venue->venue_country ) ) {

			// Maybe add Country.
			$country = $this->country_get_by_name( $venue->venue_country );
			if ( ! empty( $country ) ) {
				$address_data['country_id'] = $country['id'];
			}

			// Maybe add State/Province.
			if ( ! empty( $venue->venue_state ) && ! empty( $country ) ) {
				$state_province = $this->state_province_get_by_name( $venue->venue_state, $country['id'] );
				if ( ! empty( $state_province ) ) {
					$address_data['state_province_id'] = $state_province['id'];
				}
			}

		}

		// Add geocodes if present.
		if ( ! empty( $venue->venue_lat ) ) {
			$address_data['geo_code_1'] = $venue->venue_lat;
		}
		if ( ! empty( $venue->venue_lng ) ) {
			$address_data['geo_code_2'] = $venue->venue_lng;
		}

		// --<
		return $address_data;

	}

	/**
	 * Has an Address changed?
	 *
	 * It's worth noting that when there is no data for a property of a CiviCRM
	 * Location, it will not exist as an entry in the data array. This is not
	 * the case for Event Organiser Venues, whose objects always contain all
	 * properties, whether they have a value or not.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array  $address The array of CiviCRM Address data.
	 * @return bool $is_changed True if changed, false otherwise.
	 */
	private function is_address_changed( $venue, $address ) {

		// Check Address Name.
		if ( ! isset( $address['name'] ) ) {
			$address['name'] = '';
		}
		if ( $address['name'] !== $venue->name ) {
			return true;
		}

		// Check Street Address.
		if ( ! isset( $address['street_address'] ) ) {
			$address['street_address'] = '';
		}
		if ( $address['street_address'] !== $venue->venue_address ) {
			return true;
		}

		// Check City.
		if ( ! isset( $address['city'] ) ) {
			$address['city'] = '';
		}
		if ( $address['city'] !== $venue->venue_city ) {
			return true;
		}

		// Check Postcode.
		if ( ! isset( $address['postal_code'] ) ) {
			$address['postal_code'] = '';
		}
		if ( $address['postal_code'] !== $venue->venue_postcode ) {
			return true;
		}

		// Check Country.
		if ( ! empty( $address['country_id'] ) ) {
			$country = $this->country_get_by_id( $address['country_id'] );
			if ( ! empty( $country ) ) {
				if ( ! empty( $venue->venue_country ) ) {
					if ( $country['name'] !== $venue->venue_country ) {
						return true;
					}
				}
			}
		} else {
			// There's a Venue Country but no Address Country ID.
			if ( ! empty( $venue->venue_country ) ) {
				return true;
			}
		}

		// Check State/Province.
		if ( ! empty( $address['state_province_id'] ) ) {
			$state_province = $this->state_province_get_by_id( $address['state_province_id'] );
			if ( ! empty( $state_province ) ) {
				if ( ! empty( $venue->venue_state ) ) {
					if ( $state_province['name'] !== $venue->venue_state ) {
						return true;
					}
				}
			}
		} else {
			// There's a Venue State but no Address State/Province ID.
			if ( ! empty( $venue->venue_state ) ) {
				return true;
			}
		}

		// Check geocodes.
		if ( ! isset( $address['geo_code_1'] ) ) {
			$address['geo_code_1'] = '';
		}
		if ( $address['geo_code_1'] !== $venue->venue_lat ) {
			return true;
		}
		if ( ! isset( $address['geo_code_2'] ) ) {
			$address['geo_code_2'] = '';
		}
		if ( $address['geo_code_2'] !== $venue->venue_lng ) {
			return true;
		}

		// --<
		return false;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get a Country by its numeric ID.
	 *
	 * @since 0.7.1
	 *
	 * @param integer $country_id The numeric ID of the Country.
	 * @return array $country The array of Country data.
	 */
	public function country_get_by_id( $country_id ) {

		// Init return.
		$country = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $country;
		}

		// Params to get the Country.
		$params = [
			'version'    => 3,
			'sequential' => 1,
			'id'         => $country_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Country', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $country;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $country;
		}

		// The result set should contain only one item.
		$country = array_pop( $result['values'] );

		// --<
		return $country;

	}

	/**
	 * Get a Country by its "short name".
	 *
	 * @since 0.7.1
	 *
	 * @param string $country_short The "short name" of the Country.
	 * @return array $country The array of Country data, empty on failure.
	 */
	public function country_get_by_short( $country_short ) {

		// Init return.
		$country = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $country;
		}

		// Params to get the Country.
		$params = [
			'version'    => 3,
			'sequential' => 1,
			'iso_code'   => $country_short,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Country', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $country;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $country;
		}

		// The result set should contain only one item.
		$country = array_pop( $result['values'] );

		// --<
		return $country;

	}

	/**
	 * Get a Country by its name.
	 *
	 * @since 0.7.1
	 *
	 * @param string $name The name of the Country.
	 * @return array $country The array of Country data, empty on failure.
	 */
	public function country_get_by_name( $name ) {

		// Init return.
		$country = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $country;
		}

		// Params to get the Country.
		$params = [
			'version'    => 3,
			'sequential' => 1,
			'name'       => $name,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Country', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $country;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $country;
		}

		// The result set should contain only one item.
		$country = array_pop( $result['values'] );

		// --<
		return $country;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get a State/Province by its numeric ID.
	 *
	 * @since 0.7.1
	 *
	 * @param integer $state_province_id The numeric ID of the State/Province.
	 * @return array $state_province The array of State/Province data.
	 */
	public function state_province_get_by_id( $state_province_id ) {

		// Init return.
		$state_province = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $state_province;
		}

		// Params to get the State/Province.
		$params = [
			'version'           => 3,
			'sequential'        => 1,
			'state_province_id' => $state_province_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'StateProvince', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $state_province;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $state_province;
		}

		// The result set should contain only one item.
		$state_province = array_pop( $result['values'] );

		// --<
		return $state_province;

	}

	/**
	 * Get a State/Province by its "short name".
	 *
	 * @since 0.7.1
	 *
	 * @param string  $abbreviation The short name of the State/Province.
	 * @param integer $country_id The numeric ID of the Country.
	 * @return array $state_province The array of State/Province data.
	 */
	public function state_province_get_by_short( $abbreviation, $country_id = 0 ) {

		// Init return.
		$state_province = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $state_province;
		}

		// Params to get the State/Province.
		$params = [
			'version'      => 3,
			'sequential'   => 1,
			'abbreviation' => $abbreviation,
		];

		// Add Country ID if present.
		if ( 0 !== $country_id ) {
			$params['country_id'] = $country_id;
		}

		// Call the CiviCRM API.
		$result = civicrm_api( 'StateProvince', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $state_province;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $state_province;
		}

		// The result set should contain only one item.
		$state_province = array_pop( $result['values'] );

		// --<
		return $state_province;

	}

	/**
	 * Get a State/Province by its name.
	 *
	 * @since 0.7.1
	 *
	 * @param string  $name The name of the State/Province.
	 * @param integer $country_id The numeric ID of the Country.
	 * @return array $state_province The array of State/Province data.
	 */
	public function state_province_get_by_name( $name, $country_id = 0 ) {

		// Init return.
		$state_province = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $state_province;
		}

		// Params to get the State/Province.
		$params = [
			'version'    => 3,
			'sequential' => 1,
			'name'       => $name,
		];

		// Add Country ID if present.
		if ( 0 !== $country_id ) {
			$params['country_id'] = $country_id;
		}

		// Call the CiviCRM API.
		$result = civicrm_api( 'StateProvince', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $state_province;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $state_province;
		}

		// The result set should contain only one item.
		$state_province = array_pop( $result['values'] );

		// --<
		return $state_province;

	}

}
