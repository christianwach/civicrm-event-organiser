<?php
/**
 * Event Organiser Venue Class.
 *
 * Handles functionality related to Event Organiser Venues.
 *
 * Each Event Organiser Venue maps to a CiviCRM LocBlock and stores that mapping
 * in the "_civi_loc_id" value in its Term Meta.
 *
 * A CiviCRM LocBlock for an Event in the CiviCRM UI (though the code allows for
 * more "Blocks" than those shown in the UI) consists of:
 *
 * * Address x 1
 * * Email x 2
 * * Phone x 2
 *
 * The Address record is used to populate the Venue itself. Email and Phone records
 * are stored in the Venue's Term Meta.
 *
 * Note that CiviCRM incorrectly duplicates LocBlocks (and the "Blocks" that are
 * associated with it) when using the Event Location UI. I suspect that this
 * happened as a result of the switch to API4 in CiviCRM 5.31:
 *
 * @see https://github.com/civicrm/civicrm-core/pull/18586
 *
 * For this code to work correctly again, a patch needs to be applied to CiviCRM.
 *
 * @see https://lab.civicrm.org/dev/core/-/issues/2103
 * @see https://github.com/civicrm/civicrm-core/pull/23041
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser Venues Class.
 *
 * A class that encapsulates functionality related to Event Organiser Venues.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_EO_Venue {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;



	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin = $parent;

		// Add hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', [ $this, 'register_hooks' ] );

	}

	/**
	 * Register hooks on BuddyPress loaded.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Check for Event Organiser.
		if ( ! $this->plugin->eo->is_active() ) {
			return;
		}

		// Intercept create Venue.
		add_action( 'eventorganiser_insert_venue', [ $this, 'insert_venue' ], 10, 1 );

		// Intercept save Venue.
		add_action( 'eventorganiser_save_venue', [ $this, 'save_venue' ], 10, 1 );

		// Intercept Term deletion (before delete Venue).
		add_action( 'delete_term', [ $this, 'delete_venue_term' ], 20, 4 );
		add_action( 'delete_event-venue', [ $this, 'delete_venue' ], 20, 3 );

		// Intercept after delete Venue.
		add_action( 'eventorganiser_venue_deleted', [ $this, 'deleted_venue' ], 10, 1 );

		// Add our Venue meta box.
		add_action( 'add_meta_boxes', [ $this, 'venue_meta_box' ] );

		// Filter Terms after Event Organiser does.
		add_filter( 'wp_get_object_terms', [ $this, 'update_venue_meta' ], 20, 4 );

		// Intercept Terms after Event Organiser does.
		add_filter( 'get_terms', [ $this, 'update_venue_meta_cache' ], 20, 2 );
		add_filter( 'get_event-venue', [ $this, 'update_venue_meta_cache' ], 20, 2 );

	}

	// -------------------------------------------------------------------------

	/**
	 * Intercept insert Venue.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 */
	public function insert_venue( $venue_id ) {

		// Check permissions.
		if ( ! $this->allow_venue_edit() ) {
			return;
		}

		// Save custom Event Organiser components.
		$this->save_venue_components( $venue_id );

		// Get full Venue.
		$venue = eo_get_venue_by( 'id', $venue_id );

		/*
		 * Manually add Venue metadata because since Event Organiser 3.0 it is
		 * no longer added by default to the Venue object.
		 */
		$address = eo_get_venue_address( $venue_id );
		$venue->venue_address  = isset( $address['address'] ) ? $address['address'] : '';
		$venue->venue_postal   = isset( $address['postcode'] ) ? $address['postcode'] : '';
		$venue->venue_postcode = isset( $address['postcode'] ) ? $address['postcode'] : '';
		$venue->venue_city     = isset( $address['city'] ) ? $address['city'] : '';
		$venue->venue_country  = isset( $address['country'] ) ? $address['country'] : '';
		$venue->venue_state    = isset( $address['state'] ) ? $address['state'] : '';

		$venue->venue_lat = number_format( floatval( eo_get_venue_lat( $venue_id ) ), 6 );
		$venue->venue_lng = number_format( floatval( eo_get_venue_lng( $venue_id ) ), 6 );

		// Create CiviCRM Event Location.
		$location = $this->plugin->civi->location->update_location( $venue );

		// Store LocBlock ID.
		$this->store_civi_location( $venue_id, $location );

	}

	/**
	 * Intercept save Venue.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 */
	public function save_venue( $venue_id ) {

		// Check permissions.
		if ( ! $this->allow_venue_edit() ) {
			return;
		}

		// Save custom Event Organiser components.
		$this->save_venue_components( $venue_id );

		// Get full Venue.
		$venue = eo_get_venue_by( 'id', $venue_id );

		/*
		 * Manually add Venue metadata because since Event Organiser 3.0 it is
		 * no longer added by default to the Venue object.
		 */
		$address = eo_get_venue_address( $venue_id );
		$venue->venue_address  = isset( $address['address'] ) ? $address['address'] : '';
		$venue->venue_postal   = isset( $address['postcode'] ) ? $address['postcode'] : '';
		$venue->venue_postcode = isset( $address['postcode'] ) ? $address['postcode'] : '';
		$venue->venue_city     = isset( $address['city'] ) ? $address['city'] : '';
		$venue->venue_country  = isset( $address['country'] ) ? $address['country'] : '';
		$venue->venue_state    = isset( $address['state'] ) ? $address['state'] : '';

		$venue->venue_lat = number_format( floatval( eo_get_venue_lat( $venue_id ) ), 6 );
		$venue->venue_lng = number_format( floatval( eo_get_venue_lng( $venue_id ) ), 6 );

		// Update CiviCRM Event Location.
		$location = $this->plugin->civi->location->update_location( $venue );

		// Store LocBlock ID.
		$this->store_civi_location( $venue_id, $location );

	}

	/**
	 * Intercept before delete Venue Term.
	 *
	 * @since 0.1
	 *
	 * @param object $term The Term object of the Venue.
	 * @param int $tt_id The numeric ID of the Venue Term Taxonomy.
	 * @param string $taxonomy The deleted Term's Taxonomy name.
	 * @param object $deleted_term The deleted Term object of the Venue.
	 */
	public function delete_venue_term( $term, $tt_id, $taxonomy, $deleted_term ) {

		// Sanity checks.
		if ( ! is_object( $deleted_term ) ) {
			return;
		}
		if ( is_array( $taxonomy ) && ! in_array( 'event-venue', $taxonomy ) ) {
			return;
		}
		if ( ! is_array( $taxonomy ) && $taxonomy != 'event-venue' ) {
			return;
		}

		// Delete anything associated with this Venue.
		$this->delete_venue_meta( $deleted_term );

	}

	/**
	 * Intercept before delete Venue Term by 'delete_$taxonomy' hook.
	 *
	 * @since 0.1
	 *
	 * @param object $term The Term object of the Venue.
	 * @param int $tt_id The numeric ID of the Venue Term Taxonomy.
	 * @param object $deleted_term The deleted Term object of the Venue.
	 */
	public function delete_venue( $term, $tt_id, $deleted_term ) {

		// Sanity check.
		if ( ! is_object( $deleted_term ) ) {
			return;
		}

		// Delete anything associated with this Venue.
		$this->delete_venue_meta( $deleted_term );

	}

	/**
	 * Delete anything associated with this Venue.
	 *
	 * @since 0.1
	 *
	 * @param object $deleted_term The Term object of the Venue.
	 */
	public function delete_venue_meta( $deleted_term ) {

		// Only do this once.
		static $term_deleted;
		if ( isset( $term_deleted ) && $term_deleted === $deleted_term->term_id ) {
			return;
		}

		// Sanity check.
		if ( ! is_object( $deleted_term ) ) {
			return;
		}

		// Get Venue ID.
		$venue_id = $deleted_term->term_id;

		// Get all remaining Venue meta.
		$venue_meta = eo_get_venue_meta( $venue_id, '', false );

		// Did we get a CiviCRM Location ID?
		if (
			isset( $venue_meta['_civi_loc_id'] )
			&&
			is_array( $venue_meta['_civi_loc_id'] )
			&&
			count( $venue_meta['_civi_loc_id'] ) === 1
		) {

			// Delete CiviCRM Event Location.
			$this->plugin->civi->location->delete_location_by_id( $venue_meta['_civi_loc_id'][0] );

		}

		// Delete components.
		$this->delete_venue_components( $venue_id );

		// Set flag.
		$term_deleted = $deleted_term->term_id;

	}

	/**
	 * Intercept after delete Venue.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 */
	public function deleted_venue( $venue_id ) {

		// Check permissions.
		if ( ! $this->allow_venue_edit() ) {
			return;
		}

		// Delete components.
		$this->delete_venue_components( $venue_id );

		// $venue_meta = eo_get_venue_meta( $venue_id, '', false );

	}

	/**
	 * Create an Event Organiser Venue given a CiviCRM Event Location.
	 *
	 * @since 0.1
	 *
	 * @param array $location The CiviCRM Event Location data.
	 * @return int $term_id The numeric ID of the Venue.
	 */
	public function create_venue( $location ) {

		// Check permissions.
		if ( ! $this->allow_venue_edit() ) {
			return false;
		}

		/*
		 * CiviCRM does not show the "Address Name" Field for Locations by default
		 * but it can be enabled on the CiviCRM "Address Settings" screen.
		 *
		 * When creating a Venue from a Location, the "Address Name" Field is only
		 * populated when the "Address Settings" have been modified to show it.
		 *
		 * We use it if it has a value, but need to fall back to using the "Street
		 * Address" Field when empty.
		 */
		if ( ! empty( $location['address']['name'] ) ) {

			// Use "Address Name" Field.
			$name = $location['address']['name'];

		} else {

			// Set a sensible default.
			$name = __( 'Untitled venue', 'civicrm-event-organiser' );

			// Construct name from "Street Address" Field if we have it.
			if ( ! empty( $location['address']['street_address'] ) ) {
				$name = $location['address']['street_address'];
			}

		}

		// Construct args.
		$args = [
			//'description' => $location['description'], // CiviCRM has no Location description at present.
		];

		// Add Address sub-query data if present.
		if ( ! isset( $location['api.Address.getsingle']['is_error'] ) ) {

			// Maybe add Country.
			if ( ! empty( $location['api.Address.getsingle']['country_id.name'] ) ) {
				$args['country'] = $location['api.Address.getsingle']['country_id.name'];
			}

			// Maybe add State/Province.
			if ( ! empty( $location['api.Address.getsingle']['state_province_id.name'] ) ) {
				$args['state'] = $location['api.Address.getsingle']['state_province_id.name'];
			}

		}

		// Add Street Address if present.
		if ( ! empty( $location['address']['street_address'] ) ) {
			$args['address'] = $location['address']['street_address'];
		}

		// Add City if present.
		if ( ! empty( $location['address']['city'] ) ) {
			$args['city'] = $location['address']['city'];
		}

		// Add Postcode if present.
		if ( ! empty( $location['address']['postal_code'] ) ) {
			$args['postcode'] = $location['address']['postal_code'];
		}

		// Add geocodes if present.
		if ( ! empty( $location['address']['geo_code_1'] ) ) {
			$args['latitude'] = $location['address']['geo_code_1'];
		}
		if ( ! empty( $location['address']['geo_code_2'] ) ) {
			$args['longtitude'] = $location['address']['geo_code_2'];
		}

		// Remove actions to prevent recursion.
		remove_action( 'eventorganiser_insert_venue', [ $this, 'insert_venue' ], 10 );
		remove_action( 'eventorganiser_save_venue', [ $this, 'save_venue' ], 10 );

		// Retrieve Venue with slug-to-be-used.
		$existing_venue = eo_get_venue_by( 'slug', sanitize_title( $name ) );

		/**
		 * Force the creation of a unique slug.
		 *
		 * Event Organiser will return a WP_Error object if there is already a
		 * Venue with the same name as the one being created.
		 *
		 * When there isn't an existing Venue, you can force the use of a unique
		 * slug with the following code:
		 *
		 * add_filter( 'civicrm_event_organiser_force_unique_slug', '__return_true' );
		 *
		 * @since 0.3.5
		 *
		 * @param bool False by default, which does not force unique slugs.
		 */
		if ( $existing_venue || apply_filters( 'civicrm_event_organiser_force_unique_slug', false ) ) {

			// Create a slug we know will be unique.
			$args['slug'] = sanitize_title( $name . '-' . $location['id'] );

		}

		// Insert Venue.
		$result = eo_insert_venue( $name, $args );

		// Add actions again.
		add_action( 'eventorganiser_insert_venue', [ $this, 'insert_venue' ], 10, 1 );
		add_action( 'eventorganiser_save_venue', [ $this, 'save_venue' ], 10, 1 );

		// Log and bail if we get an error.
		if ( is_wp_error( $result ) || ! isset( $result['term_id'] ) ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'Venue not created.', 'civicrm-event-organiser' ),
				'result' => $result,
				'location' => $location,
				'backtrace' => $trace,
			], true ) );
			return;
		}

		// Create Venue meta data.

		// Do we have an Email for the Location?
		if ( isset( $location['email']['email'] ) && ! empty( $location['email']['email'] ) ) {

			// Yes, get it.
			$email = $location['email']['email'];

			// Store Email in meta.
			eo_update_venue_meta( $result['term_id'], '_civi_email', esc_sql( $email ) );

		}

		// Do we have a phone number for the Location?
		if ( isset( $location['phone']['phone'] ) && ! empty( $location['phone']['phone'] ) ) {

			// Store phone in meta.
			eo_update_venue_meta( $result['term_id'], '_civi_phone', esc_sql( $location['phone']['phone'] ) );

		}

		// Store Location ID.
		eo_update_venue_meta( $result['term_id'], '_civi_loc_id', $location['id'] );

		// --<
		return $result['term_id'];

	}

	/**
	 * Update an Event Organiser Venue given a CiviCRM Event Location.
	 *
	 * @since 0.1
	 *
	 * @param array $location The CiviCRM Event Location data.
	 * @return int $term_id The numeric ID of the Venue.
	 */
	public function update_venue( $location ) {

		// Check permissions.
		if ( ! $this->allow_venue_edit() ) {
			return;
		}

		// Does this Location have an existing Venue?
		$venue_id = $this->get_venue_id( $location );

		// If we do not get one.
		if ( $venue_id === false ) {

			// Create Venue.
			$term_id = $this->create_venue( $location );

			// --<
			return $term_id;

		} else {

			// Get full Venue object.
			$venue = eo_get_venue_by( 'id', $venue_id );

			// If for some reason the linkage fails.
			if ( ! is_object( $venue ) ) {

				// Create Venue.
				$term_id = $this->create_venue( $location );

				// --<
				return $term_id;

			}

			// Use the Venue Name by default.
			$name = $venue->name;

			/*
			 * CiviCRM does not show the "Address Name" Field for Locations by
			 * default but it can be enabled on the CiviCRM "Address Settings"
			 * screen.
			 *
			 * When updating a Venue from a Location, the "Address Name" Field
			 * is populated when the "Address Settings" have been modified to
			 * show it or when the Venue has been previously synced to CiviCRM.
			 *
			 * We use it if it has a value, but need to fall back to using the
			 * "Street Address" Field when empty.
			 *
			 */
			if ( ! empty( $location['address']['name'] ) ) {

				// Get Street Address.
				$street_address = '';
				if ( ! empty( $location['address']['street_address'] ) ) {
					$street_address = $location['address']['street_address'];
				}

				// Use the Address Name if different to Street Address.
				if ( $location['address']['name'] !== $street_address ) {
					$name = $location['address']['name'];
				}

			}

			// Construct args.
			$args = [
				'name' => $name,
			];

			// CiviCRM has no Location description at present.
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
			//$args['description'] => $location['description'];

			// Add Street Address if present.
			if ( ! empty( $location['address']['street_address'] ) ) {
				$args['address'] = $location['address']['street_address'];
			}

			// Add City if present.
			if ( ! empty( $location['address']['city'] ) ) {
				$args['city'] = $location['address']['city'];
			}

			// Add Postcode if present.
			if ( ! empty( $location['address']['postal_code'] ) ) {
				$args['postcode'] = $location['address']['postal_code'];
			}

			// Add Address sub-query data if present.
			if ( empty( $location['api.Address.getsingle']['is_error'] ) ) {

				// Maybe add Country.
				if ( ! empty( $location['api.Address.getsingle']['country_id.name'] ) ) {
					$args['country'] = $location['api.Address.getsingle']['country_id.name'];
				}

				// Maybe add State.
				if ( ! empty( $location['api.Address.getsingle']['state_province_id.name'] ) ) {
					$args['state'] = $location['api.Address.getsingle']['state_province_id.name'];
				}

			}

			// Add geocodes if present.
			if ( ! empty( $location['address']['geo_code_1'] ) ) {
				$args['latitude'] = $location['address']['geo_code_1'];
			}
			if ( ! empty( $location['address']['geo_code_2'] ) ) {
				$args['longtitude'] = $location['address']['geo_code_2'];
			}

			// Remove callback to prevent recursion.
			remove_action( 'eventorganiser_save_venue', [ $this, 'save_venue' ], 10 );

			// Insert Venue.
			$result = eo_update_venue( $venue_id, $args );

			// Restore callback.
			add_action( 'eventorganiser_save_venue', [ $this, 'save_venue' ], 10, 1 );

			// Bail if we get an error.
			if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
				return false;
			}

			// Create Venue meta data, if present.
			if ( ! empty( $location['email']['email'] ) ) {
				eo_update_venue_meta( $result['term_id'], '_civi_email', esc_sql( $location['email']['email'] ) );
			}
			if ( ! empty( $location['phone']['phone'] ) ) {
				eo_update_venue_meta( $result['term_id'], '_civi_phone', esc_sql( $location['phone']['phone'] ) );
			}

			// Always store Location ID.
			eo_update_venue_meta( $result['term_id'], '_civi_loc_id', $location['id'] );

		}

		// --<
		return $result['term_id'];

	}

	/**
	 * Get an Event Organiser Venue ID given a CiviCRM Event Location.
	 *
	 * @since 0.1
	 *
	 * @param array $location The CiviCRM Event Location data.
	 * @return int $venue_id The numeric ID of the Venue.
	 */
	public function get_venue_id( $location ) {

		// ---------------------------------------------------------------------
		// First, see if we have a matching ID in the Venue's meta table.
		// ---------------------------------------------------------------------

		// Access wpdb.
		global $wpdb;

		// To avoid the pro plugin, hit the database directly.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$venue_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT eo_venue_id FROM $wpdb->eo_venuemeta WHERE
				meta_key = '_civi_loc_id' AND
				meta_value = %d",
				$location['id']
			)
		);

		// If we get one, return it.
		if ( isset( $venue_id ) && ! is_null( $venue_id ) && $venue_id > 0 ) {
			return $venue_id;
		}

		// ---------------------------------------------------------------------
		// Next, see if we have an identical Location.
		// ---------------------------------------------------------------------

		// Do we have geo data?
		if ( isset( $location['address']['geo_code_1'] ) && isset( $location['address']['geo_code_2'] ) ) {

			/*
			 * To avoid the pro plugin, hit the db directly.
			 * This could use some refinement from someone better at SQL than me.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$venue_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT eo_venue_id FROM $wpdb->eo_venuemeta
					WHERE
						( meta_key = '_lat' AND meta_value = %f )
					AND
					eo_venue_id = (
						SELECT eo_venue_id FROM $wpdb->eo_venuemeta WHERE
						( meta_key = '_lng' AND meta_value = %f )
					)",
					floatval( $location['address']['geo_code_1'] ),
					floatval( $location['address']['geo_code_2'] )
				)
			);

			// If we get one, return it.
			if ( isset( $venue_id ) && ! is_null( $venue_id ) && $venue_id > 0 ) {
				return $venue_id;
			}

		}

		// ---------------------------------------------------------------------
		// Lastly, see if we have an identical Street Address.
		// ---------------------------------------------------------------------

		// Do we have Street Address data?
		if ( isset( $location['address']['street_address'] ) ) {

			// Get value.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$venue_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT eo_venue_id FROM $wpdb->eo_venuemeta WHERE
					meta_key = '_address' AND
					meta_value = %s",
					$location['address']['street_address']
				)
			);

			// If we get one, return it.
			if ( isset( $venue_id ) && ! is_null( $venue_id ) && $venue_id > 0 ) {
				return $venue_id;
			}

		}

		// --<
		return false;

	}

	/**
	 * Register Venue meta box.
	 *
	 * @since 0.1
	 */
	public function venue_meta_box() {

		// Create it.
		add_meta_box(
			'civi_eo_venue_metabox',
			__( 'CiviCRM Settings', 'civicrm-event-organiser' ),
			[ $this, 'venue_meta_box_render' ],
			'event_page_venues',
			'side',
			'high'
		);

	}

	/**
	 * Define Venue meta box.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The Event Organiser Venue object.
	 */
	public function venue_meta_box_render( $venue ) {

		// Init vars.
		$email = '';
		$phone = '';

		// Get meta box data if this is an edit.
		if ( isset( $venue->term_id ) ) {
			$email = eo_get_venue_meta( $venue->term_id, '_civi_email', true );
			$phone = eo_get_venue_meta( $venue->term_id, '_civi_phone', true );
		}

		// Add nonce.
		wp_nonce_field( 'civi_eo_venue_meta_save', 'civi_eo_nonce_field' );

		echo '
		<p>
		<label for="civi_eo_venue_email">' . __( 'Email contact:', 'civicrm-event-organiser' ) . '</label>
		<input type="text" id="civi_eo_venue_email" name="civi_eo_venue_email" value="' . esc_attr( $email ) . '" />
		</p>

		<p>
		<label for="civi_eo_venue_phone">' . __( 'Phone contact:', 'civicrm-event-organiser' ) . '</label>
		<input type="text" id="civi_eo_venue_phone" name="civi_eo_venue_phone" value="' . esc_attr( $phone ) . '" />
		</p>
		';

	}

	/*
	 * -------------------------------------------------------------------------
	 * The next two methods have been adapted from Event Organiser so we can add
	 * our custom data to the Venue object when standard Event Organiser functions
	 * are called.
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Updates Venue meta cache when an Event's Venue is retrieved.
	 *
	 * @since 0.1
	 *
	 * @param array $terms Array of Terms.
	 * @param array $post_ids Array of Post IDs.
	 * @param string $taxonomies Should be (an array containing) 'event-venue'.
	 * @param string $args Additional parameters.
	 * @return array $terms Array of Term objects.
	 */
	public function update_venue_meta( $terms, $post_ids, $taxonomies, $args ) {

		// Passes Taxonomies as a string inside quotes.
		$taxonomies = explode( ',', trim( $taxonomies, "\x22\x27" ) );
		return $this->update_venue_meta_cache( $terms, $taxonomies );

	}

	/**
	 * Updates Venue meta cache when Event Venues are retrieved.
	 *
	 * @since 0.1
	 *
	 * @param array $terms Array of Terms.
	 * @param string $tax Should be (an array containing) 'event-venue'.
	 * @return array $terms Array of event-venue Terms.
	 */
	public function update_venue_meta_cache( $terms, $tax ) {

		if ( is_array( $tax ) && ! in_array( 'event-venue', $tax ) ) {
			return $terms;
		}
		if ( ! is_array( $tax ) && $tax != 'event-venue' ) {
			return $terms;
		}

		$single = false;
		if ( ! is_array( $terms ) ) {
			$single = true;
			$terms = [ $terms ];
		}

		if ( empty( $terms ) ) {
			return $terms;
		}

		// Check if its array of Terms or Term IDs.
		$first_element = reset( $terms );
		if ( is_object( $first_element ) ) {
			$term_ids = wp_list_pluck( $terms, 'term_id' );
		} else {
			$term_ids = $terms;
		}

		update_meta_cache( 'eo_venue', $term_ids );

		// Loop through.
		foreach ( $terms as $term ) {

			// Skip if not useful.
			if ( ! is_object( $term ) ) {
				continue;
			}

			// Get Term ID.
			$term_id = (int) $term->term_id;

			if ( ! isset( $term->venue_civi_email ) ) {
				$term->venue_civi_email = eo_get_venue_meta( $term_id, '_civi_email', true );
			}

			if ( ! isset( $term->venue_civi_phone ) ) {
				$term->venue_civi_phone = eo_get_venue_meta( $term_id, '_civi_phone', true );
			}

			if ( ! isset( $term->venue_civi_id ) ) {
				$term->venue_civi_id = eo_get_venue_meta( $term_id, '_civi_loc_id', true );
			}

		}

		if ( $single ) {
			return $terms[0];
		}

		// --<
		return $terms;

	}

	/**
	 * Store a CiviCRM LocBlock ID for a given Event Organiser Venue ID.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 * @param array $civi_loc_block The array of CiviCRM LocBlock data.
	 */
	public function store_civi_location( $venue_id, $civi_loc_block ) {

		// Skip if there's no LocBlock ID.
		if ( empty( $civi_loc_block['id'] ) ) {
			return;
		}

		// Update Venue meta.
		eo_update_venue_meta( $venue_id, '_civi_loc_id', absint( $civi_loc_block['id'] ) );

	}

	/**
	 * Get a CiviCRM Event LocBlock ID for a given Event Organiser Venue ID.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 * @return int $loc_block_id The numeric ID of the CiviCRM LocBlock.
	 */
	public function get_civi_location( $venue_id ) {

		// Get Venue meta data.
		$loc_block_id = eo_get_venue_meta( $venue_id, '_civi_loc_id', true );

		// --<
		return $loc_block_id;

	}

	/**
	 * Clear a CiviCRM Event LocBlock ID for a given Event Organiser Venue ID.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 */
	public function clear_civi_location( $venue_id ) {

		// Delete Venue meta.
		eo_delete_venue_meta( $venue_id, '_civi_loc_id' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Check current user's permission to edit Venue Taxonomy.
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	public function allow_venue_edit() {

		// Check permissions.
		$tax = get_taxonomy( 'event-venue' );

		// Return permission.
		return current_user_can( $tax->cap->edit_terms );

	}

	/**
	 * Save custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 */
	private function save_venue_components( $venue_id ) {

		// Skip if neither Email nor phone is set.
		if ( ! isset( $_POST['civi_eo_venue_email'] ) && ! isset( $_POST['civi_eo_venue_phone'] ) ) {
			return;
		}

		// Check nonce.
		check_admin_referer( 'civi_eo_venue_meta_save', 'civi_eo_nonce_field' );

		// Save Email if set.
		$email = isset( $_POST['civi_eo_venue_email'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_venue_email'] ) ) : '';
		if ( ! empty( $email ) ) {
			$this->update_venue_email( $venue_id, $email );
		}

		// Save phone if set.
		$phone = isset( $_POST['civi_eo_venue_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_venue_phone'] ) ) : '';
		if ( ! empty( $phone ) ) {
			$this->update_venue_phone( $venue_id, $phone );
		}

	}

	/**
	 * Delete custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 */
	private function delete_venue_components( $venue_id ) {

		// Event Organiser garbage-collects when it deletes a Venue, so no need.

	}

	/**
	 * Clear custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 */
	public function clear_venue_components( $venue_id ) {

		// Delete Venue meta.
		eo_delete_venue_meta( $venue_id, '_civi_email' );
		eo_delete_venue_meta( $venue_id, '_civi_phone' );

	}

	/**
	 * Update Venue Email value.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 * @param str $venue_email The Email associated with the Venue.
	 */
	public function update_venue_email( $venue_id, $venue_email ) {

		// Retrieve meta value.
		$value = sanitize_email( $venue_email );

		// Validate as Email address.
		if ( ! is_email( $value ) ) {
			return;
		}

		// Update Venue meta.
		eo_update_venue_meta( $venue_id, '_civi_email', esc_sql( $value ) );

	}

	/**
	 * Update Venue phone value.
	 *
	 * @since 0.1
	 *
	 * @param int $venue_id The numeric ID of the Venue.
	 * @param str $venue_phone The phone number associated with the Venue.
	 */
	public function update_venue_phone( $venue_id, $venue_phone ) {

		// Use CiviCRM to validate?

		// Update Venue meta.
		eo_update_venue_meta( $venue_id, '_civi_phone', esc_sql( $venue_phone ) );

	}

}
