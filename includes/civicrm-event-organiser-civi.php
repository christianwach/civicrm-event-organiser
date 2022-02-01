<?php
/**
 * CiviCRM Class.
 *
 * Handles interactions with CiviCRM.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser CiviCRM Class.
 *
 * A class that encapsulates interactions with CiviCRM.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_CiviCRM {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Flag for overriding sync process.
	 *
	 * @since 0.1
	 * @access public
	 * @var bool $do_not_sync True if overriding, false otherwise.
	 */
	public $do_not_sync = false;



	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Add CiviCRM hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', [ $this, 'register_hooks' ] );

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
	 * Register hooks on plugin init.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Register template directory for form amends.
		add_action( 'civicrm_config', [ $this, 'register_form_directory' ], 10 );

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



	/**
	 * Test if CiviCRM plugin is active.
	 *
	 * @since 0.1
	 *
	 * @return bool True if CiviCRM initialized, false otherwise.
	 */
	public function is_active() {

		// Bail if no CiviCRM init function.
		if ( ! function_exists( 'civi_wp' ) ) {
			return false;
		}

		// Try and init CiviCRM.
		return civi_wp()->initialize();

	}



	/**
	 * Register directory that CiviCRM searches in for our form template file.
	 *
	 * @since 0.1
	 * @since 0.6.3 Renamed.
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_form_directory( &$config ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Get template instance.
		$template = CRM_Core_Smarty::singleton();

		// Define our custom path.
		$custom_path = CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/civicrm';

		// Add our custom template directory.
		$template->addTemplateDir( $custom_path );

		// Register template directory.
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );

	}



	/**
	 * Check a CiviCRM permission.
	 *
	 * @since 0.3
	 *
	 * @param str $permission The permission string.
	 * @return bool $permitted True if allowed, false otherwise.
	 */
	public function check_permission( $permission ) {

		// Always deny if CiviCRM is not active.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Deny by default.
		$permitted = false;

		// Check CiviCRM permissions.
		if ( CRM_Core_Permission::check( $permission ) ) {
			$permitted = true;
		}

		/**
		 * Return permission but allow overrides.
		 *
		 * @since 0.3.4
		 *
		 * @param bool $permitted True if allowed, false otherwise.
		 * @param str $permission The CiviCRM permission string.
		 * @return bool $permitted True if allowed, false otherwise.
		 */
		return apply_filters( 'civicrm_event_organiser_permitted', $permitted, $permission );

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
			CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/js/civi-eo-feature-image.js',
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
		$hidden_field = $form->add( 'hidden', 'ceo_attachment_id', '0' );

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
		$post_id = $this->plugin->db->get_eo_event_id_by_civi_event_id( $event_id );
		if ( $post_id === false ) {
			return;
		}

		// Enqueue the WordPress media scripts.
		wp_enqueue_media();

		// Enqueue our Javascript in footer.
		wp_enqueue_script(
			'ceo-feature-image',
			CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/js/civi-eo-feature-image.js',
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
		$post_id = $this->plugin->db->get_eo_event_id_by_civi_event_id( $event_id );
		if ( $post_id === false ) {
			return;
		}

		// Does this Event have a Feature Image?
		$attachment_id = '';
		if ( has_post_thumbnail( $post_id ) ) {
			$attachment_id = get_post_thumbnail_id( $post_id );
		}

		// Add hidden field to hold the Attachment ID.
		$hidden_field = $form->add( 'hidden', 'ceo_attachment_id', $attachment_id );

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
	 *
	 * @param array $query The existing query.
	 * @return array $query The modified query.
	 */
	public function form_event_image_filter_media( $query ) {

		/**
		 * Filter the capability that is needed to view all media.
		 *
		 * @since 0.6.3
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
	 * This is called *after* the Event has been synced to EO, so we can apply
	 * the Attachment to the EO Event knowing that it exists. Only needs to be
	 * done for *new* Events.
	 *
	 * @since 0.6.3
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

		// Bail if there's no EO Event ID.
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
	 * Create an EO Event when a CiviCRM Event is created.
	 *
	 * @since 0.1
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

		// Update a single EO Event - or create if it doesn't exist.
		$event_id = $this->plugin->eo->update_event( (array) $objectRef );

		// Bail if we don't get an Event ID.
		if ( is_wp_error( $event_id ) ) {
			return;
		}

		// Get Occurrences.
		$occurrences = eo_get_the_occurrences_of( $event_id );

		// In this context, a CiviCRM Event can only have an EO Event with a
		// single Occurrence associated with it, so use first item.
		$keys = array_keys( $occurrences );
		$occurrence_id = array_shift( $keys );

		// Store correspondences.
		$this->plugin->db->store_event_correspondences( $event_id, [ $occurrence_id => $objectRef->id ] );

		// Store Event IDs for possible use in the "form_event_process" method.
		$this->civicrm_event_created_id = $objectRef->id;
		$this->eo_event_created_id = $event_id;

	}



	/**
	 * Update an EO Event when a CiviCRM Event is updated.
	 *
	 * Only CiviCRM Events that are in a one-to-one correspondence with an Event
	 * Organiser Event can update that Event Organiser Event. CiviCRM Events which
	 * are part of an Event Organiser sequence can be updated, but no data will
	 * be synced across to the Event Organiser Event.
	 *
	 * @since 0.1
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

		// Bail if this CiviCRM Event is part of an EO sequence.
		if ( $this->plugin->db->is_civi_event_in_eo_sequence( $objectId ) ) {
			return;
		}

		// Get full Event data.
		$updated_event = $this->get_event_by_id( $objectId );

		// Bail if not found.
		if ( $updated_event === false ) {
			return;
		}

		// Update the EO Event.
		$event_id = $this->plugin->eo->update_event( $updated_event );

		// Bail if we don't get an Event ID.
		if ( is_wp_error( $event_id ) ) {
			return;
		}

		// Get Occurrences.
		$occurrences = eo_get_the_occurrences_of( $event_id );

		// In this context, a CiviCRM Event can only have an EO Event with a
		// single Occurrence associated with it, so use first item.
		$keys = array_keys( $occurrences );
		$occurrence_id = array_shift( $keys );

		// Store correspondences.
		$this->plugin->db->store_event_correspondences( $event_id, [ $occurrence_id => $objectId ] );

	}



	/**
	 * Delete an EO Event when a CiviCRM Event is deleted.
	 *
	 * @since 0.1
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
		$post_id = $this->plugin->db->get_eo_event_id_by_civi_event_id( $objectId );
		$occurrence_id = $this->plugin->db->get_eo_occurrence_id_by_civi_event_id( $objectId );
		$this->plugin->db->clear_event_correspondence( $post_id, $occurrence_id, $objectId );

		// Set the EO Event to 'draft' status if it's not a recurring Event.
		if ( ! eo_recurs( $post_id ) ) {
			$this->plugin->eo->update_event_status( $post_id, 'draft' );
		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Prepare a CiviCRM Event with data from an EO Event.
	 *
	 * @since 0.1
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
		$civi_event['is_public'] = 1;
		$civi_event['participant_listing_id'] = null;

		// If the Event is in draft mode, set as 'inactive'.
		if ( $post->post_status == 'draft' ) {
			$civi_event['is_active'] = 0;
		} else {
			$civi_event['is_active'] = 1;
		}

		// Get Venue for this Event.
		$venue_id = eo_get_venue( $post->ID );

		// Get CiviCRM Event Location.
		$location_id = $this->plugin->eo_venue->get_civi_location( $venue_id );

		// Did we get one?
		if ( is_numeric( $location_id ) ) {

			// Add to our params.
			$civi_event['loc_block_id'] = $location_id;

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

		// Participant_role default.
		$civi_event['default_role_id'] = 0;

		// Get existing role ID.
		$existing_id = $this->get_participant_role( $post );

		// Add existing role ID to our params if we get one.
		if ( $existing_id !== false && is_numeric( $existing_id ) && $existing_id != 0 ) {
			$civi_event['default_role_id'] = $existing_id;
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
		$is_confirm_enabled = $this->get_registration_confirm_enabled( $post->ID );

		// Set confirmation screen value to our params if we get one.
		if ( $is_confirm_enabled == 0 ) {
			$civi_event['is_confirm_enabled'] = 0;
		}

		/**
		 * Filter prepared CiviCRM Event.
		 *
		 * @since 0.3.1
		 *
		 * @param array $civi_event The array of data for the CiviCRM Event.
		 * @param object $post The WP Post object.
		 * @return array $civi_event The modified array of data for the CiviCRM Event.
		 */
		return apply_filters( 'civicrm_event_organiser_prepared_civi_event', $civi_event, $post );

	}



	/**
	 * Create CiviCRM Events for an EO Event.
	 *
	 * @since 0.1
	 *
	 * @param object $post The WP Post object.
	 * @param array $dates Array of properly formatted dates.
	 * @return array|bool $correspondences Array of correspondences, keyed by Occurrence ID.
	 */
	public function create_civi_events( $post, $dates ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

				// Log error.
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
			$this->enable_registration( array_pop( $result['values'] ), $post );

			// Add the new CiviCRM Event ID to array, keyed by Occurrence ID.
			$correspondences[ $date['occurrence_id'] ] = $result['id'];

		} // End dates loop.

		// Store these in post meta.
		$this->plugin->db->store_event_correspondences( $post->ID, $correspondences );

		// --<
		return $correspondences;

	}



	/**
	 * Update CiviCRM Events for an Event.
	 *
	 * @since 0.1
	 *
	 * @param object $post The WP Post object.
	 * @param array $dates Array of properly formatted dates.
	 * @return array $correspondences Array of correspondences, keyed by Occurrence ID.
	 */
	public function update_civi_events( $post, $dates ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Just for safety, check we get some (though we must).
		if ( count( $dates ) === 0 ) {
			return false;
		}

		// Get existing CiviCRM Events from post meta.
		$correspondences = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post->ID );

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
		 * Event sequences can only be generated from EO, so any CiviCRM Events that
		 * are part of a sequence must have been generated automatically.
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
		 * the EO Events.
		 *
		 * Next, we match by date and time. Any CiviCRM Events that match have their
		 * info updated since we assume their correspondence remains unaltered.
		 *
		 * Any additions to the EO Event are treated as new CiviCRM Events and are
		 * added to CiviCRM. Any removals are treated as if the Event has been
		 * cancelled and the CiviCRM Event is set to 'disabled' rather than deleted.
		 * This is to preserve any data that may have been collected for the
		 * removed Event.
		 *
		 * The bottom line is: make sure your sequences are right before hitting
		 * the Publish button and be wary of making further changes.
		 *
		 * Things get a bit more complicated when a sequence is split, but it's
		 * not too bad. This functionality will eventually be handled by the EO
		 * 'occurrence' hooks when I get round to it.
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

					// Log error.
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
				$this->enable_registration( array_pop( $result['values'] ), $post );

				// Add the CiviCRM Event ID to array, keyed by Occurrence ID.
				$new_correspondences[ $date['occurrence_id'] ] = $result['id'];

			}

			// Overwrite those stored in post meta.
			$this->plugin->db->store_event_correspondences( $post->ID, $new_correspondences );

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

			// Continue if not found.
			if ( $full_civi_event === false ) {
				continue;
			}

			// Add CiviCRM Event to array.
			$civi_events[] = $full_civi_event;

		}

		// Init orphaned CiviCRM Event data.
		$orphaned_civi_events = [];

		// Get orphaned CiviCRM Events for this EO Event.
		$orphaned = $this->plugin->db->get_orphaned_events_by_eo_event_id( $post->ID );

		// Did we get any?
		if ( count( $orphaned ) > 0 ) {

			// Get CiviCRM Events by ID.
			foreach ( $orphaned as $civi_event_id ) {

				// Get full CiviCRM Event.
				$orphaned_civi_event = $this->get_event_by_id( $civi_event_id );

				// Continue if not found.
				if ( $orphaned_civi_event === false ) {
					continue;
				}

				// Add CiviCRM Event to array.
				$orphaned_civi_events[] = $orphaned_civi_event;

			}

		}

		// Get matches between EO Events and CiviCRM Events.
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

					// Log error.
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
				$this->enable_registration( array_pop( $result['values'] ), $post );

				// Add to new correspondence array.
				$new_correspondences[ $occurrence_id ] = $civi_id;

			}

		} // End check for empty array.

		// Extract unmatched EO Events array.
		$unmatched_eo = $matches['unmatched_eo'];

		// Do we have any unmatched EO Occurrences?
		if ( count( $unmatched_eo ) > 0 ) {

			// Now loop through unmatched EO dates and create CiviCRM Events.
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

					// Log failures and skip to next.
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
				$this->enable_registration( array_pop( $result['values'] ), $post );

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
		$this->plugin->db->store_event_correspondences( $post->ID, $new_correspondences, $orphans );

	}



	/**
	 * Match EO Events and CiviCRM Events.
	 *
	 * @since 0.1
	 *
	 * @param array $dates An array of EO Event Occurrence data.
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

		// Match EO dates to CiviCRM Events.
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

			// Match EO dates to orphaned CiviCRM Events.
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

		// Init EO unmatched.
		$unmatched_eo = [];

		// Find unmatched EO dates.
		foreach ( $dates as $key => $date ) {

			// If the matched array has no entry.
			if ( ! isset( $matched[ $date['occurrence_id'] ] ) ) {

				// Add to unmatched.
				$unmatched_eo[] = $date;

			}

		}

		// Init CiviCRM unmatched.
		$unmatched_civi = [];

		// Find unmatched EO dates.
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
	 *
	 * @return array $events The CiviCRM Events data.
	 */
	public function get_all_civi_events() {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

			// Log error.
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $events['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			// --<
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
	 *
	 * @param array $civi_event_ids An array of CiviCRM Event IDs.
	 * @return array $results An array of CiviCRM results.
	 */
	public function delete_civi_events( $civi_event_ids ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

				// Log failures and skip to next.
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
	 *
	 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
	 * @return array|bool $result The CiviCRM API result array or false otherwise.
	 */
	public function disable_civi_event( $civi_event_id ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
			return false;
		}

		/**
		 * Allow plugins to skip disabling CiviCRM Events.
		 *
		 * @since 0.6.6
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

			// Log error.
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $result['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			// --<
			return false;

		}

		// --<
		return $result;

	}



	/**
	 * Get a CiviCRM Event by ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
	 * @return array|bool $event The CiviCRM Event Location data, or false if not found.
	 */
	public function get_event_by_id( $civi_event_id ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Construct Locations array.
		$params = [
			'version' => 3,
			'id' => $civi_event_id,
		];

		// Call API.
		$event = civicrm_api( 'Event', 'getsingle', $params );

		// Log failures and return boolean false.
		if ( isset( $event['is_error'] ) && $event['is_error'] == '1' ) {

			// Log error.
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $event['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			// --<
			return false;

		}

		// --<
		return $event;

	}



	/**
	 * Get a CiviCRM Event's "Info & Settings" link.
	 *
	 * @since 0.3.6
	 *
	 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
	 * @return string $link The URL of the CiviCRM "Info & Settings" page.
	 */
	public function get_settings_link( $civi_event_id ) {

		// Init link.
		$link = '';

		// Init CiviCRM or bail.
		if ( ! $this->is_active() ) {
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

		// Check loc_block_id.
		$result = $this->_validate_loc_block_id();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

	}



	/**
	 * Updates a CiviCRM Event Location given an EO Venue.
	 *
	 * @since 0.1
	 *
	 * @param array $venue The EO Venue data.
	 * @return array|bool $location The CiviCRM Event Location data, or false on failure.
	 */
	public function update_location( $venue ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Get existing Location.
		$location = $this->get_location( $venue );

		// If this Venue already has a CiviCRM Event Location.
		if ( $location !== false ) {

			// Is there a record on the EO side?
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
	 * Delete a CiviCRM Event Location given an EO Venue.
	 *
	 * @since 0.1
	 *
	 * @param array $venue The EO Venue data.
	 * @return array $result CiviCRM API result data.
	 */
	public function delete_location( $venue ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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
	 *
	 * @param int $location_id The numeric ID of the CiviCRM Location.
	 * @return array $result CiviCRM API result data.
	 */
	public function delete_location_by_id( $location_id ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

			// --<
			return false;

		}

		// --<
		return $result;

	}



	/**
	 * Gets a CiviCRM Event Location given an EO Venue.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The EO Venue data.
	 * @return bool|array $location The CiviCRM Event Location data, or false if not found.
	 */
	public function get_location( $venue ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

			// --<
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
	 *
	 * @return array $locations The array of CiviCRM Event Location data.
	 */
	public function get_all_locations() {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

			// --<
			return false;

		}

		// --<
		return $locations;

	}



	/**
	 * WARNING: deletes all CiviCRM Event Locations.
	 *
	 * @since 0.1
	 */
	public function delete_all_locations() {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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
	 *
	 * @param int $loc_id The CiviCRM Event Location ID.
	 * @return array $location The CiviCRM Event Location data.
	 */
	public function get_location_by_id( $loc_id ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

			// --<
			return false;

		}

		// Get Location from nested array.
		$location = array_shift( $result['values'] );

		// --<
		return $location;

	}



	/**
	 * Creates (or updates) a CiviCRM Event Location given an EO Venue.
	 *
	 * The only disadvantage to this method is that, for example, if we update
	 * the email and that email already exists in the DB, it will not be found
	 * and associated - but rather the existing email will be updated. Same goes
	 * for phone. This is not a deal-breaker, but not very DRY either.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The EO Venue object.
	 * @param array $location The existing CiviCRM Location data.
	 * @return array $location The CiviCRM Location data.
	 */
	public function create_civi_loc_block( $venue, $location ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

		// Did we do okay?
		if ( isset( $location['is_error'] ) && $location['is_error'] == '1' ) {

			// Log failed Location.
			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $location['error_message'],
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			// --<
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
	 * Get the existing Participant Role for a Post, but fall back to the default
	 * as set on the admin screen. Fall back to false otherwise.
	 *
	 * @since 0.1
	 *
	 * @param object $post An EO Event object.
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
	 *
	 * @param object $post An EO Event object.
	 * @return array|bool $participant_roles Array of CiviCRM role data, or false if none exist.
	 */
	public function get_participant_roles( $post = null ) {

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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
	 *
	 * @param object $post An EO Event object.
	 * @return str $html Markup to display in the form.
	 */
	public function get_participant_roles_select( $post = null ) {

		// Init html.
		$html = '';

		// Init CiviCRM or die.
		if ( ! $this->is_active() ) {
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

		// Get CiviCRM Events for this EO Event.
		$civi_events = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post->ID );

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
			if ( ! $this->is_active() ) {
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

			// Test for errors.
			if ( isset( $result['is_error'] ) && $result['is_error'] == '1' ) {

				// Log error.
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
	 * Get the default Registration form profile for an EO Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.2.4
	 *
	 * @param object $post An EO Event object.
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
	 *
	 * @return array|bool $result CiviCRM API return array, or false on failure.
	 */
	public function get_registration_profiles() {

		// Bail if we fail to init CiviCRM.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Define params.
		$params = [
			'version' => 3,
		];

		// Get them via API.
		$result = civicrm_api( 'UFGroup', 'get', $params );

		// Error check.
		if ( isset( $result['is_error'] ) && $result['is_error'] == '1' ) {

			$e = new Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $result['error_message'],
				'result' => $result,
				'params' => $params,
				'backtrace' => $trace,
			], true ) );

			// --<
			return false;

		}

		// --<
		return $result;

	}



	/**
	 * Get all CiviCRM Event Registration form profiles formatted as a dropdown list.
	 *
	 * @since 0.2.4
	 *
	 * @param object $post An EO Event object.
	 * @return str $html Markup containing select options.
	 */
	public function get_registration_profiles_select( $post = null ) {

		// Init return.
		$html = '';

		// Init CiviCRM or bail.
		if ( ! $this->is_active() ) {
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
	 * Get the default Registration form confirmation page setting for an EO Event.
	 *
	 * Falls back to the default as set on the plugin settings screen.
	 * Falls back to false otherwise.
	 *
	 * @since 0.6.4
	 *
	 * @param int $post_id The numeric ID of an EO Event.
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



	// -------------------------------------------------------------------------



	/**
	 * Query email via API and update if necessary.
	 *
	 * @since 0.1
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
	 * the case for EO Venues, whose objects always contain all properties,
	 * whether they have a value or not.
	 *
	 * @since 0.1
	 *
	 * @param object $venue The EO Venue object being updated.
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
