<?php

/**
 * CiviCRM Event Organiser EO General Class.
 *
 * A class that encapsulates functionality generally related to Event Organiser.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_EO {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Insert event flag.
	 *
	 * @since 0.1
	 * @access public
	 * @var bool $insert_event True if inserting an event, false otherwise.
	 */
	public $insert_event = false;



	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Add Event Organiser hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', array( $this, 'register_hooks' ) );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object.
	 */
	public function set_references( $parent ) {

		// Store reference.
		$this->plugin = $parent;

	}



	/**
	 * Register hooks if Event Organiser is present.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Check for Event Organiser.
		if ( ! $this->is_active() ) return;

		// Intercept insert post.
		add_action( 'wp_insert_post', array( $this, 'insert_post' ), 10, 2 );

		// Intercept save event.
		add_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10, 1 );

		// Intercept update event - though by misuse of a filter.
		//add_filter( 'eventorganiser_update_event_event_data', array( $this, 'intercept_update_event' ), 10, 3 );

		// Intercept before delete post.
		add_action( 'before_delete_post', array( $this, 'intercept_before_delete_post' ), 10, 1 );

		// Intercept delete event occurrences - which is the preferred way to hook into event deletion.
		add_action( 'eventorganiser_delete_event_occurrences', array( $this, 'delete_event_occurrences' ), 10, 2 );

		// Intercept before break occurrence
		add_action( 'eventorganiser_pre_break_occurrence', array( $this, 'pre_break_occurrence' ), 10, 2 );

		// There's no hook for 'eventorganiser_delete_event_occurrence', which moves the occurrence.
		// To the 'exclude' array of the date sequence

		// Intercept after break occurrence.
		add_action( 'eventorganiser_occurrence_broken', array( $this, 'occurrence_broken' ), 10, 3 );

		// Intercept "Delete Occurrence" in admin calendar.
		add_action( 'eventorganiser_admin_calendar_occurrence_deleted', array( $this, 'occurrence_deleted' ), 10, 2 );

		// Debug.
		//add_filter( 'eventorganiser_pre_event_content', array( $this, 'pre_event_content' ), 10, 2 );

		// Add our event meta box.
		add_action( 'add_meta_boxes_event', array( $this, 'event_meta_boxes' ), 11 );

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
		if ( $eo_active ) return true;

		// Access Event Organiser option.
		$installed_version = get_option( 'eventorganiser_version', 'etueue' );

		// This plugin will not work without Event Organiser v3+.
		if ( $installed_version === 'etueue' OR version_compare( $installed_version, '3', '<' ) ) {

			// Let's show an admin notice.
			add_action( 'admin_notices', array( $this->plugin->db, 'dependency_alert' ) );

			// Set flag.
			$eo_active = false;

		} else {

			// Set flag.
			$eo_active = true;

		}

		// --<
		return $eo_active;

	}



 	//##########################################################################



	/**
	 * Intercept insert post and check if we're inserting an event.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @param object $post The WP post object.
	 */
	public function insert_post( $post_id, $post ) {

		// Kick out if not event.
		if ( $post->post_type != 'event' ) return;

		// Set flag.
		$this->insert_event = true;

		// Check the validity of our CiviCRM options.
		$success = $this->plugin->civi->validate_civi_options( $post_id, $post );

	}



	/**
	 * Intercept save event.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 */
	public function intercept_save_event( $post_id ) {

		// Save custom EO event components.
		$this->_save_event_components( $post_id );

		// Sync checkbox is only shown to people who can publish posts.
		if ( ! current_user_can( 'publish_posts' ) ) return;

		// Is our checkbox checked?
		if ( ! isset( $_POST['civi_eo_event_sync'] ) ) return;
		if ( $_POST['civi_eo_event_sync'] != 1 ) return;

		// Get post data.
		$post = get_post( $post_id );

		// Get all dates.
		$dates = $this->get_all_dates( $post_id );

		// Get event data.
		//$schedule = eo_get_event_schedule( $post_id );

		// Prevent recursion.
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_created' ), 10 );
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_updated' ), 10 );
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_deleted' ), 10 );

		// Update our CiviCRM events - or create new if none exist.
		$this->plugin->civi->update_civi_events( $post, $dates );

		// Restore hooks.
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_created' ), 10, 4 );
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_updated' ), 10, 4 );
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_deleted' ), 10, 4 );

	}



	/**
	 * Intercept update event.
	 *
	 * Disabled because it's unused. Also, there appears to be some confusion
	 * regarding the filter signature in EO itself.
	 *
	 * @see https://github.com/stephenharris/Event-Organiser/blob/develop/includes/event.php#L76
	 *
	 * @since 0.1
	 *
	 * @param array $event_data The new event data.
	 * @param int $post_id The numeric ID of the WP post.
	 * @param array $post_data The updated post data.
	 * @return array $event_data The updated event data.
	 */
	public function intercept_update_event( $event_data, $post_id, $post_data ) {

		// --<
		return $event_data;

	}



	/**
	 * Intercept before delete post.
	 *
	 * @since 0.4
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 */
	public function intercept_before_delete_post( $post_id ) {

		// Get post data.
		$post = get_post( $post_id );

		// Bail if not an event.
		if ( $post->post_type != 'event' ) return;

		// Get correspondences from post meta to use once the event has been deleted.
		$this->saved_correspondences = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post_id );

	}



	/**
	 * Intercept delete event occurrences.
	 *
	 * There is an ambiguity as to when this method is called, because it will
	 * be called when an Event Organiser event is deleted AND when an existing
	 * event has its date(s) changed.
	 *
	 * If the date(s) have been changed without "Sync this event with CiviCRM"
	 * selected, then the next time the event is updated, the changed dates will
	 * be handled by CiviCRM_WP_Event_Organiser_CiviCRM::event_updated()
	 *
	 * If "Sync this event with CiviCRM" is selected during the event update and
	 * the date(s) have been changed, then this method is called during the
	 * update process.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @param array|bool $occurrence_ids An array of occurrence IDs to be deleted, or false if all occurrences are to be removed.
	 */
	public function delete_event_occurrences( $post_id, $occurrence_ids ) {

		// If an event is not being deleted
		if ( ! doing_action( 'delete_post' ) ) {

			// Bail if our sync checkbox is not checked
			if ( ! isset( $_POST['civi_eo_event_sync'] ) ) return;
			if ( $_POST['civi_eo_event_sync'] != 1 ) return;

		}

		/*
		 * Once again, the question arises as to whether we should actually delete
		 * the CiviEvents or set them to "disabled"... I guess this behaviour could
		 * be set as a plugin option.
		 *
		 * Also whether we should delete the correspondences or transfer them to an
		 * "inactive" array of some kind.
		 */

		// Prevent recursion.
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_created' ), 10 );
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_updated' ), 10 );
		remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_deleted' ), 10 );

		// Are we deleting an event?
		if ( doing_action( 'delete_post' ) AND isset( $this->saved_correspondences ) ) {

			// Yes: get IDs from saved post meta.
			$correspondences = $this->saved_correspondences;

		} else {

			// Get IDs from post meta.
			$correspondences = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post_id );

		}

		// Loop through them.
		foreach ( $correspondences AS $occurrence_id => $civi_event_id ) {

			// Is this occurrence being deleted?
			if ( $occurrence_ids === false OR in_array( $occurrence_id, $occurrence_ids ) ) {

				// Disable corresponding CiviEvent.
				$return = $this->plugin->civi->disable_civi_event( $civi_event_id );

			}

		}

		// Restore hooks.
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_created' ), 10, 4 );
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_updated' ), 10, 4 );
		add_action( 'civicrm_post', array( $this->plugin->civi, 'event_deleted' ), 10, 4 );

		// TODO: Decide if we delete CiviEvents.
		return;

		// Bail if an event is not being deleted.
		if ( ! doing_action( 'delete_post' ) ) return;

		// Delete those CiviCRM events - not used at present.
		//$this->plugin->civi->delete_civi_events( $correspondences );

		// Delete our stored CiviCRM event IDs.
		//$this->plugin->db->clear_event_correspondences( $post_id );

	}



	/**
	 * Intercept delete occurrence.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @param int $occurrence_id The numeric ID of the EO event occurrence.
	 */
	public function occurrence_deleted( $post_id, $occurrence_id ) {

		// Init or die.
		if ( ! $this->is_active() ) return;

		// Get CiviEvent ID from post meta.
		$civi_event_id = $this->plugin->db->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );

		// Disable CiviEvent.
		$return = $this->plugin->civi->disable_civi_event( $civi_event_id );

		// Convert occurrence to orphaned.
		$this->plugin->db->occurrence_orphaned( $post_id, $occurrence_id, $civi_event_id );

	}



	//##########################################################################



	/**
	 * Update an EO event, given a CiviEvent.
	 *
	 * If no EO event exists then create one. Please note that this method will
	 * NOT create sequences for the time being.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_event An array of data for the CiviEvent.
	 * @return int $event_id The numeric ID of the event.
	 */
	public function update_event( $civi_event ) {

		// Define schedule.
		$event_data = array(

			// Start date.
			'start' => new DateTime( $civi_event['start_date'], eo_get_blog_timezone() ),

			// End date and end of schedule are the same.
			'end' => new DateTime( $civi_event['end_date'], eo_get_blog_timezone() ),
			'schedule_last' => new DateTime( $civi_event['end_date'], eo_get_blog_timezone() ),

			// We can't tell if a CiviEvent is repeating, so only once.
			'frequency' => 1,

			// CiviCRM does not have "all day".
			'all_day' => 0,

			// We can't tell if a CiviEvent is repeating.
			'schedule' => 'once',

		);

		// Define post.
		$post_data = array(

			// Standard post data.
			'post_title' => $civi_event['title'],
			'post_content' => isset( $civi_event['description'] ) ? $civi_event['description'] : '',
			'post_excerpt' => isset( $civi_event['summary']) ? $civi_event['summary'] : '',

			// Quick fixes for Windows which need to be present.
			'to_ping' => '',
			'pinged' => '',
			'post_content_filtered' => '',

		);

		// Test for created date, which may be absent.
		if ( isset( $civi_event['created_date'] ) AND ! empty( $civi_event['created_date'] ) ) {

			// Create DateTime object.
			$datetime = new DateTime( $civi_event['created_date'], eo_get_blog_timezone() );

			// Add it, but format it first since CiviCRM seems to send data in the form 20150916135435.
			$post_data['post_date'] = $datetime->format( 'Y-m-d H:i:s' );

		}

		// Init venue as undefined.
		$venue_id = 0;

		// Get location ID.
		if ( isset( $civi_event['loc_block_id'] ) ) {

			// We have a location...

			// Get location data.
			$location = $this->plugin->civi->get_location_by_id( $civi_event['loc_block_id'] );

			// Get corresponding EO venue ID.
			$venue_id = $this->plugin->eo_venue->get_venue_id( $location );

			// If we get a match, create/update venue.
			if ( $venue_id === false ) {
				$venue_id = $this->plugin->eo_venue->create_venue( $location );
			} else {
				$venue_id = $this->plugin->eo_venue->update_venue( $location );
			}

		}

		// Init category as undefined.
		$terms = array();

		// Get location ID.
		if ( isset( $civi_event['event_type_id'] ) ) {

			// We have a category...

			// Get event type data for this pseudo-ID (actually "value").
			$type = $this->plugin->taxonomy->get_event_type_by_value( $civi_event['event_type_id'] );

			// Does this type have an existing term?
			$term_id = $this->plugin->taxonomy->get_term_id( $type );

			// If not ten create one and assign term ID.
			if ( $term_id === false ) {
				$term = $this->plugin->taxonomy->create_term( $type );
				$term_id = $term['term_id'];
			}

			// Define as array.
			$terms = array( absint( $term_id ) );

		}

		// Add to post data.
		$post_data['tax_input'] = array(
			'event-venue' => array( absint( $venue_id ) ),
			'event-category' => $terms,
		);

		// Default to published.
		$post_data['post_status'] = 'publish';

		// Make the EO event a draft if the CiviEvent is not active.
		// Not public event will be private post.
		if ( $civi_event['is_active'] == 0 ) {
			$post_data['post_status'] = 'draft';
		} elseif ( $civi_event['is_public'] == 0 ) {
			$post_data['post_status'] = 'private';
		}

		// Do we have a post ID for this event?
		$eo_post_id = $this->plugin->db->get_eo_event_id_by_civi_event_id( $civi_event['id'] );

		// Regardless of CiviEvent status: if the EO event is private, keep it that way.
		if ( $eo_post_id !== false ) {
			$eo_event = get_post( $eo_post_id );
			if ( $eo_event->post_status == 'private' ) {
				$post_data['post_status'] = $eo_event->post_status;
			}
		}

		// Remove hooks.
		remove_action( 'wp_insert_post', array( $this, 'insert_post' ), 10 );
		remove_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10 );

		// Use EO's API to create/update an event.
		if ( $eo_post_id === false ) {
			$event_id = eo_insert_event( $post_data, $event_data );
		} else {
			$event_id = eo_update_event( $eo_post_id, $post_data, $event_data );
		}

		// Re-add hooks.
		add_action( 'wp_insert_post', array( $this, 'insert_post' ), 10, 2 );
		add_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10, 1 );

		// Save event meta if the event has online registration enabled.
		if (
			isset( $civi_event['is_online_registration'] ) AND
			$civi_event['is_online_registration'] == 1
		) {
			$this->set_event_registration( $event_id, $civi_event['is_online_registration'] );
		} else {
			$this->set_event_registration( $event_id );
		}

		// Save event meta if the event has a participant role specified.
		if (
			isset( $civi_event['default_role_id'] ) AND
			! empty( $civi_event['default_role_id'] )
		) {
			$this->set_event_role( $event_id, $civi_event['default_role_id'] );
		} else {
			$this->set_event_role( $event_id );
		}

		/*
		 * Syncing Registration Profiles presents us with some issues: when this
		 * method is called from an update to a CiviEvent via the CiviCRM admin
		 * interface, we cannot determine what the new Registration Profile is.
		 * This is because Registration Profiles are updated *after* the
		 * CiviEvent has been saved in `CRM_Event_Form_ManageEvent_Registration`
		 * and `CRM_Event_BAO_Event::add($params)` has been called.
		 *
		 * Nor can we hook into `civicrm_post` to catch updates to UF_Join items
		 * because the hook does not fire in class CRM_Core_BAO_UFJoin.
		 *
		 * This leaves us with only a few options: (a) We assume that the update
		 * is being done via the CiviCRM admin interface and hook into
		 * `civicrm_postProcess` or (b) we find a WordPress hook that fires
		 * after this process has completed and use that instead. To do so, we
		 * would need to know some information about the event that is being
		 * processed right now.
		 */

		// Save some data.
		$this->sync_data = array(
			'event_id' => $event_id,
			'civi_event' => $civi_event,
		);

		// Let's hook into postProcess for now.
		add_action( 'civicrm_postProcess', array( $this, 'maybe_update_event_registration_profile' ), 10, 2 );

		/**
		 * Broadcast end of EO event update.
		 *
		 * @since 0.3.2
		 *
		 * @param int $event_id The numeric ID of the EO event.
		 * @param array $civi_event An array of data for the CiviEvent.
		 */
		do_action( 'civicrm_event_organiser_eo_event_updated', $event_id, $civi_event );

		// --<
		return $event_id;

	}



	//##########################################################################



	/**
	 * Intercept before break occurrence.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the original parent event.
	 * @param int $occurrence_id The numeric ID of the occurrence being broken.
	 */
	public function pre_break_occurrence( $post_id, $occurrence_id ) {

		// Init or die.
		if ( ! $this->is_active() ) return;

		/*
		 * At minimum, we need to prevent our '_civi_eo_civicrm_events' post meta
		 * from being copied as is to the new EO event. We need to rebuild the data
		 * for both EO events, excluding from the broken and adding to the new EO event.
		 * We get the excluded CiviEvent before the break, remove it from the event,
		 * then rebuild after - see occurrence_broken() below.
		 */

		// Unhook eventorganiser_save_event, because that relies on $_POST.
		remove_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10 );

		// Get the CiviEvent that this occurrence is synced with.
		$this->temp_civi_event_id = $this->plugin->db->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );

		// Remove it from the correspondences for this post.
		$this->plugin->db->clear_event_correspondence( $post_id, $occurrence_id );

		// Do not copy across the '_civi_eo_civicrm_events' meta.
		add_filter( 'eventorganiser_breaking_occurrence_exclude_meta', array( $this, 'occurrence_exclude_meta' ), 10, 1 );

	}



	/**
	 * When an occurrence is broken, do not copy the '_civi_eo_civicrm_events'
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
	 * Intercept after break occurrence.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @param int $occurrence_id The numeric ID of the occurrence.
	 * @param int $new_event_id The numeric ID of the new WP post.
	 */
	public function occurrence_broken( $post_id, $occurrence_id, $new_event_id ) {

		/*
		 * EO transfers across all existing post meta, so we don't need to update
		 * registration or event_role values (for example).
		 *
		 * We prevent the correspondence data from being copied over by filtering
		 * EO's "ignore array", which means we have to rebuild it here.
		 */

		// Build new correspondences array.
		$correspondences = array( $occurrence_id => $this->temp_civi_event_id );

		// Store new correspondences.
		$this->plugin->db->store_event_correspondences( $new_event_id, $correspondences );

	}



	/**
	 * Intercept before event content.
	 *
	 * @since 0.1
	 *
	 * @param str $event_content The EO event content.
	 * @param str $content The content of the WP post.
	 * @return str $event_content The modified EO event content.
	 */
	public function pre_event_content( $event_content, $content ) {

		// Init or die.
		if ( ! $this->is_active() ) return $event_content;

		// Let's see.
		//$this->get_participant_roles();

		// --<
		return $event_content;

	}



	//##########################################################################



	/**
	 * Register event meta boxes.
	 *
	 * @since 0.1
	 */
	public function event_meta_boxes() {

		// Check permission.
		if ( ! $this->plugin->civi->check_permission( 'access CiviEvent' ) ) return;

		// Create CiviCRM Settings and Sync metabox.
		add_meta_box(
			'civi_eo_event_metabox',
			__( 'CiviCRM Settings', 'civicrm-event-organiser' ),
			array( $this, 'event_meta_box_render' ),
			'event',
			'side', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Create CiviCRM Settings and Sync metabox.
		add_meta_box(
			'civi_eo_event_links_metabox',
			__( 'Edit Events in CiviCRM', 'civicrm-event-organiser' ),
			array( $this, 'event_links_meta_box_render' ),
			'event',
			'normal', // Column: options are 'normal' and 'side'.
			'high' // Vertical placement: options are 'core', 'high', 'low'.
		);

	}



	/**
	 * Render a meta box on event edit screens.
	 *
	 * @since 0.1
	 *
	 * @param object $event The EO event.
	 */
	public function event_meta_box_render( $event ) {

		// Add nonce.
		wp_nonce_field( 'civi_eo_event_meta_save', 'civi_eo_event_nonce_field' );

		// Get online registration.
		$is_reg_checked = $this->get_event_registration( $event->ID );

		// Init checkbox status.
		$reg_checked = '';

		// Override if registration is allowed.
		if ( $is_reg_checked == 1 ) {
			$reg_checked = ' checked="checked"';
		}

		// Get participant roles.
		$roles = $this->plugin->civi->get_participant_roles_select( $event );

		// Get registration profiles.
		$profiles = $this->plugin->civi->get_registration_profiles_select( $event );

		// Init sync options.
		$sync_options = '';

		// Show checkbox to people who can publish posts.
		if ( current_user_can( 'publish_posts' ) ) {

			// Define sync options.
			$sync_options = '
			<p class="civi_eo_event_desc">' . __( 'Choose whether or not to sync this event and (if the sequence has changed) whether or not to delete the unused corresponding CiviEvents. If you do not delete them, they will be set to "disabled".', 'civicrm-event-organiser' ) . '</p>

			<p>
			<label for="civi_eo_event_sync">' . __( 'Sync this event with CiviCRM:', 'civicrm-event-organiser' ) . '</label>
			<input type="checkbox" id="civi_eo_event_sync" name="civi_eo_event_sync" value="1" />
			</p>

			<p>
			<label for="civi_eo_event_delete_unused">' . __( 'Delete unused CiviEvents:', 'civicrm-event-organiser' ) . '</label>
			<input type="checkbox" id="civi_eo_event_delete_unused" name="civi_eo_event_delete_unused" value="1" />
			</p>

			<hr />

			';

		}

		// Show sync options, if allowed.
		echo $sync_options;

		// Show meta box.
		echo '
		<h4>' . __( 'CiviEvent Options', 'civicrm-event-organiser' ) . '</h4>

		<p class="civi_eo_event_desc">' . __( '<strong>NOTE:</strong> these options will be set for <em>all corresponding CiviEvents</em> when you sync this event to CiviCRM. Changes that you make will override the defaults set on the CiviCRM Event Organiser Settings page.', 'civicrm-event-organiser' ) . '</p>

		<hr />

		<p>
		<label for="civi_eo_event_reg">' . __( 'Enable Online Registration:', 'civicrm-event-organiser' ) . '</label>
		<input type="checkbox" id="civi_eo_event_reg" name="civi_eo_event_reg" value="1"' . $reg_checked . ' />
		</p>

		<hr />

		<p>
		<label for="civi_eo_event_profile">' . __( 'Online Registration Profile:', 'civicrm-event-organiser' ) . '</label>
		<select id="civi_eo_event_profile" name="civi_eo_event_profile">
			' . $profiles . '
		</select>
		</p>

		<p class="description">' . __( 'The profile assigned to the online registration form.', 'civicrm-event-organiser' ) . '</p>

		<hr />

		<p>
		<label for="civi_eo_event_role">' . __( 'Participant Role:', 'civicrm-event-organiser' ) . '</label>
		<select id="civi_eo_event_role" name="civi_eo_event_role">
			' . $roles . '
		</select>
		</p>

		<p class="description">' . __( 'This role is automatically assigned to people when they register online for this event and where the registration profile does not allow a role to be selected.', 'civicrm-event-organiser' ) . '</p>

		';

		/**
		 * Broadcast end of metabox.
		 *
		 * @since 0.3
		 *
		 * @param object $event The EO event object.
		 */
		do_action( 'civicrm_event_organiser_event_meta_box_after', $event );

	}



	/**
	 * Render a meta box on event edit screens with links to CiviEvents.
	 *
	 * @since 0.3.6
	 *
	 * @param object $event The EO event.
	 */
	public function event_links_meta_box_render( $event ) {

		// Get linked CiviEvents.
		$civi_events = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $event->ID );

		// Bail if we get none.
		if ( empty( $civi_events ) ) return;

		// Init links.
		$links = array();

		// Show them.
		foreach( $civi_events AS $civi_event_id ) {

			// Get link.
			$link = $this->plugin->civi->get_settings_link( $civi_event_id );

			// Get CiviEvent.
			$civi_event = $this->plugin->civi->get_event_by_id( $civi_event_id );

			// Continue if not found.
			if ( $civi_event === false ) continue;

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
			$content = sprintf( __( 'Info and Settings for: %s', 'civicrm-event-organiser' ), $link );

			// Add to array.
			$links[] = $content;

		}

		// Show prettified list.
		echo '<ul><li style="margin: 0.5em 0; padding: 0.5em 0 0.5em 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee;">' .
				implode( '</li><li style="margin: 0.5em 0; padding: 0 0 0.5em 0; border-bottom: 1px solid #eee;">', $links ) .
			 '</li></ul>';

		/**
		 * Broadcast end of metabox.
		 *
		 * @since 0.3.6
		 *
		 * @param object $event The EO event object.
		 */
		do_action( 'civicrm_event_organiser_event_links_meta_box_after', $event );

	}



	//##########################################################################



	/**
	 * Get all Event Organiser dates for a given post ID.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @return array $all_dates All dates for the post.
	 */
	public function get_all_dates( $post_id ) {

		// Init dates.
		$all_dates = array();

		// Get all dates.
		$all_event_dates = new WP_Query( array(
			'post_type' => 'event',
			'posts_per_page' => -1,
			'event_series' => $post_id,
			'group_events_by' => 'occurrence'
		));

		// If we have some.
		if( $all_event_dates->have_posts() ) {

			// Loop through them.
			while( $all_event_dates->have_posts() ) {

				// Get the post.
				$all_event_dates->the_post();

				// Access post.
				global $post;

				// Init.
				$date = array();

				// Add to our array, formatted for CiviCRM.
				$date['occurrence_id'] = $post->occurrence_id;
				$date['start'] = eo_get_the_start( 'Y-m-d H:i:s' );
				$date['end'] = eo_get_the_end( 'Y-m-d H:i:s' );
				$date['human'] = eo_get_the_start( 'g:ia, M jS, Y' );

				// Add to our array.
				$all_dates[] = $date;

			}

			// Reset post data.
			wp_reset_postdata();

		}

		// --<
		return $all_dates;

	}



	/**
	 * Get all Event Organiser date objects for a given post ID.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @return array $all_dates All dates for the post, keyed by ID.
	 */
	public function get_date_objects( $post_id ) {

		// Get schedule.
		$schedule = eo_get_event_schedule( $post_id );

		// If we have some dates, return them.
		if( isset( $schedule['_occurrences'] ) AND count( $schedule['_occurrences'] ) > 0 ) {
			return $schedule['_occurrences'];
		}

		// --<
		return array();

	}



	//##########################################################################



	/**
	 * Save custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event.
	 */
	private function _save_event_components( $event_id ) {

		// Save online registration.
		$this->update_event_registration( $event_id );

		// Save participant role.
		$this->update_event_role( $event_id );

		// Save registration profile.
		$this->update_event_registration_profile( $event_id );

		/**
		 * Broadcast end of componenents update.
		 *
		 * @since 0.3.2
		 *
		 * @param int $event_id The numeric ID of the EO event.
		 */
		do_action( 'civicrm_event_organiser_event_components_updated', $event_id );

	}



	/**
	 * Delete custom components that sync with CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event.
	 */
	private function _delete_event_components( $event_id ) {

		// EO garbage-collects when it deletes a event, so no need.

	}



	//##########################################################################



	/**
	 * Update event online registration value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event.
	 * @param bool $value Whether registration is enabled or not.
	 */
	public function update_event_registration( $event_id, $value = 0 ) {

		// If the checkbox is ticked.
		if ( isset( $_POST['civi_eo_event_reg'] ) ) {

			// Override the meta value with the checkbox state.
			$value = absint( $_POST['civi_eo_event_reg'] );

		}

		// Go ahead and set the value.
		$this->set_event_registration( $event_id, $value );

	}



	/**
	 * Get event registration value.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @return bool $civi_reg The event registration value for the CiviEvent.
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
	 * Set event registration value.
	 *
	 * @since 0.2.3
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @param bool $value Whether registration is enabled or not.
	 */
	public function set_event_registration( $post_id, $value = 0 ) {

		// Update event meta.
		update_post_meta( $post_id,  '_civi_reg', $value );

	}



	/**
	 * Delete event registration value for a CiviEvent.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 */
	public function clear_event_registration( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_reg' );

	}



	//##########################################################################



	/**
	 * Update event participant role value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event.
	 */
	public function update_event_role( $event_id ) {

		// Kick out if not set.
		if ( ! isset( $_POST['civi_eo_event_role'] ) ) return;

		// Retrieve meta value.
		$value = absint( $_POST['civi_eo_event_role'] );

		// Update event meta.
		update_post_meta( $event_id,  '_civi_role', $value );

	}



	/**
	 * Update event participant role value.
	 *
	 * @since 0.1
	 *
	 * @param int $event_id The numeric ID of the event.
	 * @param int $value The event participant role value for the CiviEvent.
	 */
	public function set_event_role( $event_id, $value = null ) {

		// If not set.
		if ( is_null( $value ) ) {

			// Do we have a default set?
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_role' );

			// Override with default value if we get one.
			if ( $default !== '' AND is_numeric( $default ) ) {
				$value = absint( $default );
			}

		}

		// Update event meta.
		update_post_meta( $event_id,  '_civi_role', $value );

	}



	/**
	 * Get event participant role value.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @return int $civi_role The event participant role value for the CiviEvent.
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
	 * Delete event participant role value for a CiviEvent.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 */
	public function clear_event_role( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_role' );

	}



	//##########################################################################



	/**
	 * Update event registration profile value.
	 *
	 * @since 0.3.1
	 *
	 * @param int $event_id The numeric ID of the event.
	 */
	public function update_event_registration_profile( $event_id ) {

		// Kick out if not set.
		if ( ! isset( $_POST['civi_eo_event_profile'] ) ) return;

		// Retrieve meta value.
		$profile_id = absint( $_POST['civi_eo_event_profile'] );

		// Update event meta.
		update_post_meta( $event_id,  '_civi_registration_profile', $profile_id );

	}



	/**
	 * Update event registration profile value.
	 *
	 * @since 0.3.1
	 *
	 * @param int $event_id The numeric ID of the event.
	 * @param int $profile_id The event registration profile ID for the CiviEvent.
	 */
	public function set_event_registration_profile( $event_id, $profile_id = null ) {

		// If not set.
		if ( is_null( $profile_id ) ) {

			// Do we have a default set?
			$default = $this->plugin->db->option_get( 'civi_eo_event_default_profile' );

			// Override with default value if we get one.
			if ( $default !== '' AND is_numeric( $default ) ) {
				$profile_id = absint( $default );
			}

		}

		// Update event meta
		update_post_meta( $event_id,  '_civi_registration_profile', $profile_id );

	}



	/**
	 * Get event registration profile value.
	 *
	 * @since 0.3.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 * @return int $profile_id The event registration profile ID for the CiviEvent.
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
	 * Delete event registration profile value for a CiviEvent.
	 *
	 * @since 0.3.1
	 *
	 * @param int $post_id The numeric ID of the WP post.
	 */
	public function clear_event_registration_profile( $post_id ) {

		// Delete the meta value.
		delete_post_meta( $post_id, '_civi_registration_profile' );

	}



	/**
	 * Maybe update an EO event's Registration Profile.
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

		// Kick out if not a CiviEvent Online Registration form.
		if ( $formName != 'CRM_Event_Form_ManageEvent_Registration' ) return;

		// Bail if we don't have our sync data.
		if ( ! isset( $this->sync_data ) ) return;

		// Get registration profile.
		$existing_profile = $this->plugin->civi->has_registration_profile( $this->sync_data['civi_event'] );

		// Save event meta if this event have a registration profile specified.
		if ( $existing_profile !== false ) {
			$this->set_event_registration_profile( $this->sync_data['event_id'], $existing_profile['uf_group_id'] );
		} else {
			$this->set_event_registration_profile( $this->sync_data['event_id'] );
		}

		// Clear saved data.
		unset( $this->sync_data );

	}



} // Class ends.



