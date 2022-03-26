<?php
/**
 * CiviCRM Event Registration Class.
 *
 * Handles interactions with CiviCRM Event Registrations.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser CiviCRM Event Registration Class.
 *
 * A class that encapsulates interactions with CiviCRM Registrations.
 *
 * @since 0.7
 */
class CiviCRM_WP_Event_Organiser_CiviCRM_Registration {

	/**
	 * Plugin object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $civicrm The CiviCRM object.
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
		$this->plugin = $parent->plugin;
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

	// -------------------------------------------------------------------------

	/**
	 * Get the existing Participant Role for a Post, but fall back to the default
	 * as set on the admin screen. Fall back to false otherwise.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $post An Event Organiser Event object.
	 * @return mixed $existing_id The numeric ID of the role, false if none exists.
	 */
	public function get_participant_role( $post = null ) {

		// Init with impossible ID.
		$existing_id = false;

		// Do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_role' );

		// Override with default value if we get one.
		if ( $default !== '' && is_numeric( $default ) ) {
			$existing_id = absint( $default );
		}

		// If we have a Post.
		if ( isset( $post ) && is_object( $post ) ) {

			// Get stored value.
			$stored_id = $this->plugin->eo->get_event_role( $post->ID );

			// Override with stored value if we get one.
			if ( $stored_id !== '' && is_numeric( $stored_id ) && $stored_id > 0 ) {
				$existing_id = absint( $stored_id );
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
	 * @param object $post An Event Organiser Event object.
	 * @return array|bool $participant_roles Array of CiviCRM role data, or false if none exist.
	 */
	public function get_participant_roles( $post = null ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// First, get participant_role option_group ID.
		$opt_group = [
			'version' => 3,
			'name' => 'participant_role',
		];

		// Call CiviCRM API.
		$participant_role = civicrm_api( 'OptionGroup', 'getsingle', $opt_group );

		// Next, get option_values for that group.
		$opt_values = [
			'version' => 3,
			'is_active' => 1,
			'option_group_id' => $participant_role['id'],
			'options' => [
				'sort' => 'weight ASC',
			],
		];

		// Call CiviCRM API.
		$participant_roles = civicrm_api( 'OptionValue', 'get', $opt_values );

		// Return the Participant Roles array if we have one.
		if ( $participant_roles['is_error'] == '0' && count( $participant_roles['values'] ) > 0 ) {
			return $participant_roles;
		}

		// Fallback.
		return false;

	}

	/**
	 * Builds a form element for Participant Roles.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $post An Event Organiser Event object.
	 * @return str $html Markup to display in the form.
	 */
	public function get_participant_roles_select( $post = null ) {

		// Init html.
		$html = '';

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return $html;
		}

		// First, get all participant_roles.
		$all_roles = $this->get_participant_roles();

		// Did we get any?
		if ( $all_roles['is_error'] == '0' && count( $all_roles['values'] ) > 0 ) {

			// Get the values array.
			$roles = $all_roles['values'];

			// Init options.
			$options = [];

			// Get existing role ID.
			$existing_id = $this->get_participant_role( $post );

			// Loop.
			foreach ( $roles as $key => $role ) {

				// Get role.
				$role_id = absint( $role['value'] );

				// Init selected.
				$selected = '';

				// Override if the value is the same as in the Post.
				if ( $existing_id === $role_id ) {
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

	// -------------------------------------------------------------------------

	/**
	 * Checks the status of a CiviCRM Event's Registration option.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $post The WP Event object.
	 * @return str $default Checkbox checked or not.
	 */
	public function get_registration( $post ) {

		// Checkbox unticked by default.
		$default = '';

		// Sanity check.
		if ( ! is_object( $post ) ) {
			return $default;
		}

		// Get CiviCRM Events for this Event Organiser Event.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );

		// Did we get any?
		if ( is_array( $civi_events ) && count( $civi_events ) > 0 ) {

			// Get the first CiviCRM Event, though any would do as they all have the same value.
			$civi_event = $this->get_event_by_id( array_shift( $civi_events ) );

			// Set checkbox to ticked if Online Registration is selected.
			if ( $civi_event !== false && $civi_event['is_error'] == '0' && $civi_event['is_online_registration'] == '1' ) {
				$default = ' checked="checked"';
			}

		}

		// --<
		return $default;

	}

	/**
	 * Get a CiviCRM Event's Registration link.
	 *
	 * @since 0.2.2
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $civi_event An array of data for the CiviCRM Event.
	 * @return str $link The URL of the CiviCRM Registration page.
	 */
	public function get_registration_link( $civi_event ) {

		// Init link.
		$link = '';

		// If this Event has Registration enabled.
		if ( isset( $civi_event['is_online_registration'] ) && $civi_event['is_online_registration'] == '1' ) {

			// Init CiviCRM or bail.
			if ( ! $this->civicrm->is_active() ) {
				return $link;
			}

			// Use CiviCRM to construct link.
			$link = CRM_Utils_System::url(
				'civicrm/event/register', 'reset=1&id=' . $civi_event['id'],
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
	 * Check if Registration is closed for a given CiviCRM Event.
	 *
	 * How this works in CiviCRM is as follows: if a CiviCRM Event has "Registration
	 * Start Date" and "Registration End Date" set, then Registration is open
	 * if now() is between those two datetimes. There is a special case to check
	 * for - when an Event has ended but "Registration End Date" is specifically
	 * set to allow Registration after the Event has ended.
	 *
	 * @see CRM_Event_BAO_Event::validRegistrationDate()
	 *
	 * @since 0.3.4
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $civi_event The array of data that represents a CiviCRM Event.
	 * @return bool $closed True if Registration is closed, false otherwise.
	 */
	public function is_registration_closed( $civi_event ) {

		// Bail if Online Registration is not enabled.
		if ( ! isset( $civi_event['is_online_registration'] ) ) {
			return true;
		}
		if ( $civi_event['is_online_registration'] != 1 ) {
			return true;
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
		 *
		 * @param obj $reg_start The starting DateTime object for Registration.
		 * @param array $civi_event The array of data that represents a CiviCRM Event.
		 * @return obj $reg_start The modified starting DateTime object for Registration.
		 */
		$reg_start = apply_filters( 'civicrm_event_organiser_registration_start_date', $reg_start, $civi_event );

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
		 *
		 * @param obj $reg_end The ending DateTime object for Registration.
		 * @param array $civi_event The array of data that represents a CiviCRM Event.
		 * @return obj $reg_end The modified ending DateTime object for Registration.
		 */
		$reg_end = apply_filters( 'civicrm_event_organiser_registration_end_date', $reg_end, $civi_event );

		// Init Event end.
		$event_end = false;

		// Override with Event end date if set.
		if ( ! empty( $civi_event['end_date'] ) ) {
			$event_end = new DateTime( $civi_event['end_date'], eo_get_blog_timezone() );
		}

		// Assume open.
		$open = true;

		// Check if started yet.
		if ( $reg_start && $reg_start >= $now ) {
			$open = false;

		// Check if already ended.
		} elseif ( $reg_end && $reg_end < $now ) {
			$open = false;

		// If the Event has ended, Registration may still be specifically open.
		} elseif ( $event_end && $event_end < $now && $reg_end === false ) {
			$open = false;

		}

		// Flip for appropriate value.
		$closed = ! $open;

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
	 * @param array $civi_event An array of data representing a CiviCRM Event.
	 * @param object $post The WP Post object.
	 */
	public function enable_registration( $civi_event, $post = null ) {

		// Does this Event have Online Registration?
		if ( $civi_event['is_online_registration'] == 1 ) {

			// Get specified Registration Profile.
			$profile_id = $this->get_registration_profile( $post );

			// Construct profile params.
			$params = [
				'version' => 3,
				'module' => 'CiviEvent',
				'entity_table' => 'civicrm_event',
				'entity_id' => $civi_event['id'],
				'uf_group_id' => $profile_id,
				'is_active' => 1,
				'weight' => 1,
				'sequential' => 1,
			];

			// Trigger update if this Event already has a Registration Profile.
			$existing_profile = $this->has_registration_profile( $civi_event );
			if ( $existing_profile !== false ) {
				$params['id'] = $existing_profile['id'];
			}

			// Call API.
			$result = civicrm_api( 'UFJoin', 'create', $params );

			// Log any errors.
			if ( ! empty( $result['is_error'] ) ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => $result['error_message'],
					'civi_event' => $civi_event,
					'params' => $params,
					'backtrace' => $trace,
				], true ) );
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
			'version' => 3,
			'entity_table' => 'civicrm_event',
			'module' => 'CiviEvent',
			'entity_id' => $civi_event['id'],
			'weight' => 1,
			'sequential' => 1,
		];

		// Query via API.
		$result = civicrm_api( 'UFJoin', 'getsingle', $params );

		// Return false if we get an error.
		if ( isset( $result['is_error'] ) && $result['is_error'] == '1' ) {
			return false;
		}

		// Return false if the Event has no profile.
		if ( isset( $result['count'] ) && $result['count'] == '0' ) {
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
	 * @param object $post An Event Organiser Event object.
	 * @return int|bool $profile_id The default Registration form profile ID, false on failure.
	 */
	public function get_registration_profile( $post = null ) {

		// Init with impossible ID.
		$profile_id = false;

		// Do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_profile' );

		// Override with default value if we have one.
		if ( $default !== '' && is_numeric( $default ) ) {
			$profile_id = absint( $default );
		}

		// If we have a Post.
		if ( isset( $post ) && is_object( $post ) ) {

			// Get stored value.
			$stored_id = $this->plugin->eo->get_event_registration_profile( $post->ID );

			// Override with stored value if we get a value.
			if ( $stored_id !== '' && is_numeric( $stored_id ) && $stored_id > 0 ) {
				$profile_id = absint( $stored_id );
			}

		}

		// --<
		return $profile_id;

	}

	/**
	 * Get all CiviCRM Event Registration form profiles.
	 *
	 * @since 0.2.4
	 * @since 0.7 Moved to this class.
	 *
	 * @return array|bool $result CiviCRM API return array, or false on failure.
	 */
	public function get_registration_profiles() {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Define params.
		$params = [
			'version' => 3,
		];

		// Get them via API.
		$result = civicrm_api( 'UFGroup', 'get', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $result['error_message'],
				'result' => $result,
				'params' => $params,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// --<
		return $result;

	}

	/**
	 * Get all CiviCRM Event Registration form profiles formatted as a dropdown list.
	 *
	 * @since 0.2.4
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $post An Event Organiser Event object.
	 * @return str $html Markup containing select options.
	 */
	public function get_registration_profiles_select( $post = null ) {

		// Init return.
		$html = '';

		// Init CiviCRM or bail.
		if ( ! $this->civicrm->is_active() ) {
			return $html;
		}

		// Get all profiles.
		$result = $this->get_registration_profiles();

		// Did we get any?
		if (
			$result !== false &&
			$result['is_error'] == '0' &&
			count( $result['values'] ) > 0
		) {

			// Get the values array.
			$profiles = $result['values'];

			// Init options.
			$options = [];

			// Get existing profile ID.
			$existing_id = $this->get_registration_profile( $post );

			// Loop.
			foreach ( $profiles as $key => $profile ) {

				// Get profile value.
				$profile_id = absint( $profile['id'] );

				// Init selected.
				$selected = '';

				// Set selected if this value is the same as the default.
				if ( $existing_id === $profile_id ) {
					$selected = ' selected="selected"';
				}

				// Construct option.
				$options[] = '<option value="' . $profile_id . '"' . $selected . '>' . esc_html( $profile['title'] ) . '</option>';

			}

			// Create html.
			$html = implode( "\n", $options );

		}

		// --<
		return $html;

	}

	// -------------------------------------------------------------------------

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
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_confirm' );

		// Override with default value if we have one.
		if ( $default !== '' && is_numeric( $default ) ) {
			$setting = absint( $default );
		}

		// If we have a Post.
		if ( isset( $post_id ) && is_numeric( $post_id ) ) {

			// Get stored value.
			$stored_setting = $this->plugin->eo->get_event_registration_confirm( $post_id );

			// Override with stored value if we get a value.
			if ( $stored_setting !== '' && is_numeric( $stored_setting ) ) {
				$setting = absint( $stored_setting );
			}

		}

		// --<
		return $setting;

	}

} // Class ends.
