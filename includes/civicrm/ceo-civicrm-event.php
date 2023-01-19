<?php
/**
 * CiviCRM Event Class.
 *
 * Handles interactions with CiviCRM Events.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser CiviCRM Event Class.
 *
 * A class that encapsulates interactions with CiviCRM.
 *
 * @since 0.7
 */
class CiviCRM_WP_Event_Organiser_CiviCRM_Event {

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
	 * Location object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $location The Location object.
	 */
	public $location;

	/**
	 * Registration object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $registration The Registration object.
	 */
	public $registration;



	/**
	 * Constructor.
	 *
	 * @since 0.7
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin = $parent->plugin;
		$this->civicrm = $parent;

		// Add CiviCRM hooks when parent is loaded.
		add_action( 'ceo/civicrm/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Perform initialisation tasks.
	 *
	 * @since 0.7
	 */
	public function initialise() {

		// Store references.
		$this->location = $this->civicrm->location;
		$this->registration = $this->civicrm->registration;

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks on parent init.
	 *
	 * @since 0.7
	 */
	public function register_hooks() {

		// Add actions to intercept Event form.
		add_action( 'civicrm_buildForm', [ $this, 'form_event_new' ], 10, 2 );
		add_action( 'civicrm_buildForm', [ $this, 'form_event_edit' ], 10, 2 );
		add_action( 'civicrm_buildForm', [ $this, 'form_event_snippet' ], 10, 2 );
		add_action( 'wp_ajax_ceo_feature_image', [ $this, 'form_event_image' ] );

		// Filter Attachments to show only those for a User.
		add_filter( 'ajax_query_attachments_args', [ $this, 'form_event_image_filter_media' ] );

		// Intercept CiviCRM Event form submission process.
		add_action( 'civicrm_postProcess', [ $this, 'form_event_process' ], 10, 2 );

		// Intercept CiviCRM Event create/update/delete actions.
		add_action( 'civicrm_post', [ $this, 'event_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'event_updated' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'event_deleted' ], 10, 4 );

	}

	// -------------------------------------------------------------------------

	/**
	 * Add Javascript and template for new Events on page load.
	 *
	 * For new CiviCRM Events, the form is not loaded via AJAX so there is no
	 * need to split the injection of the template and the addition of the
	 * Javascript across two callbacks.
	 *
	 * @see self::form_event_edit()
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_new( $formName, &$form ) {

		// Is this the Event Info form?
		if ( $formName != 'CRM_Event_Form_ManageEvent_EventInfo' ) {
			return;
		}

		// We want the page, so grab "Print" var from form controller.
		$controller = $form->getVar( 'controller' );
		if ( ! empty( $controller->_print ) ) {
			return;
		}

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// We *must not* have a CiviCRM Event ID.
		$event_id = $form->getVar( '_id' );
		if ( ! empty( $event_id ) ) {
			return;
		}

		// Enqueue the WordPress media scripts.
		wp_enqueue_media();

		// Enqueue our Javascript in footer.
		wp_enqueue_script(
			'ceo-feature-image',
			CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/js/civicrm/event-feature-image.js',
			[ 'jquery' ],
			CIVICRM_WP_EVENT_ORGANISER_VERSION,
			true // In footer.
		);

		// Init localisation.
		$localisation = [
			'title' => __( 'Choose Feature Image', 'civicrm-event-organiser' ),
			'button' => __( 'Set Feature Image', 'civicrm-event-organiser' ),
		];

		/// Init settings.
		$settings = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'loading' => CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/images/loading.gif',
		];

		// Localisation array.
		$vars = [
			'localisation' => $localisation,
			'settings' => $settings,
		];

		// Localise the WordPress way.
		wp_localize_script(
			'ceo-feature-image',
			'CEO_Feature_Image_Settings',
			$vars
		);

		// Add hidden field to hold the Attachment ID.
		$form->add( 'hidden', 'ceo_attachment_id', '0' );

		// Build the placeholder image markup.
		$placeholder_url = CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/images/placeholder.gif';
		$img_width = get_option( 'medium_size_w', 300 );
		$img_style = 'display: none; width: ' . $img_width . 'px; height: 100px;';
		$markup = '<img src="' . $placeholder_url . '" class="wp-post-image" style="' . $img_style . '">';

		// Add image markup.
		$form->assign(
			'ceo_attachment_markup',
			$markup
		);

		// Add button ID.
		$form->assign(
			'ceo_attachment_id_button_id',
			'ceo-feature-image-switcher'
		);

		// Add button text.
		$form->assign(
			'ceo_attachment_id_button',
			__( 'Choose Feature Image', 'civicrm-event-organiser' )
		);

		// Add help text.
		$form->assign(
			'ceo_attachment_id_help',
			__( 'If you would like to add a Feature Image to the Event, do so here.', 'civicrm-event-organiser' )
		);

		// Insert template block into the page.
		CRM_Core_Region::instance( 'page-body' )->add( [
			'template' => 'ceo-featured-image.tpl',
		] );

	}

	/**
	 * Add Javascript on page load.
	 *
	 * The "CRM_Event_Form_ManageEvent_EventInfo" form is loaded twice: firstly
	 * when the page is loaded and secondly as a "snippet" that is AJAX-loaded
	 * into the tab container. The WordPress Media scripts need to be loaded on
	 * page load, while the template needs to be loaded into the snippet.
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_edit( $formName, &$form ) {

		// Is this the Event Info form?
		if ( $formName != 'CRM_Event_Form_ManageEvent_EventInfo' ) {
			return;
		}

		// We want the page, so grab "Print" var from form controller.
		$controller = $form->getVar( 'controller' );
		if ( ! empty( $controller->_print ) ) {
			return;
		}

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// We *must* have a CiviCRM Event ID.
		$event_id = $form->getVar( '_id' );
		if ( empty( $event_id ) ) {
			return;
		}

		// Get the Post ID that this Event is mapped to.
		$post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $event_id );
		if ( $post_id === false ) {
			return;
		}

		// Enqueue the WordPress media scripts.
		wp_enqueue_media();

		// Enqueue our Javascript in footer.
		wp_enqueue_script(
			'ceo-feature-image',
			CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/js/civicrm/event-feature-image.js',
			[ 'jquery' ],
			CIVICRM_WP_EVENT_ORGANISER_VERSION,
			true // In footer.
		);

		// Init localisation.
		$localisation = [
			'title' => __( 'Choose Feature Image', 'civicrm-event-organiser' ),
			'button' => __( 'Set Feature Image', 'civicrm-event-organiser' ),
		];

		/// Init settings.
		$settings = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'loading' => CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/images/loading.gif',
		];

		// Localisation array.
		$vars = [
			'localisation' => $localisation,
			'settings' => $settings,
		];

		// Localise the WordPress way.
		wp_localize_script(
			'ceo-feature-image',
			'CEO_Feature_Image_Settings',
			$vars
		);

	}

	/**
	 * Inject template into AJAX-loaded snippet.
	 *
	 * @see self::form_event_edit()
	 *
	 * @since 0.6.3
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_snippet( $formName, &$form ) {

		// Is this the Event Info form?
		if ( $formName != 'CRM_Event_Form_ManageEvent_EventInfo' ) {
			return;
		}

		// We want the snippet, so grab "Print" var from form controller.
		$controller = $form->getVar( 'controller' );
		if ( empty( $controller->_print ) || $controller->_print !== 'json' ) {
			return;
		}

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// We need the CiviCRM Event ID.
		$event_id = $form->getVar( '_id' );
		if ( empty( $event_id ) ) {
			return;
		}

		// Get the Post ID that this Event is mapped to.
		$post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $event_id );
		if ( $post_id === false ) {
			return;
		}

		// Does this Event have a Feature Image?
		$attachment_id = '';
		if ( has_post_thumbnail( $post_id ) ) {
			$attachment_id = get_post_thumbnail_id( $post_id );
		}

		// Add hidden field to hold the Attachment ID.
		$form->add( 'hidden', 'ceo_attachment_id', $attachment_id );

		// Get the image markup.
		$placeholder_url = CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/images/placeholder.gif';
		$markup = '<img src="' . $placeholder_url . '" class="wp-post-image" style="display: none;">';
		if ( ! empty( $attachment_id ) ) {
			$markup = get_the_post_thumbnail( $post_id, 'medium' );
		}

		// Add image markup.
		$form->assign(
			'ceo_attachment_markup',
			$markup
		);

		// Add button ID.
		$form->assign(
			'ceo_attachment_id_button_id',
			'ceo-feature-image-switcher-' . $post_id
		);

		// Add button text.
		$form->assign(
			'ceo_attachment_id_button',
			__( 'Choose Feature Image', 'civicrm-event-organiser' )
		);

		// Add help text.
		$form->assign(
			'ceo_attachment_id_help',
			__( 'If you would like to add a Feature Image to the Event Organiser Event, choose one here.', 'civicrm-event-organiser' )
		);

		// Insert template block into the page.
		CRM_Core_Region::instance( 'page-body' )->add( [
			'template' => 'ceo-featured-image.tpl',
		] );

	}

	/**
	 * AJAX handler for Feature Image calls.
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 */
	public function form_event_image() {

		// Init response.
		$data = [
			'success' => 'false',
		];

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			return $data;
		}

		// Get Attachment ID.
		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) trim( $_POST['attachment_id'] ) : 0;
		if ( ! is_numeric( $attachment_id ) || $attachment_id === 0 ) {
			return $data;
		}

		// Handle Feature Image if there is a Post ID.
		$post_id = isset( $_POST['post_id'] ) ? (int) trim( $_POST['post_id'] ) : 0;
		if ( is_numeric( $post_id ) && $post_id !== 0 ) {

			// Set Feature Image.
			set_post_thumbnail( $post_id, $attachment_id );

			// Get the Feature Image markup.
			$markup = get_the_post_thumbnail( $post_id, 'medium' );

		} else {

			// Filter the image class.
			add_filter( 'wp_get_attachment_image_attributes', [ $this, 'form_event_image_filter' ], 10, 1 );

			// Get the Attachment Image markup.
			$markup = wp_get_attachment_image( $attachment_id, 'medium', false );

			// Remove filter.
			remove_filter( 'wp_get_attachment_image_attributes', [ $this, 'form_event_image_filter' ] );

		}

		// Add to data.
		$data['markup'] = $markup;

		// Overwrite flag.
		$data['success'] = 'true';

		// Send data to browser.
		wp_send_json( $data );

	}

	/**
	 * Adds the feature image class to the list of attachment image attributes.
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $attr The existing array of attribute values for the image markup.
	 * @return array $attr The modified array of attribute values for the image markup.
	 */
	public function form_event_image_filter( $attr ) {

		// Make sure the feature image class is present.
		if ( ! empty( $attr['class'] ) ) {
			$attr['class'] .= ' wp-post-image';
		} else {
			$attr['class'] = 'wp-post-image';
		}

		// --<
		return $attr;

	}

	/**
	 * Ensure that Users see just their own uploaded media.
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $query The existing query.
	 * @return array $query The modified query.
	 */
	public function form_event_image_filter_media( $query ) {

		/**
		 * Filter the capability that is needed to view all media.
		 *
		 * @since 0.6.3
		 * @since 0.7 Moved to this class.
		 *
		 * @param str The default capability needed to view all media.
		 */
		$capability = apply_filters( 'civicrm_event_organiser_filter_media', 'edit_posts' );

		// Admins and Editors get to see everything.
		if ( ! current_user_can( $capability ) ) {
			$query['author'] = get_current_user_id();
		}

		// --<
		return $query;

	}

	/**
	 * Callback for the CiviEvent Component Settings form's postProcess hook.
	 *
	 * This is called *after* the Event has been synced to Event Organiser, so
	 * we can apply the Attachment to the Event Organiser Event knowing that it
	 * exists. Only needs to be done for *new* Events.
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_process( $formName, &$form ) {

		// Is this the Event Info form?
		if ( $formName != 'CRM_Event_Form_ManageEvent_EventInfo' ) {
			return;
		}

		// This gets called *three* times!
		static $done;
		if ( isset( $done ) && $done === true ) {
			return;
		}

		// Grab submitted values.
		$values = $form->getSubmitValues();

		// Kick out if the Event is a template.
		if ( ! empty( $values['is_template'] ) ) {
			return;
		}

		// Bail if there's no Event Organiser Event ID.
		if ( empty( $this->eo_event_created_id ) ) {
			return;
		}

		// Bail if there's no Feature Image ID.
		if ( empty( $values['ceo_attachment_id'] ) || ! is_numeric( $values['ceo_attachment_id'] ) ) {
			return;
		}

		// Sanity check.
		$attachment_id = (int) $values['ceo_attachment_id'];

		// Set Feature Image.
		set_post_thumbnail( $this->eo_event_created_id, $attachment_id );

		// We're done.
		$done = true;

	}

	// -------------------------------------------------------------------------

	/**
	 * Create an Event Organiser Event when a CiviCRM Event is created.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function event_created( $op, $objectName, $objectId, $objectRef ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'Event' ) {
			return;
		}

		// Kick out if not Event object.
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) {
			return;
		}

		// Kick out if the Event is a template.
		if ( ! empty( $objectRef->is_template ) ) {
			return;
		}

		// Update a single Event Organiser Event - or create if it doesn't exist.
		$event_id = $this->plugin->eo->update_event( (array) $objectRef );

		// Bail if we don't get an Event ID.
		if ( is_wp_error( $event_id ) ) {
			return;
		}

		// Get Occurrences.
		$occurrences = eo_get_the_occurrences_of( $event_id );

		/*
		 * In this context, a CiviCRM Event can only have an Event Organiser Event
		 * with a single Occurrence associated with it, so use first item.
		 */
		$keys = array_keys( $occurrences );
		$occurrence_id = array_shift( $keys );

		// Store correspondences.
		$this->plugin->mapping->store_event_correspondences( $event_id, [ $occurrence_id => $objectRef->id ] );

		// Store Event IDs for possible use in the "form_event_process" method.
		$this->civicrm_event_created_id = $objectRef->id;
		$this->eo_event_created_id = $event_id;

	}

	/**
	 * Update an Event Organiser Event when a CiviCRM Event is updated.
	 *
	 * Only CiviCRM Events that are in a one-to-one correspondence with an Event
	 * Organiser Event can update that Event Organiser Event. CiviCRM Events which
	 * are part of an Event Organiser sequence can be updated, but no data will
	 * be synced across to the Event Organiser Event.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function event_updated( $op, $objectName, $objectId, $objectRef ) {

		// Target our operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'Event' ) {
			return;
		}

		// Kick out if not Event object.
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) {
			return;
		}

		// Kick out if the Event is a template.
		if ( ! empty( $objectRef->is_template ) ) {
			return;
		}

		// Bail if this CiviCRM Event is part of an Event Organiser sequence.
		if ( $this->plugin->mapping->is_civi_event_in_eo_sequence( $objectId ) ) {
			return;
		}

		// Get full Event data.
		$updated_event = $this->get_event_by_id( $objectId );
		if ( $updated_event === false ) {
			return;
		}

		// Update the Event Organiser Event.
		$event_id = $this->plugin->eo->update_event( $updated_event );

		// Bail if we don't get an Event ID.
		if ( is_wp_error( $event_id ) ) {
			return;
		}

		// Get Occurrences.
		$occurrences = eo_get_the_occurrences_of( $event_id );

		/*
		 * In this context, a CiviCRM Event can only have an Event Organiser Event
		 * with a single Occurrence associated with it, so use first item.
		 */
		$keys = array_keys( $occurrences );
		$occurrence_id = array_shift( $keys );

		// Store correspondences.
		$this->plugin->mapping->store_event_correspondences( $event_id, [ $occurrence_id => $objectId ] );

	}

	/**
	 * Delete an Event Organiser Event when a CiviCRM Event is deleted.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param string $op The type of database operation.
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function event_deleted( $op, $objectName, $objectId, $objectRef ) {

		// Target our operation.
		if ( $op != 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'Event' ) {
			return;
		}

		// Kick out if not Event object.
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) {
			return;
		}

		// Clear the correspondence between the Occurrence and the CiviCRM Event.
		$post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $objectId );
		$occurrence_id = $this->plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $objectId );
		$this->plugin->mapping->clear_event_correspondence( $post_id, $occurrence_id, $objectId );

		// Set the Event Organiser Event to 'draft' status if it's not a recurring Event.
		if ( ! eo_recurs( $post_id ) ) {
			$this->plugin->eo->update_event_status( $post_id, 'draft' );
		}

	}

	// -------------------------------------------------------------------------

	/**
	 * Prepare a CiviCRM Event with data from an Event Organiser Event.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $post The WordPress Post object.
	 * @return array $civi_event The basic CiviCRM Event data.
	 */
	public function prepare_civi_event( $post ) {

		// Init CiviCRM Event array.
		$civi_event = [
			'version' => 3,
		];

		// Add items that are common to all CiviCRM Events.
		$civi_event['title'] = $post->post_title;
		$civi_event['description'] = $post->post_content;
		$civi_event['summary'] = wp_strip_all_tags( $post->post_excerpt );
		$civi_event['created_date'] = $post->post_date;
		$civi_event['participant_listing_id'] = null;

		// Get Status Sync setting.
		$status_sync = (int) $this->plugin->db->option_get( 'civi_eo_event_default_status_sync', 3 );

		/*
		 * For "Do not sync" (3) and "Sync CiviCRM -> EO" (2), we can leave out
		 * the params and they will remain unchanged when updating a CiviCRM Event.
		 *
		 * When creating an Event, let's see...
		 */
		if ( $status_sync === 0 || $status_sync === 1 ) {

			// Default both "status" settings to "false".
			$civi_event['is_public'] = 0;
			$civi_event['is_active'] = 0;

			// If the Event is in "draft" or "pending" mode, set as 'not active' and 'not public'.
			if ( $post->post_status === 'draft' || $post->post_status === 'pending' ) {
				$civi_event['is_active'] = 0;
				$civi_event['is_public'] = 0;
			}

			// If the Event is in "publish" or "future" mode, set as 'active' and 'public'.
			if ( $post->post_status === 'publish' || $post->post_status === 'future' ) {
				$civi_event['is_active'] = 1;
				$civi_event['is_public'] = 1;
			}

			// If the Event is in "private" mode, set as 'active' and 'not public'.
			if ( $post->post_status === 'private' ) {
				$civi_event['is_active'] = 1;
				$civi_event['is_public'] = 0;
			}

		}

		// Get Venue for this Event.
		$venue_id = eo_get_venue( $post->ID );

		// Get CiviCRM Event Location.
		$location_id = $this->plugin->eo_venue->get_civi_location( $venue_id );

		// Did we get one?
		if ( is_numeric( $location_id ) ) {

			// Add to our params.
			$civi_event['loc_block_id'] = (int) $location_id;

			// Set CiviCRM to add map.
			$civi_event['is_map'] = 1;

		}

		// Online Registration off by default.
		$civi_event['is_online_registration'] = 0;

		// Get CiviCRM Event Online Registration value.
		$is_reg = $this->plugin->eo->get_event_registration( $post->ID );

		// Add Online Registration value to our params if we get one.
		if ( is_numeric( $is_reg ) && $is_reg != 0 ) {
			$civi_event['is_online_registration'] = 1;
		}

		// Participant Role default.
		$civi_event['default_role_id'] = 0;

		// Get existing Participant Role ID.
		$existing_id = $this->registration->get_participant_role( $post );

		// Add existing Participant Role ID to our params if we get one.
		if ( $existing_id !== false && is_numeric( $existing_id ) && $existing_id != 0 ) {
			$civi_event['default_role_id'] = (int) $existing_id;
		}

		// Get Event Type pseudo-ID (or value), because it is required in CiviCRM.
		$type_value = $this->plugin->taxonomy->get_default_event_type_value( $post );

		// Die if there are no Event Types defined in CiviCRM.
		if ( $type_value === false ) {
			wp_die( __( 'You must have some CiviCRM Event Types defined', 'civicrm-event-organiser' ) );
		}

		// Assign Event Type value.
		$civi_event['event_type_id'] = $type_value;

		// CiviCRM Event Registration Confirmation screen enabled by default.
		$civi_event['is_confirm_enabled'] = 1;

		// Get CiviCRM Event Registration Confirmation screen value.
		$is_confirm_enabled = $this->registration->get_registration_confirm_enabled( $post->ID );

		// Set confirmation screen value to our params if we get one.
		if ( $is_confirm_enabled == 0 ) {
			$civi_event['is_confirm_enabled'] = 0;
		}

		// CiviCRM Event Confirmation Email off by default.
		$civi_event['is_email_confirm'] = 0;

		// Get CiviCRM Event Confirmation Email value.
		$is_email_confirm = $this->registration->get_registration_send_email_enabled( $post->ID );

		// Set Confirmation Email value if we get one.
		if ( ! empty( $civi_event['is_online_registration'] ) && ! empty( $is_email_confirm ) ) {
			$civi_event['is_email_confirm'] = 1;
		}

		// Set Confirmation Email sub-fields to our params if enabled.
		if ( ! empty( $civi_event['is_email_confirm'] ) ) {
			$civi_event['confirm_from_name'] = $this->registration->get_registration_send_email_from_name( $post->ID );
			$civi_event['confirm_from_email'] = $this->registration->get_registration_send_email_from( $post->ID );
		}

		/**
		 * Filter prepared CiviCRM Event.
		 *
		 * @since 0.3.1
		 * @since 0.7 Moved to this class.
		 *
		 * @param array $civi_event The array of data for the CiviCRM Event.
		 * @param object $post The WP Post object.
		 * @return array $civi_event The modified array of data for the CiviCRM Event.
		 */
		return apply_filters( 'civicrm_event_organiser_prepared_civi_event', $civi_event, $post );

	}

	/**
	 * Create CiviCRM Events for an Event Organiser Event.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $post The WP Post object.
	 * @param array $dates Array of properly formatted dates.
	 * @return array|bool $correspondences Array of correspondences keyed by Occurrence ID, or false on failure.
	 */
	public function create_civi_events( $post, $dates ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Just for safety, check we get some (though we must).
		if ( count( $dates ) === 0 ) {
			return false;
		}

		// Init links.
		$links = [];

		// Init correspondences.
		$correspondences = [];

		// Prepare CiviCRM Event.
		$civi_event = $this->prepare_civi_event( $post );

		// Now loop through dates and create CiviCRM Events per date.
		foreach ( $dates as $date ) {

			// Overwrite dates.
			$civi_event['start_date'] = $date['start'];
			$civi_event['end_date'] = $date['end'];

			// Use API to create Event.
			$result = civicrm_api( 'Event', 'create', $civi_event );

			// Log failures and skip to next.
			if ( isset( $result['is_error'] ) && $result['is_error'] == '1' ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => $result['error_message'],
					'civi_event' => $civi_event,
					'backtrace' => $trace,
				], true ) );
				continue;
			}

			// Enable Registration if selected.
			$this->registration->enable_registration( array_pop( $result['values'] ), $post );

			// Add the new CiviCRM Event ID to array, keyed by Occurrence ID.
			$correspondences[ $date['occurrence_id'] ] = $result['id'];

		} // End dates loop.

		// Store these in post meta.
		$this->plugin->mapping->store_event_correspondences( $post->ID, $correspondences );

		// --<
		return $correspondences;

	}

	/**
	 * Update CiviCRM Events for an Event.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $post The WP Post object.
	 * @param array $dates Array of properly formatted dates.
	 * @return array|bool $correspondences Array of correspondences keyed by Occurrence ID, or false on failure.
	 */
	public function update_civi_events( $post, $dates ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Just for safety, check we get some (though we must).
		if ( count( $dates ) === 0 ) {
			return false;
		}

		// Get existing CiviCRM Events from post meta.
		$correspondences = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );

		// If we have none yet.
		if ( count( $correspondences ) === 0 ) {

			// Create them.
			$correspondences = $this->create_civi_events( $post, $dates );

			// --<
			return $correspondences;

		}

		/*
		 * The logic for updating is as follows:
		 *
		 * Event sequences can only be generated from Event Organiser, so any
		 * CiviCRM Events that are part of a sequence must have been generated
		 * automatically.
		 *
		 * Since CiviCRM Events will only be generated when the "Create CiviCRM Events"
		 * checkbox is ticked (and only those with 'publish_posts' caps can see
		 * the checkbox) we assume that this is the definitive set of Events.
		 *
		 * Any further changes work thusly:
		 *
		 * We already have the correspondence array, so retrieve the CiviCRM Events.
		 * The correspondence array is already sorted by start date, so the
		 * CiviCRM Events will be too.
		 *
		 * If the length of the two Event arrays is identical, we assume the
		 * sequences correspond and update the CiviCRM Events with the details of
		 * the Event Organiser Events.
		 *
		 * Next, we match by date and time. Any CiviCRM Events that match have their
		 * info updated since we assume their correspondence remains unaltered.
		 *
		 * Any additions to the Event Organiser Event are treated as new CiviCRM Events
		 * and are added to CiviCRM. Any removals are treated as if the Event has been
		 * cancelled and the CiviCRM Event is set to 'disabled' rather than deleted.
		 * This is to preserve any data that may have been collected for the
		 * removed Event.
		 *
		 * The bottom line is: make sure your sequences are right before hitting
		 * the Publish button and be wary of making further changes.
		 *
		 * Things get a bit more complicated when a sequence is split, but it's
		 * not too bad. This functionality will eventually be handled by the
		 * Event Organiser 'occurrence' hooks when I get round to it.
		 *
		 * Also, note the inline comment discussing what to do with CiviCRM Events
		 * that have been "orphaned" from the sequence. The current need is to
		 * retain the CiviCRM Event, since there may be associated data.
		 */

		// Start with new correspondence array.
		$new_correspondences = [];

		// Sort existing correspondences by key, which will always be chronological.
		ksort( $correspondences );

		// Prepare CiviCRM Event.
		$civi_event = $this->prepare_civi_event( $post );

		// ---------------------------------------------------------------------
		// When arrays are equal in length
		// ---------------------------------------------------------------------

		// Do the arrays have the same length?
		if ( count( $dates ) === count( $correspondences ) ) {

			// Let's assume that the intention is simply to update the CiviCRM Events
			// and that each date corresponds to the sequential CiviCRM Event.

			// Loop through dates.
			foreach ( $dates as $date ) {

				// Set ID, triggering update.
				$civi_event['id'] = array_shift( $correspondences );

				// Overwrite dates.
				$civi_event['start_date'] = $date['start'];
				$civi_event['end_date'] = $date['end'];

				// Use API to create Event.
				$result = civicrm_api( 'Event', 'create', $civi_event );

				// Log failures and skip to next.
				if ( $result['is_error'] == '1' ) {
					$e = new Exception();
					$trace = $e->getTraceAsString();
					error_log( print_r( [
						'method' => __METHOD__,
						'message' => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace' => $trace,
					], true ) );
					continue;
				}

				// Enable Registration if selected.
				$this->registration->enable_registration( array_pop( $result['values'] ), $post );

				// Add the CiviCRM Event ID to array, keyed by Occurrence ID.
				$new_correspondences[ $date['occurrence_id'] ] = $result['id'];

			}

			// Overwrite those stored in post meta.
			$this->plugin->mapping->store_event_correspondences( $post->ID, $new_correspondences );

			// --<
			return $new_correspondences;

		}

		// ---------------------------------------------------------------------
		// When arrays are NOT equal in length, we MUST have correspondences
		// ---------------------------------------------------------------------

		// Init CiviCRM Events array.
		$civi_events = [];

		// Get CiviCRM Events by ID.
		foreach ( $correspondences as $occurrence_id => $civi_event_id ) {

			// Get full CiviCRM Event.
			$full_civi_event = $this->get_event_by_id( $civi_event_id );
			if ( $full_civi_event === false ) {
				continue;
			}

			// Add CiviCRM Event to array.
			$civi_events[] = $full_civi_event;

		}

		// Init orphaned CiviCRM Event data.
		$orphaned_civi_events = [];

		// Get orphaned CiviCRM Events for this Event Organiser Event.
		$orphaned = $this->plugin->mapping->get_orphaned_events_by_eo_event_id( $post->ID );

		// Did we get any?
		if ( count( $orphaned ) > 0 ) {

			// Get CiviCRM Events by ID.
			foreach ( $orphaned as $civi_event_id ) {

				// Get full CiviCRM Event.
				$orphaned_civi_event = $this->get_event_by_id( $civi_event_id );
				if ( $orphaned_civi_event === false ) {
					continue;
				}

				// Add CiviCRM Event to array.
				$orphaned_civi_events[] = $orphaned_civi_event;

			}

		}

		// Get matches between Event Organiser Events and CiviCRM Events.
		$matches = $this->get_event_matches( $dates, $civi_events, $orphaned_civi_events );

		// Amend the orphans array, removing on what has been "unorphaned".
		$orphans = array_diff( $orphaned, $matches['unorphaned'] );

		// Extract matched array.
		$matched = $matches['matched'];

		// Do we have any matched?
		if ( count( $matched ) > 0 ) {

			// Loop through matched dates and update CiviCRM Events.
			foreach ( $matched as $occurrence_id => $civi_id ) {

				// Assign ID so we perform an update.
				$civi_event['id'] = $civi_id;

				// Use API to update Event.
				$result = civicrm_api( 'Event', 'create', $civi_event );

				// Log failures and skip to next.
				if ( $result['is_error'] == '1' ) {
					$e = new Exception();
					$trace = $e->getTraceAsString();
					error_log( print_r( [
						'method' => __METHOD__,
						'message' => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace' => $trace,
					], true ) );
					continue;
				}

				// Enable Registration if selected.
				$this->registration->enable_registration( array_pop( $result['values'] ), $post );

				// Add to new correspondence array.
				$new_correspondences[ $occurrence_id ] = $civi_id;

			}

		} // End check for empty array.

		// Extract unmatched Event Organiser Events array.
		$unmatched_eo = $matches['unmatched_eo'];

		// Do we have any unmatched Event Organiser Occurrences?
		if ( count( $unmatched_eo ) > 0 ) {

			// Now loop through unmatched Event Organiser dates and create CiviCRM Events.
			foreach ( $unmatched_eo as $eo_date ) {

				// Make sure there's no ID.
				unset( $civi_event['id'] );

				// Overwrite dates.
				$civi_event['start_date'] = $eo_date['start'];
				$civi_event['end_date'] = $eo_date['end'];

				// Use API to create Event.
				$result = civicrm_api( 'Event', 'create', $civi_event );

				// Log failures and skip to next.
				if ( $result['is_error'] == '1' ) {
					$e = new Exception();
					$trace = $e->getTraceAsString();
					error_log( print_r( [
						'method' => __METHOD__,
						'message' => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace' => $trace,
					], true ) );
					continue;
				}

				// Enable Registration if selected.
				$this->registration->enable_registration( array_pop( $result['values'] ), $post );

				// Add the CiviCRM Event ID to array, keyed by Occurrence ID.
				$new_correspondences[ $eo_date['occurrence_id'] ] = $result['id'];

			}

		} // End check for empty array.

		// Extract unmatched CiviCRM Events array.
		$unmatched_civi = $matches['unmatched_civi'];

		// Do we have any unmatched CiviCRM Events?
		if ( count( $unmatched_civi ) > 0 ) {

			// Assume we're not deleting extra CiviCRM Events.
			$unmatched_delete = false;

			// Get "delete unused" checkbox value.
			if (
				isset( $_POST['civi_eo_event_delete_unused'] ) &&
				absint( $_POST['civi_eo_event_delete_unused'] ) === 1
			) {

				// Override - we ARE deleting.
				$unmatched_delete = true;

			}

			// Loop through unmatched CiviCRM Events.
			foreach ( $unmatched_civi as $civi_id ) {

				// If deleting.
				if ( $unmatched_delete ) {

					// Delete CiviCRM Event.
					$result = $this->delete_civi_events( [ $civi_id ] );

					// Delete this ID from the orphans array?
					//$orphans = array_diff( $orphans, [ $civi_id ] );

				} else {

					// Set CiviCRM Event to disabled.
					$result = $this->disable_civi_event( $civi_id );

					// Add to orphans array.
					$orphans[] = $civi_id;

				}

			}

		} // End check for empty array.

		// Store new correspondences and orphans.
		$this->plugin->mapping->store_event_correspondences( $post->ID, $new_correspondences, $orphans );

		// --<
		return $new_correspondences;

	}

	/**
	 * Match Event Organiser Events and CiviCRM Events.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $dates An array of Event Organiser Event Occurrence data.
	 * @param array $civi_events An array of CiviCRM Event data.
	 * @param array $orphaned_civi_events An array of orphaned CiviCRM Event data.
	 * @return array $event_data A nested array of matched and unmatched Events.
	 */
	public function get_event_matches( $dates, $civi_events, $orphaned_civi_events ) {

		// Init return array.
		$event_data = [
			'matched' => [],
			'unmatched_eo' => [],
			'unmatched_civi' => [],
			'unorphaned' => [],
		];

		// Init matched.
		$matched = [];

		// Match Event Organiser dates to CiviCRM Events.
		foreach ( $dates as $key => $date ) {

			// Run through CiviCRM Events.
			foreach ( $civi_events as $civi_event ) {

				// Does the start_date match?
				if ( $date['start'] == $civi_event['start_date'] ) {

					// Add to matched array.
					$matched[ $date['occurrence_id'] ] = $civi_event['id'];

					// Found - break this loop.
					break;

				}

			}

		}

		// Init unorphaned.
		$unorphaned = [];

		// Check orphaned array.
		if ( count( $orphaned_civi_events ) > 0 ) {

			// Match Event Organiser dates to orphaned CiviCRM Events.
			foreach ( $dates as $key => $date ) {

				// Run through orphaned CiviCRM Events.
				foreach ( $orphaned_civi_events as $orphaned_civi_event ) {

					// Does the start_date match?
					if ( $date['start'] == $orphaned_civi_event['start_date'] ) {

						// Add to matched array.
						$matched[ $date['occurrence_id'] ] = $orphaned_civi_event['id'];

						// Add to "unorphaned" array.
						$unorphaned[] = $orphaned_civi_event['id'];

						// Found - break this loop.
						break;

					}

				}

			}

		}

		// Init Event Organiser unmatched.
		$unmatched_eo = [];

		// Find unmatched Event Organiser dates.
		foreach ( $dates as $key => $date ) {

			// If the matched array has no entry.
			if ( ! isset( $matched[ $date['occurrence_id'] ] ) ) {

				// Add to unmatched.
				$unmatched_eo[] = $date;

			}

		}

		// Init CiviCRM unmatched.
		$unmatched_civi = [];

		// Find unmatched Event Organiser dates.
		foreach ( $civi_events as $civi_event ) {

			// Does the matched array have an entry?
			if ( ! in_array( $civi_event['id'], $matched ) ) {

				// Add to unmatched.
				$unmatched_civi[] = $civi_event['id'];

			}

		}

		// Sort matched by key.
		ksort( $matched );

		// Construct return array.
		$event_data['matched'] = $matched;
		$event_data['unmatched_eo'] = $unmatched_eo;
		$event_data['unmatched_civi'] = $unmatched_civi;
		$event_data['unorphaned'] = $unorphaned;

		// --<
		return $event_data;

	}

	/**
	 * Get all CiviCRM Events.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @return array $events The CiviCRM Events data.
	 */
	public function get_all_civi_events() {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Construct Events array.
		$params = [
			'version' => 3,
			'is_template' => 0,
			'options' => [
				'limit' => 0, // Get all Events.
			],
		];

		// Call API.
		$events = civicrm_api( 'Event', 'get', $params );

		// Log failures and return boolean false.
		if ( $events['is_error'] == '1' ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $events['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// --<
		return $events;

	}

	/**
	 * Delete all CiviCRM Events.
	 *
	 * WARNING: only for dev purposes really!
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $civi_event_ids An array of CiviCRM Event IDs.
	 * @return array $results An array of CiviCRM results.
	 */
	public function delete_civi_events( $civi_event_ids ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Just for safety, check we get some.
		if ( count( $civi_event_ids ) == 0 ) {
			return false;
		}

		// Init return.
		$results = [];

		// One by one, it seems.
		foreach ( $civi_event_ids as $civi_event_id ) {

			// Construct "query".
			$params = [
				'version' => 3,
				'id' => $civi_event_id,
			];

			// Okay, let's do it.
			$result = civicrm_api( 'Event', 'delete', $params );

			// Log failures and skip to next.
			if ( $result['is_error'] == '1' ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => $result['error_message'],
					'params' => $params,
					'backtrace' => $trace,
				], true ) );
				continue;
			}

			// Add to return array.
			$results[] = $result;

		}

		// --<
		return $results;

	}

	/**
	 * Disable a CiviCRM Event.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
	 * @return array|bool $result The CiviCRM API result array or false otherwise.
	 */
	public function disable_civi_event( $civi_event_id ) {

		// Init CiviCRM or die.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		/**
		 * Allow plugins to skip disabling CiviCRM Events.
		 *
		 * @since 0.6.6
		 * @since 0.7 Moved to this class.
		 *
		 * @param bool False by default, pass true to skip.
		 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
		 */
		$skip = apply_filters( 'ceo_skip_disable_civi_event', false, $civi_event_id );
		if ( $skip === true ) {
			return false;
		}

		// Build params.
		$params = [
			'version' => 3,
			'id' => $civi_event_id,
			'is_active' => 0,
		];

		// Use API to update Event.
		$result = civicrm_api( 'Event', 'create', $params );

		// Log failures and return boolean false.
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
	 * Gets a CiviCRM Event by ID.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $event_id The numeric ID of the CiviCRM Event.
	 * @return array|bool $event The array of CiviCRM Event data, or false if not found.
	 */
	public function get_event_by_id( $event_id ) {

		// Init return.
		$event = false;

		// Bail if no CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $event;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'id' => $event_id,
		];

		// Call API.
		$result = civicrm_api( 'Event', 'get', $params );

		// Log failures and bail.
		if ( isset( $result['is_error'] ) && $result['is_error'] == '1' ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			], true ) );
			return $event;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $event;
		}

		// The result set should contain only one item.
		$event = array_pop( $result['values'] );

		// --<
		return $event;

	}

	/**
	 * Get a CiviCRM Event's "Info & Settings" link.
	 *
	 * @since 0.3.6
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
	 * @return string $link The URL of the CiviCRM "Info & Settings" page.
	 */
	public function get_settings_link( $civi_event_id ) {

		// Init link.
		$link = '';

		// Init CiviCRM or bail.
		if ( ! $this->civicrm->is_active() ) {
			return $link;
		}

		// Use CiviCRM to construct link.
		$link = CRM_Utils_System::url(
			'civicrm/event/manage/settings',
			'reset=1&action=update&id=' . $civi_event_id,
			true,
			null,
			false,
			false,
			true
		);

		// --<
		return $link;

	}

	// -------------------------------------------------------------------------

	/**
	 * Validate all CiviCRM Event data for an Event Organiser Event.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param int $post_id The numeric ID of the WP Post.
	 * @param object $post The WP Post object.
	 * @return mixed True if success, otherwise WP error object.
	 */
	public function validate_civi_options( $post_id, $post ) {

		// Disabled.
		return true;

		// Check default Event Type.
		$result = $this->_validate_event_type();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Check participant_role.
		$result = $this->_validate_participant_role();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Check is_online_registration.
		$result = $this->_validate_is_online_registration();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Check LocBlock ID.
		$result = $this->_validate_loc_block_id();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

	}

} // Class ends.
