<?php
/**
 * CiviCRM Event Class.
 *
 * Handles interactions with CiviCRM Events.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Event Class.
 *
 * A class that encapsulates interactions with CiviCRM.
 *
 * @since 0.7
 */
class CEO_CiviCRM_Event {

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
	 * Location object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CEO_CiviCRM_Location
	 */
	public $location;

	/**
	 * Registration object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CEO_CiviCRM_Registration
	 */
	public $registration;

	/**
	 * Bridging property for CiviCRM Event ID.
	 *
	 * @since 0.7
	 * @access private
	 * @var integer
	 */
	private $civicrm_event_created_id;

	/**
	 * Bridging property for Event Organiser Event ID.
	 *
	 * @since 0.7
	 * @access private
	 * @var integer
	 */
	private $eo_event_created_id;

	/**
	 * Constructor.
	 *
	 * @since 0.7
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin  = $parent->plugin;
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
		$this->location     = $this->civicrm->location;
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

		// Add action to inject "Sync to WordPress" elements into the Event form.
		add_action( 'civicrm_buildForm', [ $this, 'form_event_sync_page' ], 10, 2 );
		add_action( 'civicrm_buildForm', [ $this, 'form_event_sync_snippet' ], 10, 2 );

		// Add actions to inject "Feature Image" elements into the Event Form.
		add_action( 'civicrm_buildForm', [ $this, 'form_event_scripts_enqueue' ], 20, 2 );
		add_action( 'civicrm_buildForm', [ $this, 'form_event_new_markup' ], 20, 2 );
		add_action( 'civicrm_buildForm', [ $this, 'form_event_edit_markup' ], 20, 2 );
		add_action( 'wp_ajax_ceo_feature_image', [ $this, 'form_event_image_ajax' ] );
		// Filter Attachments to show only those for a User.
		add_filter( 'ajax_query_attachments_args', [ $this, 'form_event_image_filter_media' ] );
		// Apply "Feature Image" after CiviCRM Event form submission process.
		add_action( 'civicrm_postProcess', [ $this, 'form_event_image_process' ], 10, 2 );

		// Intercept CiviCRM Event create/update/delete actions.
		add_action( 'civicrm_post', [ $this, 'event_created' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'event_updated' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'event_deleted' ], 10, 4 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Check if the CiviCRM Form is a "page".
	 *
	 * @since 0.8.2
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 * @return bool True if the CiviCRM Form is a "page" or false if not.
	 */
	public function form_is_page( $form_name, $form ) {

		// Check "Print" var in the Form controller.
		$controller = $form->getVar( 'controller' );
		if ( ! empty( $controller->_print ) ) {
			return false;
		}

		// It is a "page".
		return true;

	}

	/**
	 * Check if the CiviCRM Form is an AJAX-loaded "snippet".
	 *
	 * @since 0.8.2
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 * @return bool True if the CiviCRM Form is a "snippet" or false if not.
	 */
	public function form_is_snippet( $form_name, $form ) {

		// Check "Print" var in the Form controller.
		$controller = $form->getVar( 'controller' );
		if ( empty( $controller->_print ) || 'json' !== $controller->_print ) {
			return false;
		}

		// It is a "snippet".
		return true;

	}

	/**
	 * Safely gets the ID the CiviCRM Event.
	 *
	 * @since 0.8.2
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 * @return int|bool $event_id The CiviCRM Event ID, or false on failure.
	 */
	public function form_event_get_id( $form_name, $form ) {

		// Init return.
		$event_id = false;

		// The "Manage Events" screen cannot have an ID.
		if ( 'CRM_Event_Form_SearchEvent' === $form_name ) {
			return $event_id;
		}

		// The "Add Event" screen cannot have an ID.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$path = implode( '/', $form->urlPath );
		if ( false !== strstr( $path, 'civicrm/event/add' ) ) {
			return $event_id;
		}

		// Okay, now get the ID from the Form.
		$event_id = $form->getVar( '_id' );

		// Cast as integer when we have an Event ID.
		if ( ! empty( $event_id ) && is_numeric( $event_id ) ) {
			$event_id = (int) $event_id;
		}

		// --<
		return $event_id;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Add Javascript on page load.
	 *
	 * There are three screens on which we want our Javascript:
	 *
	 * 1. The "Manage Events" screen.
	 * 2. The "Add Event" screen.
	 * 3. The "Configure Event" screen.
	 *
	 * (1) and (3) share the path "civicrm/event/manage" while (2) can be loaded either
	 * as a standalone screen on in a popup dialog.
	 *
	 * The "Configure Event" form is loaded twice: firstly when the page is loaded and
	 * secondly as a "snippet" that is AJAX-loaded into the tab container. The WordPress
	 * Media scripts need to be loaded on page load, while the template needs to be
	 * loaded into the snippet.
	 *
	 * We can't use the form "name" (which is actually the name of the class that is
	 * responsible for the form) because it may not conform to the naming convention
	 * generally used for the "Configure Event" screen - i.e. prefixed with
	 * `CRM_Event_Form_ManageEvent_`.
	 *
	 * The "Tell a Friend" class, for example, is called `CRM_Friend_Form_Event`.
	 *
	 * What is consistent, it seems, is the "URL path" of the form object so let's
	 * test the first three parts of that.
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_scripts_enqueue( $form_name, &$form ) {

		// Get the Form URL path.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$path = implode( '/', $form->urlPath );

		// Define the valid Form URL paths.
		$is_manage = strstr( $path, 'civicrm/event/manage' );
		$is_add    = strstr( $path, 'civicrm/event/add' );

		// Bail if this is not a URL path we're after.
		if ( false === $is_manage && false === $is_add ) {
			return;
		}

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// Bail if not a Form "page".
		if ( ! $this->form_is_page( $form_name, $form ) ) {
			return;
		}

		// Enqueue script.
		$this->form_event_image_script();

	}

	/**
	 * Injects "Feature Image" template into "New Event" screen.
	 *
	 * @since 0.7.3
	 * @since 0.8.2 Renamed
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_new_markup( $form_name, &$form ) {

		// Bail if this is not the URL path we're after.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$path = implode( '/', $form->urlPath );

		if ( false === strstr( $path, 'civicrm/event/add' ) ) {
			return;
		}

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// Add hidden field to hold the Attachment ID.
		$form->add( 'hidden', 'ceo_attachment_id', '0' );

		// Build the placeholder image markup.
		$placeholder_url = CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/images/placeholder.gif';
		$img_width       = get_option( 'medium_size_w', 300 );
		$img_style       = 'display: none; width: ' . $img_width . 'px; height: 100px;';
		$markup          = '<img src="' . $placeholder_url . '" class="wp-post-image" style="' . $img_style . '">';

		// Add image markup.
		$form->assign( 'ceo_attachment_markup', $markup );

		// Add help text.
		$form->assign(
			'ceo_attachment_id_help',
			__( 'If you would like to add a Feature Image to the Event, do so here.', 'civicrm-event-organiser' )
		);

		// Add template block into the page.
		$this->form_event_image_template( $form );

	}

	/**
	 * Injects "Feature Image" template into "Configure Event - Info and Settings" screen.
	 *
	 * @since 0.6.3
	 * @since 0.8.2 Renamed
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_edit_markup( $form_name, &$form ) {

		// Is this the Event Info form?
		if ( 'CRM_Event_Form_ManageEvent_EventInfo' !== $form_name ) {
			return;
		}

		// Bail if not a Form "snippet".
		if ( ! $this->form_is_snippet( $form_name, $form ) ) {
			return;
		}

		// Bail if this is an "Add Event" snippet.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$path = implode( '/', $form->urlPath );
		if ( false !== strstr( $path, 'civicrm/event/add' ) ) {
			return;
		}

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// We want the CiviCRM Event ID.
		$event_id = $this->form_event_get_id( $form_name, $form );

		// Get the Post ID that this Event is mapped to.
		$post_id = false;
		if ( ! empty( $event_id ) ) {
			$post_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $event_id );
		}

		// Sanity check.
		if ( false === $post_id ) {
			$post_id = 0;
		}

		// Does this Event have a Feature Image?
		$attachment_id = '';
		if ( ! empty( $post_id ) && has_post_thumbnail( $post_id ) ) {
			$attachment_id = get_post_thumbnail_id( $post_id );
		}

		// Add hidden field to hold the Attachment ID.
		$form->add( 'hidden', 'ceo_attachment_id', $attachment_id );

		// Get the image markup.
		$placeholder_url = CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/images/placeholder.gif';
		$markup          = '<img src="' . $placeholder_url . '" class="wp-post-image" style="display: none;">';
		if ( ! empty( $attachment_id ) ) {
			$markup = get_the_post_thumbnail( $post_id, 'medium' );
		}

		// Add image markup.
		$form->assign( 'ceo_attachment_markup', $markup );

		// Add help text.
		if ( empty( $attachment_id ) ) {
			$form->assign(
				'ceo_attachment_id_help',
				__( 'If you would like to add a Feature Image to the Event Organiser Event, choose one here.', 'civicrm-event-organiser' )
			);
		} else {
			$form->assign(
				'ceo_attachment_id_help',
				__( 'If you would like to change the Feature Image for the Event Organiser Event, choose one here.', 'civicrm-event-organiser' )
			);
		}

		// Add template block into the page.
		$this->form_event_image_template( $form );

	}

	/**
	 * Adds the Feature Image script.
	 *
	 * @since 0.8.2
	 *
	 * @param object $form The CiviCRM form object.
	 */
	private function form_event_image_template( &$form ) {

		// Add button text.
		$form->assign(
			'ceo_attachment_id_button',
			__( 'Choose Feature Image', 'civicrm-event-organiser' )
		);

		// Insert template block into the page.
		CRM_Core_Region::instance( 'page-body' )->add( [ 'template' => 'ceo-featured-image.tpl' ] );

	}

	/**
	 * Adds the Feature Image javascript.
	 *
	 * @since 0.7.3
	 */
	private function form_event_image_script() {

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
			'title'  => __( 'Choose Feature Image', 'civicrm-event-organiser' ),
			'button' => __( 'Set Feature Image', 'civicrm-event-organiser' ),
		];

		// Init settings.
		$settings = [
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'loading'    => CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/images/loading.gif',
			'ajax_nonce' => wp_create_nonce( 'ceo_nonce_action_feature_image' ),
		];

		// Localisation array.
		$vars = [
			'localisation' => $localisation,
			'settings'     => $settings,
		];

		// Localise the WordPress way.
		wp_localize_script(
			'ceo-feature-image',
			'CEO_Feature_Image_Settings',
			$vars
		);

	}

	/**
	 * AJAX handler for Feature Image calls.
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 * @since 0.7.3 Renamed.
	 */
	public function form_event_image_ajax() {

		// Init response.
		$data = [
			'success' => 'false',
		];

		// Since this is an AJAX request, check security.
		$result = check_ajax_referer( 'ceo_nonce_action_feature_image', false, false );
		if ( false === $result ) {
			wp_send_json( $data );
		}

		// Disallow users without upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json( $data );
		}

		// Get Attachment ID.
		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		if ( ! is_numeric( $attachment_id ) || 0 === $attachment_id ) {
			wp_send_json( $data );
		}

		// Filter the image class.
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'form_event_image_filter' ] );

		// Get the Attachment Image markup.
		$markup = wp_get_attachment_image( $attachment_id, 'medium', false );

		// Remove filter.
		remove_filter( 'wp_get_attachment_image_attributes', [ $this, 'form_event_image_filter' ] );

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
		 * @deprecated 0.8.0 Use the {@see 'ceo/civicrm/event/media/view/cap'} filter instead.
		 *
		 * @param str The default capability needed to view all media.
		 */
		$capability = apply_filters_deprecated( 'civicrm_event_organiser_filter_media', [ 'edit_posts' ], '0.8.0', 'ceo/civicrm/event/media/view/cap' );

		/**
		 * Filter the capability that is needed to view all media.
		 *
		 * @since 0.8.0
		 *
		 * @param str The default capability needed to view all media.
		 */
		$capability = apply_filters( 'ceo/civicrm/event/media/view/cap', $capability );

		// Admins and Editors get to see everything.
		if ( ! current_user_can( $capability ) ) {
			$query['author'] = get_current_user_id();
		}

		// --<
		return $query;

	}

	/**
	 * Callback for the CiviEvent "Info and Settings" form's postProcess hook.
	 *
	 * This is called *after* the Event has been synced to Event Organiser, so
	 * we can apply the Attachment to the Event Organiser Event knowing that it
	 * exists. Only needs to be done for *new* Events.
	 *
	 * @since 0.6.3
	 * @since 0.7 Moved to this class.
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_image_process( $form_name, &$form ) {

		// Is this the Event Info form?
		if ( 'CRM_Event_Form_ManageEvent_EventInfo' !== $form_name ) {
			return;
		}

		// This gets called *three* times!
		static $done;
		if ( isset( $done ) && true === $done ) {
			return;
		}

		// Grab submitted values.
		$values = $form->getSubmitValues();

		// Bail if the Event is a template.
		if ( ! empty( $values['is_template'] ) ) {
			return;
		}

		// Bail if there's no Feature Image ID.
		// TODO: Provide means to remove the Feature Image.
		if ( empty( $values['ceo_attachment_id'] ) || ! is_numeric( $values['ceo_attachment_id'] ) ) {
			return;
		}

		// Cast as integer.
		$attachment_id = (int) $values['ceo_attachment_id'];

		// Find the ID of the Event Organiser Event.
		$eo_event_id = false;
		if ( ! empty( $this->eo_event_created_id ) ) {
			$eo_event_id = $this->eo_event_created_id;
		} else {
			$civicrm_event_id = $form->getVar( '_id' );
			if ( ! empty( $civicrm_event_id ) ) {
				$mapped_event_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( (int) $civicrm_event_id );
				if ( ! empty( $mapped_event_id ) ) {
					$eo_event_id = $mapped_event_id;
				}
			}
		}

		// Set the Feature Image for the Event Organiser Event.
		if ( ! empty( $eo_event_id ) ) {
			set_post_thumbnail( $eo_event_id, $attachment_id );
		}

		// We're done.
		$done = true;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Add sync template into Form for Events.
	 *
	 * @since 0.7.3
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_sync_page( $form_name, &$form ) {

		// Is this the Event Info form?
		if ( 'CRM_Event_Form_ManageEvent_EventInfo' !== $form_name ) {
			return;
		}

		// Add checkbox depending on CiviCRM Event sync setting.
		$civicrm_event_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_civicrm_event_sync', 0 );
		if ( 0 === $civicrm_event_sync ) {
			return;
		}

		// Bail if not a Form "page".
		if ( ! $this->form_is_page( $form_name, $form ) ) {
			return;
		}

		// Disallow users without permission to create events.
		if ( ! current_user_can( 'edit_events' ) ) {
			return;
		}

		// Inject template.
		$this->form_event_sync_template( $form_name, $form );

	}

	/**
	 * Add sync template into Form for Events.
	 *
	 * @since 0.7.3
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_event_sync_snippet( $form_name, &$form ) {

		// Is this the Event Info form?
		if ( 'CRM_Event_Form_ManageEvent_EventInfo' !== $form_name ) {
			return;
		}

		// Add checkbox depending on CiviCRM Event sync setting.
		$civicrm_event_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_civicrm_event_sync', 0 );
		if ( 0 === $civicrm_event_sync ) {
			return;
		}

		// Bail if not a Form "snippet".
		if ( ! $this->form_is_snippet( $form_name, $form ) ) {
			return;
		}

		// Disallow users without permission to create events.
		if ( ! current_user_can( 'edit_events' ) ) {
			return;
		}

		// Inject template.
		$this->form_event_sync_template( $form_name, $form );

	}

	/**
	 * Insert template block into the page.
	 *
	 * @since 0.7.3
	 *
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	private function form_event_sync_template( $form_name, &$form ) {

		// We want the CiviCRM Event ID.
		$event_id = $this->form_event_get_id( $form_name, $form );

		// Only check for skip when we have an Event ID.
		if ( ! empty( $event_id ) ) {

			// No need for checkbox when there is already an Event Organiser Event.
			$eo_event_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $event_id );
			if ( ! empty( $eo_event_id ) ) {
				return;
			}

		}

		// Add our checkbox.
		$label = '<strong>' . __( 'Sync this Event to WordPress now', 'civicrm-event-organiser' ) . '</strong>';
		$form->add( 'checkbox', 'ceo_event_sync_checkbox', $label );

		// Insert template block into the page.
		CRM_Core_Region::instance( 'page-body' )->add( [ 'template' => 'ceo-event-sync.tpl' ] );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Create an Event Organiser Event when a CiviCRM Event is created.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param string  $op The type of database operation.
	 * @param string  $object_name The type of object.
	 * @param integer $object_id The ID of the object.
	 * @param object  $object_ref The object.
	 */
	public function event_created( $op, $object_name, $object_id, $object_ref ) {

		// Target our operation.
		if ( 'create' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'Event' !== $object_name ) {
			return;
		}

		// Kick out if not Event object.
		if ( ! ( $object_ref instanceof CRM_Event_DAO_Event ) ) {
			return;
		}

		// Bail if the Event is a template.
		if ( isset( $object_ref->is_template ) && ! empty( $this->civicrm->denullify( $object_ref->is_template ) ) ) {
			return;
		}

		// Query checkbox depending on CiviCRM Event sync setting.
		$civicrm_event_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_civicrm_event_sync', 0 );
		if ( 1 === $civicrm_event_sync ) {

			// CiviCRM handles verification.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sync = isset( $_POST['ceo_event_sync_checkbox'] ) ? sanitize_text_field( wp_unslash( $_POST['ceo_event_sync_checkbox'] ) ) : 0;

			// Bail if our sync checkbox is not checked.
			if ( '1' !== (string) $sync ) {
				return;
			}

		}

		// Update a single Event Organiser Event - or create if it doesn't exist.
		$event_id = $this->plugin->wordpress->eo->update_event( (array) $object_ref );

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
		$keys          = array_keys( $occurrences );
		$occurrence_id = array_shift( $keys );

		// Store correspondences.
		$this->plugin->mapping->store_event_correspondences( $event_id, [ $occurrence_id => $object_ref->id ] );

		// Store Event IDs for possible use in the "form_event_image_process" method.
		$this->civicrm_event_created_id = $object_ref->id;
		$this->eo_event_created_id      = $event_id;

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
	 * @param string  $op The type of database operation.
	 * @param string  $object_name The type of object.
	 * @param integer $object_id The ID of the object.
	 * @param object  $object_ref The object.
	 */
	public function event_updated( $op, $object_name, $object_id, $object_ref ) {

		// Target our operation.
		if ( 'edit' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'Event' !== $object_name ) {
			return;
		}

		// Kick out if not Event object.
		if ( ! ( $object_ref instanceof CRM_Event_DAO_Event ) ) {
			return;
		}

		// Bail if the Event is a template.
		if ( isset( $object_ref->is_template ) && ! empty( $this->civicrm->denullify( $object_ref->is_template ) ) ) {
			return;
		}

		// Query checkbox depending on CiviCRM Event sync setting.
		$civicrm_event_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_civicrm_event_sync', 0 );
		if ( 1 === $civicrm_event_sync ) {

			// CiviCRM handles verification.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sync = isset( $_POST['ceo_event_sync_checkbox'] ) ? sanitize_text_field( wp_unslash( $_POST['ceo_event_sync_checkbox'] ) ) : 0;

			// If our sync checkbox is not checked or not present.
			if ( '1' !== (string) $sync ) {

				// Bail if there is no Event Organiser Event.
				$eo_event_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $object_id );
				if ( empty( $eo_event_id ) ) {
					return;
				}

			}

		}

		// Bail if this CiviCRM Event is part of an Event Organiser sequence.
		if ( $this->plugin->mapping->is_civi_event_in_eo_sequence( $object_id ) ) {
			return;
		}

		// Get full Event data.
		$updated_event = $this->get_event_by_id( $object_id );
		if ( false === $updated_event ) {
			return;
		}

		// Update the Event Organiser Event.
		$event_id = $this->plugin->wordpress->eo->update_event( $updated_event );

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
		$keys          = array_keys( $occurrences );
		$occurrence_id = array_shift( $keys );

		// Store correspondences.
		$this->plugin->mapping->store_event_correspondences( $event_id, [ $occurrence_id => $object_id ] );

	}

	/**
	 * Delete an Event Organiser Event when a CiviCRM Event is deleted.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param string  $op The type of database operation.
	 * @param string  $object_name The type of object.
	 * @param integer $object_id The ID of the object.
	 * @param object  $object_ref The object.
	 */
	public function event_deleted( $op, $object_name, $object_id, $object_ref ) {

		// Target our operation.
		if ( 'delete' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'Event' !== $object_name ) {
			return;
		}

		// Kick out if not Event object.
		if ( ! ( $object_ref instanceof CRM_Event_DAO_Event ) ) {
			return;
		}

		// Clear the correspondence between the Occurrence and the CiviCRM Event.
		$post_id       = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $object_id );
		$occurrence_id = $this->plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $object_id );
		$this->plugin->mapping->clear_event_correspondence( $post_id, $occurrence_id, $object_id );

		// Set the Event Organiser Event to 'draft' status if it's not a recurring Event.
		if ( ! eo_recurs( $post_id ) ) {
			$this->plugin->wordpress->eo->update_event_status( $post_id, 'draft' );
		}

	}

	// -----------------------------------------------------------------------------------

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
		$civi_event['title']                  = $post->post_title;
		$civi_event['description']            = wpautop( $post->post_content );
		$civi_event['summary']                = wp_strip_all_tags( $post->post_excerpt );
		$civi_event['created_date']           = $post->post_date;
		$civi_event['participant_listing_id'] = null;

		// Get Status Sync setting.
		$status_sync = (int) $this->plugin->admin->option_get( 'civi_eo_event_default_status_sync', 3 );

		/*
		 * For "Do not sync" (3) and "Sync CiviCRM -> EO" (2), we can leave out
		 * the params and they will remain unchanged when updating a CiviCRM Event.
		 *
		 * When creating an Event, let's see...
		 */
		if ( 0 === $status_sync || 1 === $status_sync ) {

			// Default both "status" settings to "false".
			$civi_event['is_public'] = 0;
			$civi_event['is_active'] = 0;

			// If the Event is in "draft" or "pending" mode, set as 'not active' and 'not public'.
			if ( 'draft' === $post->post_status || 'pending' === $post->post_status ) {
				$civi_event['is_active'] = 0;
				$civi_event['is_public'] = 0;
			}

			// If the Event is in "publish" or "future" mode, set as 'active' and 'public'.
			if ( 'publish' === $post->post_status || 'future' === $post->post_status ) {
				$civi_event['is_active'] = 1;
				$civi_event['is_public'] = 1;
			}

			// If the Event is in "private" mode, set as 'active' and 'not public'.
			if ( 'private' === $post->post_status ) {
				$civi_event['is_active'] = 1;
				$civi_event['is_public'] = 0;
			}

		}

		// Get Venue for this Event.
		$venue_id = eo_get_venue( $post->ID );

		// Get CiviCRM Event Location.
		$location_id = $this->plugin->wordpress->eo_venue->get_civi_location( $venue_id );

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
		$is_reg = $this->plugin->wordpress->eo->get_event_registration( $post->ID );

		// Add Online Registration value to our params if we get one.
		if ( is_numeric( $is_reg ) && 0 !== (int) $is_reg ) {
			$civi_event['is_online_registration'] = 1;
		}

		// Dedupe Rule default.
		$civi_event['dedupe_rule_group_id'] = '';

		// Add existing Dedupe Rule ID to our params if we have one.
		$existing_rule_id = $this->registration->get_registration_dedupe_rule( $post );
		if ( ! empty( $existing_rule_id ) && is_numeric( $existing_rule_id ) && $existing_rule_id > 0 ) {
			$civi_event['dedupe_rule_group_id'] = (int) $existing_rule_id;
		}

		// Participant Role default.
		$civi_event['default_role_id'] = 0;

		// Get existing Participant Role ID.
		$existing_id = $this->registration->get_participant_role( $post );

		// Add existing Participant Role ID to our params if we get one.
		if ( false !== $existing_id && is_numeric( $existing_id ) && 0 !== (int) $existing_id ) {
			$civi_event['default_role_id'] = (int) $existing_id;
		}

		// Get Event Type pseudo-ID (or value), because it is required in CiviCRM.
		$type_value = $this->plugin->wordpress->taxonomy->get_default_event_type_value( $post );

		// Die if there are no Event Types defined in CiviCRM.
		if ( false === $type_value ) {
			wp_die( esc_html__( 'You must have some CiviCRM Event Types defined', 'civicrm-event-organiser' ) );
		}

		// Assign Event Type value.
		$civi_event['event_type_id'] = $type_value;

		// CiviCRM Event Registration Confirmation screen enabled by default.
		$civi_event['is_confirm_enabled'] = 1;

		// Get CiviCRM Event Registration Confirmation screen value.
		$is_confirm_enabled = $this->registration->get_registration_confirm_enabled( $post->ID );

		// Set confirmation screen value to our params if we get one.
		if ( 0 === (int) $is_confirm_enabled ) {
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
			$civi_event['confirm_from_name']  = $this->registration->get_registration_send_email_from_name( $post->ID );
			$civi_event['confirm_from_email'] = $this->registration->get_registration_send_email_from( $post->ID );
			$civi_event['cc_confirm']         = $this->registration->get_registration_send_email_cc( $post->ID );
			$civi_event['bcc_confirm']        = $this->registration->get_registration_send_email_bcc( $post->ID );
		}

		/**
		 * Filter prepared CiviCRM Event.
		 *
		 * @since 0.3.1
		 * @since 0.7 Moved to this class.
		 * @deprecated 0.8.0 Use the {@see 'ceo/civicrm/event/prepared'} filter instead.
		 *
		 * @param array $civi_event The array of data for the CiviCRM Event.
		 * @param object $post The WP Post object.
		 */
		$civi_event = apply_filters_deprecated( 'civicrm_event_organiser_prepared_civi_event', [ $civi_event, $post ], '0.8.0', 'ceo/civicrm/event/prepared' );

		/**
		 * Filter the prepared CiviCRM Event.
		 *
		 * @since 0.8.0
		 *
		 * @param array $civi_event The array of data for the CiviCRM Event.
		 * @param object $post The WP Post object.
		 */
		return apply_filters( 'ceo/civicrm/event/prepared', $civi_event, $post );

	}

	/**
	 * Create CiviCRM Events for an Event Organiser Event.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param object $post The WP Post object.
	 * @param array  $dates Array of properly formatted dates.
	 * @return array|bool $correspondences Array of correspondences keyed by Occurrence ID, or false on failure.
	 */
	public function create_civi_events( $post, $dates ) {

		// Bail if CiviCRM is not active.
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
			$civi_event['end_date']   = $date['end'];

			// Use API to create Event.
			$result = civicrm_api( 'Event', 'create', $civi_event );

			// Log failures and skip to next.
			if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
				$e     = new Exception();
				$trace = $e->getTraceAsString();
				$log   = [
					'method'     => __METHOD__,
					'message'    => $result['error_message'],
					'civi_event' => $civi_event,
					'backtrace'  => $trace,
				];
				$this->plugin->log_error( $log );
				continue;
			}

			// Enable Registration if selected.
			$this->registration->enable_registration( array_pop( $result['values'] ), $post );

			// Add the new CiviCRM Event ID to array, keyed by Occurrence ID.
			$correspondences[ $date['occurrence_id'] ] = $result['id'];

		}

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
	 * @param array  $dates Array of properly formatted dates.
	 * @return array|bool $correspondences Array of correspondences keyed by Occurrence ID, or false on failure.
	 */
	public function update_civi_events( $post, $dates ) {

		// Bail if CiviCRM is not active.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Just for safety, check we get some (though we must).
		if ( count( $dates ) === 0 ) {
			return false;
		}

		// Get existing CiviCRM Events from post meta.
		$correspondences = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post->ID );

		// Create them and bail if we have none yet.
		if ( count( $correspondences ) === 0 ) {
			$correspondences = $this->create_civi_events( $post, $dates );
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
				$civi_event['end_date']   = $date['end'];

				// Use API to create Event.
				$result = civicrm_api( 'Event', 'create', $civi_event );

				// Log failures and skip to next.
				if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
					$e     = new Exception();
					$trace = $e->getTraceAsString();
					$log   = [
						'method'     => __METHOD__,
						'message'    => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace'  => $trace,
					];
					$this->plugin->log_error( $log );
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
			if ( false === $full_civi_event ) {
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
				if ( false === $orphaned_civi_event ) {
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
				if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
					$e     = new Exception();
					$trace = $e->getTraceAsString();
					$log   = [
						'method'     => __METHOD__,
						'message'    => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace'  => $trace,
					];
					$this->plugin->log_error( $log );
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
				$civi_event['end_date']   = $eo_date['end'];

				// Use API to create Event.
				$result = civicrm_api( 'Event', 'create', $civi_event );

				// Log failures and skip to next.
				if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
					$e     = new Exception();
					$trace = $e->getTraceAsString();
					$log   = [
						'method'     => __METHOD__,
						'message'    => $result['error_message'],
						'civi_event' => $civi_event,
						'backtrace'  => $trace,
					];
					$this->plugin->log_error( $log );
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

			// Get "delete unused" checkbox value. Nonce is checked in "intercept_save_event".
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$unused = isset( $_POST['civi_eo_event_delete_unused'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_delete_unused'] ) ) : 0;
			if ( '1' === (string) $unused ) {

				// Override - we ARE deleting.
				$unmatched_delete = true;

			}

			// Loop through unmatched CiviCRM Events.
			foreach ( $unmatched_civi as $civi_id ) {

				// If deleting.
				if ( $unmatched_delete ) {

					// Delete CiviCRM Event.
					$result = $this->delete_civi_events( [ $civi_id ] );

					/*
					// Delete this ID from the orphans array?
					$orphans = array_diff( $orphans, [ $civi_id ] );
					*/

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
			'matched'        => [],
			'unmatched_eo'   => [],
			'unmatched_civi' => [],
			'unorphaned'     => [],
		];

		// Init matched.
		$matched = [];

		// Match Event Organiser dates to CiviCRM Events.
		foreach ( $dates as $key => $date ) {

			// Run through CiviCRM Events.
			foreach ( $civi_events as $civi_event ) {

				// Does the start_date match?
				if ( $date['start'] === $civi_event['start_date'] ) {

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
					if ( $date['start'] === $orphaned_civi_event['start_date'] ) {

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

		// Find unmatched Event Organiser dates.
		$unmatched_eo = [];
		foreach ( $dates as $key => $date ) {
			if ( ! isset( $matched[ $date['occurrence_id'] ] ) ) {
				$unmatched_eo[] = $date;
			}
		}

		// Find unmatched CiviCRM dates.
		$unmatched_civi = [];
		foreach ( $civi_events as $civi_event ) {
			if ( ! in_array( $civi_event['id'], $matched ) ) {
				$unmatched_civi[] = $civi_event['id'];
			}
		}

		// Sort matched by key.
		ksort( $matched );

		// Construct return array.
		$event_data['matched']        = $matched;
		$event_data['unmatched_eo']   = $unmatched_eo;
		$event_data['unmatched_civi'] = $unmatched_civi;
		$event_data['unorphaned']     = $unorphaned;

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

		// Bail if CiviCRM is not active.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		// Construct Events array.
		$params = [
			'version'     => 3,
			'is_template' => 0,
			'options'     => [
				'limit' => 0, // Get all Events.
			],
		];

		// Call API.
		$events = civicrm_api( 'Event', 'get', $params );

		// Log failures and return boolean false.
		if ( ! empty( $events['is_error'] ) && 1 === (int) $events['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => $events['error_message'],
				'params'    => $params,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// --<
		return $events;

	}

	/**
	 * Deletes a set of CiviCRM Events.
	 *
	 * @since 0.1
	 * @since 0.7 Moved to this class.
	 *
	 * @param array $event_ids An array of CiviCRM Event IDs.
	 * @return array|bool $events The IDs of the deleted Events, or false on error.
	 */
	public function delete_civi_events( $event_ids ) {

		// Bail if there are no Events to delete.
		if ( empty( $event_ids ) | ! is_array( $event_ids ) ) {
			return false;
		}

		// Bail if CiviCRM is not active.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		try {

			// Call the API.
			$result = \Civi\Api4\Event::delete( false )
				->addWhere( 'id', 'IN', $event_ids )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'event_ids' => $event_ids,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Return empty array if no result.
		if ( 0 === $result->count() ) {
			return [];
		}

		// We only need the ArrayObject.
		$events = $result->getArrayCopy();

		// --<
		return $events;

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

		// Bail if CiviCRM is not active.
		if ( ! $this->civicrm->is_active() ) {
			return false;
		}

		/**
		 * Allow plugins to skip disabling CiviCRM Events.
		 *
		 * @since 0.6.6
		 * @since 0.7 Moved to this class.
		 * @deprecated 0.8.0 Use the {@see 'ceo/civicrm/event/disable/skip'} filter instead.
		 *
		 * @param bool False by default, pass true to skip.
		 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
		 */
		$skip = apply_filters_deprecated( 'ceo_skip_disable_civi_event', [ false, $civi_event_id ], '0.8.0', 'ceo/civicrm/event/disable/skip' );

		/**
		 * Allow plugins to skip disabling CiviCRM Events.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $skip False by default, return true to skip.
		 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
		 */
		$skip = apply_filters( 'ceo/civicrm/event/disable/skip', $skip, $civi_event_id );
		if ( true === $skip ) {
			return false;
		}

		// Build params.
		$params = [
			'version'   => 3,
			'id'        => $civi_event_id,
			'is_active' => 0,
		];

		// Use API to update Event.
		$result = civicrm_api( 'Event', 'create', $params );

		// Log failures and return boolean false.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => $result['error_message'],
				'params'    => $params,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
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
			'id'      => $event_id,
		];

		// Call API.
		$result = civicrm_api( 'Event', 'get', $params );

		// Log failures and bail.
		if ( ! empty( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $event;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $event;
		}

		// The result set should contain only one item.
		$event = array_pop( $result['values'] );

		// Backfill any missing keys.
		$event = $this->backfill( $event );

		// --<
		return $event;

	}

	/**
	 * Gets a CiviCRM Event by ID via API4.
	 *
	 * CiviCRM API4 helpfully includes *all* values when retrieving an Event. However,
	 * it has a different format for Custom Field keys, so we can't use it for that.
	 * So for now, this is useful for populating empty values via `self::backfill()`.
	 *
	 * @since 0.8.2
	 *
	 * @param int $event_id The numeric ID of the CiviCRM Event.
	 * @return array|bool $event The array of CiviCRM Event data, or false if not found.
	 */
	public function get_by_id( $event_id ) {

		// Init return.
		$event = false;

		// Bail if no CiviCRM.
		if ( ! $this->civicrm->is_active() ) {
			return $event;
		}

		try {

			$result = \Civi\Api4\Event::get( false )
				->addSelect( '*' )
				->addWhere( 'id', '=', $event_id )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			$log = [
				'method'    => __METHOD__,
				'error'     => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			];
			$this->plugin->log_error( $log );
			return $event;
		}

		// Bail if there is not exactly one result.
		if ( 1 !== (int) $result->count() ) {
			return $event;
		}

		// We only need the first item.
		$event = $result->first();

		// --<
		return $event;

	}

	/**
	 * Fill out the missing data for a CiviCRM Event.
	 *
	 * @since 0.8.2
	 *
	 * @param array $event The CiviCRM Event data.
	 * @return array $event The backfilled CiviCRM Event data.
	 */
	public function backfill( $event ) {

		// Get the full Event data.
		$event_full = $this->get_by_id( (int) $event['id'] );
		if ( false === $event_full ) {
			return $event;
		}

		// Fill out missing Event data.
		foreach ( $event_full as $key => $item ) {
			if ( ! array_key_exists( $key, $event ) ) {
				$event[ $key ] = $item;
			}
		}

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

}
