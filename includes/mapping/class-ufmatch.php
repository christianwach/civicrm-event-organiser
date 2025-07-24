<?php
/**
 * UFMatch Mapping Class.
 *
 * Handles User-Contact matching functionality.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * UFMatch Mapping Class.
 *
 * A class that encapsulates User-Contact matching functionality.
 *
 * @since 0.8.2
 */
class CEO_UFMatch {

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
	 * @since 0.8.2
	 *
	 * @param CiviCRM_Event_Organiser $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin = $parent;

		// Initialise.
		add_action( 'ceo/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 0.8.2
	 */
	public function initialise() {

	}

	// -------------------------------------------------------------------------

	/**
	 * Get a CiviCRM Contact ID for a given WordPress User ID.
	 *
	 * By default, CiviCRM will return the matching Contact ID in the current
	 * Domain only. Pass a numeric Domain ID and only that Domain will be queried.
	 *
	 * Sometimes, however, we need to know if there is a matching Contact in
	 * *any* Domain - if so, pass a string such as "all" for "$domain_id" and
	 * all Domains will be searched for a matching Contact.
	 *
	 * @since 0.8.2
	 *
	 * @param int        $user_id The numeric ID of the WordPress User.
	 * @param int|string $domain_id The Domain ID (defaults to current Domain ID) or a string to search all Domains.
	 * @return int|bool $contact_id The CiviCRM Contact ID, or false on failure.
	 */
	public function contact_id_get_by_user_id( $user_id, $domain_id = '' ) {

		/*
		// Only do this once per Contact ID and Domain.
		static $pseudocache;
		if ( isset( $pseudocache[ $domain_id ][ $user_id ] ) ) {
			return $pseudocache[ $domain_id ][ $user_id ];
		}
		*/

		// Init return.
		$contact_id = false;

		// Return early if no User ID is given.
		if ( empty( $user_id ) ) {
			return $contact_id;
		}

		// If CiviCRM is initialised.
		if ( $this->plugin->civi->is_active() ) {

			// Get UFMatch entry.
			$entry = $this->entry_get_by_user_id( $user_id, $domain_id );

			// If we get one.
			if ( false !== $entry ) {

				// Get the Contact ID if present.
				if ( ! empty( $entry->contact_id ) ) {
					$contact_id = (int) $entry->contact_id;
				}

				// Get the Contact ID from the returned array.
				if ( is_array( $entry ) ) {
					foreach ( $entry as $item ) {
						$contact_id = (int) $item->contact_id;
						break;
					}
				}

			}

		}

		/*
		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[ $domain_id ][ $user_id ] ) ) {
			$pseudocache[ $domain_id ][ $user_id ] = $contact_id;
		}
		*/

		// --<
		return $contact_id;

	}

	/**
	 * Get a WordPress User ID given a CiviCRM Contact ID.
	 *
	 * @since 0.8.2
	 *
	 * @param int        $contact_id The numeric ID of the CiviCRM Contact.
	 * @param int|string $domain_id The Domain ID (defaults to current Domain ID) or a string to search all Domains.
	 * @return int|bool $user_id The numeric WordPress User ID, or false on failure.
	 */
	public function user_id_get_by_contact_id( $contact_id, $domain_id = '' ) {

		/*
		// Only do this once per Contact ID and Domain.
		static $pseudocache;
		if ( isset( $pseudocache[ $domain_id ][ $contact_id ] ) ) {
			return $pseudocache[ $domain_id ][ $contact_id ];
		}
		*/

		// Init return.
		$user_id = false;

		// Get UFMatch entry (or entries).
		$entry = $this->entry_get_by_contact_id( $contact_id, $domain_id );

		// If we get a UFMatch entry.
		if ( false !== $entry ) {

			// Get the User ID if a single UFMatch item is returned.
			if ( ! empty( $entry->uf_id ) ) {
				$user_id = (int) $entry->uf_id;
			}

			// Get the User ID from the returned array.
			if ( is_array( $entry ) ) {
				foreach ( $entry as $item ) {
					$user_id = (int) $item->uf_id;
					break;
				}
			}

		}

		/*
		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[ $domain_id ][ $contact_id ] ) ) {
			$pseudocache[ $domain_id ][ $contact_id ] = $user_id;
		}
		*/

		// --<
		return $user_id;

	}

	// -------------------------------------------------------------------------

	/**
	 * Get the UFMatch data for a given CiviCRM Contact ID.
	 *
	 * This method optionally allows a Domain ID to be specified:
	 *
	 * * If no Domain ID is passed, then we default to current Domain ID.
	 * * If a Domain ID is passed as a string, then we search all Domain IDs.
	 *
	 * @since 0.8.2
	 *
	 * @param int        $contact_id The numeric ID of the CiviCRM Contact.
	 * @param int|string $domain_id The CiviCRM Domain ID (defaults to current Domain ID).
	 * @return array|object|bool $entry The UFMatch data on success, or false on failure.
	 */
	public function entry_get_by_contact_id( $contact_id, $domain_id = '' ) {

		// Init return.
		$entry = false;

		// Bail if CiviCRM is not active.
		if ( ! $this->plugin->civi->is_active() ) {
			return $entry;
		}

		// Sanity checks.
		if ( ! is_numeric( $contact_id ) ) {
			return $entry;
		}

		// Construct params.
		$params = [
			'version'    => 3,
			'contact_id' => $contact_id,
		];

		// If no Domain ID is specified, default to current Domain ID.
		if ( empty( $domain_id ) ) {
			$params['domain_id'] = CRM_Core_Config::domainID();
		}

		// Maybe add Domain ID if passed as an integer.
		if ( ! empty( $domain_id ) && is_numeric( $domain_id ) ) {
			$params['domain_id'] = $domain_id;
		}

		/**
		 * Filters the params used to query the UFMatch data.
		 *
		 * This filter may be used, for example, to modify the Domain ID when a
		 * Contact is edited by a CiviCRM Admin and that Contact does NOT have a
		 * UFMatch record in the current Domain.
		 *
		 * @since 0.5.9
		 *
		 * @param array $params The params passed to the CiviCRM API.
		 */
		$params = apply_filters( 'ceo/mapper/ufmatch/entry_get_by_contact_id', $params );

		// Get all UFMatch records via API.
		$result = civicrm_api( 'UFMatch', 'get', $params );

		// Log and bail on failure.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'     => __METHOD__,
				'contact_id' => $contact_id,
				'params'     => $params,
				'result'     => $result,
				'backtrace'  => $trace,
			];
			$this->plugin->log_error( $log );
			return $entry;
		}

		// Bail if there's no entry data.
		if ( empty( $result['values'] ) ) {
			return $entry;
		}

		// Assign the entry data if there's only one.
		if ( count( $result['values'] ) === 1 ) {
			$entry = (object) array_pop( $result['values'] );
		}

		// Assign entries to an array if there's more than one.
		if ( count( $result['values'] ) > 1 ) {
			$entry = [];
			foreach ( $result['values'] as $item ) {
				$entry[] = (object) $item;
			}
		}

		// --<
		return $entry;

	}

	/**
	 * Get the UFMatch data for a given WordPress User ID.
	 *
	 * This method optionally allows a Domain ID to be specified:
	 *
	 * * If no Domain ID is passed, then we default to current Domain ID.
	 * * If a Domain ID is passed as a string, then we search all Domain IDs.
	 *
	 * @since 0.8.2
	 *
	 * @param int        $user_id The numeric ID of the WordPress User.
	 * @param int|string $domain_id The CiviCRM Domain ID (defaults to current Domain ID).
	 * @return array|object|bool $entry The UFMatch data on success, or false on failure.
	 */
	public function entry_get_by_user_id( $user_id, $domain_id = '' ) {

		// Init return.
		$entry = false;

		// Bail if CiviCRM is not active.
		if ( ! $this->plugin->civi->is_active() ) {
			return $entry;
		}

		// Sanity checks.
		if ( ! is_numeric( $user_id ) ) {
			return $entry;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'uf_id'   => $user_id,
		];

		// If no Domain ID is specified, default to current Domain ID.
		if ( empty( $domain_id ) ) {
			$params['domain_id'] = CRM_Core_Config::domainID();
		}

		// Maybe add Domain ID if passed as an integer.
		if ( ! empty( $domain_id ) && is_numeric( $domain_id ) ) {
			$params['domain_id'] = $domain_id;
		}

		/**
		 * Filters the params used to query the UFMatch data.
		 *
		 * @since 0.5.9
		 *
		 * @param array $params The params passed to the CiviCRM API.
		 */
		$params = apply_filters( 'ceo/mapper/ufmatch/entry_get_by_user_id', $params );

		// Get all UFMatch records via API.
		$result = civicrm_api( 'UFMatch', 'get', $params );

		// Log and bail on failure.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'user_id'   => $user_id,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $entry;
		}

		// Bail if there's no entry data.
		if ( empty( $result['values'] ) ) {
			return $entry;
		}

		// Assign the entry data if there's only one.
		if ( count( $result['values'] ) === 1 ) {
			$entry = (object) array_pop( $result['values'] );
		}

		// Assign entries to an array if there's more than one.
		if ( count( $result['values'] ) > 1 ) {
			$entry = [];
			foreach ( $result['values'] as $item ) {
				$entry[] = (object) $item;
			}
		}

		// --<
		return $entry;

	}

	/**
	 * Get the UFMatch data for a given WordPress User email.
	 *
	 * This method optionally allows a Domain ID to be specified.
	 * If no Domain ID is passed, then we default to current Domain ID.
	 * If a Domain ID is passed as a string, then we search all Domain IDs.
	 *
	 * @since 0.8.2
	 *
	 * @param string     $email The WordPress User's email address.
	 * @param int|string $domain_id The CiviCRM Domain ID (defaults to current Domain ID).
	 * @return array|object|bool $entry The UFMatch data on success, or false on failure.
	 */
	public function entry_get_by_user_email( $email, $domain_id = '' ) {

		// Init return.
		$entry = false;

		// Bail if CiviCRM is not active.
		if ( ! $this->plugin->civi->is_active() ) {
			return $entry;
		}

		// Sanity checks.
		if ( ! is_email( $email ) ) {
			return $entry;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'uf_name' => $email,
		];

		// If no Domain ID is specified, default to current Domain ID.
		if ( empty( $domain_id ) ) {
			$params['domain_id'] = CRM_Core_Config::domainID();
		}

		// Maybe add Domain ID if passed as an integer.
		if ( ! empty( $domain_id ) && is_numeric( $domain_id ) ) {
			$params['domain_id'] = $domain_id;
		}

		// Get all UFMatch records via API.
		$result = civicrm_api( 'UFMatch', 'get', $params );

		// Log and bail on failure.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'email'     => $email,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $entry;
		}

		// Bail if there's no entry data.
		if ( empty( $result['values'] ) ) {
			return $entry;
		}

		// Assign the entry data if there's only one.
		if ( count( $result['values'] ) === 1 ) {
			$entry = (object) array_pop( $result['values'] );
		}

		// Assign entries to an array if there's more than one.
		if ( count( $result['values'] ) > 1 ) {
			$entry = [];
			foreach ( $result['values'] as $item ) {
				$entry[] = (object) $item;
			}
		}

		// --<
		return $entry;

	}

}
