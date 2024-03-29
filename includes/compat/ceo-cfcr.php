<?php
/**
 * Caldera Forms CiviCRM Redirect Class.
 *
 * Handles compatibility with the "Caldera Forms CiviCRM Redirect" plugin.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.5.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Event Organiser "Caldera Forms CiviCRM Redirect" Compatibility Class.
 *
 * This class provides compatibility with the "Caldera Forms CiviCRM Redirect" plugin.
 *
 * @since 0.5.3
 */
class CiviCRM_WP_Event_Organiser_CFCR {

	/**
	 * Plugin object.
	 *
	 * @since 0.5.3
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Caldera Forms CiviCRM Redirect reference.
	 *
	 * @since 0.5.3
	 * @access public
	 * @var object $cfcr The "Caldera Forms CiviCRM Redirect" plugin reference.
	 */
	public $cfcr = false;

	/**
	 * Initialises this object.
	 *
	 * @since 0.5.3
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin = $parent;

		// Initialise after "Caldera Forms CiviCRM Redirect" is loaded.
		add_action( 'plugins_loaded', [ $this, 'initialise' ], 50 );

	}

	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.5.3
	 */
	public function initialise() {

		// Maybe store reference to CFC Forms CiviCRM Redirect.
		if ( defined( 'CFC_REDIRECT_VERSION' ) ) {
			$this->cfcr = new stdClass();
			$this->cfcr->redirect_api = new \CFCR\Api\DB();
		}

		// Bail if "CFC Forms CiviCRM Redirect" isn't detected.
		if ( $this->cfcr === false ) {
			return;
		}

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.5.3
	 */
	public function register_hooks() {

		// Add option at the end of the Online Registration options.
		add_action( 'civicrm_event_organiser_event_meta_box_online_reg_after', [ $this, 'metabox_append' ] );

		// Query new Redirect Location.
		add_action( 'wp_ajax_url_to_post_id', [ $this, 'url_to_post_id' ] );

		// Filter the queried Post Types.
		add_filter( 'wp_link_query_args', [ $this, 'query_post_type' ] );

		// Intercept Event components update.
		add_action( 'civicrm_event_organiser_event_components_updated', [ $this, 'redirect_update' ] );

	}

	// -------------------------------------------------------------------------

	/**
	 * Add our component to the Online Registration options in the Event metabox.
	 *
	 * @since 0.5.3
	 *
	 * @param object $event The Event Organiser Event object.
	 */
	public function metabox_append( $event ) {

		// Get linked CiviCRM Events.
		$civi_events = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event->ID );

		// Bail if there are none.
		if ( empty( $civi_events ) ) {
			return;
		}

		// Set multiple status.
		$multiple = false;
		if ( count( $civi_events ) > 1 ) {
			$multiple = true;
		}

		// Bail if multiple (for now).
		if ( $multiple ) {
			return;
		}

		// Build list of redirects.
		$redirects = [];
		foreach ( $civi_events as $civi_event_id ) {
			$redirect_data = $this->cfcr->redirect_api->get_by_entity_id( $civi_event_id );
			if ( ! empty( $redirect_data ) ) {
				$redirects[] = $redirect_data;
			}
		}

		// Build markup for Post.
		$page = __( 'None selected', 'civicrm-event-organiser' );
		$post_id = 0;
		$is_active = '';
		if ( ! empty( $redirects ) ) {
			$redirect = array_pop( $redirects );
			$page = '<a href="' . get_permalink( $redirect->post_id ) . '">' . esc_html( $redirect->post_title ) . '</a>' . "\n";
			$post_id = $redirect->post_id;
			$is_active = ( $redirect->is_active == 1 ) ? ' checked="checked"' : '';
		}

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-event-cfcr.php';

		// Add our metabox JavaScript in the footer.
		wp_enqueue_script(
			'civi_eo_event_metabox_cfcr_js',
			CIVICRM_WP_EVENT_ORGANISER_URL . '/assets/js/wordpress/metabox-event-cfcr.js',
			[ 'wplink' ],
			CIVICRM_WP_EVENT_ORGANISER_VERSION,
			true
		);

		// Init localisation.
		$localisation = [
			'title' => __( 'Choose Redirect Location', 'civicrm-event-organiser' ),
			'button' => __( 'Set Redirect Location', 'civicrm-event-organiser' ),
			'no-selection' => __( 'None selected', 'civicrm-event-organiser' ),
		];

		// Init settings.
		$settings = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'loading' => CIVICRM_WP_EVENT_ORGANISER_URL . 'assets/images/loading.gif',
		];

		// Localisation array.
		$vars = [
			'localisation' => $localisation,
			'settings' => $settings,
		];

		// Localise.
		wp_localize_script(
			'civi_eo_event_metabox_cfcr_js',
			'CiviCRM_Event_Organiser_CFCR_Settings',
			$vars
		);

	}

	/**
	 * Filter the Post Types in the switcher.
	 *
	 * @since 0.5.3
	 *
	 * @param array $query The existing WP_Query params.
	 * @return array $query The modified WP_Query params.
	 */
	public function query_post_type( $query ) {

		// Is this our metabox calling?
		$cfcr = isset( $_POST['cfcr'] ) ? sanitize_text_field( wp_unslash( $_POST['cfcr'] ) ) : '';
		$is_cfcr = false;
		if ( ! empty( $cfcr ) && $cfcr === 'true' ) {
			$is_cfcr = true;
		}

		// Bail if not us.
		if ( $is_cfcr === false ) {
			return $query;
		}

		// Show only Posts and Pages.
		$query['post_type'] = [ 'post', 'page' ];

		// --<
		return $query;

	}

	/**
	 * Return the Post ID for a given URL.
	 *
	 * @since 0.5.3
	 */
	public function url_to_post_id() {

		// Init data.
		$data = [
			'success' => 'false',
			'post_id' => 0,
		];

		// Bail if not at least editor.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $data;
		}

		// Get Post URL.
		// TODO: Sanitise the incoming URL.
		$post_url = isset( $_POST['post_url'] ) ? trim( wp_unslash( $_POST['post_url'] ) ) : '';

		// Sanity checks.
		if ( empty( $post_url ) ) {
			return $data;
		}

		// Try and get the Post ID.
		$post_id = url_to_postid( $post_url );

		// Bail if we don't get one.
		if ( $post_id === 0 ) {
			return $data;
		}

		// Add Post ID to data.
		$data['post_id'] = $post_id;

		// Send a link back.
		$data['markup'] = '<a href="' . get_permalink( $post_id ) . '">' . get_the_title( $post_id ) . '</a>' . "\n";

		// init data
		$data['success'] = 'true';

		// Send data to browser.
		wp_send_json( $data );

	}

	/**
	 * Update our component with the value from the Event metabox.
	 *
	 * @since 0.5.3
	 *
	 * @param int $event_id The numeric ID of the Event Organiser Event.
	 * @param int $redirect_post_id The numeric ID of the WordPress Post.
	 */
	public function redirect_update( $event_id, $redirect_post_id = 0 ) {

		// Override if set in POST.
		if ( isset( $_POST['civi_eo_event_redirect_post_id'] ) ) {
			$redirect_post_id = (int) sanitize_text_field( wp_unslash( $_POST['civi_eo_event_redirect_post_id'] ) );
		}

		// Trigger delete if Redirect Post ID is 0.
		if ( $redirect_post_id === 0 ) {
			$this->redirect_delete( $event_id );
			return;
		}

		// Set default but override if the checkbox is ticked.
		$is_active = isset( $_POST['civi_eo_event_redirect_active'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_redirect_active'] ) ) : 0;

		// Get linked CiviCRM Event IDs.
		$civi_event_ids = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event_id );

		// Set multiple status.
		$multiple = false;
		if ( count( $civi_event_ids ) > 1 ) {
			$multiple = true;
		}

		// Bail if multiple (for now).
		if ( $multiple ) {
			return;
		}

		// Get the Redirect Post object.
		$redirect_post = get_post( $redirect_post_id );

		// Get the CiviCRM Event ID.
		$civi_event_id = array_pop( $civi_event_ids );

		// Get existing redirect data.
		$existing = $this->cfcr->redirect_api->get_by_entity_id( $civi_event_id );

		// Build redirect params.
		$redirect = [
			'entity_id' => $civi_event_id,
			'page_type' => 'event',
			'is_active' => $is_active,
			'post_type' => get_post_type( $redirect_post_id ),
			'post_id' => $redirect_post_id,
			'page_title' => get_the_title( $event_id ),
			'post_title' => get_the_title( $redirect_post_id ),
		];

		// Create or update a redirect.
		if ( empty( $existing->id ) ) {

			// Create redirect data.
			$this->cfcr->redirect_api->insert( $redirect );

		} else {

			// Update redirect data.
			$redirect['id'] = $existing->id;
			$this->cfcr->redirect_api->update( $redirect, [ 'id' => $redirect['id'] ] );

		}

	}

	/**
	 * Maybe delete the redirect data for an Event.
	 *
	 * @since 0.5.3
	 *
	 * @param int $event_id The numeric ID of the Event Organiser Event.
	 */
	public function redirect_delete( $event_id ) {

		// Get linked CiviCRM Event IDs.
		$civi_event_ids = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $event_id );

		// Bail if there are none.
		if ( empty( $civi_event_ids ) ) {
			return;
		}

		// Determine multiple status.
		$multiple = false;
		if ( count( $civi_event_ids ) > 1 ) {
			$multiple = true;
		}

		// Bail if multiple (for now).
		if ( $multiple ) {
			return;
		}

		// Get the CiviCRM Event ID.
		$civi_event_id = array_pop( $civi_event_ids );

		// Get existing redirect data.
		$existing = $this->cfcr->redirect_api->get_by_entity_id( $civi_event_id );

		// No need to delete if there isn't any.
		if ( empty( $existing ) ) {
			return;
		}

		// Okay to delete now.
		$this->cfcr->redirect_api->delete( (array) $existing );

	}

}
