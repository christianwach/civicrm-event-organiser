<?php
/**
 * CiviCRM Event Registration Class.
 *
 * Handles interactions with CiviCRM Event Registrations.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Event Registration Class.
 *
 * A class that encapsulates interactions with CiviCRM Registrations.
 *
 * @since 0.7
 */
class CEO_CiviCRM_Registration {

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
	 * @param CEO_CiviCRM $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin  = $parent->plugin;
		$this->civicrm = $parent;

		// Add CiviCRM hooks when parent is loaded.
		add_action( 'ceo/civicrm/loaded', [ $this, 'register_hooks' ] );

	}

	/**
	 * Register hooks on parent init.
	 *
	 * @since 0.7
	 */
	public function register_hooks() {

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get the existing Participant Role for a Post, but fall back to the default
	 * as set on the admin screen. Fall back to false otherwise.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param WP_Post $post An Event Organiser Event object.
	 * @return mixed $existing_id The numeric ID of the role, false if none exists.
	 */
	public function get_participant_role( $post = null ) {

		// Init with impossible ID.
		$existing_id = false;

		// Do we have a default set?
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_role' );

		// Override with default value if we get one.
		if ( '' !== $default && is_numeric( $default ) ) {
			$existing_id = (int) $default;
		}

		// If we have a Post.
		if ( isset( $post ) && is_object( $post ) ) {

			// Get stored value.
			$stored_id = $this->plugin->wordpress->eo->get_event_role( $post->ID );

			// Override with stored value if we get one.
			if ( '' !== $stored_id && is_numeric( $stored_id ) && $stored_id > 0 ) {
				$existing_id = (int) $stored_id;
			}

		}

		// --<
		return $existing_id;

	}

	/**
	 * Get all Participant Roles.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param WP_Post $post An Event Organiser Event object.
	 * @return array|bool $participant_roles Array of CiviCRM role data, or false if none exist.
	 */
	public function get_participant_roles( $post = null ) {

		// Bail if CiviCRM is not active.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// First, get participant_role option_group ID.
		$opt_group = [
			'version' => 3,
			'name'    => 'participant_role',
		];

		// Call CiviCRM API.
		$participant_role = civicrm_api( 'OptionGroup', 'getsingle', $opt_group );

		// Next, get option_values for that group.
		$opt_values = [
			'version'         => 3,
			'is_active'       => 1,
			'option_group_id' => $participant_role['id'],
			'options'         => [
				'limit' => 0, // Get all Participant Roles.
				'sort'  => 'weight ASC',
			],
		];

		// Call CiviCRM API.
		$participant_roles = civicrm_api( 'OptionValue', 'get', $opt_values );

		// Return the Participant Roles array if we have one.
		if ( 0 === (int) $participant_roles['is_error'] && count( $participant_roles['values'] ) > 0 ) {
			return $participant_roles;
		}

		// Fallback.
		return false;

	}

	/**
	 * Builds an array of Participant Roles keyed by ID.
	 *
	 * @since 0.8.2
	 *
	 * @return array $participant_roles The array of Participant Roles keyed by ID.
	 */
	public function get_participant_roles_mapped() {

		// First, get all Participant_Roles.
		$result = $this->get_participant_roles();

		// Bail on error or no results.
		if ( false === $result || empty( $result['values'] ) ) {
			return [];
		}

		// Build mapped array.
		$participant_roles = [];
		foreach ( $result['values'] as $key => $participant_role ) {
			$participant_roles[ (int) $participant_role['value'] ] = $participant_role['label'];
		}

		// --<
		return $participant_roles;

	}

	/**
	 * Gets the "counted" CiviCRM Participants for a given CiviCRM Event ID.
	 *
	 * @since 0.6.4
	 *
	 * @param integer $event_id The numeric CiviCRM Event ID.
	 * @param string  $select The fields to return from the query. Default is all fields.
	 * @return array|bool $participants An array of Participant data, or false on failure.
	 */
	public function participants_get_counted_for_event( $event_id, $select = '*' ) {

		// Init data.
		$participants = false;

		// Get the "counted" Status IDs.
		$counted    = $this->participant_types_counted_get();
		$status_ids = array_keys( $counted );
		if ( empty( $status_ids ) ) {
			return $participants;
		}

		// Bail if we fail to init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $is_registered;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\Participant::get( false )
				->addSelect( $select )
				->addWhere( 'event_id', '=', $event_id )
				->addWhere( 'status_id', 'IN', $status_ids )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return $participants;
		}

		// Bail if there are no results.
		if ( 0 === $result->count() ) {
			return [];
		}

		// We only need the array.
		$participants = $result->getArrayCopy();

		// --<
		return $participants;

	}

	/**
	 * Gets the data for the "counted" CiviCRM Participant Types.
	 *
	 * @since 0.6.4
	 *
	 * @return array $participant_types An array of Participant Type data, or empty on failure.
	 */
	public function participant_types_counted_get() {

		// Init return.
		$participant_types = [];

		// Bail if CiviCRM is not active.
		if ( ! $this->civicrm->is_active() ) {
			return $participant_types;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\ParticipantStatusType::get( false )
				->addWhere( 'is_counted', '=', true )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return $participant_types;
		}

		// Bail if there are no results.
		if ( 0 === $result->count() ) {
			return $participant_types;
		}

		// Parse the result set.
		foreach ( $result as $item ) {
			$participant_types[ (int) $item['id'] ] = $item;
		}

		// Sort for convenience.
		ksort( $participant_types );

		// --<
		return $participant_types;

	}

	/**
	 * Gets the data for the "not counted" CiviCRM Participant Types.
	 *
	 * @since 0.6.4
	 *
	 * @return array $participant_types An array of Participant Type data, or empty on failure.
	 */
	public function participant_types_not_counted_get() {

		// Init return.
		$participant_types = [];

		// Bail if CiviCRM is not active.
		if ( ! $this->civicrm->is_active() ) {
			return $participant_types;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\ParticipantStatusType::get( false )
				->addWhere( 'is_counted', '=', false )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return $participant_types;
		}

		// Bail if there are no results.
		if ( 0 === $result->count() ) {
			return $participant_types;
		}

		// Parse the result set.
		foreach ( $result as $item ) {
			$participant_types[ (int) $item['id'] ] = $item;
		}

		// Sort for convenience.
		ksort( $participant_types );

		// --<
		return $participant_types;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Checks the status of a CiviCRM Event's Registration option.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param WP_Post $post The Event Organiser Event object.
	 * @return string $default Checkbox checked or not.
	 */
	public function get_registration( $post ) {

		// Checkbox unticked by default.
		$default = '';

		// Sanity check.
		if ( ! is_object( $post ) ) {
			return $default;
		}

		// Get the CiviCRM Event IDs for this Event Organiser Event.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );
		if ( empty( $civi_events ) || ! is_array( $civi_events ) ) {
			return $default;
		}

		// Get the first CiviCRM Event, though any would do as they all have the same value.
		$civi_event = $this->get_event_by_id( array_shift( $civi_events ) );
		if ( false === $civi_event ) {
			return $default;
		}

		// Set checkbox to ticked if Online Registration is selected.
		if ( isset( $civi_event['is_online_registration'] ) && 1 === (int) $civi_event['is_online_registration'] ) {
			$default = ' checked="checked"';
		}

		// --<
		return $default;

	}

	/**
	 * Get a CiviCRM Event's Registration link.
	 *
	 * This should always link to the CiviCRM Base Page regardless of whether there
	 * is other CiviCRM content.
	 *
	 * @since 0.2.2
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $civi_event An array of data for the CiviCRM Event.
	 * @param bool  $other Passing true returns the link to register someone else for the CiviCRM Event.
	 * @return string $link The URL of the CiviCRM Registration page.
	 */
	public function get_registration_link( $civi_event, $other = false ) {

		// Init link.
		$link = '';

		// Bail this Event does not have Registration enabled.
		if ( ! isset( $civi_event['is_online_registration'] ) || 1 !== (int) $civi_event['is_online_registration'] ) {
			return $link;
		}

		// Init CiviCRM or bail.
		if ( ! $this->civicrm->is_active() ) {
			return $link;
		}

		// Build CiviCRM query.
		$query = 'reset=1&id=' . $civi_event['id'];
		if ( false !== $other ) {
			$query .= '&cid=0';
		}

		// Use direct Base Page link method if present.
		if ( function_exists( 'civicrm_basepage_url' ) ) {

			// Use CiviCRM to construct link.
			$link = civicrm_basepage_url(
				'civicrm/event/register',
				$query,
				true,
				null,
				false,
			);

		} else {

			// Use CiviCRM to construct link.
			$link = CRM_Utils_System::url(
				'civicrm/event/register',
				$query,
				true,
				null,
				false,
				true
			);

		}

		// --<
		return $link;

	}

	/**
	 * Gets the Registration status of a given CiviCRM Event.
	 *
	 * How this works in CiviCRM is as follows: if a CiviCRM Event has "Registration
	 * Start Date" and "Registration End Date" set, then Registration is open
	 * if now() is between those two datetimes. There is a special case to check
	 * for - when an Event has ended but "Registration End Date" is specifically
	 * set to allow Registration after the Event has ended.
	 *
	 * @see CRM_Event_BAO_Event::validRegistrationDate()
	 *
	 * @since 0.8.2
	 *
	 * @param array $civi_event The array of data that represents a CiviCRM Event.
	 * @return string|bool $status The Registration status, or false if Online Registration is not enabled.
	 */
	public function get_registration_status( $civi_event ) {

		// Bail if Online Registration is not enabled.
		if ( ! isset( $civi_event['is_online_registration'] ) ) {
			return false;
		}
		if ( 1 !== (int) $civi_event['is_online_registration'] ) {
			return false;
		}

		// Gotta have a reference to now.
		$now = new DateTime( 'now', eo_get_blog_timezone() );

		// Init Registration start.
		$reg_start = false;

		// Override with Registration start date if set.
		if ( ! empty( $civi_event['registration_start_date'] ) ) {
			$reg_start = new DateTime( $civi_event['registration_start_date'], eo_get_blog_timezone() );
		}

		/**
		 * Filter the Registration start date.
		 *
		 * @since 0.4
		 * @since 0.7 Moved to this class.
		 * @deprecated 0.8.0 Use the {@see 'ceo/civicrm/registration/start_date'} filter instead.
		 *
		 * @param DateTime|bool $reg_start The starting DateTime object for Registration.
		 * @param array         $civi_event The array of data that represents a CiviCRM Event.
		 */
		$reg_start = apply_filters_deprecated( 'civicrm_event_organiser_registration_start_date', [ $reg_start, $civi_event ], '0.8.0', 'ceo/civicrm/registration/start_date' );

		/**
		 * Filter the Registration start date.
		 *
		 * @since 0.8.0
		 *
		 * @param DateTime|bool $reg_start The starting DateTime object for Registration.
		 * @param array         $civi_event The array of data that represents a CiviCRM Event.
		 */
		$reg_start = apply_filters( 'ceo/civicrm/registration/start_date', $reg_start, $civi_event );

		// Init Registration end.
		$reg_end = false;

		// Override with Registration end date if set.
		if ( ! empty( $civi_event['registration_end_date'] ) ) {
			$reg_end = new DateTime( $civi_event['registration_end_date'], eo_get_blog_timezone() );
		}

		/**
		 * Filter the Registration end date.
		 *
		 * @since 0.4.2
		 * @since 0.7 Moved to this class.
		 * @deprecated 0.8.0 Use the {@see 'ceo/civicrm/registration/end_date'} filter instead.
		 *
		 * @param DateTime|bool $reg_end The ending DateTime object for Registration.
		 * @param array         $civi_event The array of data that represents a CiviCRM Event.
		 */
		$reg_end = apply_filters_deprecated( 'civicrm_event_organiser_registration_end_date', [ $reg_end, $civi_event ], '0.8.0', 'ceo/civicrm/registration/end_date' );

		/**
		 * Filter the Registration end date.
		 *
		 * @since 0.8.0
		 *
		 * @param DateTime|bool $reg_end The ending DateTime object for Registration.
		 * @param array         $civi_event The array of data that represents a CiviCRM Event.
		 */
		$reg_end = apply_filters( 'ceo/civicrm/registration/end_date', $reg_end, $civi_event );

		// Init Event end.
		$event_end = false;

		// Override with Event end date if set.
		if ( ! empty( $civi_event['end_date'] ) ) {
			$event_end = new DateTime( $civi_event['end_date'], eo_get_blog_timezone() );
		}

		// Assume open.
		$status = 'open';

		// Check if started yet.
		if ( $reg_start && $reg_start >= $now ) {
			$status = 'not-yet-open';

			// Check if already ended.
		} elseif ( $reg_end && $reg_end < $now ) {
			$status = 'ended';

			// If the Event has ended, Registration may still be specifically open.
		} elseif ( $event_end && $event_end < $now && ! $reg_end ) {
			$status = 'not-specifically-open';

		}

		// --<
		return $status;

	}

	/**
	 * Check if Registration is closed for a given CiviCRM Event.
	 *
	 * @since 0.3.4
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $civi_event The array of data that represents a CiviCRM Event.
	 * @return bool $closed True if Registration is closed, false otherwise.
	 */
	public function is_registration_closed( $civi_event ) {

		// Assume closed.
		$closed = true;

		// Get the Event's Registration status.
		$status = $this->get_registration_status( $civi_event );

		// Return early if Online Registration is not enabled.
		if ( false === $status ) {
			return $closed;
		}

		// Only open means open.
		if ( 'open' === $status ) {
			$closed = false;
		}

		// --<
		return $closed;

	}

	/**
	 * Enable a CiviCRM Event's Registration form.
	 *
	 * Just setting the 'is_online_registration' flag on an Event is not enough
	 * to generate a valid Online Registration form in CiviCRM. There also needs
	 * to be a default "UF Group" associated with the Event - for example the
	 * one that is supplied with a fresh installation of CiviCRM - it's called
	 * "Your Registration Info". This always seems to have ID = 12 but since it
	 * can be deleted that cannot be relied upon.
	 *
	 * We are only dealing with the profile included at the top of the page, so
	 * need to specify `weight = 1` to save that profile.
	 *
	 * @since 0.2.4
	 * @since 0.7 Moved to this class.
	 *
	 * @param array   $civi_event An array of data representing a CiviCRM Event.
	 * @param WP_Post $post The WordPress Post object.
	 */
	public function enable_registration( $civi_event, $post = null ) {

		// Does this Event have Online Registration?
		if ( 1 === (int) $civi_event['is_online_registration'] ) {

			// Get specified Registration Profile.
			$profile_id = $this->get_registration_profile( $post );

			// Construct profile params.
			$params = [
				'version'      => 3,
				'module'       => 'CiviEvent',
				'entity_table' => 'civicrm_event',
				'entity_id'    => $civi_event['id'],
				'uf_group_id'  => $profile_id,
				'is_active'    => 1,
				'weight'       => 1,
				'sequential'   => 1,
			];

			// Trigger update if this Event already has a Registration Profile.
			$existing_profile = $this->has_registration_profile( $civi_event );
			if ( false !== $existing_profile ) {
				$params['id'] = $existing_profile['id'];
			}

			// Call API.
			$result = civicrm_api( 'UFJoin', 'create', $params );

			// Log any errors.
			if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
				$e     = new Exception();
				$trace = $e->getTraceAsString();
				$log   = [
					'method'     => __METHOD__,
					'message'    => $result['error_message'],
					'civi_event' => $civi_event,
					'params'     => $params,
					'backtrace'  => $trace,
				];
				$this->plugin->log_error( $log );
			}

		}

	}

	/**
	 * Check if a CiviCRM Event has a Registration form profile set.
	 *
	 * We are only dealing with the profile included at the top of the page, so
	 * need to specify `weight = 1` to retrieve just that profile.
	 *
	 * We also need to specify the "module" - because CiviCRM Event can specify an
	 * additional module called "CiviEvent_Additional" which refers to Profiles
	 * used for (surprise, surprise) Registrations for additional people. At the
	 * moment, this plugin does not handle profiles used when "Register multiple
	 * participants" is enabled.
	 *
	 * @since 0.2.4
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $civi_event An array of data representing a CiviCRM Event.
	 * @return array|bool $result The profile data if the CiviCRM Event has one, false otherwise.
	 */
	public function has_registration_profile( $civi_event ) {

		// Define query params.
		$params = [
			'version'      => 3,
			'entity_table' => 'civicrm_event',
			'module'       => 'CiviEvent',
			'entity_id'    => $civi_event['id'],
			'weight'       => 1,
			'sequential'   => 1,
		];

		// Query via API.
		$result = civicrm_api( 'UFJoin', 'getsingle', $params );

		// Return false if we get an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return false;
		}

		// Return false if the Event has no profile.
		if ( isset( $result['count'] ) && 0 === (int) $result['count'] ) {
			return false;
		}

		// --<
		return $result;

	}

	/**
	 * Get the default Registration form profile for an Event Organiser Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.2.4
	 * @since 0.7 Moved to this class.
	 *
	 * @param WP_Post $post An Event Organiser Event object.
	 * @return int|bool $profile_id The default Registration form profile ID, false on failure.
	 */
	public function get_registration_profile( $post = null ) {

		// Init with impossible ID.
		$profile_id = false;

		// Do we have a default set?
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_profile' );

		// Override with default value if we have one.
		if ( '' !== $default && is_numeric( $default ) ) {
			$profile_id = (int) $default;
		}

		// If we have a Post.
		if ( ! is_null( $post ) && ( $post instanceof WP_Post ) ) {

			// Get stored value.
			$stored_id = $this->plugin->wordpress->eo->get_event_registration_profile( $post->ID );

			// Override with stored value if we get a value.
			if ( '' !== $stored_id && is_numeric( $stored_id ) && $stored_id > 0 ) {
				$profile_id = (int) $stored_id;
			}

		}

		// --<
		return $profile_id;

	}

	/**
	 * Gets all CiviCRM Profiles.
	 *
	 * @since 0.2.4
	 * @since 0.7 Moved to this class.
	 * @since 0.8.2 Returns Profiles array - not raw API return array.
	 *
	 * @return array|bool $profiles The array of CiviCRM Profiles, or false on failure.
	 */
	public function get_registration_profiles() {

		// Init return.
		$profiles = false;

		// Bail if we fail to init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $profiles;
		}

		// Define params.
		$params = [
			'version' => 3,
			'options' => [
				'limit' => 0, // Get all profiles.
			],
		];

		// Get them via API.
		$result = civicrm_api( 'UFGroup', 'get', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => $result['error_message'],
				'result'    => $result,
				'params'    => $params,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $profiles;
		}

		// Return early if there are no results.
		if ( empty( $result['values'] ) ) {
			return $profiles;
		}

		// Use result set.
		$profiles = $result['values'];

		// --<
		return $profiles;

	}

	/**
	 * Gets the array of allowed CiviCRM Profiles.
	 *
	 * @since 0.8.2
	 *
	 * @return array $allowed The array of allowed CiviCRM Profiles.
	 */
	public function get_registration_profiles_allowed() {

		// Get all profiles.
		$all_profiles = $this->get_registration_profiles();
		if ( false === $all_profiles ) {
			return [];
		}

		// Get allowed Profiles.
		$profiles_allowed = $this->plugin->admin->option_get( 'civi_eo_event_allowed_profiles', [] );

		/*
		 * Filter the Profiles, including only those allowed.
		 * When no Profiles have been selected, get all.
		 */
		if ( ! empty( $profiles_allowed ) ) {
			$allowed = [];
			foreach ( $all_profiles as $key => $profile ) {
				if ( in_array( (int) $profile['id'], $profiles_allowed, true ) ) {
					$allowed[] = $profile;
				}
			}
		} else {
			$allowed = $all_profiles;
		}

		/**
		 * Filters the allowed CiviCRM Profiles.
		 *
		 * @since 0.8.2
		 *
		 * @param array $allowed The array of allowed CiviCRM Profiles.
		 */
		$allowed = apply_filters( 'ceo/civicrm/registration/profiles/allowed', $allowed );

		// --<
		return $allowed;

	}

	/**
	 * Gets the array of allowed CiviCRM Profiles keyed by ID.
	 *
	 * @since 0.8.2
	 *
	 * @return array $profiles The array of allowed CiviCRM Profiles keyed by ID.
	 */
	public function get_registration_profiles_mapped() {

		// Get all profiles.
		$allowed_profiles = $this->get_registration_profiles_allowed();
		if ( false === $allowed_profiles ) {
			return [];
		}

		// Build keyed array.
		$profiles = [];
		foreach ( $allowed_profiles as $key => $profile ) {
			$profiles[ (int) $profile['id'] ] = $profile['title'];
		}

		// --<
		return $profiles;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get the default Registration Dedupe Rule for an Event Organiser Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.7.6
	 *
	 * @param WP_Post $post An Event Organiser Event object.
	 * @return int|bool $dedupe_rule_id The default Registration Dedupe Rule ID, or false on failure.
	 */
	public function get_registration_dedupe_rule( $post = null ) {

		// Init with impossible ID.
		$dedupe_rule_id = false;

		// Do we have a default set?
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_dedupe', 'xxxxx' );

		// Override with default value if we have one.
		if ( 'xxxxx' !== $default ) {
			$dedupe_rule_id = $default;
		}

		// If we have a Post.
		if ( ! is_null( $post ) && is_object( $post ) ) {

			// Get stored value.
			$stored_id = $this->plugin->wordpress->eo->get_event_registration_dedupe_rule( $post->ID );

			// Override with stored value if we have one.
			if ( '' !== $stored_id ) {
				$dedupe_rule_id = (int) $stored_id;
			}

		}

		// --<
		return $dedupe_rule_id;

	}

	/**
	 * Gets the CiviCRM Event Registration Dedupe Rules.
	 *
	 * @since 0.7.6
	 *
	 * @return array $dedupe_rules The array of Dedupe Rules, or empty on failure.
	 */
	public function get_registration_dedupe_rules() {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return [];
		}

		// Init return.
		$dedupe_rules = [];

		/*
		 * If the API4 Entity is available, use it.
		 *
		 * @see https://github.com/civicrm/civicrm-core/blob/master/Civi/Api4/DedupeRuleGroup.php#L20
		 */
		$version = CRM_Utils_System::version();
		if ( version_compare( $version, '5.39', '>=' ) ) {

			// Build params to get Dedupe Rule Groups.
			$params = [
				'limit'            => 0,
				'checkPermissions' => false,
				'where'            => [
					[ 'contact_type', '=', 'Individual' ],
				],
			];

			// Call CiviCRM API4.
			$result = civicrm_api4( 'DedupeRuleGroup', 'get', $params );

			// Bail if there are no results.
			if ( empty( $result->count() ) ) {
				return $dedupe_rules;
			}

			// Build the return array.
			foreach ( $result as $item ) {

				// Format Dedupe Rule name.
				$title = ! empty( $item['title'] ) ? $item['title'] : ( ! empty( $item['name'] ) ? $item['name'] : $item['contact_type'] );

				// Add to return array with "used" appended.
				$dedupe_rules[ (int) $item['id'] ] = $title . ' - ' . $item['used'];

			}

		} else {

			// Get the Dedupe Rules for the "Individual" Contact Type.
			$dedupe_rules = CRM_Dedupe_BAO_RuleGroup::getByType( 'Individual' );

		}

		// --<
		return $dedupe_rules;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get the default Registration form confirmation page setting for an Event Organiser Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.6.4
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $post_id The numeric ID of an Event Organiser Event.
	 * @return int|bool $setting The default Registration form confirmation page setting, false on failure.
	 */
	public function get_registration_confirm_enabled( $post_id = null ) {

		// Init with impossible value.
		$setting = false;

		// Do we have a default set?
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_confirm' );

		// Override with default value if we have one.
		if ( '' !== $default && is_numeric( $default ) ) {
			$setting = (int) $default;
		}

		// If we have a Post.
		if ( isset( $post_id ) && is_numeric( $post_id ) ) {

			// Get stored value.
			$stored_setting = $this->plugin->wordpress->eo->get_event_registration_confirm( $post_id );

			// Override with stored value if we get a value.
			if ( '' !== $stored_setting && is_numeric( $stored_setting ) ) {
				$setting = (int) $stored_setting;
			}

		}

		// --<
		return $setting;

	}

	/**
	 * Get the default Confirmation Screen "Page Title" setting for an Event Organiser Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to CiviCRM default otherwise.
	 *
	 * @since 0.8.5
	 *
	 * @param int $post_id The numeric ID of an Event Organiser Event.
	 * @return string $setting The default Confirmation Screen "Page Title" setting.
	 */
	public function get_registration_confirm_title( $post_id = null ) {

		// Init as empty.
		$setting = '';

		// Use default value if we have one.
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_confirm_title' );
		if ( ! empty( $default ) ) {
			$setting = $default;
		}

		// If we have a Post.
		if ( ! empty( $post_id ) && is_numeric( $post_id ) ) {

			// Override with stored value if we get a value.
			$stored_setting = $this->plugin->wordpress->eo->get_event_registration_confirm_title( $post_id );
			if ( ! empty( $stored_setting ) ) {
				$setting = $stored_setting;
			}

		}

		// If still empty, use the CiviCRM default.
		if ( empty( $setting ) ) {
			$setting = __( 'Confirm Your Registration Information', 'civicrm-event-organiser' );
		}

		// --<
		return $setting;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get the default Thank You Screen "Page Title" setting for an Event Organiser Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to CiviCRM default otherwise.
	 *
	 * @since 0.8.5
	 *
	 * @param int $post_id The numeric ID of an Event Organiser Event.
	 * @return string $setting The default Confirmation Screen "Page Title" setting.
	 */
	public function get_registration_thank_you_title( $post_id = null ) {

		// Init as empty.
		$setting = '';

		// Use default value if we have one.
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_thank_you_title' );
		if ( ! empty( $default ) ) {
			$setting = $default;
		}

		// If we have a Post.
		if ( ! empty( $post_id ) && is_numeric( $post_id ) ) {

			// Override with stored value if we get a value.
			$stored_setting = $this->plugin->wordpress->eo->get_event_registration_thank_you_title( $post_id );
			if ( ! empty( $stored_setting ) ) {
				$setting = $stored_setting;
			}

		}

		// If still empty, use the CiviCRM default.
		if ( empty( $setting ) ) {
			$setting = __( 'Thank You for Registering', 'civicrm-event-organiser' );
		}

		// --<
		return $setting;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get the default Confirmation Email setting for an Event Organiser Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of an Event Organiser Event.
	 * @return int|bool $setting The default Confirmation Email setting, false on failure.
	 */
	public function get_registration_send_email_enabled( $post_id = null ) {

		// Init with impossible value.
		$setting = false;

		// Do we have a default set?
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_send_email' );

		// Override with default value if we have one.
		if ( '' !== $default && is_numeric( $default ) ) {
			$setting = (int) $default;
		}

		// If we have a Post.
		if ( isset( $post_id ) && is_numeric( $post_id ) ) {

			// Get stored value.
			$stored_setting = $this->plugin->wordpress->eo->get_event_registration_send_email( $post_id );

			// Override with stored value if we get a value.
			if ( '' !== $stored_setting && is_numeric( $stored_setting ) ) {
				$setting = (int) $stored_setting;
			}

		}

		// --<
		return $setting;

	}

	/**
	 * Get the default Confirmation Email "From Name" setting for an Event Organiser Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of an Event Organiser Event.
	 * @return string $setting The default Confirmation Email "From Name" setting.
	 */
	public function get_registration_send_email_from_name( $post_id = null ) {

		// Init as empty.
		$setting = '';

		// Use default value if we have one.
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_send_email_from_name' );
		if ( ! empty( $default ) ) {
			$setting = $default;
		}

		// If we have a Post.
		if ( ! empty( $post_id ) && is_numeric( $post_id ) ) {

			// Override with stored value if we get a value.
			$stored_setting = $this->plugin->wordpress->eo->get_event_registration_send_email_from_name( $post_id );
			if ( ! empty( $stored_setting ) ) {
				$setting = $stored_setting;
			}

		}

		// --<
		return $setting;

	}

	/**
	 * Get the default Confirmation Email "From Email" setting for an Event Organiser Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of an Event Organiser Event.
	 * @return string $setting The default Confirmation Email "From Email" setting.
	 */
	public function get_registration_send_email_from( $post_id = null ) {

		// Init as empty.
		$setting = '';

		// Use default value if we have one.
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_send_email_from' );
		if ( ! empty( $default ) ) {
			$setting = $default;
		}

		// If we have a Post.
		if ( ! empty( $post_id ) && is_numeric( $post_id ) ) {

			// Override with stored value if we get a value.
			$stored_setting = $this->plugin->wordpress->eo->get_event_registration_send_email_from( $post_id );
			if ( ! empty( $stored_setting ) ) {
				$setting = $stored_setting;
			}

		}

		// --<
		return $setting;

	}

	/**
	 * Get the Confirmation Email "CC" setting for an Event Organiser Event.
	 *
	 * @since 0.7.4
	 *
	 * @param int $post_id The numeric ID of an Event Organiser Event.
	 * @return string $setting The Confirmation Email "CC" setting.
	 */
	public function get_registration_send_email_cc( $post_id = null ) {

		// Init as empty.
		$setting = '';

		// Use default value if we have one.
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_send_email_cc' );
		if ( ! empty( $default ) ) {
			$setting = $default;
		}

		// If we have a Post.
		if ( ! empty( $post_id ) && is_numeric( $post_id ) ) {

			// Override with stored value if we get a value.
			$stored_setting = $this->plugin->wordpress->eo->get_event_registration_send_email_cc( $post_id );
			if ( ! empty( $stored_setting ) && 'none' !== $stored_setting ) {
				$setting = $stored_setting;
			}

			// Override with empty value if we get the "blanked out" token.
			if ( 'none' === $stored_setting ) {
				$setting = '';
			}

		}

		// --<
		return $setting;

	}

	/**
	 * Get the Confirmation Email "BCC" setting for an Event Organiser Event.
	 *
	 * @since 0.7.4
	 *
	 * @param int $post_id The numeric ID of an Event Organiser Event.
	 * @return string $setting The Confirmation Email "BCC" setting.
	 */
	public function get_registration_send_email_bcc( $post_id = null ) {

		// Init as empty.
		$setting = '';

		// Use default value if we have one.
		$default = $this->plugin->admin->option_get( 'civi_eo_event_default_send_email_bcc' );
		if ( ! empty( $default ) ) {
			$setting = $default;
		}

		// If we have a Post.
		if ( ! empty( $post_id ) && is_numeric( $post_id ) ) {

			// Override with stored value if we get a value.
			$stored_setting = $this->plugin->wordpress->eo->get_event_registration_send_email_bcc( $post_id );
			if ( ! empty( $stored_setting ) && 'none' !== $stored_setting ) {
				$setting = $stored_setting;
			}

			// Override with empty value if we get the "blanked out" token.
			if ( 'none' === $stored_setting ) {
				$setting = '';
			}

		}

		// --<
		return $setting;

	}

	/**
	 * Gets the number of free places for a given CiviCRM Event.
	 *
	 * @since 0.8.6
	 *
	 * @param int $event_id The numeric ID of a CiviCRM Event.
	 * @return int|bool $remaining The number of free places, or false on failure.
	 */
	public function get_remaining_participants( $event_id ) {

		// Init as not registered.
		$remaining = false;

		// Bail if we fail to init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $remaining;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\Event::get( false )
				->addSelect( 'remaining_participants' )
				->addWhere( 'id', '=', $event_id )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return $remaining;
		}

		// Return failure if no result.
		if ( 0 === $result->count() ) {
			return $remaining;
		}

		// The first result is what we're after.
		$data = $result->first();

		// Cast as integer.
		$remaining = (int) $data['remaining_participants'];

		// --<
		return $remaining;

	}

	/**
	 * Check if a Contact is registered for a given CiviCRM Event.
	 *
	 * @since 0.8.2
	 *
	 * @param int $event_id The numeric ID of a CiviCRM Event.
	 * @param int $contact_id The numeric ID of a CiviCRM Contact.
	 * @return bool $is_registered True if the Contact is registered, false otherwise.
	 */
	public function is_registered( $event_id, $contact_id ) {

		// Init as not registered.
		$is_registered = false;

		// Bail if we fail to init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $is_registered;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\Participant::get( false )
				->addSelect( '*' )
				->addWhere( 'event_id', '=', $event_id )
				->addWhere( 'contact_id', '=', $contact_id )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return $is_registered;
		}

		// Bail if there are none.
		if ( 0 === $result->count() ) {
			return $is_registered;
		}

		// We only need the array of records.
		$participant_records = $result->getArrayCopy();

		// Anyone whose status type has `is_counted` OR is on the waitlist should be considered as registered.
		$is_counted          = CRM_Event_PseudoConstant::participantStatus( null, 'is_counted = 1' );
		$on_waitlist         = CRM_Event_PseudoConstant::participantStatus( null, "name = 'On waitlist'" );
		$registered_statuses = $is_counted + $on_waitlist;

		// Let's check the records (though there should only be one).
		foreach ( $participant_records as $participant_record ) {
			if ( array_key_exists( $participant_record['status_id'], $registered_statuses ) ) {
				$is_registered = true;
				break;
			}
		}

		// --<
		return $is_registered;

	}

	/**
	 * Checks if a CiviCRM Event is full.
	 *
	 * @since 0.6.4
	 *
	 * @param array $civi_event The array of CiviCRM Event data.
	 * @return bool $full True if the Event is full, false otherwise.
	 */
	public function is_full( $civi_event ) {

		// Assume not full.
		$full = false;

		// If "max_participants" is not set, then there is no Participant limit.
		if ( ! empty( $civi_event['max_participants'] ) ) {

			// Get the Event's "counted" Participants.
			$participants = $this->participants_get_counted_for_event( $civi_event['id'] );

			// Skip on error.
			if ( false !== $participants ) {

				// It's full when the count is greater than the Field setting.
				if ( count( $participants ) >= (int) $civi_event['max_participants'] ) {
					$full = true;
				}

			}

		}

		/**
		 * Allows the "is full" logic in this method to be overridden.
		 *
		 * Some plugins or extensions may determine what it means for an Event to be full
		 * in custom ways.
		 *
		 * @since 0.6.4
		 *
		 * @param bool  $full True if the Event is full, false otherwise.
		 * @param array $civi_event The array of CiviCRM Event data.
		 */
		$full = apply_filters( 'ceo/civicrm/registration/is_full', $full, $civi_event );

		// --<
		return $full;

	}

}
