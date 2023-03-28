<?php
/**
 * Event Organiser Class.
 *
 * Handles functionality generally related to Event Organiser.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Event Organiser Event Organiser utility Class.
 *
 * A class that encapsulates functionality generally related to Event Organiser.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_EO {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Insert Event flag.
	 *
	 * @since 0.1
	 * @access public
	 * @var bool $insert_event True if inserting an Event, false otherwise.
	 */
	public $insert_event = false;

	/**
	 * Metabox nonce name.
	 *
	 * @since 0.7.4
	 * @access private
	 * @var string $nonce_field The name of the metabox nonce element.
	 */
	private $nonce_field = 'ceo_event_nonce';

	/**
	 * Metabox nonce action.
	 *
	 * @since 0.7.4
	 * @access private
	 * @var string $nonce_action The name of the metabox nonce action.
	 */
	private $nonce_action = 'ceo_event_action';

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

		// Add Event Organiser hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', [ $this, 'register_hooks' ] );

	}

	/**
	 * Register hooks if Event Organiser is present.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Check for Event Organiser.
		if ( ! $this->is_active() ) {
			return;
		}

		// Intercept "Insert Post".
		add_action( 'wp_insert_post', [ $this, 'insert_post' ], 10, 2 );

		// Intercept "Save Event".
		add_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ], 10, 1 );

		/*
		// Intercept "Update Event" - though by misuse of a filter.
		add_filter( 'eventorganiser_update_event_event_data', [ $this, 'intercept_update_event' ], 10, 3 );
		*/

		// Intercept before "Delete Post".
		add_action( 'before_delete_post', [ $this, 'intercept_before_delete_post' ], 10, 1 );

		// Intercept "Delete Event Occurrences" - which is the preferred way to hook into Event deletion.
		add_action( 'eventorganiser_delete_event_occurrences', [ $this, 'delete_event_occurrences' ], 10, 2 );

		// Intercept before "Break Occurrence".
		add_action( 'eventorganiser_pre_break_occurrence', [ $this, 'pre_break_occurrence' ], 10, 2 );

		// There's no hook for 'eventorganiser_delete_event_occurrence', which moves the Occurrence.
		// To the 'exclude' array of the date sequence.

		// Intercept after "Break Occurrence".
		add_action( 'eventorganiser_occurrence_broken', [ $this, 'occurrence_broken' ], 10, 3 );

		// Intercept "Delete Occurrence" in admin calendar.
		add_action( 'eventorganiser_admin_calendar_occurrence_deleted', [ $this, 'occurrence_deleted' ], 10, 2 );

		/*
		// Debug.
		add_filter( 'eventorganiser_pre_event_content', [ $this, 'pre_event_content' ], 10, 2 );
		*/

		// Add our Event meta box.
		add_action( 'add_meta_boxes_event', [ $this, 'event_meta_boxes' ], 11 );

		// Maybe add a Menu Item to CiviCRM Admin Utilities menu.
		add_action( 'civicrm_admin_utilities_menu_top', [ $this, 'menu_item_add_to_cau' ], 10, 2 );

		// Maybe add a Menu Item to the CiviCRM Event's "Event Links" menu.
		add_action( 'civicrm_alterContent', [ $this, 'menu_item_add_to_civi' ], 10, 4 );

		// Maybe add a link to action links on the Events list table.
		add_action( 'post_row_actions', [ $this, 'menu_item_add_to_row_actions' ], 10, 2 );

	}

	/**
	 * Utility to check if Event Organiser is present and active.
	 *
	 * @since 0.1
	 *
	 * @return bool $eo_active True if Event Organiser is active, false otherwise.
	 */
	public function is_active() {

		// Only check once.
		static $eo_active = false;
		if ( $eo_active ) {
			return true;
		}

		// Access Event Organiser option.
		$installed_version = defined( 'EVENT_ORGANISER_VER' ) ? EVENT_ORGANISER_VER : '0';

		// Assume we're okay.
		$eo_active = true;

		// This plugin will not work without Event Organiser v3+.
		if ( $installed_version === '0' || version_compare( $installed_version, '3', '<' ) ) {

			// Let's show an admin notice.
			add_action( 'admin_notices', [ $this->plugin->db, 'dependency_alert' ] );

			// We're not okay.
			$eo_active = false;

		}

		// --<
		return $eo_active;

	}

	// -------------------------------------------------------------------------

	/**
	 * Intercept "Insert Post" and check if we're inserting an Event.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @param object $post The WP Post object.
	 */
	public function insert_post( $post_id, $post ) {

		// Kick out if not Event.
		if ( $post->post_type != 'event' ) {
			return;
		}

		// Set flag.
		$this->insert_event = true;

		// Check the validity of our CiviCRM options.
		$success = $this->plugin->civi->event->validate_civi_options( $post_id, $post );

	}

	/**
	 * Intercept "Save Event".
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function intercept_save_event( $post_id ) {

		// Authenticate.
		$nonce = isset( $_POST[ $this->nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->nonce_field ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			return;
		}

		// Save custom Event Organiser Event components.
		$this->save_event_components( $post_id );

		// Sync checkbox is only shown to people who can publish Posts.
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}

		// Is our checkbox checked?
		$sync = isset( $_POST['civi_eo_event_sync'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_sync'] ) ) : 0;
		if ( '1' !== (string) $sync ) {
			return;
		}

		// Get Post data.
		$post = get_post( $post_id );

		// Get all dates.
		$dates = $this->get_all_dates( $post_id );

		/*
		// Get Event data.
		$schedule = eo_get_event_schedule( $post_id );
		*/

		// Prevent recursion.
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ], 10 );
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10 );
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ], 10 );

		// Update our CiviCRM Events - or create new if none exist.
		$this->plugin->civi->event->update_civi_events( $post, $dates );

		// Restore hooks.
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10, 4 );
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ], 10, 4 );

	}

	/**
	 * Intercept "Update Event".
	 *
	 * Disabled because it's unused. Also, there appears to be some confusion
	 * regarding the filter signature in Event Organiser itself.
	 *
	 * @see https://github.com/stephenharris/Event-Organiser/blob/develop/includes/event.php#L76
	 *
	 * @since 0.1
	 *
	 * @param array $event_data The new Event data.
	 * @param int $post_id The numeric ID of the WP Post.
	 * @param array $post_data The updated Post data.
	 * @return array $event_data The updated Event data.
	 */
	public function intercept_update_event( $event_data, $post_id, $post_data ) {

		// --<
		return $event_data;

	}

	/**
	 * Intercept before "Delete Post".
	 *
	 * @since 0.4
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function intercept_before_delete_post( $post_id ) {

		// Get Post data.
		$post = get_post( $post_id );

		// Bail if not an Event.
		if ( $post->post_type != 'event' ) {
			return;
		}

		// Get correspondences from post meta to use once the Event has been deleted.
		$this->saved_correspondences = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );

	}

	/**
	 * Intercept "Delete Event Occurrences".
	 *
	 * There is an ambiguity as to when this method is called, because it will
	 * be called when an Event Organiser Event is deleted AND when an existing
	 * Event has its date(s) changed.
	 *
	 * If the date(s) have been changed without "Sync this Event with CiviCRM"
	 * selected, then the next time the Event is updated, the changed dates will
	 * be handled by CiviCRM_WP_Event_Organiser_CiviCRM_Event::event_updated()
	 *
	 * If "Sync this Event with CiviCRM" is selected during the Event update and
	 * the date(s) have been changed, then this method is called during the
	 * update process.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @param array|bool $occurrence_ids An array of Occurrence IDs to be deleted, or false if all Occurrences are to be removed.
	 */
	public function delete_event_occurrences( $post_id, $occurrence_ids ) {

		// If an Event is not being deleted.
		if ( ! doing_action( 'delete_post' ) ) {

			// Authenticate.
			$nonce = isset( $_POST[ $this->nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->nonce_field ] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
				return;
			}

			// Bail if our sync checkbox is not checked.
			$sync = isset( $_POST['civi_eo_event_sync'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_sync'] ) ) : 0;
			if ( '1' !== (string) $sync ) {
				return;
			}

		}

		/*
		 * Once again, the question arises as to whether we should actually delete
		 * the CiviCRM Events or set them to "disabled"... I guess this behaviour could
		 * be set as a plugin option.
		 *
		 * Also whether we should delete the correspondences or transfer them to an
		 * "inactive" array of some kind.
		 */

		// Prevent recursion.
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ], 10 );
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10 );
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ], 10 );

		// Are we deleting an Event?
		if ( doing_action( 'delete_post' ) && isset( $this->saved_correspondences ) ) {

			// Yes: get IDs from saved post meta.
			$correspondences = $this->saved_correspondences;

		} else {

			// Get IDs from post meta.
			$correspondences = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );

		}

		// Loop through them.
		foreach ( $correspondences as $occurrence_id => $civi_event_id ) {

			// Is this Occurrence being deleted?
			if ( $occurrence_ids === false || in_array( $occurrence_id, $occurrence_ids ) ) {

				// Disable corresponding CiviCRM Event.
				$return = $this->plugin->civi->event->disable_civi_event( $civi_event_id );

				// Maybe delete the CiviCRM Event correspondence for this Occurrence.
				if ( doing_action( 'delete_post' ) ) {
					$this->plugin->mapping->clear_event_correspondence( $post_id, $occurrence_id, $civi_event_id );
				}

			}

		}

		// Restore hooks.
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10, 4 );
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ], 10, 4 );

		// Bail if an Event is not being deleted.
		if ( ! doing_action( 'delete_post' ) ) {
			return;
		}

		/*
		// Delete all the stored CiviCRM Event correspondences for this Event Organiser Event.
		$this->plugin->mapping->clear_event_correspondences( $post_id, $correspondences );

		// TODO: Decide if we delete CiviCRM Events.

		// Delete those CiviCRM Events - not used at present.
		$this->plugin->civi->event->delete_civi_events( $correspondences );
		*/

	}

	/**
	 * Intercept "Delete Occurrence".
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @param int $occurrence_id The numeric ID of the Event Organiser Event Occurrence.
	 */
	public function occurrence_deleted( $post_id, $occurrence_id ) {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Get CiviCRM Event ID from post meta.
		$civi_event_id = $this->plugin->mapping->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );

		// Disable CiviCRM Event.
		$return = $this->plugin->civi->event->disable_civi_event( $civi_event_id );

		// Convert Occurrence to orphaned.
		$this->plugin->mapping->occurrence_orphaned( $post_id, $occurrence_id, $civi_event_id );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update an Event Organiser Event, given a CiviCRM Event.
	 *
	 * If no Event Organiser Event exists then create one. Please note that this
	 * method will NOT create sequences for the time being.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_event An array of data for the CiviCRM Event.
	 * @return int|WP_Error $event_id The numeric ID of the Event Organiser Event, or WP_Error on failure.
	 */
	public function update_event( $civi_event ) {

		// Make sure we have a valid end date.
		if ( empty( $civi_event['end_date'] ) || $civi_event['end_date'] == 'null' ) {
			$end_date = '';
		} else {
			$end_date = new DateTime( $civi_event['end_date'], eo_get_blog_timezone() );
		}

		// Define schedule.
		$event_data = [

			// Start date.
			'start' => new DateTime( $civi_event['start_date'], eo_get_blog_timezone() ),

			// End date and end of schedule are the same.
			'end' => $end_date,
			'schedule_last' => $end_date,

			// We can't tell if a CiviCRM Event is repeating, so only once.
			'frequency' => 1,

			// CiviCRM does not have "all day".
			'all_day' => 0,

			// We can't tell if a CiviCRM Event is repeating.
			'schedule' => 'once',

		];

		/*
		 * Init Post array with quick fixes for Windows.
		 * Note: These may no longer be needed.
		 */
		$post_data = [
			'to_ping' => '',
			'pinged' => '',
			'post_content_filtered' => '',
		];

		// We must have at minimum a Post title.
		$post_data['post_title'] = __( 'Untitled CiviCRM Event', 'civicrm-event-organiser' );
		if ( ! empty( $civi_event['title'] ) ) {
			$post_data['post_title'] = $civi_event['title'];
		}

		// Assign Description and Summary if present.
		if ( ! empty( $civi_event['description'] ) ) {
			$post_data['post_content'] = $civi_event['description'];
		}
		if ( ! empty( $civi_event['summary'] ) ) {
			$post_data['post_excerpt'] = $civi_event['summary'];
		}

		// Test for created date, which may be absent.
		if ( isset( $civi_event['created_date'] ) && ! empty( $civi_event['created_date'] ) ) {

			// Create DateTime object.
			$datetime = new DateTime( $civi_event['created_date'], eo_get_blog_timezone() );

			// Add it, but format it first since CiviCRM seems to send data in the form 20150916135435.
			$post_data['post_date'] = $datetime->format( 'Y-m-d H:i:s' );

		}

		// Init Taxonomy params.
		$post_data['tax_input'] = [];

		// Init Venue as undefined.
		$venue_id = 0;

		// Get Location ID.
		if ( isset( $civi_event['loc_block_id'] ) ) {

			// We have a Location...

			// Get Location data.
			$location = $this->plugin->civi->location->get_location_by_id( $civi_event['loc_block_id'] );

			// Get corresponding Event Organiser Venue ID.
			$venue_id = $this->plugin->eo_venue->get_venue_id( $location );

			// If we get a match, create/update Venue.
			if ( $venue_id === false ) {
				$venue_id = $this->plugin->eo_venue->create_venue( $location );
			} else {
				$venue_id = $this->plugin->eo_venue->update_venue( $location );
			}

		}

		// Add Venue ID if we get one.
		if ( ! empty( $venue_id ) && is_int( $venue_id ) ) {
			$post_data['tax_input']['event-venue'] = [ $venue_id ];
		}

		// Init Category as undefined.
		$terms = [];

		// Get Location ID.
		if ( isset( $civi_event['event_type_id'] ) ) {

			// We have a Category...

			// Get Event Type data for this pseudo-ID (actually "value").
			$type = $this->plugin->taxonomy->get_event_type_by_value( $civi_event['event_type_id'] );

			// Does this Event Type have an existing Term?
			$term_id = $this->plugin->taxonomy->get_term_id( $type );

			// If not then create one and assign Term ID.
			if ( $term_id === false ) {
				$term = $this->plugin->taxonomy->create_term( $type );
				if ( $term !== false ) {
					$term_id = $term['term_id'];
				}
			}

			// Define as array.
			if ( is_numeric( $term_id ) ) {
				$terms = [ (int) $term_id ];
			}

		}

		// Add Category if we get one.
		if ( ! empty( $terms ) ) {
			$post_data['tax_input']['event-category'] = $terms;
		}

		// Default to published.
		$post_data['post_status'] = 'publish';

		// Make the Event Organiser Event a draft if the CiviCRM Event is not active.
		if ( $civi_event['is_active'] == 0 ) {
			$post_data['post_status'] = 'draft';

		// Make the Event Organiser Event private if the CiviCRM Event is not public.
		} elseif ( $civi_event['is_public'] == 0 ) {
			$post_data['post_status'] = 'private';
		}

		// Get Status Sync setting.
		$status_sync = (int) $this->plugin->db->option_get( 'civi_eo_event_default_status_sync', 3 );

		// Do we have a Post ID for this Event?
		$eo_post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $civi_event['id'] );

		// "Do not sync" or "sync EO -> CiviCRM".
		if ( $status_sync === 3 || $status_sync === 1 ) {

			// Regardless of CiviCRM Event status, retain the Event Organiser Event status.
			if ( $eo_post_id !== false ) {
				$eo_event = get_post( $eo_post_id );
				if ( ! empty( $eo_event->post_status ) ) {
					$post_data['post_status'] = $eo_event->post_status;
				}
			}

		}

		// Remove hooks.
		remove_action( 'wp_insert_post', [ $this, 'insert_post' ], 10 );
		remove_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ], 10 );

		// Use Event Organiser's API to create/update an Event.
		if ( $eo_post_id === false ) {
			$event_id = eo_insert_event( $post_data, $event_data );
		} else {
			$event_id = eo_update_event( $eo_post_id, $event_data, $post_data );
		}

		// Log and bail if there's an error.
		if ( is_wp_error( $event_id ) ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'error' => $event_id->get_error_message(),
				'civi_event' => $civi_event,
				'backtrace' => $trace,
			], true ) );
			return $event_id;
		}

		// Re-add hooks.
		add_action( 'wp_insert_post', [ $this, 'insert_post' ], 10, 2 );
		add_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ], 10, 1 );

		// Save Event meta if the Event has Online Registration enabled.
		if ( ! empty( $civi_event['is_online_registration'] ) ) {
			$this->set_event_registration( $event_id, (int) $civi_event['is_online_registration'] );
		} else {
			$this->set_event_registration( $event_id );
		}

		// Save Event meta if the Event has a Dedupe Rule specified.
		if ( ! empty( $civi_event['dedupe_rule_group_id'] ) ) {
			$this->set_event_registration_dedupe_rule( $event_id, (int) $civi_event['dedupe_rule_group_id'] );
		} else {
			$this->set_event_registration_dedupe_rule( $event_id, '0' );
		}

		// Save Event meta if the Event has a Participant Role specified.
		if ( ! empty( $civi_event['default_role_id'] ) ) {
			$this->set_event_role( $event_id, $civi_event['default_role_id'] );
		} else {
			$this->set_event_role( $event_id );
		}

		// Save Event meta if the Event has a Registration Confirmation page setting specified.
		if ( ! empty( $civi_event['is_confirm_enabled'] ) ) {
			$this->set_event_registration_confirm( $event_id, (int) $civi_event['is_confirm_enabled'] );
		} else {
			$this->set_event_registration_confirm( $event_id, '0' );
		}

		// Save Event meta if the Event has a Confirmation Email setting specified.
		if ( ! empty( $civi_event['is_email_confirm'] ) ) {
			$this->set_event_registration_send_email( $event_id, (int) $civi_event['is_email_confirm'] );
		} else {
			$this->set_event_registration_send_email( $event_id, '0' );
		}

		// Save Event meta if the Event has a Confirmation Email "From Name" setting specified.
		if ( ! empty( $civi_event['confirm_from_name'] ) ) {
			$this->set_event_registration_send_email_from_name( $event_id, $civi_event['confirm_from_name'] );
		} else {
			$this->set_event_registration_send_email_from_name( $event_id );
		}

		// Save Event meta if the Event has a Confirmation Email "From Email" setting specified.
		if ( ! empty( $civi_event['confirm_from_email'] ) ) {
			$this->set_event_registration_send_email_from( $event_id, $civi_event['confirm_from_email'] );
		} else {
			$this->set_event_registration_send_email_from( $event_id );
		}

		// Save Event meta if the Event has a Confirmation Email "CC" setting specified.
		if ( ! empty( $civi_event['cc_confirm'] ) ) {
			$this->set_event_registration_send_email_cc( $event_id, $civi_event['cc_confirm'] );
		}

		// Save Event meta if the Event has a Confirmation Email "BCC" setting specified.
		if ( ! empty( $civi_event['bcc_confirm'] ) ) {
			$this->set_event_registration_send_email_bcc( $event_id, $civi_event['bcc_confirm'] );
		}

		/*
		 * Syncing Registration Profiles presents us with some issues: when this
		 * method is called from an update to a CiviCRM Event via the CiviCRM admin
		 * interface, we cannot determine what the new Registration Profile is.
		 *
		 * This is because Registration Profiles are updated *after* the
		 * CiviCRM Event has been saved in `CRM_Event_Form_ManageEvent_Registration`
		 * and `CRM_Event_BAO_Event::add($params)` has been called.
		 *
		 * Nor can we hook into `civicrm_post` to catch updates to UF_Join items
		 * because the hook does not fire in class CRM_Core_BAO_UFJoin.
		 *
		 * This leaves us with only a few options:
		 *
		 * (a) We assume that the update is being done via the CiviCRM admin
		 * interface and hook into `civicrm_postProcess`
		 *
		 * or:
		 *
		 * (b) we find a WordPress hook that fires after this process has
		 * completed and use that instead. To do so, we would need to know some
		 * information about the Event that is being processed right now.
		 */

		// Save some data.
		$this->sync_data = [
			'event_id' => $event_id,
			'civi_event' => $civi_event,
		];

		// Let's hook into postProcess for now.
		// TODO: This will not work when the Event is updated via the API.
		add_action( 'civicrm_postProcess', [ $this, 'maybe_update_event_registration_profile' ], 10, 2 );

		/**
		 * Broadcast end of Event Organiser Event update.
		 *
		 * @since 0.3.2
		 *
		 * @param int $event_id The numeric ID of the Event Organiser Event.
		 * @param array $civi_event An array of data for the CiviCRM Event.
		 */
		do_action( 'civicrm_event_organiser_eo_event_updated', $event_id, $civi_event );

		// --<
		return $event_id;

	}

	/**
	 * Updates the Post Status of an Event Organiser Event.
	 *
	 * @since 0.6.4
	 *
	 * @param int $post_id The numeric ID of the Event Organiser Event.
	 * @param str $status The status for the Event Organiser Event.
	 */
	public function update_event_status( $post_id, $status ) {

		// Remove hooks in case of recursion.
		remove_action( 'wp_insert_post', [ $this, 'insert_post' ], 10 );
		remove_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ], 10 );

		// Set the Event Organiser Event to the status.
		$post_data = [
			'ID' => $post_id,
			'post_status' => $status,
		];

		// Do the update.
		wp_update_post( $post_data );

		// Re-add hooks.
		add_action( 'wp_insert_post', [ $this, 'insert_post' ], 10, 2 );
		add_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ], 10, 1 );

	}

	// -------------------------------------------------------------------------

	/**
	 * Intercept before "Break Occurrence".
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the original parent Event.
	 * @param int $occurrence_id The numeric ID of the Occurrence being broken.
	 */
	public function pre_break_occurrence( $post_id, $occurrence_id ) {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		/*
		 * At minimum, we need to prevent our '_civi_eo_civicrm_events' post meta
		 * from being copied as is to the new Event Organiser Event. We need to rebuild
		 * the data for both Event Organiser Events, excluding from the broken and
		 * adding to the new Event Organiser Event.
		 * We get the excluded CiviCRM Event before the break, remove it from the Event,
		 * then rebuild after - see occurrence_broken() below.
		 */

		// Unhook eventorganiser_save_event, because that relies on $_POST.
		remove_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ], 10 );

		// Get the CiviCRM Event that this Occurrence is synced with.
		$this->temp_civi_event_id = $this->plugin->mapping->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );

		// Remove it from the correspondences for this Post.
		$this->plugin->mapping->clear_event_correspondence( $post_id, $occurrence_id );

		// Do not copy across the '_civi_eo_civicrm_events' meta.
		add_filter( 'eventorganiser_breaking_occurrence_exclude_meta', [ $this, 'occurrence_exclude_meta' ], 10, 1 );

	}

	/**
	 * When an Occurrence is broken, do not copy the '_civi_eo_civicrm_events'
	 * meta data.
	 *
	 * @since 0.4
	 *
	 * @param array $ignore_meta The existing array of meta keys to be ignored.
	 * @return array $ignore_meta The modified array of meta keys to be ignored.
	 */
	public function occurrence_exclude_meta( $ignore_meta ) {

		// Add our meta key.
		$ignore_meta[] = '_civi_eo_civicrm_events';

		// --<
		return $ignore_meta;

	}

	/**
	 * Intercept after "Break Occurrence".
	 *
	 * Event Organiser transfers across all existing post meta, so we don't need to
	 * update Registration or event_role values (for example).
	 *
	 * We prevent the correspondence data from being copied over by filtering
	 * Event Organiser's "ignore array", which means we have to rebuild it here.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @param int $occurrence_id The numeric ID of the Occurrence.
	 * @param int $new_event_id The numeric ID of the new WP Post.
	 */
	public function occurrence_broken( $post_id, $occurrence_id, $new_event_id ) {

		// Build new correspondences array.
		$correspondences = [ $occurrence_id => $this->temp_civi_event_id ];

		// Store new correspondences.
		$this->plugin->mapping->store_event_correspondences( $new_event_id, $correspondences );

	}

	/**
	 * Intercept before Event content.
	 *
	 * @since 0.1
	 *
	 * @param str $event_content The Event Organiser Event content.
	 * @param str $content The content of the WP Post.
	 * @return str $event_content The modified Event Organiser Event content.
	 */
	public function pre_event_content( $event_content, $content ) {

		// Init or die.
		if ( ! $this->is_active() ) {
			return $event_content;
		}

		/*
		// Let's see.
		$this->get_participant_roles();
		*/

		// --<
		return $event_content;

	}

	// -------------------------------------------------------------------------

	/**
	 * Register Event meta boxes.
	 *
	 * @since 0.1
	 */
	public function event_meta_boxes() {

		// Check permission.
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) {
			return;
		}

		// Create CiviCRM Settings and Sync metabox.
		add_meta_box(
			'civi_eo_event_metabox',
			__( 'CiviCRM Settings', 'civicrm-event-organiser' ),
			[ $this, 'event_meta_box_render' ],
			'event',
			'side', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Create CiviCRM Settings and Sync metabox.
		add_meta_box(
			'civi_eo_event_links_metabox',
			__( 'Edit Events in CiviCRM', 'civicrm-event-organiser' ),
			[ $this, 'event_links_meta_box_render' ],
			'event',
			'normal', // Column: options are 'normal' and 'side'.
			'high' // Vertical placement: options are 'core', 'high', 'low'.
		);

	}

	/**
	 * Render a meta box on Event edit screens.
	 *
	 * @since 0.1
	 *
	 * @param object $event The Event Organiser Event.
	 */
	public function event_meta_box_render( $event ) {

		// Get linked CiviCRM Events.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event->ID );

		// Set multiple status.
		$multiple = false;
		if ( count( $civi_events ) > 1 ) {
			$multiple = true;
		}

		// Get Online Registration.
		$is_reg_checked = $this->get_event_registration( $event->ID );

		// Set checkbox status.
		$reg_checked = '';
		if ( $is_reg_checked == 1 ) {
			$reg_checked = ' checked="checked"';
		}

		// Get Participant Roles.
		$roles = $this->plugin->civi->registration->get_participant_roles_select( $event );

		// Get Registration Profiles.
		$profiles = $this->plugin->civi->registration->get_registration_profiles_select( $event );

		// Get Registration Dedupe Rules.
		$dedupe_rules = $this->plugin->civi->registration->get_registration_dedupe_rules_select( $event );

		// Get the current confirmation page setting.
		$confirm_enabled = $this->plugin->civi->registration->get_registration_confirm_enabled( $event->ID );

		// Set checkbox status.
		$confirm_checked = '';
		if ( $confirm_enabled ) {
			$confirm_checked = ' checked="checked"';
		}

		// Get the current Confirmation Email setting.
		$send_email_enabled = $this->plugin->civi->registration->get_registration_send_email_enabled( $event->ID );

		// Set checkbox status.
		$send_email_checked = '';
		if ( $send_email_enabled ) {
			$send_email_checked = ' checked="checked"';
		}

		// Get the current Confirmation Email sub-field settings.
		$send_email_from_name = $this->plugin->civi->registration->get_registration_send_email_from_name( $event->ID );
		$send_email_from = $this->plugin->civi->registration->get_registration_send_email_from( $event->ID );
		$send_email_cc = $this->plugin->civi->registration->get_registration_send_email_cc( $event->ID );
		$send_email_bcc = $this->plugin->civi->registration->get_registration_send_email_bcc( $event->ID );

		// Show Event Sync Metabox.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-event-sync.php';

		// Add our metabox JavaScript in the footer.
		wp_enqueue_script(
			'civi_eo_event_metabox_js',
			CIVICRM_WP_EVENT_ORGANISER_URL . '/assets/js/wordpress/metabox-event-sync.js',
			[ 'jquery' ],
			CIVICRM_WP_EVENT_ORGANISER_VERSION,
			true
		);

		// Init localisation.
		$localisation = [];

		// Init settings.
		$settings = [];

		// Localisation array.
		$vars = [
			'localisation' => $localisation,
			'settings' => $settings,
		];

		// Localise.
		wp_localize_script(
			'civi_eo_event_metabox_js',
			'CiviCRM_Event_Organiser_Metabox_Settings',
			$vars
		);

	}

	/**
	 * Render a meta box on Event edit screens with links to CiviCRM Events.
	 *
	 * @since 0.3.6
	 *
	 * @param object $event The Event Organiser Event.
	 */
	public function event_links_meta_box_render( $event ) {

		// Get linked CiviCRM Events.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event->ID );

		// Bail if we get none.
		if ( empty( $civi_events ) ) {
			return;
		}

		// Init links.
		$links = [];

		// Show them.
		foreach ( $civi_events as $civi_event_id ) {

			// Get link.
			$link = $this->plugin->civi->event->get_settings_link( $civi_event_id );

			// Get CiviCRM Event.
			$civi_event = $this->plugin->civi->event->get_event_by_id( $civi_event_id );
			if ( $civi_event === false ) {
				continue;
			}

			// Get DateTime object.
			$start = new DateTime( $civi_event['start_date'], eo_get_blog_timezone() );

			// Construct date and time format.
			$format = get_option( 'date_format' );
			if ( ! eo_is_all_day( $event->ID ) ) {
				$format .= ' ' . get_option( 'time_format' );
			}

			// Get datetime string.
			$datetime_string = eo_format_datetime( $start, $format );

			// Construct link.
			$link = '<a href="' . esc_url( $link ) . '">' . esc_html( $datetime_string ) . '</a>';

			// Construct list item content.
			$content = sprintf(
				/* translators: %s: The formatted link to the Event. */
				__( 'Info and Settings for: %s', 'civicrm-event-organiser' ),
				$link
			);

			// Add to array.
			$links[] = $content;

		}

		// Show Event Links Metabox.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-event-links.php';

	}

	// -------------------------------------------------------------------------

	/**
	 * Add a add a Menu Item to the CiviCRM Event's "Event Links" menu.
	 *
	 * @since 0.4.5
	 *
	 * @param str $content The previously generated content.
	 * @param string $context The context of the content - 'page' or 'form'.
	 * @param string $tplName The name of the ".tpl" template file.
	 * @param object $object A reference to the page or form object.
	 */
	public function menu_item_add_to_civi( &$content, $context, $tplName, &$object ) {

		// Bail if not a form.
		if ( $context != 'form' ) {
			return;
		}

		// Bail if not our target template.
		if ( $tplName != 'CRM/Event/Form/ManageEvent/Tab.tpl' ) {
			return;
		}

		/*
		 * We do this to Contact View = "CRM/Contact/Page/View/Summary.tpl" as
		 * well, though the actions hook may work.
		 */

		// Get the ID of the displayed Event.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( ! isset( $object->_defaultValues['id'] ) ) {
			return;
		}
		if ( ! is_numeric( $object->_defaultValues['id'] ) ) {
			return;
		}
		$event_id = (int) $object->_defaultValues['id'];
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// Get the Post ID that this Event is mapped to.
		$post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $event_id );
		if ( $post_id === false ) {
			return;
		}

		// Build view link.
		$link_view = '<li><a class="crm-event-wordpress-view" href="' . get_permalink( $post_id ) . '">' .
			__( 'View Event in WordPress', 'civicrm-event-organiser' ) .
		'</a><li>' . "\n";

		// Add edit link if permissions allow.
		$link_edit = '';
		if ( current_user_can( 'edit_post', $post_id ) ) {
			$link_edit = '<li><a class="crm-event-wordpress-edit" href="' . get_edit_post_link( $post_id ) . '">' .
				__( 'Edit Event in WordPress', 'civicrm-event-organiser' ) .
			'</a><li>' . "\n";
		}

		// Build final link.
		$link = $link_view . $link_edit . '<li><a class="crm-event-info"';

		// Gulp, do the replace.
		$content = str_replace( '<li><a class="crm-event-info"', $link, $content );

	}

	/**
	 * Add a add a Menu Item to the CiviCRM Admin Utilities menu.
	 *
	 * Currently this only adds a link when there is a one-to-one mapping
	 * between an Event Organiser Event and a CiviCRM Event.
	 *
	 * @since 0.4.5
	 *
	 * @param str $id The menu parent ID.
	 * @param array $components The active CiviCRM Conponents.
	 */
	public function menu_item_add_to_cau( $id, $components ) {

		// Access WordPress admin bar.
		global $wp_admin_bar, $post;

		// Bail if there's no Post.
		if ( empty( $post ) ) {
			return;
		}

		// Bail if there's no Post and it's WordPress admin.
		if ( empty( $post ) && is_admin() ) {
			return;
		}

		// Kick out if not Event.
		if ( $post->post_type != 'event' ) {
			return;
		}

		// Check permission.
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) {
			return;
		}

		// Get linked CiviCRM Events.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );

		// TODO: Consider how to display Repeating Events in menu.

		// Bail if we get more than one.
		if ( empty( $civi_events ) || count( $civi_events ) > 1 ) {
			return;
		}

		// Init links.
		$links = [];

		// Show them.
		foreach ( $civi_events as $civi_event_id ) {

			// Get link.
			$settings_link = $this->plugin->civi->event->get_settings_link( $civi_event_id );

			/*
			// Get CiviCRM Event.
			$civi_event = $this->plugin->civi->event->get_event_by_id( $civi_event_id );
			if ( $civi_event === false ) {
				continue;
			}

			// Get DateTime object.
			$start = new DateTime( $civi_event['start_date'], eo_get_blog_timezone() );

			// Construct date and time format.
			$format = get_option( 'date_format' );
			if ( ! eo_is_all_day( $event->ID ) ) {
				$format .= ' ' . get_option( 'time_format' );
			}

			// Get datetime string.
			$datetime_string = eo_format_datetime( $start, $format );

			// Construct link.
			$link = '<a href="' . esc_url( $settings_link ) . '">' . esc_html( $datetime_string ) . '</a>';

			// Construct list item content.
			$content = sprintf( __( 'Info and Settings for: %s', 'civicrm-event-organiser' ), $link );

			// Add to array.
			$links[] = $content;
			*/

			// Add item to menu.
			$wp_admin_bar->add_node( [
				'id' => 'cau-0',
				'parent' => $id,
				//'parent' => 'edit',
				'title' => __( 'Edit in CiviCRM', 'civicrm-event-organiser' ),
				'href' => $settings_link,
			] );

		}

	}

	/**
	 * Add a link to action links on the Events list table.
	 *
	 * Currently this only adds a link when there is a one-to-one mapping
	 * between an Event Organiser Event and a CiviCRM Event.
	 *
	 * @since 0.4.5
	 *
	 * @param array $actions The array of row action links.
	 * @param WP_Post $post The WordPress Post object.
	 */
	public function menu_item_add_to_row_actions( $actions, $post ) {

		// Bail if there's no Post object.
		if ( empty( $post ) ) {
			return $actions;
		}

		// Kick out if not Event.
		if ( $post->post_type != 'event' ) {
			return $actions;
		}

		// Check permission.
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) {
			return $actions;
		}

		// Get linked CiviCRM Events.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );

		// Bail if we get more than one.
		if ( empty( $civi_events ) || count( $civi_events ) > 1 ) {
			return $actions;
		}

		// Show them.
		foreach ( $civi_events as $civi_event_id ) {

			// Get link.
			$settings_link = $this->plugin->civi->event->get_settings_link( $civi_event_id );

			// Add link to actions.
			$actions['civicrm'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_link ),
				esc_html__( 'CiviCRM', 'civicrm-event-organiser' )
			);

		}

		// --<
		return $actions;

	}

	// -------------------------------------------------------------------------

	/**
	 * Get all Event Organiser dates for a given Post ID.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return array $all_dates All dates for the Post.
	 */
	public function get_all_dates( $post_id ) {

		// Init dates.
		$all_dates = [];

		// Get Occurrences.
		$occurrences = eo_get_the_occurrences_of( $post_id );
		if ( empty( $occurrences ) ) {
			return $all_dates;
		}

		// Loop through them.
		foreach ( $occurrences as $occurrence_id => $occurrence ) {

			// Build an array, formatted for CiviCRM.
			$date = [];
			$date['occurrence_id'] = $occurrence_id;
			$date['start'] = eo_get_the_start( 'Y-m-d H:i:s', $post_id, $occurrence_id );
			$date['end'] = eo_get_the_end( 'Y-m-d H:i:s', $post_id, $occurrence_id );
			$date['human'] = eo_get_the_start( 'g:ia, M jS, Y', $post_id, $occurrence_id );

			// Add to our return array.
			$all_dates[] = $date;

		}

		// --<
		return $all_dates;

	}

	/**
	 * Get all Event Organiser date objects for a given Post ID.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return array $all_dates All dates for the Post, keyed by ID.
	 */
	public function get_date_objects( $post_id ) {

		// Get schedule.
		$schedule = eo_get_event_schedule( $post_id );

		// If we have some dates, return them.
		if ( isset( $schedule['_occurrences'] ) && count( $schedule['_occurrences'] ) > 0 ) {
			return $schedule['_occurrences'];
		}

		// --<
		return [];

	}

	// -------------------------------------------------------------------------

	/**
	 * Save custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the Event.
	 */
	private function save_event_components( $event_id ) {

		// Save Online Registration.
		$this->update_event_registration( $event_id );

		// Save Participant Role.
		$this->update_event_role( $event_id );

		// Save Registration Profile.
		$this->update_event_registration_profile( $event_id );

		// Save Registration Dedupe Rule.
		$this->update_event_registration_dedupe_rule( $event_id );

		// Save Registration Confirmation page setting.
		$this->update_event_registration_confirm( $event_id );

		// Save Confirmation Email settings.
		$this->update_event_registration_send_email( $event_id );
		$this->update_event_registration_send_email_from_name( $event_id );
		$this->update_event_registration_send_email_from( $event_id );
		$this->update_event_registration_send_email_cc( $event_id );
		$this->update_event_registration_send_email_bcc( $event_id );

		/**
		 * Broadcast end of componenents update.
		 *
		 * @since 0.3.2
		 *
		 * @param int $event_id The numeric ID of the Event Organiser Event.
		 */
		do_action( 'civicrm_event_organiser_event_components_updated', $event_id );

	}

	/**
	 * Delete custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the Event.
	 */
	private function delete_event_components( $event_id ) {

		// Event Organiser garbage-collects when it deletes a Event, so no need.

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Online Registration value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param bool $value Whether Registration is enabled or not.
	 */
	public function update_event_registration( $event_id, $value = 0 ) {

		// Get the checkbox state. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reg = isset( $_POST['civi_eo_event_reg'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['civi_eo_event_reg'] ) ) : 0;

		// Maybe override the meta value with the checkbox state.
		if ( ! empty( $reg ) ) {
			$value = $reg;
		}

		// Go ahead and set the value.
		$this->set_event_registration( $event_id, $value );

	}

	/**
	 * Get Event Registration value.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return bool $civi_reg The Event Registration value for the CiviCRM Event.
	 */
	public function get_event_registration( $post_id ) {

		// Get the meta value.
		$civi_reg = get_post_meta( $post_id, '_civi_reg', true );

		// If it's not yet set it will be an empty string, so cast as boolean.
		if ( $civi_reg === '' ) {
			$civi_reg = 0;
		}

		// --<
		return absint( $civi_reg );

	}

	/**
	 * Set Event Registration value.
	 *
	 * @since 0.2.3
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @param bool $value Whether Registration is enabled or not.
	 */
	public function set_event_registration( $post_id, $value = 0 ) {

		// Update Event meta.
		update_post_meta( $post_id, '_civi_reg', $value );

	}

	/**
	 * Delete Event Registration value for a CiviCRM Event.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_reg' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Participant Role value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the Event.
	 */
	public function update_event_role( $event_id ) {

		// Get the Participant Role. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$role = isset( $_POST['civi_eo_event_role'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['civi_eo_event_role'] ) ) : 0;

		// Bail if not set.
		if ( empty( $role ) ) {
			return;
		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_role', $role );

	}

	/**
	 * Update Event Participant Role value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $value The Event Participant Role value for the CiviCRM Event.
	 */
	public function set_event_role( $event_id, $value = null ) {

		// If not set.
		if ( is_null( $value ) ) {

			// Do we have a default set?
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_role' );

			// Override with default value if we get one.
			if ( $default !== '' && is_numeric( $default ) ) {
				$value = absint( $default );
			}

		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_role', $value );

	}

	/**
	 * Get Event Participant Role value.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int $civi_role The Event Participant Role value for the CiviCRM Event.
	 */
	public function get_event_role( $post_id ) {

		// Get the meta value.
		$civi_role = get_post_meta( $post_id, '_civi_role', true );

		// If it's not yet set it will be an empty string, so cast as number.
		if ( $civi_role === '' ) {
			$civi_role = 0;
		}

		// --<
		return absint( $civi_role );

	}

	/**
	 * Delete Event Participant Role value for a CiviCRM Event.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_role( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_role' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Registration Profile value.
	 *
	 * @since 0.3.1
	 *
	 * @param int $event_id The numeric ID of the Event.
	 */
	public function update_event_registration_profile( $event_id ) {

		// Retrieve meta value. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$profile_id = isset( $_POST['civi_eo_event_profile'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['civi_eo_event_profile'] ) ) : 0;

		// Bail if not set.
		if ( empty( $profile_id ) ) {
			return;
		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_profile', $profile_id );

	}

	/**
	 * Update Event Registration Profile value.
	 *
	 * @since 0.3.1
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $profile_id The Event Registration Profile ID for the CiviCRM Event.
	 */
	public function set_event_registration_profile( $event_id, $profile_id = null ) {

		// If not set.
		if ( is_null( $profile_id ) ) {

			// Do we have a default set?
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_profile' );

			// Override with default value if we get one.
			if ( $default !== '' && is_numeric( $default ) ) {
				$profile_id = absint( $default );
			}

		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_profile', $profile_id );

	}

	/**
	 * Get Event Registration Profile value.
	 *
	 * @since 0.3.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int $profile_id The Event Registration Profile ID for the CiviCRM Event.
	 */
	public function get_event_registration_profile( $post_id ) {

		// Get the meta value.
		$profile_id = get_post_meta( $post_id, '_civi_registration_profile', true );

		// If it's not yet set it will be an empty string, so cast as number.
		if ( $profile_id === '' ) {
			$profile_id = 0;
		}

		// --<
		return absint( $profile_id );

	}

	/**
	 * Delete Event Registration Profile value for a CiviCRM Event.
	 *
	 * @since 0.3.1
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration_profile( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_profile' );

	}

	/**
	 * Maybe update an Event Organiser Event's Registration Profile.
	 *
	 * This is a callback from `civicrm_postProcess` as defined in
	 * `$this->update_event()` above. It's purpose is to act after a delay so
	 * that the Registration Profile has been saved.
	 *
	 * We don't need any data from the form because we can query for the value
	 * of the Registration Profile. Perhaps not as efficient, but let's look at
	 * whether it's worth inspecting the form later.
	 *
	 * @since 0.3.3
	 *
	 * @param string $formName The name of the form.
	 * @param object $form The form object.
	 */
	public function maybe_update_event_registration_profile( $formName, &$form ) {

		// Kick out if not a CiviCRM Event Online Registration form.
		if ( $formName != 'CRM_Event_Form_ManageEvent_Registration' ) {
			return;
		}

		// Bail if we don't have our sync data.
		if ( ! isset( $this->sync_data ) ) {
			return;
		}

		// Get Registration Profile.
		$existing_profile = $this->plugin->civi->registration->has_registration_profile( $this->sync_data['civi_event'] );

		// Save Event meta if this Event have a Registration Profile specified.
		if ( $existing_profile !== false ) {
			$this->set_event_registration_profile( $this->sync_data['event_id'], $existing_profile['uf_group_id'] );
		} else {
			$this->set_event_registration_profile( $this->sync_data['event_id'] );
		}

		// Clear saved data.
		unset( $this->sync_data );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Registration Dedupe Rule value.
	 *
	 * @since 0.7.6
	 *
	 * @param int $event_id The numeric ID of the Event.
	 */
	public function update_event_registration_dedupe_rule( $event_id ) {

		// Retrieve meta value. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$dedupe_rule_id = isset( $_POST['civi_eo_event_dedupe_rule'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['civi_eo_event_dedupe_rule'] ) ) : 0;

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_dedupe_rule', $dedupe_rule_id );

	}

	/**
	 * Update Event Registration Dedupe Rule value.
	 *
	 * @since 0.7.6
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $dedupe_rule_id The Event Registration Dedupe Rule ID for the CiviCRM Event.
	 */
	public function set_event_registration_dedupe_rule( $event_id, $dedupe_rule_id = null ) {

		// If not set.
		if ( is_null( $dedupe_rule_id ) ) {

			// Do we have a default set?
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_dedupe' );

			// Override with default value if we get one.
			if ( $default !== '' && is_numeric( $default ) ) {
				$dedupe_rule_id = absint( $default );
			}

		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_dedupe_rule', $dedupe_rule_id );

	}

	/**
	 * Get Event Registration Dedupe Rule value.
	 *
	 * @since 0.7.6
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int|str $dedupe_rule_id The Dedupe Rule ID, or empty string if not set.
	 */
	public function get_event_registration_dedupe_rule( $post_id ) {

		// Get the meta value.
		$dedupe_rule_id = get_post_meta( $post_id, '_civi_registration_dedupe_rule', true );

		// Cast as integer if set.
		if ( '' !== $dedupe_rule_id ) {
			$dedupe_rule_id =  (int) $dedupe_rule_id;
		}

		// --<
		return $dedupe_rule_id;

	}

	/**
	 * Delete Event Registration Dedupe Rule value for a CiviCRM Event.
	 *
	 * @since 0.7.6
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration_dedupe_rule( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_dedupe_rule' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Registration Confirmation screen value from Event Organiser meta box.
	 *
	 * @since 0.6.4
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param str $value Whether the Registration Confirmation screen is enabled or not.
	 */
	public function update_event_registration_confirm( $event_id, $value = '0' ) {

		// Retrieve meta value. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$event_confirm = isset( $_POST['civi_eo_event_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_confirm'] ) ) : 0;
		if ( '1' === (string) $event_confirm ) {
			$value = '1';
		} else {
			$value = '0';
		}

		// Go ahead and set the value.
		$this->set_event_registration_confirm( $event_id, $value );

	}

	/**
	 * Get Event Registration Confirmation screen value.
	 *
	 * @since 0.6.4
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int $setting The Event Registration Confirmation screen setting for the CiviCRM Event.
	 */
	public function get_event_registration_confirm( $post_id ) {

		// Get the meta value.
		$setting = get_post_meta( $post_id, '_civi_registration_confirm', true );

		// If it's not yet set it will be an empty string, so cast as boolean.
		if ( $setting === '' ) {
			$setting = 1; // The default in CiviCRM is to show a confirmation screen.
		}

		// --<
		return absint( $setting );

	}

	/**
	 * Update Event Registration Confirmation screen value.
	 *
	 * @since 0.6.4
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $setting The Event Registration Confirmation screen setting for the CiviCRM Event.
	 */
	public function set_event_registration_confirm( $event_id, $setting = null ) {

		// If not set.
		if ( is_null( $setting ) ) {

			// Do we have a default set?
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_confirm' );

			// Override with default value if we get one.
			if ( $default !== '' && is_numeric( $default ) ) {
				$setting = absint( $default );
			}

		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_confirm', $setting );

	}

	/**
	 * Delete Event Registration Confirmation screen setting for a CiviCRM Event.
	 *
	 * @since 0.6.4
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration_confirm( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_confirm' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email value from Event Organiser meta box.
	 *
	 * @since 0.7.2
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param str $value Whether the Confirmation Email is enabled or not.
	 */
	public function update_event_registration_send_email( $event_id, $value = '0' ) {

		// Retrieve meta value. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$send_email = isset( $_POST['civi_eo_event_send_email'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_send_email'] ) ) : 0;
		if ( '1' === (string) $send_email ) {
			$value = '1';
		} else {
			$value = '0';
		}

		// Go ahead and set the value.
		$this->set_event_registration_send_email( $event_id, $value );

	}

	/**
	 * Get Event Confirmation Email value.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int $setting The Event Confirmation Email setting for the CiviCRM Event.
	 */
	public function get_event_registration_send_email( $post_id ) {

		// Get the meta value.
		$setting = get_post_meta( $post_id, '_civi_registration_send_email', true );

		// If it's not yet set it will be an empty string, so cast as boolean.
		if ( $setting === '' ) {
			$setting = 0; // The default in CiviCRM is not to send a Confirmation Email.
		}

		// --<
		return absint( $setting );

	}

	/**
	 * Update Event Confirmation Email value.
	 *
	 * @since 0.7.2
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $setting The Event Confirmation Email setting for the CiviCRM Event.
	 */
	public function set_event_registration_send_email( $event_id, $setting = null ) {

		// If not set.
		if ( is_null( $setting ) ) {

			// Do we have a default set?
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_send_email' );

			// Override with default value if we get one.
			if ( $default !== '' && is_numeric( $default ) ) {
				$setting = absint( $default );
			}

		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_send_email', $setting );

	}

	/**
	 * Delete Event Confirmation Email setting for a CiviCRM Event.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration_send_email( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_send_email' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email "From Name" value from Event Organiser meta box.
	 *
	 * @since 0.7.2
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param str $value The Confirmation Email "From Name" value.
	 */
	public function update_event_registration_send_email_from_name( $event_id, $value = '' ) {

		// Retrieve meta value. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$from_name = isset( $_POST['civi_eo_event_send_email_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_send_email_from_name'] ) ) : 0;

		// Maybe apply meta value.
		if ( ! empty( $from_name ) ) {
			$value = $from_name;
		} else {
			$value = null;
		}

		// Go ahead and set the value.
		$this->set_event_registration_send_email_from_name( $event_id, $value );

	}

	/**
	 * Get Event Confirmation Email "From Name" value.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int $setting The Event Confirmation Email "From Name" setting for the CiviCRM Event.
	 */
	public function get_event_registration_send_email_from_name( $post_id ) {

		// Get the meta value.
		$setting = get_post_meta( $post_id, '_civi_registration_send_email_from_name', true );

		// If it's empty, cast as string.
		if ( empty( $setting ) ) {
			$setting = '';
		}

		// --<
		return $setting;

	}

	/**
	 * Update Event Confirmation Email "From Name" value.
	 *
	 * @since 0.7.2
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $setting The Event Confirmation Email "From Name" setting for the CiviCRM Event.
	 */
	public function set_event_registration_send_email_from_name( $event_id, $setting = null ) {

		// If not set.
		if ( is_null( $setting ) ) {

			// Override with default value if we get one.
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_send_email_from_name' );
			if ( ! empty( $default ) ) {
				$setting = $default;
			}

		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_send_email_from_name', $setting );

	}

	/**
	 * Delete Event Confirmation Email "From Name" setting for a CiviCRM Event.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration_send_email_from_name( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_send_email_from_name' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email "From Email" value from Event Organiser meta box.
	 *
	 * @since 0.7.2
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param str $value The Confirmation Email "From Email" value.
	 */
	public function update_event_registration_send_email_from( $event_id, $value = '' ) {

		// Retrieve meta value. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$from = isset( $_POST['civi_eo_event_send_email_from'] ) ? sanitize_email( wp_unslash( $_POST['civi_eo_event_send_email_from'] ) ) : 0;

		// Maybe apply meta value.
		if ( ! empty( $from ) && is_email( $from ) ) {
			$value = $from;
		} else {
			$value = null;
		}

		// Go ahead and set the value.
		$this->set_event_registration_send_email_from( $event_id, $value );

	}

	/**
	 * Get Event Confirmation Email "From Email" value.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int $setting The Event Confirmation Email "From Email" setting for the CiviCRM Event.
	 */
	public function get_event_registration_send_email_from( $post_id ) {

		// Get the meta value.
		$setting = get_post_meta( $post_id, '_civi_registration_send_email_from', true );

		// If it's empty, cast as string.
		if ( empty( $setting ) ) {
			$setting = '';
		}

		// --<
		return $setting;

	}

	/**
	 * Update Event Confirmation Email "From Email" value.
	 *
	 * @since 0.7.2
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $setting The Event Confirmation Email "From Email" setting for the CiviCRM Event.
	 */
	public function set_event_registration_send_email_from( $event_id, $setting = null ) {

		// If not set.
		if ( is_null( $setting ) ) {

			// Override with default value if we get one.
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_send_email_from' );
			if ( ! empty( $default ) ) {
				$setting = $default;
			}

		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_send_email_from', $setting );

	}

	/**
	 * Delete Event Confirmation Email "From Email" setting for a CiviCRM Event.
	 *
	 * @since 0.7.2
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration_send_email_from( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_send_email_from' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email "CC" value from Event Organiser meta box.
	 *
	 * @since 0.7.4
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param str $value The Confirmation Email "CC" value.
	 */
	public function update_event_registration_send_email_cc( $event_id, $value = '' ) {

		// Retrieve meta value. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cc = isset( $_POST['civi_eo_event_send_email_cc'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_send_email_cc'] ) ) : 0;

		// Default to empty.
		$value = '';

		// Maybe apply meta value.
		if ( ! empty( $cc ) ) {

			// Only save valid emails.
			$valid = [];
			$emails = explode( ',', $cc );
			foreach ( $emails as $email ) {
				if ( is_email( sanitize_email( trim( $email ) ) ) ) {
					$valid[] = trim( $email );
				}
			}

			// Save valid emails.
			$value = implode( ', ', array_unique( $valid ) );

		}

		// In order to "blank out" this field, we need to store a token.
		if ( empty( $value ) ) {
			$value = 'none';
		}

		// Go ahead and set the value.
		$this->set_event_registration_send_email_cc( $event_id, $value );

	}

	/**
	 * Get Event Confirmation Email "CC" value.
	 *
	 * Do not use this method directly. Use "get_registration_send_email_cc()" instead so
	 * that default values are populated when necessary and the "blanked out" token is
	 * substituted when necessary.
	 *
	 * @see CiviCRM_WP_Event_Organiser_CiviCRM_Registration::get_registration_send_email_cc()
	 *
	 * @since 0.7.4
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int $setting The Event Confirmation Email "CC" setting for the CiviCRM Event.
	 */
	public function get_event_registration_send_email_cc( $post_id ) {

		// Get the meta value.
		$setting = get_post_meta( $post_id, '_civi_registration_send_email_cc', true );

		// If it's empty, cast as string.
		if ( empty( $setting ) ) {
			$setting = '';
		}

		// --<
		return $setting;

	}

	/**
	 * Update Event Confirmation Email "CC" value.
	 *
	 * @since 0.7.4
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $setting The Event Confirmation Email "CC" setting for the CiviCRM Event.
	 */
	public function set_event_registration_send_email_cc( $event_id, $setting = null ) {

		// Delete if not set.
		if ( empty( $setting ) ) {
			$this->clear_event_registration_send_email_cc( $event_id );
			return;
		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_send_email_cc', $setting );

	}

	/**
	 * Delete Event Confirmation Email "CC" setting for a CiviCRM Event.
	 *
	 * @since 0.7.4
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration_send_email_cc( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_send_email_cc' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email "BCC" value from Event Organiser meta box.
	 *
	 * @since 0.7.4
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param str $value The Confirmation Email "BCC" value.
	 */
	public function update_event_registration_send_email_bcc( $event_id, $value = '' ) {

		// Retrieve meta value. Nonce is checked in "intercept_save_event".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$bcc = isset( $_POST['civi_eo_event_send_email_bcc'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_send_email_bcc'] ) ) : 0;

		// Default to empty.
		$value = '';

		// Maybe apply meta value.
		if ( ! empty( $bcc ) ) {

			// Only save valid emails.
			$valid = [];
			$emails = explode( ',', $bcc );
			foreach ( $emails as $email ) {
				if ( is_email( sanitize_email( trim( $email ) ) ) ) {
					$valid[] = trim( $email );
				}
			}

			// Save valid emails.
			$value = implode( ', ', array_unique( $valid ) );

		}

		// In order to "blank out" this field, we need to store a token.
		if ( empty( $value ) ) {
			$value = 'none';
		}

		// Go ahead and set the value.
		$this->set_event_registration_send_email_bcc( $event_id, $value );

	}

	/**
	 * Get Event Confirmation Email "BCC" value.
	 *
	 * Do not use this method directly. Use "get_registration_send_email_bcc()" instead so
	 * that default values are populated when necessary and the "blanked out" token is
	 * substituted when necessary.
	 *
	 * @see CiviCRM_WP_Event_Organiser_CiviCRM_Registration::get_registration_send_email_bcc()
	 *
	 * @since 0.7.4
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @return int $setting The Event Confirmation Email "BCC" setting for the CiviCRM Event.
	 */
	public function get_event_registration_send_email_bcc( $post_id ) {

		// Get the meta value.
		$setting = get_post_meta( $post_id, '_civi_registration_send_email_bcc', true );

		// If it's empty, cast as string.
		if ( empty( $setting ) ) {
			$setting = '';
		}

		// --<
		return $setting;

	}

	/**
	 * Update Event Confirmation Email "BCC" value.
	 *
	 * @since 0.7.4
	 *
	 * @param int $event_id The numeric ID of the Event.
	 * @param int $setting The Event Confirmation Email "BCC" setting for the CiviCRM Event.
	 */
	public function set_event_registration_send_email_bcc( $event_id, $setting = null ) {

		// Delete if not set.
		if ( empty( $setting ) ) {
			$this->clear_event_registration_send_email_bcc( $event_id );
			return;
		}

		// Update Event meta.
		update_post_meta( $event_id, '_civi_registration_send_email_bcc', $setting );

	}

	/**
	 * Delete Event Confirmation Email "BCC" setting for a CiviCRM Event.
	 *
	 * @since 0.7.4
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 */
	public function clear_event_registration_send_email_bcc( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_send_email_bcc' );

	}

}
