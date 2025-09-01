<?php
/**
 * Event Organiser Class.
 *
 * Handles functionality generally related to Event Organiser.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Event Organiser Class.
 *
 * A class that encapsulates functionality generally related to Event Organiser.
 *
 * @since 0.1
 */
class CEO_WordPress_EO {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * WordPress object.
	 *
	 * @since 0.8.0
	 * @access public
	 * @var CEO_WordPress
	 */
	public $wordpress;

	/**
	 * Insert Event flag.
	 *
	 * True if inserting an Event, false otherwise.
	 *
	 * @since 0.1
	 * @access public
	 * @var bool
	 */
	public $insert_event = false;

	/**
	 * Metabox nonce name.
	 *
	 * The name of the metabox nonce element.
	 *
	 * @since 0.7.4
	 * @access private
	 * @var string
	 */
	private $nonce_field = 'ceo_event_nonce';

	/**
	 * Metabox nonce action.
	 *
	 * The name of the metabox nonce action.
	 *
	 * @since 0.7.4
	 * @access private
	 * @var string
	 */
	private $nonce_action = 'ceo_event_action';

	/**
	 * Event data to sync to Registration Profile.
	 *
	 * @since 0.3.3
	 * @access public
	 * @var array
	 */
	public $sync_data;

	/**
	 * Event correspondences to use once the Event has been deleted.
	 *
	 * @since 0.4
	 * @access private
	 * @var array
	 */
	private $saved_correspondences = [];

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param CEO_WordPress $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin    = $parent->plugin;
		$this->wordpress = $parent;

		// Add Event Organiser hooks when WordPress class is loaded.
		add_action( 'ceo/wordpress/loaded', [ $this, 'register_hooks' ] );

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

		// Intercept "Save Event".
		add_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ] );

		// Intercept before "Delete Post".
		add_action( 'before_delete_post', [ $this, 'intercept_before_delete_post' ] );

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

		// Add our meta box to Event screens.
		add_action( 'add_meta_boxes_event', [ $this, 'meta_boxes_register' ], 11 );

		// Add our "Delete unused CiviCRM Events" checkbox to the "Recurring Event notice".
		add_filter( 'eventorganiser_event_metabox_notice', [ $this, 'partial_recurring_notice_render' ], 10, 2 );

		// Add our "Sync to CiviCRM" checkbox to the "Publish" metabox.
		add_action( 'post_submitbox_misc_actions', [ $this, 'partial_sync_checkbox_render' ] );

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
		if ( '0' === $installed_version || version_compare( $installed_version, '3', '<' ) ) {

			// Let's show an admin notice.
			add_action( 'admin_notices', [ $this->plugin->admin, 'dependency_alert' ] );

			// We're not okay.
			$eo_active = false;

		}

		// --<
		return $eo_active;

	}

	// -----------------------------------------------------------------------------------

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

		// Always save custom Event Organiser Event components.
		$this->save_event_components( $post_id );

		// Get Post data.
		$post = get_post( $post_id );

		// Bail if this Event should not be synced.
		if ( ! $this->sync_allowed_for_event( $post ) ) {
			return;
		}

		// Check the "Sync Event to CiviCRM" checkbox.
		if ( ! $this->sync_progress_allowed( $post_id ) ) {
			return;
		}

		// Get all dates.
		$dates = $this->get_all_dates( $post_id );

		/*
		// Get Event data.
		$schedule = eo_get_event_schedule( $post_id );
		*/

		// Prevent recursion.
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ] );
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ] );
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ] );

		// Update our CiviCRM Events - or create new if none exist.
		$this->plugin->civi->event->update_civi_events( $post, $dates );

		// Restore hooks.
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10, 4 );
		add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ], 10, 4 );

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
		if ( 'event' !== $post->post_type ) {
			return;
		}

		// Get correspondences from post meta to use once the Event has been deleted.
		$this->saved_correspondences[ $post_id ] = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );

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
	 * be handled by CEO_CiviCRM_Event::event_updated()
	 *
	 * If "Sync this Event with CiviCRM" is selected during the Event update and
	 * the date(s) have been changed, then this method is called during the
	 * update process.
	 *
	 * @since 0.1
	 *
	 * @param int        $post_id The numeric ID of the WP Post.
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

			// Get Post data.
			$post = get_post( $post_id );

			// Bail if this Event should not be synced.
			if ( ! $this->sync_allowed_for_event( $post ) ) {
				return;
			}

			// Check the "Sync Event to CiviCRM" checkbox.
			if ( ! $this->sync_progress_allowed( $post_id ) ) {
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
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ] );
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ] );
		remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ] );

		// Are we deleting an Event?
		if ( doing_action( 'delete_post' ) && ! empty( $this->saved_correspondences[ $post_id ] ) ) {

			// Yes: get IDs from pre-delete post meta.
			$correspondences = $this->saved_correspondences[ $post_id ];

			// Clear array entry.
			unset( $this->saved_correspondences[ $post_id ] );

		} else {

			// Get IDs from post meta.
			$correspondences = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );

		}

		// Loop through them.
		foreach ( $correspondences as $occurrence_id => $civi_event_id ) {

			// Is this Occurrence being deleted?
			if ( false === $occurrence_ids || in_array( $occurrence_id, $occurrence_ids ) ) {

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

		// Delete those CiviCRM Events?
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

	// -----------------------------------------------------------------------------------

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
		$end_date = $this->plugin->civi->denullify( $civi_event['end_date'] );
		if ( ! empty( $end_date ) ) {
			$end_date = new DateTime( $civi_event['end_date'], eo_get_blog_timezone() );
		}

		// Define schedule.
		$event_data = [

			// Start date.
			'start'         => new DateTime( $civi_event['start_date'], eo_get_blog_timezone() ),

			// End date and end of schedule are the same.
			'end'           => $end_date,
			'schedule_last' => $end_date,

			// We can't tell if a CiviCRM Event is repeating, so only once.
			'frequency'     => 1,

			// CiviCRM does not have "all day".
			'all_day'       => 0,

			// We can't tell if a CiviCRM Event is repeating.
			'schedule'      => 'once',

		];

		/*
		 * Init Post array with quick fixes for Windows.
		 * Note: These may no longer be needed.
		 */
		$post_data = [
			'to_ping'               => '',
			'pinged'                => '',
			'post_content_filtered' => '',
		];

		// We must have at minimum a Post title.
		$post_data['post_title'] = __( 'Untitled CiviCRM Event', 'civicrm-event-organiser' );
		$title                   = $this->plugin->civi->denullify( $civi_event['title'] );
		if ( ! empty( $title ) ) {
			$post_data['post_title'] = $title;
		}

		// Assign Description if present.
		if ( isset( $civi_event['description'] ) ) {
			$description = $this->plugin->civi->denullify( $civi_event['description'] );
			if ( ! empty( $description ) ) {
				$post_data['post_content'] = $this->wordpress->unautop( $description );
			}
		}

		// Assign Summary if present.
		if ( isset( $civi_event['summary'] ) ) {
			$summary = $this->plugin->civi->denullify( $civi_event['summary'] );
			if ( ! empty( $summary ) ) {
				$post_data['post_excerpt'] = $summary;
			}
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
			$venue_id = $this->plugin->wordpress->eo_venue->get_venue_id( $location );

			// If we get a match, create/update Venue.
			if ( false === $venue_id ) {
				$venue_id = $this->plugin->wordpress->eo_venue->create_venue( $location );
			} else {
				$venue_id = $this->plugin->wordpress->eo_venue->update_venue( $location );
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
			$type = $this->plugin->wordpress->taxonomy->get_event_type_by_value( $civi_event['event_type_id'] );

			// Does this Event Type have an existing Term?
			$term_id = $this->plugin->wordpress->taxonomy->get_term_id( $type );

			// If not then create one and assign Term ID.
			if ( false === $term_id ) {
				$term = $this->plugin->wordpress->taxonomy->create_term( $type );
				if ( false !== $term ) {
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
		if ( 0 === (int) $civi_event['is_active'] ) {
			$post_data['post_status'] = 'draft';

			// Make the Event Organiser Event private if the CiviCRM Event is not public.
		} elseif ( 0 === (int) $civi_event['is_public'] ) {
			$post_data['post_status'] = 'private';
		}

		// Get Status Sync setting.
		$status_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_status_sync', 3 );

		// Do we have a Post ID for this Event?
		$eo_post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $civi_event['id'] );

		// "Do not sync" or "sync EO -> CiviCRM".
		if ( 3 === $status_sync || 1 === $status_sync ) {

			// Regardless of CiviCRM Event status, retain the Event Organiser Event status.
			if ( false !== $eo_post_id ) {
				$eo_event = get_post( $eo_post_id );
				if ( ! empty( $eo_event->post_status ) ) {
					$post_data['post_status'] = $eo_event->post_status;
				}
			}

		}

		// Remove hooks.
		remove_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ] );

		// Use Event Organiser's API to create/update an Event.
		if ( false === $eo_post_id ) {
			$event_id = eo_insert_event( $post_data, $event_data );
		} else {
			$event_id = eo_update_event( $eo_post_id, $event_data, $post_data );
		}

		// Log and bail if there's an error.
		if ( is_wp_error( $event_id ) ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'     => __METHOD__,
				'error'      => $event_id->get_error_message(),
				'civi_event' => $civi_event,
				'backtrace'  => $trace,
			];
			$this->plugin->log_error( $log );
			return $event_id;
		}

		// Re-add hooks.
		add_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ] );

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
			'event_id'   => $event_id,
			'civi_event' => $civi_event,
		];

		// Let's hook into postProcess for now.
		// TODO: This will not work when the Event is updated via the API.
		add_action( 'civicrm_postProcess', [ $this, 'maybe_update_event_registration_profile' ], 10, 2 );

		/**
		 * Fires at the end of the Event Organiser Event update.
		 *
		 * @since 0.3.2
		 * @deprecated 0.8.0 Use the {@see 'ceo/eo/event/updated'} filter instead.
		 *
		 * @param int   $event_id The numeric ID of the Event Organiser Event.
		 * @param array $civi_event An array of data for the CiviCRM Event.
		 */
		do_action_deprecated( 'civicrm_event_organiser_eo_event_updated', [ $event_id, $civi_event ], '0.8.0', 'ceo/eo/event/updated' );

		/**
		 * Fires at the end of the Event Organiser Event update.
		 *
		 * @since 0.8.0
		 *
		 * @param int   $event_id The numeric ID of the Event Organiser Event.
		 * @param array $civi_event An array of data for the CiviCRM Event.
		 */
		do_action( 'ceo/eo/event/updated', $event_id, $civi_event );

		// --<
		return $event_id;

	}

	/**
	 * Updates the Post Status of an Event Organiser Event.
	 *
	 * @since 0.6.4
	 *
	 * @param int    $post_id The numeric ID of the Event Organiser Event.
	 * @param string $status The status for the Event Organiser Event.
	 */
	public function update_event_status( $post_id, $status ) {

		// Remove hooks in case of recursion.
		remove_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ] );

		// Set the Event Organiser Event to the status.
		$post_data = [
			'ID'          => $post_id,
			'post_status' => $status,
		];

		// Do the update.
		wp_update_post( $post_data );

		// Re-add hooks.
		add_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ] );

	}

	// -----------------------------------------------------------------------------------

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
		remove_action( 'eventorganiser_save_event', [ $this, 'intercept_save_event' ] );

		// Get the CiviCRM Event that this Occurrence is synced with.
		$this->temp_civi_event_id = $this->plugin->mapping->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );

		// Remove it from the correspondences for this Post.
		$this->plugin->mapping->clear_event_correspondence( $post_id, $occurrence_id );

		// Do not copy across the '_civi_eo_civicrm_events' meta.
		add_filter( 'eventorganiser_breaking_occurrence_exclude_meta', [ $this, 'occurrence_exclude_meta' ] );

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

	// -----------------------------------------------------------------------------------

	/**
	 * Registers the Event meta boxes.
	 *
	 * @since 0.1
	 * @since 0.8.2 Renamed.
	 */
	public function meta_boxes_register() {

		// Check permission.
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) {
			return;
		}

		// Create CiviCRM Settings and Sync metabox.
		add_meta_box(
			'civi_eo_event_metabox',
			__( 'CiviCRM Event Settings', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_event_render' ],
			'event',
			'normal', // Column: options are 'normal' and 'side'.
			'high' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Create CiviCRM Settings and Sync metabox.
		add_meta_box(
			'civi_eo_event_links_metabox',
			__( 'Edit in CiviCRM', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_event_links_render' ],
			'event',
			'side', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

	}

	/**
	 * Render a meta box on Event edit screens.
	 *
	 * @since 0.1
	 * @since 0.8.2 Renamed.
	 *
	 * @param WP_Post $event The Event Organiser Event.
	 */
	public function meta_box_event_render( $event ) {

		// Get linked CiviCRM Events.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event->ID );

		// Set multiple status.
		$multiple = false;
		if ( count( $civi_events ) > 1 ) {
			$multiple = true;
		}

		// Get the Event Organiser Event sync setting.
		$eo_event_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_eo_event_sync', 1 );

		// Decide if we should show the "Sync to CiviCRM" checkbox.
		if ( 1 === $eo_event_sync ) {
			$show_sync_checkbox = true;
			if ( ! empty( $civi_events ) ) {
				$show_sync_checkbox = false;
				if ( $multiple ) {
					$show_sync_checkbox = true;
				}
			}
		} else {
			$show_sync_checkbox = false;
		}

		// Get Online Registration.
		$is_reg_checked = $this->get_event_registration( $event->ID );

		// Set checkbox status.
		$reg_checked = 0;
		if ( 1 === (int) $is_reg_checked ) {
			$reg_checked = 1;
		}

		// Get all Participant Roles.
		$roles = $this->plugin->civi->registration->get_participant_roles_mapped();

		// Get default Participant Role.
		$default_role = $this->plugin->civi->registration->get_participant_role( $event );

		// Get Registration Profiles.
		$profiles = $this->plugin->civi->registration->get_registration_profiles_mapped();

		// Get default Registration Profile.
		$default_profile = $this->plugin->civi->registration->get_registration_profile( $event );

		// Get all Event Registration Dedupe Rules.
		$dedupe_rules = $this->plugin->civi->registration->get_registration_dedupe_rules();

		// Get default Dedupe Rule ID.
		$default_dedupe_rule = $this->plugin->civi->registration->get_registration_dedupe_rule( $event );

		// Get the current confirmation page setting.
		$confirm_enabled = $this->plugin->civi->registration->get_registration_confirm_enabled( $event->ID );

		// Set checkbox status.
		$confirm_checked = 0;
		if ( $confirm_enabled ) {
			$confirm_checked = 1;
		}

		// Get the current Confirmation Email setting.
		$send_email_enabled = $this->plugin->civi->registration->get_registration_send_email_enabled( $event->ID );

		// Set checkbox status.
		$send_email_checked = 0;
		if ( $send_email_enabled ) {
			$send_email_checked = 1;
		}

		// Get the current Confirmation Email sub-field settings.
		$send_email_from_name = $this->plugin->civi->registration->get_registration_send_email_from_name( $event->ID );
		$send_email_from      = $this->plugin->civi->registration->get_registration_send_email_from( $event->ID );
		$send_email_cc        = $this->plugin->civi->registration->get_registration_send_email_cc( $event->ID );
		$send_email_bcc       = $this->plugin->civi->registration->get_registration_send_email_bcc( $event->ID );

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
			'settings'     => $settings,
		];

		// Localise.
		wp_localize_script(
			'civi_eo_event_metabox_js',
			'CEO_Metabox_Settings',
			$vars
		);

	}

	/**
	 * Render a meta box on Event edit screens with links to CiviCRM Events.
	 *
	 * @since 0.3.6
	 * @since 0.8.2 Renamed.
	 *
	 * @param WP_Post $event The Event Organiser Event.
	 */
	public function meta_box_event_links_render( $event ) {

		// Get linked CiviCRM Events.
		$civi_event_ids = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event->ID );

		// Init links.
		$links = [];

		// Show them if there are some.
		if ( ! empty( $civi_event_ids ) ) {

			// Let's do a single query for all the CiviCRM Events.
			$civi_events = $this->plugin->civi->event->get_events_by_ids( $civi_event_ids );

			foreach ( $civi_events as $civi_event ) {

				// Get link.
				$link = $this->plugin->civi->event->get_settings_link( $civi_event['id'] );

				// Get DateTime object.
				$start = new DateTime( $civi_event['start_date'], eo_get_blog_timezone() );

				// Construct date and time format.
				$format = get_option( 'date_format' );
				if ( ! eo_is_all_day( $event->ID ) ) {
					$format .= ' ' . get_option( 'time_format' );
				}

				// Get datetime string.
				$datetime_string = eo_format_datetime( $start, $format );

				// Add to array.
				$links[] = [
					'url'  => $link,
					'text' => $datetime_string,
				];

			}

		}

		// Show Event Links Metabox.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-event-links.php';

	}

	/**
	 * Adds the "Sync to CiviCRM" checkbox to the "Publish" metabox.
	 *
	 * @since 0.8.2
	 *
	 * @param WP_Post $event The Event Organiser Event.
	 */
	public function partial_sync_checkbox_render( $event ) {

		// Sanity check.
		if ( empty( $event ) || ! ( $event instanceof WP_Post ) ) {
			return;
		}

		// Bail if not an Event.
		if ( 'event' !== $event->post_type ) {
			return;
		}

		// Bail if User lacks capability.
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}

		// Set multiple status.
		$multiple = false;
		if ( eo_recurs( $event->ID ) ) {
			$multiple = true;
		}

		// Get linked CiviCRM Events.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event->ID );

		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'civi_events' => $civi_events,
			//'backtrace' => $trace,
		], true ) );

		// Set "multiple linked" status.
		$multiple_linked = false;
		if ( count( $civi_events ) > 1 ) {
			$multiple_linked = true;
		}

		// Do not show "Sync to CiviCRM" checkbox by default.
		$show_sync_checkbox = false;

		// Get the Event Organiser Event sync setting.
		$eo_event_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_eo_event_sync', 1 );

		// Always show checkbox when the setting says so and there are no CiviCRM Events.
		if ( 1 === $eo_event_sync && empty( $civi_events ) ) {
			$show_sync_checkbox = true;
		}

		// Always show checkbox for repeating Events.
		if ( $multiple ) {
			$show_sync_checkbox = true;
		}

		// Bail if not showing.
		if ( ! $show_sync_checkbox ) {
			return;
		}

		// Get the CiviCRM logo.
		$civicrm_logo = $this->plugin->civi->logo_get();

		// Show "Sync to CiviCRM" markup.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/partials/partial-admin-event-sync.php';

	}

	/**
	 * Filters the "Recurring Event" notice at the top of the Event details metabox.
	 *
	 * @since 0.8.2
	 *
	 * @param string  $notices The original message text.
	 * @param WP_Post $event The Event Organiser Event.
	 * @return string $notices The modified message text.
	 */
	public function partial_recurring_notice_render( $notices, $event ) {

		// Sanity check.
		if ( empty( $event ) || ! ( $event instanceof WP_Post ) ) {
			return $notices;
		}

		// Get linked CiviCRM Events.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event->ID );

		// Set multiple status.
		$multiple = false;
		if ( count( $civi_events ) > 1 ) {
			$multiple = true;
		}

		// Sneakily append our checkbox.
		if ( $multiple ) {

			// Close existing notices.
			$notices .= '</p>';

			// Get "Delete unused CiviCRM Events" markup.
			ob_start();
			include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/partials/partial-admin-event-recurring.php';
			$markup = ob_get_contents();
			ob_end_clean();

			// Append to notices.
			$notices .= $markup;

			// Reopen existing notices.
			$notices .= '<p>';

		}

		// --<
		return $notices;

	}

	// -----------------------------------------------------------------------------------

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
			$date                  = [];
			$date['occurrence_id'] = $occurrence_id;
			$date['start']         = eo_get_the_start( 'Y-m-d H:i:s', $post_id, $occurrence_id );
			$date['end']           = eo_get_the_end( 'Y-m-d H:i:s', $post_id, $occurrence_id );
			$date['human']         = eo_get_the_start( 'g:ia, M jS, Y', $post_id, $occurrence_id );

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

	/**
	 * Checks if an Event Organiser Event should be synced to CiviCRM.
	 *
	 * @since 0.8.2
	 *
	 * @param WP_Post $post The WordPress Post object.
	 * @return WP_Post|bool $post The WordPress Post object, or false if not allowed.
	 */
	public function sync_allowed_for_event( $post ) {

		// Assume Post should be synced.
		$should_be_synced = true;

		// Do not sync if no Post object.
		if ( ! $post ) {
			$should_be_synced = false;
		}

		// Do not sync if not an Event Post object.
		if ( $should_be_synced ) {
			if ( 'event' !== $post->post_type ) {
				$should_be_synced = false;
			}
		}

		// Do not sync if the User cannot publish Posts.
		if ( $should_be_synced ) {
			if ( ! current_user_can( 'publish_posts' ) ) {
				$should_be_synced = false;
			}
		}

		// Do not sync if this is an auto-draft.
		if ( $should_be_synced ) {
			if ( 'auto-draft' === $post->post_status ) {
				$should_be_synced = false;
			}
		}

		// Do not sync if this Post is in the Trash.
		if ( $should_be_synced ) {
			if ( 'trash' === $post->post_status ) {
				$should_be_synced = false;
			}
		}

		// Do not sync if this is an autosave routine.
		if ( $should_be_synced ) {
			if ( wp_is_post_autosave( $post ) ) {
				$should_be_synced = false;
			}
		}

		// Do not sync if this is a revision.
		if ( $should_be_synced ) {
			if ( wp_is_post_revision( $post ) ) {
				$should_be_synced = false;
			}
		}

		// Do not sync if this is a draft and the setting suppresses the "Sync to CiviCRM" checkbox.
		if ( $should_be_synced ) {
			$eo_event_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_eo_event_sync', 1 );
			if ( 0 === $eo_event_sync && 'draft' === $post->post_status ) {
				$should_be_synced = false;
			}
		}

		/**
		 * Filters whether or not an Event should be synced to CiviCRM.
		 *
		 * @since 0.8.2
		 *
		 * @param bool    $should_be_synced True if the Post should be synced, false otherwise.
		 * @param WP_Post $post The WordPress Post object.
		 */
		$should_be_synced = apply_filters( 'ceo/eo/event/sync_allowed_for_event', $should_be_synced, $post );

		// Return the Post object if it should be synced.
		if ( $should_be_synced ) {
			return $post;
		}

		// Do not sync.
		return false;

	}

	/**
	 * Checks if "Sync Event to CiviCRM" process should be take place.
	 *
	 * @since 0.8.2
	 *
	 * @param int $post_id The ID of the WordPress Post.
	 * @return bool True if "Sync Event to CiviCRM" process should happen, or false if not allowed.
	 */
	public function sync_progress_allowed( $post_id ) {

		// Bail if no Post ID.
		if ( empty( $post_id ) ) {
			return false;
		}

		// Get the "Sync Event to CiviCRM" setting.
		$eo_event_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_eo_event_sync', 1 );

		// Get linked CiviCRM Events from post meta.
		$correspondences = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );

		// Check if this Event is repeating.
		$repeating = ( 1 < count( $correspondences ) ) ? true : false;

		/*
		 * There is no need to check the "Sync Event to CiviCRM" checkbox when the
		 * "Sync all Event Organiser Events to CiviCRM" setting has been chosen and
		 * it's not a repeating Event.
		 */
		if ( 0 === $eo_event_sync && ! $repeating ) {
			return true;
		}

		/*
		 * We can also skip checking the checkbox when there is *exactly one*
		 * correspondence between an Event Organiser Event and a CiviCRM Event.
		 */
		if ( ! empty( $correspondences ) && ! $repeating ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sync = isset( $_POST['civi_eo_event_sync'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_sync'] ) ) : 0;

		// Only sync if the "Sync this Event with CiviCRM" checkbox is checked.
		if ( '1' === (string) $sync ) {
			return true;
		}

		// Do not sync.
		return false;

	}

	// -----------------------------------------------------------------------------------

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
		 * Fires at the end of the Event componenents update.
		 *
		 * @since 0.3.2
		 * @deprecated 0.8.0 Use the {@see 'ceo/eo/event/components/updated'} filter instead.
		 *
		 * @param int $event_id The numeric ID of the Event Organiser Event.
		 */
		do_action_deprecated( 'civicrm_event_organiser_event_components_updated', [ $event_id ], '0.8.0', 'ceo/eo/event/components/updated' );

		/**
		 * Fires at the end of the componenents update.
		 *
		 * @since 0.8.0
		 *
		 * @param int $event_id The numeric ID of the Event Organiser Event.
		 */
		do_action( 'ceo/eo/event/components/updated', $event_id );

	}

	/**
	 * Delete custom components that sync with CiviCRM.
	 *
	 * Event Organiser garbage-collects when it deletes a Event, so no need.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the Event.
	 */
	private function delete_event_components( $event_id ) {

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Update Event Online Registration value.
	 *
	 * @since 0.1
	 *
	 * @param int  $event_id The numeric ID of the Event.
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
		if ( '' === $civi_reg ) {
			$civi_reg = 0;
		}

		// --<
		return (int) $civi_reg;

	}

	/**
	 * Set Event Registration value.
	 *
	 * @since 0.2.3
	 *
	 * @param int  $post_id The numeric ID of the WP Post.
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

	// -----------------------------------------------------------------------------------

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
			$default = $this->plugin->admin->option_get( 'civi_eo_event_default_role' );

			// Override with default value if we get one.
			if ( '' !== $default && is_numeric( $default ) ) {
				$value = (int) $default;
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
		if ( '' === $civi_role ) {
			$civi_role = 0;
		}

		// --<
		return (int) $civi_role;

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

	// -----------------------------------------------------------------------------------

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
			$default = $this->plugin->admin->option_get( 'civi_eo_event_default_profile' );

			// Override with default value if we get one.
			if ( '' !== $default && is_numeric( $default ) ) {
				$profile_id = (int) $default;
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
		if ( '' === $profile_id ) {
			$profile_id = 0;
		}

		// --<
		return (int) $profile_id;

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
	 * @param string $form_name The name of the form.
	 * @param object $form The form object.
	 */
	public function maybe_update_event_registration_profile( $form_name, &$form ) {

		// Kick out if not a CiviCRM Event Online Registration form.
		if ( 'CRM_Event_Form_ManageEvent_Registration' !== $form_name ) {
			return;
		}

		// Bail if we don't have our sync data.
		if ( ! isset( $this->sync_data ) ) {
			return;
		}

		// Get Registration Profile.
		$existing_profile = $this->plugin->civi->registration->has_registration_profile( $this->sync_data['civi_event'] );

		// Save Event meta if this Event have a Registration Profile specified.
		if ( false !== $existing_profile ) {
			$this->set_event_registration_profile( $this->sync_data['event_id'], $existing_profile['uf_group_id'] );
		} else {
			$this->set_event_registration_profile( $this->sync_data['event_id'] );
		}

		// Clear saved data.
		unset( $this->sync_data );

	}

	// -----------------------------------------------------------------------------------

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
			$default = $this->plugin->admin->option_get( 'civi_eo_event_default_dedupe' );

			// Override with default value if we get one.
			if ( '' !== $default && is_numeric( $default ) ) {
				$dedupe_rule_id = (int) $default;
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
	 * @return int|string $dedupe_rule_id The Dedupe Rule ID, or empty string if not set.
	 */
	public function get_event_registration_dedupe_rule( $post_id ) {

		// Get the meta value.
		$dedupe_rule_id = get_post_meta( $post_id, '_civi_registration_dedupe_rule', true );

		// Cast as integer if set.
		if ( '' !== $dedupe_rule_id ) {
			$dedupe_rule_id = (int) $dedupe_rule_id;
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

	// -----------------------------------------------------------------------------------

	/**
	 * Update Event Registration Confirmation screen value from Event Organiser meta box.
	 *
	 * @since 0.6.4
	 *
	 * @param int    $event_id The numeric ID of the Event.
	 * @param string $value Whether the Registration Confirmation screen is enabled or not.
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
		if ( '' === $setting ) {
			$setting = 1; // The default in CiviCRM is to show a confirmation screen.
		}

		// --<
		return (int) $setting;

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
			$default = $this->plugin->admin->option_get( 'civi_eo_event_default_confirm' );

			// Override with default value if we get one.
			if ( '' !== $default && is_numeric( $default ) ) {
				$setting = (int) $default;
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

	// -----------------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email value from Event Organiser meta box.
	 *
	 * @since 0.7.2
	 *
	 * @param int    $event_id The numeric ID of the Event.
	 * @param string $value Whether the Confirmation Email is enabled or not.
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
		if ( '' === $setting ) {
			$setting = 0; // The default in CiviCRM is not to send a Confirmation Email.
		}

		// --<
		return (int) $setting;

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
			$default = $this->plugin->admin->option_get( 'civi_eo_event_default_send_email' );

			// Override with default value if we get one.
			if ( '' !== $default && is_numeric( $default ) ) {
				$setting = (int) $default;
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

	// -----------------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email "From Name" value from Event Organiser meta box.
	 *
	 * @since 0.7.2
	 *
	 * @param int    $event_id The numeric ID of the Event.
	 * @param string $value The Confirmation Email "From Name" value.
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
			$default = $this->plugin->admin->option_get( 'civi_eo_event_default_send_email_from_name' );
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

	// -----------------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email "From Email" value from Event Organiser meta box.
	 *
	 * @since 0.7.2
	 *
	 * @param int    $event_id The numeric ID of the Event.
	 * @param string $value The Confirmation Email "From Email" value.
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
			$default = $this->plugin->admin->option_get( 'civi_eo_event_default_send_email_from' );
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

	// -----------------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email "CC" value from Event Organiser meta box.
	 *
	 * @since 0.7.4
	 *
	 * @param int    $event_id The numeric ID of the Event.
	 * @param string $value The Confirmation Email "CC" value.
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
			$valid  = [];
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
	 * @see CEO_CiviCRM_Registration::get_registration_send_email_cc()
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

	// -----------------------------------------------------------------------------------

	/**
	 * Update Event Confirmation Email "BCC" value from Event Organiser meta box.
	 *
	 * @since 0.7.4
	 *
	 * @param int    $event_id The numeric ID of the Event.
	 * @param string $value The Confirmation Email "BCC" value.
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
			$valid  = [];
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
	 * @see CEO_CiviCRM_Registration::get_registration_send_email_bcc()
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
