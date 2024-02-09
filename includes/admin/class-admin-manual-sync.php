<?php
/**
 * Admin Manual Sync Class.
 *
 * Handles Admin Manual Sync functionality.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Event Organiser Manual Sync Admin Class.
 *
 * This class provides Manual Sync Admin functionality.
 *
 * @since 0.7
 */
class CiviCRM_WP_Event_Organiser_Admin_Manual_Sync {

	/**
	 * Plugin object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Single site admin object.
	 *
	 * @since 0.7
	 * @access public
	 * @var string $admin The single site admin object.
	 */
	public $admin;

	/**
	 * Settings Page object.
	 *
	 * @since 0.7
	 * @access public
	 * @var string $settings The Settings Page object.
	 */
	public $settings;

	/**
	 * Manual Sync Page.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var str $sync_page The manual sync page.
	 */
	public $sync_page;

	/**
	 * Manual Sync Page slug.
	 *
	 * @since 0.7
	 * @access public
	 * @var string $settings_page_slug The slug of the Settings Page.
	 */
	public $sync_page_slug = 'civi_eo_manual_sync';

	/**
	 * How many items to process per AJAX request.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var object $step_counts The array of item counts to process per AJAX request.
	 */
	public $step_counts = [
		'tax' => 5, // Event Organiser Category Terms & CiviCRM Event Types.
		'venue' => 5, // Event Organiser Venues & CiviCRM Locations.
		'event' => 10, // Event Organiser Events & CiviCRM Events.
	];

	/**
	 * Constructor.
	 *
	 * @since 0.7
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references to objects.
		$this->plugin = $parent->plugin;
		$this->admin = $parent;

		// Boot when parent is loaded.
		add_action( 'ceo/admin/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 0.7
	 */
	public function initialise() {

		// Store references.
		$this->settings = $this->admin->settings;

		// Register hooks.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.7
		 */
		do_action( 'ceo/admin/manual_sync/loaded' );

	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.7
	 */
	public function register_hooks() {

		// Add menu item.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 30 );

		// Add our meta boxes.
		add_action( 'civi_eo/admin/sync/add_meta_boxes', [ $this, 'meta_boxes_add' ], 11, 1 );

		// Add AJAX handlers.
		add_action( 'wp_ajax_sync_categories_to_types', [ $this, 'stepped_sync_categories_to_types' ] );
		add_action( 'wp_ajax_sync_types_to_categories', [ $this, 'stepped_sync_types_to_categories' ] );
		add_action( 'wp_ajax_sync_venues_to_locations', [ $this, 'stepped_sync_venues_to_locations' ] );
		add_action( 'wp_ajax_sync_locations_to_venues', [ $this, 'stepped_sync_locations_to_venues' ] );
		add_action( 'wp_ajax_sync_events_eo_to_civi', [ $this, 'stepped_sync_events_eo_to_civi' ] );
		add_action( 'wp_ajax_sync_events_civi_to_eo', [ $this, 'stepped_sync_events_civi_to_eo' ] );

	}

	// -------------------------------------------------------------------------

	/**
	 * Add our admin page(s) to the WordPress admin menu.
	 *
	 * @since 0.7
	 */
	public function admin_menu() {

		/**
		 * Set access capability but allow overrides.
		 *
		 * @since 0.7
		 *
		 * @param string The default capability for access to Settings.
		 */
		$capability = apply_filters( 'ceo/admin/settings/cap', 'manage_options' );

		// Check user permissions.
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		// Add Manual Sync page.
		$this->sync_page = add_submenu_page(
			$this->settings->parent_page_slug, // Parent slug.
			__( 'Manual Sync: CiviCRM Event Organiser', 'civicrm-event-organiser' ), // Page title.
			__( 'Manual Sync', 'civicrm-event-organiser' ), // Menu title.
			'manage_options', // Required caps.
			$this->sync_page_slug, // Slug name.
			[ $this, 'page_manual_sync' ] // Callback.
		);

		// Register our form submit hander.
		add_action( 'load-' . $this->sync_page, [ $this, 'form_submitted' ] );

		// Add WordPress scripts and help text.
		add_action( 'admin_head-' . $this->sync_page, [ $this, 'admin_head' ], 50 );

		// Ensure correct menu item is highlighted.
		add_action( 'admin_head-' . $this->sync_page, [ $this->settings, 'admin_menu_highlight' ], 50 );

		// Add scripts and styles.
		add_action( 'admin_print_styles-' . $this->sync_page, [ $this, 'page_manual_sync_css' ] );
		add_action( 'admin_print_scripts-' . $this->sync_page, [ $this, 'page_manual_sync_js' ] );

		// Filter the list of single site subpages and add page.
		add_filter( 'ceo/admin/settings/subpages', [ $this, 'admin_subpages_filter' ] );

		// Filter the list of single site page URLs and add page URL.
		add_filter( 'ceo/admin/settings/tab_urls', [ $this, 'page_tab_urls_filter' ] );

		// Filter the "show tabs" flag for setting templates.
		add_filter( 'ceo/admin/settings/show_tabs', [ $this, 'page_show_tabs' ] );

		// Add tab to setting templates.
		add_action( 'ceo/admin/settings/nav_tabs', [ $this, 'page_add_tab' ], 10, 2 );

	}

	/**
	 * Initialise plugin help.
	 *
	 * @since 0.7
	 */
	public function admin_head() {

		// Enqueue WordPress scripts.
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'dashboard' );

	}

	/**
	 * Append the "Manual Sync" page to Settings page.
	 *
	 * This ensures that the correct parent menu item is highlighted for our
	 * "Manual Sync" subpage.
	 *
	 * @since 0.7
	 *
	 * @param array $subpages The existing list of subpages.
	 * @return array $subpages The modified list of subpages.
	 */
	public function admin_subpages_filter( $subpages ) {

		// Add "Manual Sync" page.
		$subpages[] = $this->sync_page_slug;

		// --<
		return $subpages;

	}

	/**
	 * Get the URL for the form action.
	 *
	 * @since 0.7
	 *
	 * @return string $target_url The URL for the admin form action.
	 */
	public function admin_form_url_get() {

		// Sanitise admin page url.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target_url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( ! empty( $target_url ) ) {
			$url_array = explode( '&', $target_url );
			if ( $url_array ) {
				$target_url = htmlentities( $url_array[0] . '&updated=true' );
			}
		}

		// --<
		return $target_url;

	}

	// -------------------------------------------------------------------------

	/**
	 * Show our admin manual sync page.
	 *
	 * @since 0.2.4
	 */
	public function page_manual_sync() {

		// Only allow network admins when network activated.
		if ( $this->admin->is_network_activated() ) {
			if ( ! is_super_admin() ) {
				wp_die( __( 'You do not have permission to access this page.', 'civicrm-event-organiser' ) );
			}
		}

		// Get current screen.
		$screen = get_current_screen();

		/**
		 * Allow meta boxes to be added to this screen.
		 *
		 * The Screen ID to use is: "civicrm_page_civi_eo_manual_sync".
		 *
		 * @since 0.7
		 *
		 * @param string $screen_id The ID of the current screen.
		 */
		do_action( 'civi_eo/admin/sync/add_meta_boxes', $screen->id );

		// Get the column CSS class.
		$columns = absint( $screen->get_columns() );
		$columns_css = '';
		if ( $columns ) {
			$columns_css = " columns-$columns";
		}

		// Get admin page URLs.
		$urls = $this->settings->page_tab_urls_get();

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/pages/page-admin-manual-sync.php';

	}

	/**
	 * Enqueue any styles needed by our admin "Manual Sync" page.
	 *
	 * @since 0.2.4
	 * @since 0.7 Renamed.
	 */
	public function page_manual_sync_css() {

		// Add admin css.
		wp_enqueue_style(
			'civi_eo_manual_sync_css',
			plugins_url( 'assets/css/wordpress/page-admin-manual-sync.css', CIVICRM_WP_EVENT_ORGANISER_FILE ),
			null,
			CIVICRM_WP_EVENT_ORGANISER_VERSION,
			'all' // Media.
		);

	}

	/**
	 * Enqueue required scripts on the "Manual Sync" page.
	 *
	 * @since 0.2.4
	 * @since 0.7 Renamed.
	 */
	public function page_manual_sync_js() {

		// Enqueue javascript.
		wp_enqueue_script(
			'civi_eo_manual_sync_js',
			plugins_url( 'assets/js/wordpress/page-admin-manual-sync.js', CIVICRM_WP_EVENT_ORGANISER_FILE ),
			[ 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ],
			CIVICRM_WP_EVENT_ORGANISER_VERSION, // Version.
			true
		);

		// Get all CiviCRM Event Types and error check.
		$all_types = $this->plugin->taxonomy->get_event_types();
		if ( $all_types === false ) {
			$all_types['values'] = [];
		}

		// Get all Event Organiser Event Category Terms.
		$all_terms = $this->plugin->taxonomy->get_event_categories();

		// Get all Civi Event Locations and error check.
		$all_locations = $this->plugin->civi->location->get_all_locations();
		if ( $all_locations['is_error'] == '1' ) {
			$all_locations['values'] = [];
		}

		// Get all Event Organiser Venues.
		$all_venues = eo_get_venues();

		// Get all Civi Events and error check.
		$all_civi_events = $this->plugin->civi->event->get_all_civi_events();
		if ( $all_civi_events['is_error'] == '1' ) {
			$all_civi_events['values'] = [];
		}

		// Get all Event Organiser Events.
		$all_eo_events = get_posts( [
			'post_type' => 'event',
			'numberposts' => -1,
		] );

		// Init localisation.
		$localisation = [

			// CiviCRM Event Types.
			'event_types' => [
				'total' => __( '{{total}} event types to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing event types {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing event types {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_types['values'] ),
			],

			// Event Organiser categories.
			'categories' => [
				'total' => __( '{{total}} categories to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing categories {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing categories {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_terms ),
			],

			// CiviCRM Locations.
			'locations' => [
				'total' => __( '{{total}} locations to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing locations {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing locations {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_locations['values'] ),
			],

			// Event Organiser Venues.
			'venues' => [
				'total' => __( '{{total}} venues to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing venues {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing venues {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_venues ),
			],

			// CiviCRM Events.
			'civi_events' => [
				'total' => __( '{{total}} events to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing events {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing events {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_civi_events['values'] ),
			],

			// Event Organiser Events.
			'eo_events' => [
				'total' => __( '{{total}} events to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing events {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing events {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_eo_events ),
			],

			// Strings common to all.
			'common' => [
				'done' => __( 'All done!', 'civicrm-event-organiser' ),
			],

		];

		// Init settings.
		$settings = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'step_tax' => $this->step_counts['tax'],
			'step_venue' => $this->step_counts['venue'],
			'step_event' => $this->step_counts['event'],
		];

		// Localisation array.
		$vars = [
			'localisation' => $localisation,
			'settings' => $settings,
		];

		// Localise the WordPress way.
		wp_localize_script(
			'civi_eo_manual_sync_js',
			'CiviCRM_Event_Organiser_Settings',
			$vars
		);

	}

	// -------------------------------------------------------------------------

	/**
	 * Append the Manual Sync settings page URL to the subpage URLs.
	 *
	 * @since 0.7
	 *
	 * @param array $urls The existing list of URLs.
	 * @return array $urls The modified list of URLs.
	 */
	public function page_tab_urls_filter( $urls ) {

		// Add multidomain settings page.
		$urls['manual-sync'] = menu_page_url( $this->sync_page_slug, false );

		// --<
		return $urls;

	}

	/**
	 * Show subpage tabs on settings pages.
	 *
	 * @since 0.7
	 *
	 * @param bool $show_tabs True if tabs are shown, false otherwise.
	 * @return bool $show_tabs True if tabs are to be shown, false otherwise.
	 */
	public function page_show_tabs( $show_tabs ) {

		// Always show tabs.
		$show_tabs = true;

		// --<
		return $show_tabs;

	}

	/**
	 * Add subpage tab to tabs on settings pages.
	 *
	 * @since 0.7
	 *
	 * @param array $urls The array of subpage URLs.
	 * @param string $active_tab The key of the active tab in the subpage URLs array.
	 */
	public function page_add_tab( $urls, $active_tab ) {

		// Define title.
		$title = __( 'Manual Sync', 'civicrm-event-organiser' );

		// Default to inactive.
		$active = '';

		// Make active if it's our subpage.
		if ( $active_tab === 'manual-sync' ) {
			$active = ' nav-tab-active';
		}

		// Render tab.
		echo '<a href="' . $urls['manual-sync'] . '" class="nav-tab' . $active . '">' . $title . '</a>' . "\n";

	}

	// -------------------------------------------------------------------------

	/**
	 * Register meta boxes.
	 *
	 * @since 0.7
	 *
	 * @param string $screen_id The Admin Page Screen ID.
	 */
	public function meta_boxes_add( $screen_id ) {

		// Define valid Screen IDs.
		$screen_ids = [
			'admin_page_' . $this->sync_page_slug,
		];

		// Bail if not the Screen ID we want.
		if ( ! in_array( $screen_id, $screen_ids ) ) {
			return;
		}

		/**
		 * Set access capability but allow overrides.
		 *
		 * @since 0.7
		 *
		 * @param string The default capability for access to Settings.
		 */
		$capability = apply_filters( 'ceo/admin/settings/cap', 'manage_options' );

		// Check user permissions.
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		// Create "Event Organiser Categories to CiviCRM Event Types" metabox.
		add_meta_box(
			'ceo_manual_sync_category_type',
			__( 'Event Organiser Categories &rarr; CiviCRM Event Types', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_category_type_render' ], // Callback.
			$screen_id, // Screen ID.
			'side', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Closed by default.
		add_filter( "postbox_classes_{$screen_id}_ceo_manual_sync_category_type", [ $this, 'meta_box_closed' ] );

		// Create "CiviCRM Event Types to Event Organiser Categories" metabox.
		add_meta_box(
			'ceo_manual_sync_type_category',
			__( 'CiviCRM Event Types &rarr; Event Organiser Categories', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_type_category_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Closed by default.
		add_filter( "postbox_classes_{$screen_id}_ceo_manual_sync_type_category", [ $this, 'meta_box_closed' ] );

		// Create "Event Organiser Venues to CiviCRM Event Locations" metabox.
		add_meta_box(
			'ceo_manual_sync_venue_location',
			__( 'Event Organiser Venues &rarr; CiviCRM Event Locations', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_venue_location_render' ], // Callback.
			$screen_id, // Screen ID.
			'side', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Closed by default.
		add_filter( "postbox_classes_{$screen_id}_ceo_manual_sync_venue_location", [ $this, 'meta_box_closed' ] );

		// Create "CiviCRM Event Locations to Event Organiser Venues" metabox.
		add_meta_box(
			'ceo_manual_sync_location_venue',
			__( 'CiviCRM Event Locations &rarr; Event Organiser Venues', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_location_venue_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Closed by default.
		add_filter( "postbox_classes_{$screen_id}_ceo_manual_sync_location_venue", [ $this, 'meta_box_closed' ] );

		// Create "Event Organiser Events to CiviCRM Events" metabox.
		add_meta_box(
			'ceo_manual_sync_eo_civicrm',
			__( 'Event Organiser Events &rarr; CiviCRM Events', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_eo_civicrm_render' ], // Callback.
			$screen_id, // Screen ID.
			'side', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Closed by default.
		add_filter( "postbox_classes_{$screen_id}_ceo_manual_sync_eo_civicrm", [ $this, 'meta_box_closed' ] );

		// Create "CiviCRM Events to Event Organiser Events" metabox.
		add_meta_box(
			'ceo_manual_sync_civicrm_eo',
			__( 'CiviCRM Events &rarr; Event Organiser Events', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_civicrm_eo_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Closed by default.
		add_filter( "postbox_classes_{$screen_id}_ceo_manual_sync_civicrm_eo", [ $this, 'meta_box_closed' ] );

	}

	/**
	 * Load our meta boxes as closed by default.
	 *
	 * @since 0.7
	 *
	 * @param string[] $classes An array of postbox classes.
	 */
	public function meta_box_closed( $classes ) {

		// Add closed class.
		if ( is_array( $classes ) ) {
			if ( ! in_array( 'closed', $classes ) ) {
				$classes[] = 'closed';
			}
		}

		// --<
		return $classes;

	}

	/**
	 * Render "Event Organiser Categories to CiviCRM Event Types" meta box.
	 *
	 * @since 0.7
	 */
	public function meta_box_category_type_render() {

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-sync-category-type.php';

	}

	/**
	 * Render "CiviCRM Event Types to Event Organiser Categories" meta box.
	 *
	 * @since 0.7
	 */
	public function meta_box_type_category_render() {

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-sync-type-category.php';

	}

	/**
	 * Render "Event Organiser Venues to CiviCRM Event Locations" meta box.
	 *
	 * @since 0.7
	 */
	public function meta_box_venue_location_render() {

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-sync-venue-location.php';

	}

	/**
	 * Render "CiviCRM Event Locations to Event Organiser Venues" meta box.
	 *
	 * @since 0.7
	 */
	public function meta_box_location_venue_render() {

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-sync-location-venue.php';

	}

	/**
	 * Render "Event Organiser Events to CiviCRM Events" meta box.
	 *
	 * @since 0.7
	 */
	public function meta_box_eo_civicrm_render() {

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-sync-eo-civicrm.php';

	}

	/**
	 * Render "CiviCRM Events to Event Organiser Events" meta box.
	 *
	 * @since 0.7
	 */
	public function meta_box_civicrm_eo_render() {

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-sync-civicrm-eo.php';

	}

	// -------------------------------------------------------------------------

	/**
	 * Performs actions when a form has been submitted.
	 *
	 * @since 0.2.4
	 * @since 0.7 Renamed.
	 */
	public function form_submitted() {

		// phpcs:disable WordPress.Security.NonceVerification.Missing

		// Was an Event Type "Stop Sync" button pressed?
		$tax_eo_to_civi_stop = isset( $_POST['civi_eo_tax_eo_to_civi_stop'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_tax_eo_to_civi_stop'] ) ) : false;
		if ( ! empty( $tax_eo_to_civi_stop ) ) {
			delete_option( '_civi_eo_tax_eo_to_civi_offset' );
			return;
		}
		$tax_civi_to_eo_stop = isset( $_POST['civi_eo_tax_civi_to_eo_stop'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_tax_civi_to_eo_stop'] ) ) : false;
		if ( ! empty( $tax_civi_to_eo_stop ) ) {
			delete_option( '_civi_eo_tax_civi_to_eo_offset' );
			return;
		}

		// Was a Venue "Stop Sync" button pressed?
		$venue_eo_to_civi_stop = isset( $_POST['civi_eo_venue_eo_to_civi_stop'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_venue_eo_to_civi_stop'] ) ) : false;
		if ( ! empty( $venue_eo_to_civi_stop ) ) {
			delete_option( '_civi_eo_venue_eo_to_civi_offset' );
			return;
		}
		$venue_civi_to_eo_stop = isset( $_POST['civi_eo_venue_civi_to_eo_stop'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_venue_civi_to_eo_stop'] ) ) : false;
		if ( ! empty( $venue_civi_to_eo_stop ) ) {
			delete_option( '_civi_eo_venue_civi_to_eo_offset' );
			return;
		}

		// Was an Event "Stop Sync" button pressed?
		$event_eo_to_civi_stop = isset( $_POST['civi_eo_event_eo_to_civi_stop'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_eo_to_civi_stop'] ) ) : false;
		if ( ! empty( $event_eo_to_civi_stop ) ) {
			delete_option( '_civi_eo_event_eo_to_civi_offset' );
			return;
		}
		$event_civi_to_eo_stop = isset( $_POST['civi_eo_event_civi_to_eo_stop'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_civi_to_eo_stop'] ) ) : false;
		if ( ! empty( $event_civi_to_eo_stop ) ) {
			delete_option( '_civi_eo_event_civi_to_eo_offset' );
			return;
		}

		// Was an Event Type "Sync Now" button pressed?
		$venue_eo_to_civi = isset( $_POST['civi_eo_venue_eo_to_civi'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_venue_eo_to_civi'] ) ) : false;
		if ( ! empty( $venue_eo_to_civi ) ) {
			$this->stepped_sync_categories_to_types();
		}
		$venue_eo_to_civi = isset( $_POST['civi_eo_venue_eo_to_civi'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_venue_eo_to_civi'] ) ) : false;
		if ( ! empty( $venue_eo_to_civi ) ) {
			$this->stepped_sync_types_to_categories();
		}

		// Was a Venue "Sync Now" button pressed?
		$venue_eo_to_civi = isset( $_POST['civi_eo_venue_eo_to_civi'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_venue_eo_to_civi'] ) ) : false;
		if ( ! empty( $venue_eo_to_civi ) ) {
			$this->stepped_sync_venues_to_locations();
		}
		$venue_civi_to_eo = isset( $_POST['civi_eo_venue_civi_to_eo'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_venue_civi_to_eo'] ) ) : false;
		if ( ! empty( $venue_civi_to_eo ) ) {
			$this->stepped_sync_locations_to_venues();
		}

		// Was an Event "Sync Now" button pressed?
		$event_civi_to_eo = isset( $_POST['civi_eo_event_civi_to_eo'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_civi_to_eo'] ) ) : false;
		if ( ! empty( $event_civi_to_eo ) ) {
			$this->stepped_sync_events_eo_to_civi();
		}
		$event_civi_to_eo = isset( $_POST['civi_eo_event_civi_to_eo'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_civi_to_eo'] ) ) : false;
		if ( ! empty( $event_civi_to_eo ) ) {
			$this->stepped_sync_events_civi_to_eo();
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

	}

	// -------------------------------------------------------------------------

	/**
	 * Stepped synchronisation of Event Organiser Category Terms to CiviCRM Event Types.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_categories_to_types() {

		// Init AJAX return.
		$data = [];

		// If this is an AJAX request, check security.
		if ( wp_doing_ajax() ) {

			// Check security.
			$result = check_ajax_referer( 'civi_eo_tax_eo_to_civi', false, false );

			// Bail if check fails.
			if ( $result === false ) {

				// Set finished flag.
				$data['finished'] = 'true';

				// Delete the option to start from the beginning.
				delete_option( '_civi_eo_tax_eo_to_civi_offset' );

				// Send data to browser.
				wp_send_json( $data );
				return;

			}

		}

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( '_civi_eo_tax_eo_to_civi_offset', 'fgffgs' ) ) {

			// Start at the beginning.
			$offset = 0;
			add_option( '_civi_eo_tax_eo_to_civi_offset', '0' );

		} else {

			// Use the existing value.
			$offset = intval( get_option( '_civi_eo_tax_eo_to_civi_offset', '0' ) );

		}

		// Construct args.
		$args = [
			'taxonomy' => 'event-category',
			'orderby' => 'count',
			'hide_empty' => 0,
			'number' => $this->step_counts['tax'],
			'offset' => $offset,
		];

		// Get all Terms.
		$terms = get_terms( $args );

		// If we get results.
		if ( count( $terms ) > 0 ) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there less items than the step count?
			if ( count( $terms ) < $this->step_counts['tax'] ) {
				$diff = count( $terms );
			} else {
				$diff = $this->step_counts['tax'];
			}

			// Set from and to flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Sync each Event Term in turn.
			foreach ( $terms as $term ) {

				// Update CiviCRM Event Term - or create if it doesn't exist.
				$civi_event_type_id = $this->plugin->taxonomy->update_event_type( $term );

				// Next on failure.
				if ( $civi_event_type_id === false ) {

					// Log failed Event Term first.
					$e = new Exception();
					$trace = $e->getTraceAsString();
					error_log( print_r( [
						'method' => __METHOD__,
						'message' => __( 'Could not sync Event Term', 'civicrm-event-organiser' ),
						'term' => $term,
						'backtrace' => $trace,
					], true ) );

					continue;

				}

			}

			// Increment offset option.
			update_option( '_civi_eo_tax_eo_to_civi_offset', (string) $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			delete_option( '_civi_eo_tax_eo_to_civi_offset' );

		}

		// Send data to browser.
		wp_send_json( $data );

	}

	/**
	 * Stepped synchronisation of CiviCRM Event Types to Event Organiser Category Terms.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_types_to_categories() {

		// Init AJAX return.
		$data = [];

		// If this is an AJAX request, check security.
		if ( wp_doing_ajax() ) {

			// Check security.
			$result = check_ajax_referer( 'civi_eo_tax_civi_to_eo', false, false );

			// Bail if check fails.
			if ( $result === false ) {

				// Set finished flag.
				$data['finished'] = 'true';

				// Delete the option to start from the beginning.
				delete_option( '_civi_eo_tax_eo_to_civi_offset' );

				// Send data to browser.
				wp_send_json( $data );
				return;

			}

		}

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) {

			// Start at the beginning.
			$offset = 0;
			add_option( '_civi_eo_tax_civi_to_eo_offset', '0' );

		} else {

			// Use the existing value.
			$offset = intval( get_option( '_civi_eo_tax_civi_to_eo_offset', '0' ) );

		}

		// Get option group ID and error check.
		$opt_group_id = $this->plugin->taxonomy->get_event_types_optgroup_id();
		if ( $opt_group_id !== false ) {

			// Get Event Types (descriptions will be present if not null).
			$types = civicrm_api( 'OptionValue', 'get', [
				'version' => 3,
				'option_group_id' => $opt_group_id,
				'options' => [
					'limit' => $this->step_counts['tax'],
					'offset' => $offset,
					'sort' => 'weight ASC',
				],
			] );

		} else {

			// Do not allow progress.
			$types['is_error'] = 1;

		}

		// If we get results.
		if (
			$types['is_error'] == 0 &&
			isset( $types['values'] ) &&
			count( $types['values'] ) > 0
		) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there less items than the step count?
			if ( count( $types['values'] ) < $this->step_counts['tax'] ) {
				$diff = count( $types['values'] );
			} else {
				$diff = $this->step_counts['tax'];
			}

			// Set from and to flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Sync each Event Type in turn.
			foreach ( $types['values'] as $type ) {

				// Update CiviCRM Event Term - or create if it doesn't exist.
				$eo_term_id = $this->plugin->taxonomy->update_term( $type );

				// Next on failure.
				if ( $eo_term_id === false ) {

					// Log failed Event Type first.
					$e = new Exception();
					$trace = $e->getTraceAsString();
					error_log( print_r( [
						'method' => __METHOD__,
						'message' => __( 'Could not sync Event Type', 'civicrm-event-organiser' ),
						'type' => $type,
						'backtrace' => $trace,
					], true ) );

					continue;

				}

			}

			// Increment offset option.
			update_option( '_civi_eo_tax_civi_to_eo_offset', (string) $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			delete_option( '_civi_eo_tax_civi_to_eo_offset' );

		}

		// Send data to browser.
		wp_send_json( $data );

	}

	/**
	 * Stepped synchronisation of Event Organiser Venues to CiviCRM Locations.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_venues_to_locations() {

		// Init AJAX return.
		$data = [];

		// If this is an AJAX request, check security.
		if ( wp_doing_ajax() ) {

			// Check security.
			$result = check_ajax_referer( 'civi_eo_venue_eo_to_civi', false, false );

			// Bail if check fails.
			if ( $result === false ) {

				// Set finished flag.
				$data['finished'] = 'true';

				// Delete the option to start from the beginning.
				delete_option( '_civi_eo_tax_eo_to_civi_offset' );

				// Send data to browser.
				wp_send_json( $data );
				return;

			}

		}

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( '_civi_eo_venue_eo_to_civi_offset', 'fgffgs' ) ) {

			// Start at the beginning.
			$offset = 0;
			add_option( '_civi_eo_venue_eo_to_civi_offset', '0' );

		} else {

			// Use the existing value.
			$offset = intval( get_option( '_civi_eo_venue_eo_to_civi_offset', '0' ) );

		}

		// Get Venues.
		$venues = eo_get_venues( [
			'number' => $this->step_counts['venue'],
			'offset' => $offset,
		] );

		// If we get results.
		if ( count( $venues ) > 0 ) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there less items than the step count?
			if ( count( $venues ) < $this->step_counts['venue'] ) {
				$diff = count( $venues );
			} else {
				$diff = $this->step_counts['venue'];
			}

			// Set from and to flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Loop.
			foreach ( $venues as $venue ) {

				/*
				 * Manually add Venue metadata because since Event Organiser version 3.0 it is
				 * no longer added by default to the Venue object.
				 *
				 * @see https://github.com/stephenharris/Event-Organiser/commit/646220b336ba9c49d12bd17f5992e1391d0b411f
				 */
				$venue_id = (int) $venue->term_id;
				$address = eo_get_venue_address( $venue_id );

				$venue->venue_address  = isset( $address['address'] ) ? $address['address'] : '';
				$venue->venue_postal   = isset( $address['postcode'] ) ? $address['postcode'] : '';
				$venue->venue_postcode = isset( $address['postcode'] ) ? $address['postcode'] : '';
				$venue->venue_city     = isset( $address['city'] ) ? $address['city'] : '';
				$venue->venue_country  = isset( $address['country'] ) ? $address['country'] : '';
				$venue->venue_state    = isset( $address['state'] ) ? $address['state'] : '';

				$venue->venue_lat = number_format( floatval( eo_get_venue_lat( $venue_id ) ), 6 );
				$venue->venue_lng = number_format( floatval( eo_get_venue_lng( $venue_id ) ), 6 );

				// Update Civi Location - or create if it doesn't exist.
				$location = $this->plugin->civi->location->update_location( $venue );

				// Store in Event Organiser Venue.
				$this->plugin->eo_venue->store_civi_location( $venue_id, $location );

			}

			// Increment offset option.
			update_option( '_civi_eo_venue_eo_to_civi_offset', (string) $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			delete_option( '_civi_eo_venue_eo_to_civi_offset' );

		}

		// Send data to browser.
		wp_send_json( $data );

	}

	/**
	 * Stepped synchronisation of CiviCRM Locations to Event Organiser Venues.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_locations_to_venues() {

		// Init AJAX return.
		$data = [];

		// If this is an AJAX request, check security.
		if ( wp_doing_ajax() ) {

			// Check security.
			$result = check_ajax_referer( 'civi_eo_venue_civi_to_eo', false, false );

			// Bail if check fails.
			if ( $result === false ) {

				// Set finished flag.
				$data['finished'] = 'true';

				// Delete the option to start from the beginning.
				delete_option( '_civi_eo_tax_eo_to_civi_offset' );

				// Send data to browser.
				wp_send_json( $data );
				return;

			}

		}

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( '_civi_eo_venue_civi_to_eo_offset', 'fgffgs' ) ) {

			// Start at the beginning.
			$offset = 0;
			add_option( '_civi_eo_venue_civi_to_eo_offset', '0' );

		} else {

			// Use the existing value.
			$offset = intval( get_option( '_civi_eo_venue_civi_to_eo_offset', '0' ) );

		}

		// Init CiviCRM.
		if ( $this->plugin->civi->is_active() ) {

			// Get CiviCRM Locations.
			$locations = civicrm_api( 'LocBlock', 'get', [
				'version' => 3,
				'return' => 'all',
				'options' => [
					'limit' => $this->step_counts['tax'],
					'offset' => $offset,
				],
			] );

		} else {

			// Do not allow progress.
			$locations['is_error'] = 1;

		}

		// If we get results.
		if (
			$locations['is_error'] == 0 &&
			isset( $locations['values'] ) &&
			count( $locations['values'] ) > 0
		) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there less items than the step count?
			if ( count( $locations['values'] ) < $this->step_counts['venue'] ) {
				$diff = count( $locations['values'] );
			} else {
				$diff = $this->step_counts['venue'];
			}

			// Set from and to flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Loop.
			foreach ( $locations['values'] as $location ) {

				// Update Event Organiser Venue - or create if it doesn't exist.
				$this->plugin->eo_venue->update_venue( $location );

			}

			// Increment offset option.
			update_option( '_civi_eo_venue_civi_to_eo_offset', (string) $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			delete_option( '_civi_eo_venue_civi_to_eo_offset' );

		}

		// Send data to browser.
		wp_send_json( $data );

	}

	/**
	 * Stepped synchronisation of Event Organiser Events to CiviCRM Events.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_events_eo_to_civi() {

		// Init AJAX return.
		$data = [];

		// If this is an AJAX request, check security.
		if ( wp_doing_ajax() ) {

			// Check security.
			$result = check_ajax_referer( 'civi_eo_event_eo_to_civi', false, false );

			// Bail if check fails.
			if ( $result === false ) {

				// Set finished flag.
				$data['finished'] = 'true';

				// Delete the option to start from the beginning.
				delete_option( '_civi_eo_tax_eo_to_civi_offset' );

				// Send data to browser.
				wp_send_json( $data );
				return;

			}

		}

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( '_civi_eo_event_eo_to_civi_offset', 'fgffgs' ) ) {

			// Start at the beginning.
			$offset = 0;
			add_option( '_civi_eo_event_eo_to_civi_offset', '0' );

		} else {

			// Use the existing value.
			$offset = intval( get_option( '_civi_eo_event_eo_to_civi_offset', '0' ) );

		}

		// Get "primary" Events (i.e. not ordered by Occurrence).
		$events = eo_get_events( [
			'numberposts' => $this->step_counts['event'],
			'offset' => $offset,
			'group_events_by' => 'series',
		] );

		// If we get results.
		if ( count( $events ) > 0 ) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there less items than the step count?
			if ( count( $events ) < $this->step_counts['event'] ) {
				$diff = count( $events );
			} else {
				$diff = $this->step_counts['event'];
			}

			// Set from and to flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Prevent recursion.
			remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ], 10 );
			remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10 );
			remove_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ], 10 );

			// Loop.
			foreach ( $events as $event ) {

				// Get dates for this Event.
				$dates = $this->plugin->eo->get_all_dates( $event->ID );

				// Update CiviCRM Event - or create if it doesn't exist.
				$correspondences = $this->plugin->civi->event->update_civi_events( $event, $dates );

				// Make an array of params.
				$args = [
					'post_id' => $event->ID,
					'event_id' => $event->ID,
					'event' => $event,
					'dates' => $dates,
					'correspondences' => $correspondences,
				];

				/**
				 * Broadcast that the Event Organiser Event has been synced.
				 *
				 * Used internally to:
				 *
				 * * Update the Custom Fields synced via CiviCRM ACF Integration (obsolete)
				 * * Update the Custom Fields synced via CiviCRM Profile Sync
				 *
				 * @since 0.5.2
				 *
				 * @param array $args The array of params.
				 */
				do_action( 'civicrm_event_organiser_admin_eo_to_civi_sync', $args );

			}

			// Restore hooks.
			add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_created' ], 10, 4 );
			add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_updated' ], 10, 4 );
			add_action( 'civicrm_post', [ $this->plugin->civi->event, 'event_deleted' ], 10, 4 );

			// Increment offset option.
			update_option( '_civi_eo_event_eo_to_civi_offset', (string) $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			delete_option( '_civi_eo_event_eo_to_civi_offset' );

		}

		// Send data to browser.
		wp_send_json( $data );

	}

	/**
	 * Stepped synchronisation of CiviCRM Events to Event Organiser Events.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_events_civi_to_eo() {

		// Init AJAX return.
		$data = [];

		// If this is an AJAX request, check security.
		if ( wp_doing_ajax() ) {

			// Check security.
			$result = check_ajax_referer( 'civi_eo_event_civi_to_eo', false, false );

			// Bail if check fails.
			if ( $result === false ) {

				// Set finished flag.
				$data['finished'] = 'true';

				// Delete the option to start from the beginning.
				delete_option( '_civi_eo_tax_eo_to_civi_offset' );

				// Send data to browser.
				wp_send_json( $data );
				return;

			}

		}

		// If the offset value doesn't exist.
		if ( 'fgffgs' == get_option( '_civi_eo_event_civi_to_eo_offset', 'fgffgs' ) ) {

			// Start at the beginning.
			$offset = 0;
			add_option( '_civi_eo_event_civi_to_eo_offset', '0' );

		} else {

			// Use the existing value.
			$offset = intval( get_option( '_civi_eo_event_civi_to_eo_offset', '0' ) );

		}

		// Init CiviCRM.
		if ( $this->plugin->civi->is_active() ) {

			// Get CiviCRM Events.
			$events = civicrm_api( 'Event', 'get', [
				'version' => 3,
				'is_template' => 0,
				'options' => [
					'limit' => $this->step_counts['event'],
					'offset' => $offset,
				],
			] );

		} else {

			// Do not allow progress.
			$events['is_error'] = 1;

		}

		// If we get results.
		if (
			$events['is_error'] == 0 &&
			isset( $events['values'] ) &&
			count( $events['values'] ) > 0
		) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Are there less items than the step count?
			if ( count( $events['values'] ) < $this->step_counts['event'] ) {
				$diff = count( $events['values'] );
			} else {
				$diff = $this->step_counts['event'];
			}

			// Set from and to flags.
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// Loop.
			foreach ( $events['values'] as $civi_event ) {

				// Do we have an existing Post ID for this Event?
				$existing_event_id = $this->plugin->mapping->get_eo_event_id_by_civi_event_id( $civi_event['id'] );

				// If there's an existing Event, get Occurrence ID.
				$existing_occurrence_id = false;
				if ( ! empty( $existing_event_id ) ) {
					/*
					 * In this context, a CiviCRM Event can only have an Event Organiser Event
					 * with a single Occurrence associated with it, so use first item.
					 */
					$occurrences = eo_get_the_occurrences_of( $existing_event_id );
					$keys = array_keys( $occurrences );
					$existing_occurrence_id = array_pop( $keys );
				}

				// Make an array of params for the pre.
				$args_pre = [
					'post_id' => $existing_event_id,
					'event_id' => $existing_event_id,
					'occurrence_id' => $existing_occurrence_id,
					'civi_event_id' => $civi_event['id'],
					'civi_event' => $civi_event,
				];

				/**
				 * Broadcast that the CiviCRM Event is about to be synced.
				 *
				 * @since 0.7.3
				 *
				 * @param array $args_pre The array of params before the CiviCRM Event is synced.
				 */
				do_action( 'civicrm_event_organiser_admin_civi_to_eo_sync_pre', $args_pre );

				// Update a single Event Organiser Event - or create if it doesn't exist.
				$event_id = $this->plugin->eo->update_event( $civi_event );

				// Skip if there's an error.
				if ( is_wp_error( $event_id ) ) {
					continue;
				}

				// Get Occurrences.
				$occurrences = eo_get_the_occurrences_of( $event_id );

				/*
				 * In this context, a CiviCRM Event can only have an Event Organiser Event
				 * with a single Occurrence associated with it, so use first item.
				 */
				$keys = array_keys( $occurrences );
				$occurrence_id = array_pop( $keys );

				// Store correspondences.
				$this->plugin->mapping->store_event_correspondences( $event_id, [ $occurrence_id => $civi_event['id'] ] );

				// Make an array of params.
				$args = [
					'post_id' => $event_id,
					'event_id' => $event_id,
					'occurrence_id' => $occurrence_id,
					'civi_event_id' => $civi_event['id'],
					'civi_event' => $civi_event,
				];

				/**
				 * Broadcast that the CiviCRM Event has been synced.
				 *
				 * Used internally to:
				 *
				 * * Update the ACF Fields synced via CiviCRM ACF Integration (obsolete)
				 * * Update the ACF Fields synced via CiviCRM Profile Sync
				 *
				 * @since 0.5.2
				 *
				 * @param array $args The array of params.
				 */
				do_action( 'civicrm_event_organiser_admin_civi_to_eo_sync', $args );

			}

			// Increment offset option.
			update_option( '_civi_eo_event_civi_to_eo_offset', (string) $data['to'] );

		} else {

			// Set finished flag.
			$data['finished'] = 'true';

			// Delete the option to start from the beginning.
			delete_option( '_civi_eo_event_civi_to_eo_offset' );

		}

		// Send data to browser.
		wp_send_json( $data );

	}

	// -------------------------------------------------------------------------

	/**
	 * Get all step counts.
	 *
	 * @since 0.7
	 *
	 * @return array $step_counts The array of step counts.
	 */
	public function step_counts_get() {

		/**
		 * Filter the step counts.
		 *
		 * @since 0.7
		 *
		 * @param array $step_counts The default step counts.
		 */
		return apply_filters( 'ceo/manual/step_counts/get', $this->step_counts );

	}

	/**
	 * Get the step count for a given mapping.
	 *
	 * There's no error-checking here. Make sure the $mapping param is correct.
	 *
	 * @since 0.7
	 *
	 * @param string $type The type of mapping.
	 * @return integer $step_count The number of items to sync for this mapping.
	 */
	public function step_count_get( $type ) {

		// Only call getter once.
		static $step_counts = [];

		// Get all step counts.
		if ( empty( $step_counts ) ) {
			$step_counts = $this->step_counts_get();
		}

		// Return the value for the given key.
		return $step_counts[ $type ];

	}

} // Class ends.
