<?php
/**
 * CiviCRM Location Class.
 *
 * Handles interactions with CiviCRM Locations.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser CiviCRM Location Class.
 *
 * A class that encapsulates interactions with CiviCRM Locations.
 *
 * @since 0.7
 */
class CiviCRM_WP_Event_Organiser_CiviCRM_Location {

	/**
	 * Plugin object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $plugin The plugin object.
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
		$this->plugin = $parent->plugin;
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
		if ( $location !== false ) {

			// Is there a record on the Event Organiser side?
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
		if ( $location !== false ) {
			$result = $this->delete_location_by_id( $location['id'] );
		}

		// --<
		return $result;

	}

	/**
	 * Delete a CiviCRM Event Location given a Location ID.
	 *
	 * Be aware that only the CiviCRM loc_block is deleted - not the items that
	 * constitute it. Email, phone and address will still exist but not be
	 * associated as a loc_block.
	 *
	 * The next iteration of this plugin should probably refine the loc_block
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
			'id' => $location_id,
		];

		// Delete via API.
		$result = civicrm_api( 'LocBlock', 'delete', $params );

		// Log failure and return boolean false.
		if ( $result['is_error'] == '1' ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $result['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );
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

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// ---------------------------------------------------------------------
		// Try by sync ID
		// ---------------------------------------------------------------------

		// Init a empty.
		$civi_id = 0;

		// If sync ID is present.
		if (
			isset( $venue->venue_civi_id )
			&&
			is_numeric( $venue->venue_civi_id )
			&&
			$venue->venue_civi_id > 0
		) {

			// Use it.
			$civi_id = $venue->venue_civi_id;

		}

		// Construct get-by-id array.
		$params = [
			'version' => 3,
			'id' => $civi_id,
			'return' => 'all',
		];

		// Call API.
		$location = civicrm_api( 'LocBlock', 'get', $params );

		// Log failure and return boolean false.
		if ( $location['is_error'] == '1' ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'Could not get CiviCRM Location by ID', 'civicrm-event-organiser' ),
				'civicrm' => $location['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// Return the result if we get one.
		if ( absint( $location['count'] ) > 0 && is_array( $location['values'] ) ) {

			// Found by ID.
			return array_shift( $location['values'] );

		}

		// ---------------------------------------------------------------------
		// Now try by Location
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
			if ( isset( $location['is_error'] ) && $location['is_error'] == '1' ) {

				// Log error.
				$e = new Exception;
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'Could not get CiviCRM Location by Lat/Long', 'civicrm-event-organiser' ),
					'civicrm' => $location['error_message'],
					'params' => $params,
					'backtrace' => $trace,
				], true ) );

				// --<
				return false;

			}

			// Return the result if we get one.
			if ( absint( $location['count'] ) > 0 && is_array( $location['values'] ) ) {

				$e = new Exception;
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'procedure' => 'found by location',
					'venue' => $venue,
					'params' => $params,
					'location' => $location,
					'backtrace' => $trace,
				], true ) );

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
			'return' => 'all', // Return all data.
			'options' => [
				'limit' => 0, // Get all Locations.
			],
		];

		// Call API.
		$locations = civicrm_api( 'LocBlock', 'get', $params );

		// Log failure and return boolean false.
		if ( $locations['is_error'] == '1' ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $locations['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );
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
				'id' => $location['id'],
			];

			// Delete via API.
			$result = civicrm_api( 'LocBlock', 'delete', $params );

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
			'version' => 3,
			'id' => $loc_id,
			'return' => 'all',
			// Get country and state name.
			'api.Address.getsingle' => [
				'sequential' => 1,
				'id' => "\$value.address_id",
				'return' => [
					'country_id.name',
					'state_province_id.name',
				],
			],
		];

		// Call API ('get' returns an array keyed by the item).
		$result = civicrm_api( 'LocBlock', 'get', $params );

		// Log failure and return boolean false.
		if ( $result['is_error'] == '1' || $result['count'] != 1 ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $result['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );
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
	 * the email and that email already exists in the DB, it will not be found
	 * and associated - but rather the existing email will be updated. Same goes
	 * for phone. This is not a deal-breaker, but not very DRY either.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array $location The existing CiviCRM Location data.
	 * @return array $location The CiviCRM Location data.
	 */
	public function create_civi_loc_block( $venue, $location ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return [];
		}

		// Init create/update flag.
		$op = 'create';

		// Update if our Venue already has a Location.
		if (
			isset( $venue->venue_civi_id ) &&
			is_numeric( $venue->venue_civi_id ) &&
			$venue->venue_civi_id > 0
		) {
			$op = 'update';
		}

		// Define initial params array.
		$params = [
			'version' => 3,
		];

		/*
		 * First, see if the loc_block email, phone and address already exist.
		 *
		 * If they don't, we need params returned that trigger their creation on
		 * the CiviCRM side. If they do, then we may need to update or delete them
		 * before we include the data in the 'civicrm_api' call.
		 */

		// If we have an email.
		if ( isset( $venue->venue_civi_email ) && ! empty( $venue->venue_civi_email ) ) {

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
		if ( isset( $venue->venue_civi_phone ) && ! empty( $venue->venue_civi_phone ) ) {

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

		// If our Venue has a Location, add it.
		if ( $op == 'update' ) {

			// Target our known Location - this will trigger an update.
			$params['id'] = $venue->venue_civi_id;

		}

		// Call API.
		$location = civicrm_api( 'LocBlock', 'create', $params );

		// Log and bail if there's an error.
		if ( ! empty( $location['is_error'] ) ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $location['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		/*
		 * We now need to create a dummy CiviCRM Event, or this Venue will not show
		 * up in CiviCRM...
		 */
		//$this->create_dummy_event( $location );

		// --<
		return $location;

	}

	// -------------------------------------------------------------------------

	/**
	 * Query email via API and update if necessary.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array $location The CiviCRM Location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array $email_data Integer if found, array if not found.
	 */
	private function maybe_update_email( $venue, $location = null, $op = 'create' ) {

		// If the Location has an existing email.
		if ( ! is_null( $location ) && isset( $location['email']['id'] ) ) {

			// Check by ID.
			$email_params = [
				'version' => 3,
				'id' => $location['email']['id'],
			];

		} else {

			// Check by email.
			$email_params = [
				'version' => 3,
				'contact_id' => null,
				'is_primary' => 0,
				'location_type_id' => 1,
				'email' => $venue->venue_civi_email,
			];

		}

		// Query API.
		$existing_email_data = civicrm_api( 'Email', 'get', $email_params );

		// Did we get one?
		if (
			$existing_email_data['is_error'] == 0 &&
			$existing_email_data['count'] > 0 &&
			is_array( $existing_email_data['values'] )
		) {

			// Get first one.
			$existing_email = array_shift( $existing_email_data['values'] );

			// Has it changed?
			if ( $op == 'update' && $existing_email['email'] != $venue->venue_civi_email ) {

				// Add API version.
				$existing_email['version'] = 3;

				// Add null contact ID as this seems to be required.
				$existing_email['contact_id'] = null;

				// Replace with updated email.
				$existing_email['email'] = $venue->venue_civi_email;

				// Update it.
				$existing_email = civicrm_api( 'Email', 'create', $existing_email );

			}

			// Get its ID.
			$email_data = $existing_email['id'];

		} else {

			// Define new email.
			$email_data = [
				'location_type_id' => 1,
				'email' => $venue->venue_civi_email,
			];

		}

		// --<
		return $email_data;

	}

	/**
	 * Query phone via API and update if necessary.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array $location The CiviCRM Location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array $phone_data Integer if found, array if not found.
	 */
	private function maybe_update_phone( $venue, $location = null, $op = 'create' ) {

		// Create numeric version of phone number.
		$numeric = preg_replace( '/[^0-9]/', '', $venue->venue_civi_phone );

		// If the Location has an existing email.
		if ( ! is_null( $location ) && isset( $location['phone']['id'] ) ) {

			// Check by ID.
			$phone_params = [
				'version' => 3,
				'id' => $location['phone']['id'],
			];

		} else {

			// Check phone by its numeric field.
			$phone_params = [
				'version' => 3,
				'contact_id' => null,
				//'is_primary' => 0,
				'location_type_id' => 1,
				'phone_numeric' => $numeric,
			];

		}

		// Query API.
		$existing_phone_data = civicrm_api( 'Phone', 'get', $phone_params );

		// Did we get one?
		if (
			$existing_phone_data['is_error'] == 0 &&
			$existing_phone_data['count'] > 0 &&
			is_array( $existing_phone_data['values'] )
		) {

			// Get first one.
			$existing_phone = array_shift( $existing_phone_data['values'] );

			// Has it changed?
			if ( $op == 'update' && $existing_phone['phone'] != $venue->venue_civi_phone ) {

				// Add API version.
				$existing_phone['version'] = 3;

				// Add null contact ID as this seems to be required.
				$existing_phone['contact_id'] = null;

				// Replace with updated phone.
				$existing_phone['phone'] = $venue->venue_civi_phone;
				$existing_phone['phone_numeric'] = $numeric;

				// Update it.
				$existing_phone = civicrm_api( 'Phone', 'create', $existing_phone );

			}

			// Get its ID.
			$phone_data = $existing_phone['id'];

		} else {

			// Define new phone.
			$phone_data = [
				'location_type_id' => 1,
				'phone' => $venue->venue_civi_phone,
				'phone_numeric' => $numeric,
			];

		}

		// --<
		return $phone_data;

	}

	/**
	 * Query address via API and update if necessary.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object.
	 * @param array $location The CiviCRM Location data.
	 * @param string $op The operation - either 'create' or 'update'.
	 * @return int|array $address_data Integer if found, array if not found.
	 */
	private function maybe_update_address( $venue, $location = null, $op = 'create' ) {

		// If the Location has an existing address.
		if ( ! is_null( $location ) && isset( $location['address']['id'] ) ) {

			// Check by ID.
			$address_params = [
				'version' => 3,
				'id' => $location['address']['id'],
			];

		} else {

			// Check address.
			$address_params = [
				'version' => 3,
				'contact_id' => null,
				//'is_primary' => 0,
				'location_type_id' => 1,
				//'county' => $venue->venue_state, // Can't do county in CiviCRM yet.
				//'country' => $venue->venue_country, // Can't do country in CiviCRM yet.
			];

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
		$existing_address_data = civicrm_api( 'Address', 'get', $address_params );

		// Did we get one?
		if ( $existing_address_data['is_error'] == 0 && $existing_address_data['count'] > 0 ) {

			// Get first one.
			$existing_address = array_shift( $existing_address_data['values'] );

			// Has it changed?
			if ( $op == 'update' && $this->is_address_changed( $venue, $existing_address ) ) {

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
				$existing_address = civicrm_api( 'Address', 'create', $existing_address );

			}

			// Get its ID.
			$address_data = $existing_address['id'];

		} else {

			// Define new address.
			$address_data = [
				'location_type_id' => 1,
				//'county' => $venue->venue_state, // Can't do county in CiviCRM yet.
				//'country' => $venue->venue_country, // Can't do country in CiviCRM yet.
			];

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
	 * Location, it will no exist as an entry in the data array. This is not
	 * the case for Event Organiser Venues, whose objects always contain all
	 * properties, whether they have a value or not.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $venue The Event Organiser Venue object being updated.
	 * @param array $location The existing CiviCRM Location data.
	 * @return bool $is_changed True if changed, false otherwise.
	 */
	private function is_address_changed( $venue, $location ) {

		// Check street address.
		if ( ! isset( $location['street_address'] ) ) {
			$location['street_address'] = '';
		}
		if ( $location['street_address'] != $venue->venue_address ) {
			return true;
		}

		// Check city.
		if ( ! isset( $location['city'] ) ) {
			$location['city'] = '';
		}
		if ( $location['city'] != $venue->venue_city ) {
			return true;
		}

		// Check postcode.
		if ( ! isset( $location['postal_code'] ) ) {
			$location['postal_code'] = '';
		}
		if ( $location['postal_code'] != $venue->venue_postcode ) {
			return true;
		}

		// Check geocodes.
		if ( ! isset( $location['geo_code_1'] ) ) {
			$location['geo_code_1'] = '';
		}
		if ( $location['geo_code_1'] != $venue->venue_lat ) {
			return true;
		}
		if ( ! isset( $location['geo_code_2'] ) ) {
			$location['geo_code_2'] = '';
		}
		if ( $location['geo_code_2'] != $venue->venue_lng ) {
			return true;
		}

		// --<
		return false;

	}

} // Class ends.
