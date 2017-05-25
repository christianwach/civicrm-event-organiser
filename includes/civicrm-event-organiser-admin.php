<?php

/**
 * CiviCRM Event Organiser Admin Class.
 *
 * A class that encapsulates admin functionality.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_Admin {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object
	 */
	public $plugin;

	/**
	 * Plugin version.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var str $plugin_version The plugin version (numeric string)
	 */
	public $plugin_version;

	/**
	 * Parent Page.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var str $parent_page The parent page
	 */
	public $parent_page;

	/**
	 * Settings Page.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var str $settings_page The settings page
	 */
	public $settings_page;

	/**
	 * Manual Sync Page.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var str $sync_page The manual sync page
	 */
	public $sync_page;

	/**
	 * How many items to process per AJAX request.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var object $step_counts The array of item counts to process per AJAX request
	 */
	public $step_counts = array(
		'tax' => 5, // EO category terms & CiviCRM event types
		'venue' => 5, // EO venues & CiviCRM locations
		'event' => 1, // EO events & CiviCRM events
	);



	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// is this the back end?
		if ( is_admin() ) {

			// multisite?
			if ( $this->is_network_activated() ) {

				// add menu to Network submenu
				add_action( 'network_admin_menu', array( $this, 'admin_menu' ), 30 );

			} else {

				// add menu to Settings submenu
				add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );

			}

			// override "no category" option
			add_filter( 'radio-buttons-for-taxonomies-no-term-event-category', array( $this, 'force_taxonomy' ), 30 );

			// add AJAX handlers
			add_action( 'wp_ajax_sync_categories_to_types', array( $this, 'stepped_sync_categories_to_types' ) );
			add_action( 'wp_ajax_sync_types_to_categories', array( $this, 'stepped_sync_types_to_categories' ) );
			add_action( 'wp_ajax_sync_venues_to_locations', array( $this, 'stepped_sync_venues_to_locations' ) );
			add_action( 'wp_ajax_sync_locations_to_venues', array( $this, 'stepped_sync_locations_to_venues' ) );
			add_action( 'wp_ajax_sync_events_eo_to_civi', array( $this, 'stepped_sync_events_eo_to_civi' ) );
			add_action( 'wp_ajax_sync_events_civi_to_eo', array( $this, 'stepped_sync_events_civi_to_eo' ) );

			// initialise
			add_action( 'plugins_loaded', array( $this, 'initialise' ) );

		}

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object
	 */
	public function set_references( $parent ) {

		// store
		$this->plugin = $parent;

	}



	/**
	 * Perform initialisation tasks.
	 *
	 * @since 0.2.4
	 */
	public function initialise() {

		// assign plugin version
		$this->plugin_version = $this->option_get( 'civi_eo_version', false );

		// do upgrade tasks
		$this->upgrade_tasks();

		// has there been a change of version?
		if ( $this->plugin_version != CIVICRM_WP_EVENT_ORGANISER_VERSION ) {

			// store version for later reference
			$this->store_version();

		}

	}



	/**
	 * Utility to add a message to admin pages when an upgrade is required.
	 *
	 * @since 0.2.4
	 */
	public function upgrade_tasks() {

		// bail if this is a new install
		if ( $this->plugin_version === false ) return;

		// check for possibly missing default profile setting
		if ( 'fgffgs' == $this->option_get( 'civi_eo_event_default_profile', 'fgffgs' ) ) {

			// let's show an admin notice
			add_action( 'admin_notices', array( $this, 'upgrade_alert' ) );

		}

	}



	/**
	 * Utility to add a message to admin pages when an upgrade is required.
	 *
	 * @since 0.2.4
	 */
	public function upgrade_alert() {

		// check user permissions
		if ( ! current_user_can( 'manage_options' ) ) return;

		// get current screen
		$screen = get_current_screen();

		// prevent warning if screen not defined
		if ( empty( $screen ) ) return;

		// bail if on our settings page (or parent)
		if ( false !== strpos( $screen->id, 'civi_eo_parent') ) return;
		if ( false !== strpos( $screen->id, 'civi_eo_settings') ) return;

		// get URLs
		$urls = $this->page_get_urls();

		// construct message
		$message = sprintf(
			__( 'CiviCRM Event Organiser needs your attention. Please visit the <a href="%s">Settings Page</a>.', 'civicrm-event-organiser' ),
			$urls['settings']
		);

		// show it
		echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';

	}



	/**
	 * Store the plugin version.
	 *
	 * @since 0.2.4
	 */
	public function store_version() {

		// store version
		$this->option_save( 'civi_eo_version', CIVICRM_WP_EVENT_ORGANISER_VERSION );

	}



	//##########################################################################



	/**
	 * Add admin pages for this plugin.
	 *
	 * @since 0.1
	 */
	public function admin_menu() {

		// we must be network admin in multisite
		if ( is_multisite() AND ! is_super_admin() ) return;

		// check user permissions
		if ( ! current_user_can( 'manage_options' ) ) return;

		// try and update options
		$this->settings_update_router();

		// multisite and network activated?
		if ( $this->is_network_activated() ) {

			// add the admin page to the Network Settings menu
			$this->parent_page = add_submenu_page(
				'settings.php',
				__( 'CiviCRM Event Organiser: Settings', 'civicrm-event-organiser' ), // page title
				__( 'CiviCRM Event Organiser', 'civicrm-event-organiser' ), // menu title
				'manage_options', // required caps
				'civi_eo_parent', // slug name
				array( $this, 'page_settings' ) // callback
			);

		} else {

			// add the admin page to the Settings menu
			$this->parent_page = add_options_page(
				__( 'CiviCRM Event Organiser', 'civicrm-event-organiser' ), // page title
				__( 'CiviCRM Event Organiser', 'civicrm-event-organiser' ), // menu title
				'manage_options', // required caps
				'civi_eo_parent', // slug name
				array( $this, 'page_settings' ) // callback
			);

		}

		// add utilities
		add_action( 'admin_head-' . $this->parent_page, array( $this, 'admin_head' ), 50 );

		// add settings page
		$this->settings_page = add_submenu_page(
			'civi_eo_parent', // parent slug
			__( 'CiviCRM Event Organiser: Settings', 'civicrm-event-organiser' ), // page title
			__( 'Settings', 'civicrm-event-organiser' ), // menu title
			'manage_options', // required caps
			'civi_eo_settings', // slug name
			array( $this, 'page_settings' ) // callback
		);

		// add utilities
		add_action( 'admin_head-' . $this->settings_page, array( $this, 'admin_head' ), 50 );
		add_action( 'admin_head-' . $this->settings_page, array( $this, 'admin_menu_highlight' ), 50 );

		// add manual sync page
		$this->sync_page = add_submenu_page(
			'civi_eo_parent', // parent slug
			__( 'CiviCRM Event Organiser: Manual Sync', 'civicrm-event-organiser' ), // page title
			__( 'Manual Sync', 'civicrm-event-organiser' ), // menu title
			'manage_options', // required caps
			'civi_eo_manual_sync', // slug name
			array( $this, 'page_manual_sync' ) // callback
		);

		// add scripts and styles
		add_action( 'admin_print_styles-' . $this->sync_page, array( $this, 'admin_css_sync_page' ) );
		add_action( 'admin_print_scripts-' . $this->sync_page, array( $this, 'admin_js_sync_page' ) );

		// add utilities
		add_action( 'admin_head-' . $this->sync_page, array( $this, 'admin_head' ), 50 );
		add_action( 'admin_head-' . $this->sync_page, array( $this, 'admin_menu_highlight' ), 50 );

	}



	/**
	 * Tell WordPress to highlight the plugin's menu item, regardless of which
	 * actual admin screen we are on.
	 *
	 * @since 0.2.4
	 *
	 * @global string $plugin_page
	 * @global array $submenu
	 */
	public function admin_menu_highlight() {

		global $plugin_page, $submenu_file;

		// define subpages
		$subpages = array(
		 	'civi_eo_settings',
		 	'civi_eo_manual_sync',
		 );

		// This tweaks the Settings subnav menu to show only one menu item
		if ( in_array( $plugin_page, $subpages ) ) {
			$plugin_page = 'civi_eo_parent';
			$submenu_file = 'civi_eo_parent';
		}

	}



	/**
	 * Initialise plugin help.
	 *
	 * @since 0.1
	 */
	public function admin_head() {

		// grab screen object
		$screen = get_current_screen();

		// prevent warning if screen not defined
		if ( empty( $screen ) ) return;

		// use method in this class
		$this->admin_help( $screen );

	}



	/**
	 * Adds help copy to admin pages.
	 *
	 * @since 0.2.4
	 *
	 * @param object $screen The existing WordPress screen object
	 * @return object $screen The amended WordPress screen object
	 */
	public function admin_help( $screen ) {

		// init suffix
		$page = '';

		// the page ID is different in multisite
		if ( $this->is_network_activated() ) {
			$page = '-network';
		}

		// init page IDs
		$pages = array(
			$this->settings_page . $page,
			$this->sync_page . $page,
		);

		// kick out if not our screen
		if ( ! in_array( $screen->id, $pages ) ) return $screen;

		// add a tab - we can add more later
		$screen->add_help_tab( array(
			'id'      => 'civi_eo',
			'title'   => __( 'CiviCRM Event Organiser', 'civicrm-event-organiser' ),
			'content' => $this->admin_help_text(),
		));

		// --<
		return $screen;

	}



	/**
	 * Get help text.
	 *
	 * @since 0.2.4
	 *
	 * @return string $help Help formatted as HTML
	 */
	public function admin_help_text() {

		// stub help text, to be developed further...
		$help = '<p>' . __( 'For further information about using CiviCRM Event Organiser, please refer to the README.md that comes with this plugin.', 'civicrm-event-organiser' ) . '</p>';

		// --<
		return $help;

	}



	//##########################################################################



	/**
	 * Enqueue any styles needed by our admin "Manual Sync" page.
	 *
	 * @since 0.2.4
	 */
	public function admin_css_sync_page() {

		// add admin css
		wp_enqueue_style(
			'civi_eo_manual_sync_css',
			plugins_url( 'assets/css/civi-eo-manual-sync.css', CIVICRM_WP_EVENT_ORGANISER_FILE ),
			null,
			CIVICRM_WP_EVENT_ORGANISER_VERSION,
			'all' // media
		);

	}



	/**
	 * Enqueue required scripts on the "Manual Sync" page.
	 *
	 * @since 0.2.4
	 */
	public function admin_js_sync_page() {

		// enqueue javascript
		wp_enqueue_script(
			'civi_eo_manual_sync_js',
			plugins_url( 'assets/js/civi-eo-manual-sync.js', CIVICRM_WP_EVENT_ORGANISER_FILE ),
			array( 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ),
			CIVICRM_WP_EVENT_ORGANISER_VERSION // version
		);

		// get all CiviEvent types and error check
		$all_types = $this->plugin->civi->get_event_types();
		if ( $all_types === false ) $all_types['values'] = array();

		// get all EO event category terms
		$all_terms = $this->plugin->eo->get_event_categories();

		// get all Civi Event locations and error check
		$all_locations = $this->plugin->civi->get_all_locations();
		if ( $all_locations['is_error'] == '1' ) $all_locations['values'] = array();

		// get all EO venues
		$all_venues = eo_get_venues();

		// get all Civi Events and error check
		$all_civi_events = $this->plugin->civi->get_all_civi_events();
		if ( $all_civi_events['is_error'] == '1' ) $all_civi_events['values'] = array();

		// get all EO Events
		$all_eo_events = get_posts( array( 'post_type' => 'event', 'numberposts' => -1 ) );

		// init localisation
		$localisation = array(

			// CiviCRM event types
			'event_types' => array(
				'total' => __( '{{total}} event types to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing event types {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing event types {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_types['values'] ),
			),

			// Event Organiser categories
			'categories' => array(
				'total' => __( '{{total}} categories to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing categories {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing categories {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_terms ),
			),

			// CiviCRM locations
			'locations' => array(
				'total' => __( '{{total}} locations to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing locations {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing locations {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_locations['values'] ),
			),

			// Event Organiser venues
			'venues' => array(
				'total' => __( '{{total}} venues to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing venues {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing venues {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_venues ),
			),

			// CiviCRM events
			'civi_events' => array(
				'total' => __( '{{total}} events to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing events {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing events {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_civi_events['values'] ),
			),

			// Event Organiser events
			'eo_events' => array(
				'total' => __( '{{total}} events to sync...', 'civicrm-event-organiser' ),
				'current' => __( 'Processing events {{from}} to {{to}}', 'civicrm-event-organiser' ),
				'complete' => __( 'Processing events {{from}} to {{to}} complete', 'civicrm-event-organiser' ),
				'count' => count( $all_eo_events ),
			),

			// strings common to all
			'common' => array(
				'done' => __( 'All done!', 'civicrm-event-organiser' ),
			),

		);

		// init settings
		$settings = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'step_tax' => $this->step_counts['tax'],
			'step_venue' => $this->step_counts['venue'],
			'step_event' => $this->step_counts['event'],
		);

		// localisation array
		$vars = array(
			'localisation' => $localisation,
			'settings' => $settings,
		);

		// localise the WordPress way
		wp_localize_script(
			'civi_eo_manual_sync_js',
			'CiviCRM_Event_Organiser_Settings',
			$vars
		);

	}



	//##########################################################################



	/**
	 * Show our admin settings page.
	 *
	 * @since 0.2.4
	 */
	public function page_settings() {

		// multisite and network activated?
		if ( $this->is_network_activated() ) {

			// only allow network admins through
			if( ! is_super_admin() ) {
				wp_die( __( 'You do not have permission to access this page.', 'civicrm-event-organiser' ) );
			}

		}

		// check for possibly missing default profile setting
		$profile_required = false;
		if ( 'fgffgs' == $this->option_get( 'civi_eo_event_default_profile', 'fgffgs' ) ) {
			$profile_required = true;
		}

		// get admin page URLs
		$urls = $this->page_get_urls();

		// get all participant roles
		$roles = $this->plugin->civi->get_participant_roles_select( $event = null );

		// get all event types
		$types = $this->plugin->civi->get_event_types_select();

		// get all event registration profiles
		$profiles = $this->plugin->civi->get_registration_profiles_select();

		// include template file
		include( CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/settings.php' );

	}



	/**
	 * Show our admin manual sync page.
	 *
	 * @since 0.2.4
	 */
	public function page_manual_sync() {

		// multisite and network activated?
		if ( $this->is_network_activated() ) {

			// only allow network admins through
			if( ! is_super_admin() ) {
				wp_die( __( 'You do not have permission to access this page.', 'civicrm-event-organiser' ) );
			}

		}

		// get admin page URLs
		$urls = $this->page_get_urls();

		// get all participant roles
		$roles = $this->plugin->civi->get_participant_roles_select( $event = null );

		// get all event types
		$types = $this->plugin->civi->get_event_types_select();

		// include template file
		include( CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/manual-sync.php' );

	}



	/**
	 * Get admin page URLs.
	 *
	 * @since 0.2.4
	 *
	 * @return array $admin_urls The array of admin page URLs
	 */
	public function page_get_urls() {

		// only calculate once
		if ( isset( $this->urls ) ) return $this->urls;

		// init return
		$this->urls = array();

		// multisite?
		if ( $this->is_network_activated() ) {

			// get admin page URLs via our adapted method
			$this->urls['settings'] = $this->network_menu_page_url( 'civi_eo_settings', false );
			$this->urls['manual_sync'] = $this->network_menu_page_url( 'civi_eo_manual_sync', false );

		} else {

			// get admin page URLs
			$this->urls['settings'] = menu_page_url( 'civi_eo_settings', false );
			$this->urls['manual_sync'] = menu_page_url( 'civi_eo_manual_sync', false );

		}

		// --<
		return $this->urls;

	}



	/**
	 * Get the url to access a particular menu page based on the slug it was registered with.
	 * If the slug hasn't been registered properly no url will be returned.
	 *
	 * @since 0.2.4
	 *
	 * @param string $menu_slug The slug name to refer to this menu by (should be unique for this menu)
	 * @param bool $echo Whether or not to echo the url - default is true
	 * @return string $url The URL
	 */
	public function network_menu_page_url( $menu_slug, $echo = true ) {

		global $_parent_pages;

		if ( isset( $_parent_pages[$menu_slug] ) ) {
			$parent_slug = $_parent_pages[$menu_slug];
			if ( $parent_slug && ! isset( $_parent_pages[$parent_slug] ) ) {
				$url = network_admin_url( add_query_arg( 'page', $menu_slug, $parent_slug ) );
			} else {
				$url = network_admin_url( 'admin.php?page=' . $menu_slug );
			}
		} else {
			$url = '';
		}

		$url = esc_url( $url );

		if ( $echo ) echo $url;

		// --<
		return $url;

	}



	/**
	 * Get the URL for the form action.
	 *
	 * @since 0.2.4
	 *
	 * @return string $target_url The URL for the admin form action
	 */
	public function admin_form_url_get() {

		// sanitise admin page url
		$target_url = $_SERVER['REQUEST_URI'];
		$url_array = explode( '&', $target_url );
		if ( $url_array ) { $target_url = htmlentities( $url_array[0] . '&updated=true' ); }

		// --<
		return $target_url;

	}



	//##########################################################################



	/**
	 * Route settings updates to relevant methods.
	 *
	 * @since 0.2.4
	 *
	 * @return bool $result True on success, false otherwise
	 */
	public function settings_update_router() {

		// was the "Settings" form submitted?
		if ( isset( $_POST['civi_eo_settings_submit'] ) ) {
			$this->settings_update();
			return;
		}

	 	// was an Event Type "Stop Sync" button pressed?
		if ( isset( $_POST['civi_eo_tax_eo_to_civi_stop'] ) ) {
			delete_option( '_civi_eo_tax_eo_to_civi_offset' );
			return;
		}
		if ( isset( $_POST['civi_eo_tax_civi_to_eo_stop'] ) ) {
			delete_option( '_civi_eo_tax_civi_to_eo_offset' );
			return;
		}

	 	// was a Venue "Stop Sync" button pressed?
		if ( isset( $_POST['civi_eo_venue_eo_to_civi_stop'] ) ) {
			delete_option( '_civi_eo_venue_eo_to_civi_offset' );
			return;
		}
		if ( isset( $_POST['civi_eo_venue_civi_to_eo_stop'] ) ) {
			delete_option( '_civi_eo_venue_civi_to_eo_offset' );
			return;
		}

	 	// was an Event "Stop Sync" button pressed?
		if ( isset( $_POST['civi_eo_event_eo_to_civi_stop'] ) ) {
			delete_option( '_civi_eo_event_eo_to_civi_offset' );
			return;
		}
		if ( isset( $_POST['civi_eo_event_civi_to_eo_stop'] ) ) {
			delete_option( '_civi_eo_event_civi_to_eo_offset' );
			return;
		}

		// was an Event Type "Sync Now" button pressed?
		if ( isset( $_POST['civi_eo_tax_eo_to_civi'] ) ) {
			$this->stepped_sync_categories_to_types();
		}
		if ( isset( $_POST['civi_eo_tax_civi_to_eo'] ) ) {
			$this->stepped_sync_types_to_categories();
		}

		// was a Venue "Sync Now" button pressed?
		if ( isset( $_POST['civi_eo_venue_eo_to_civi'] ) ) {
			$this->stepped_sync_venues_to_locations();
		}
		if ( isset( $_POST['civi_eo_venue_civi_to_eo'] ) ) {
			$this->stepped_sync_locations_to_venues();
		}

		// was an Event "Sync Now" button pressed?
		if ( isset( $_POST['civi_eo_event_eo_to_civi'] ) ) {
			$this->stepped_sync_events_eo_to_civi();
		}
		if ( isset( $_POST['civi_eo_event_civi_to_eo'] ) ) {
			$this->stepped_sync_events_civi_to_eo();
		}

	}



	/**
	 * Update plugin settings.
	 *
	 * @since 0.2.4
	 */
	public function settings_update() {

		// check that we trust the source of the data
		check_admin_referer( 'civi_eo_settings_action', 'civi_eo_settings_nonce' );

		// rebuild broken correspondences in 0.1
		$this->rebuild_event_correspondences();

		// init vars
		$civi_eo_event_default_role = '0';
		$civi_eo_event_default_type = '0';
		$civi_eo_event_default_profile = '0';

		// get variables
		extract( $_POST );

		// sanitise
		$civi_eo_event_default_role = absint( $civi_eo_event_default_role );

		// save option
		$this->option_save( 'civi_eo_event_default_role', $civi_eo_event_default_role );

		// sanitise
		$civi_eo_event_default_type = absint( $civi_eo_event_default_type );

		// save option
		$this->option_save( 'civi_eo_event_default_type', $civi_eo_event_default_type );

		// sanitise
		$civi_eo_event_default_profile = absint( $civi_eo_event_default_profile );

		// save option
		$this->option_save( 'civi_eo_event_default_profile', $civi_eo_event_default_profile );

		/**
		 * Broadcast end of settings update.
		 *
		 * @since 0.3.1
		 */
		do_action( 'civicrm_event_organiser_settings_updated' );

	}



	//##########################################################################



	/**
	 * Stepped synchronisation of EO category terms to CiviCRM event types.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_categories_to_types() {

		// init AJAX return
		$data = array();

		// if the offset value doesn't exist
		if ( 'fgffgs' == get_option( '_civi_eo_tax_eo_to_civi_offset', 'fgffgs' ) ) {

			// start at the beginning
			$offset = 0;
			add_option( '_civi_eo_tax_eo_to_civi_offset', '0' );

		} else {

			// use the existing value
			$offset = intval( get_option( '_civi_eo_tax_eo_to_civi_offset', '0' ) );

		}

		// since WordPress 4.5.0, the category is specified in the arguments
		if ( function_exists( 'unregister_taxonomy' ) ) {

			// construct args
			$args = array(
				'taxonomy' => 'event-category',
				'orderby' => 'count',
				'hide_empty' => 0,
				'number' => $this->step_counts['tax'],
				'offset' => $offset,
			);

			// get all terms
			$terms = get_terms( $args );

		} else {

			// construct args
			$args = array(
				'orderby' => 'count',
				'hide_empty' => 0,
				'number' => $this->step_counts['tax'],
				'offset' => $offset,
			);

			// get all terms
			$terms = get_terms( 'event-category', $args );

		}

		// if we get results
		if ( count( $terms ) > 0 ) {

			// set finished flag
			$data['finished'] = 'false';

			// are there less items than the step count?
			if ( count( $terms ) < $this->step_counts['tax'] ) {
				$diff = count( $terms );
			} else {
				$diff = $this->step_counts['tax'];
			}

			// set from and to flags
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// sync each event term in turn
			foreach( $terms AS $term ) {

				// update CiviEvent term - or create if it doesn't exist
				$civi_event_type_id = $this->plugin->civi->update_event_type( $term );

				// next on failure
				if ( $civi_event_type_id === false ) {

					// log failed event term first
					error_log( print_r( array(
						'method' => __METHOD__,
						'message' => __( 'Could not sync Event Term', 'civicrm-event-organiser' ),
						'term' => $term,
					), true ) );

					continue;

				}

			}

			// increment offset option
			update_option( '_civi_eo_tax_eo_to_civi_offset', (string) $data['to'] );

		} else {

			// set finished flag
			$data['finished'] = 'true';

			// delete the option to start from the beginning
			delete_option( '_civi_eo_tax_eo_to_civi_offset' );

		}

		// send data to browser
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of CiviCRM event types to EO category terms.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_types_to_categories() {

		// init AJAX return
		$data = array();

		// if the offset value doesn't exist
		if ( 'fgffgs' == get_option( '_civi_eo_tax_civi_to_eo_offset', 'fgffgs' ) ) {

			// start at the beginning
			$offset = 0;
			add_option( '_civi_eo_tax_civi_to_eo_offset', '0' );

		} else {

			// use the existing value
			$offset = intval( get_option( '_civi_eo_tax_civi_to_eo_offset', '0' ) );

		}

		// get option group ID and error check
		$opt_group_id = $this->plugin->civi->get_event_types_optgroup_id();
		if ( $opt_group_id !== false ) {

			// get event types (descriptions will be present if not null)
			$types = civicrm_api( 'option_value', 'get', array(
				'option_group_id' => $opt_group_id,
				'version' => 3,
				'options' => array(
					'limit' => $this->step_counts['tax'],
					'offset' => $offset,
					'sort' => 'weight ASC',
				),
			) );

		} else {

			// do not allow progress
			$types['is_error'] = 1;

		}

		// if we get results
		if (
			$types['is_error'] == 0 AND
			isset( $types['values'] ) AND
			count( $types['values'] ) > 0
		) {

			// set finished flag
			$data['finished'] = 'false';

			// are there less items than the step count?
			if ( count( $types['values'] ) < $this->step_counts['tax'] ) {
				$diff = count( $types['values'] );
			} else {
				$diff = $this->step_counts['tax'];
			}

			// set from and to flags
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// sync each event type in turn
			foreach( $types['values'] AS $type ) {

				// update CiviEvent term - or create if it doesn't exist
				$eo_term_id = $this->plugin->eo->update_term( $type );

				// next on failure
				if ( $eo_term_id === false ) {

					// log failed event type first
					error_log( print_r( array(
						'method' => __METHOD__,
						'message' => __( 'Could not sync Event Type', 'civicrm-event-organiser' ),
						'type' => $type,
					), true ) );

					continue;

				}

			}

			// increment offset option
			update_option( '_civi_eo_tax_civi_to_eo_offset', (string) $data['to'] );

		} else {

			// set finished flag
			$data['finished'] = 'true';

			// delete the option to start from the beginning
			delete_option( '_civi_eo_tax_civi_to_eo_offset' );

		}

		// send data to browser
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of EO venues to CiviCRM locations.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_venues_to_locations() {

		// init AJAX return
		$data = array();

		// if the offset value doesn't exist
		if ( 'fgffgs' == get_option( '_civi_eo_venue_eo_to_civi_offset', 'fgffgs' ) ) {

			// start at the beginning
			$offset = 0;
			add_option( '_civi_eo_venue_eo_to_civi_offset', '0' );

		} else {

			// use the existing value
			$offset = intval( get_option( '_civi_eo_venue_eo_to_civi_offset', '0' ) );

		}

		// get venues
		$venues = eo_get_venues( array(
			'number' => $this->step_counts['venue'],
			'offset' => $offset,
		) );

		// if we get results
		if ( count( $venues ) > 0 ) {

			// set finished flag
			$data['finished'] = 'false';

			// are there less items than the step count?
			if ( count( $venues ) < $this->step_counts['venue'] ) {
				$diff = count( $venues );
			} else {
				$diff = $this->step_counts['venue'];
			}

			// set from and to flags
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// loop
			foreach( $venues AS $venue ) {

				/*
				 * Manually add venue metadata because since EO version 3.0 it is
				 * no longer added by default to the venue object.
				 *
				 * @see https://github.com/stephenharris/Event-Organiser/commit/646220b336ba9c49d12bd17f5992e1391d0b411f
				 */
				$venue_id = (int) $venue->term_id;
				$address  = eo_get_venue_address( $venue_id );

				$venue->venue_address  = isset( $address['address'] ) ? $address['address'] : '';
				$venue->venue_postal   = isset( $address['postcode'] ) ? $address['postcode'] : '';
				$venue->venue_postcode = isset( $address['postcode'] ) ? $address['postcode'] : '';
				$venue->venue_city     = isset( $address['city'] ) ? $address['city'] : '';
				$venue->venue_country  = isset( $address['country'] ) ? $address['country'] : '';
				$venue->venue_state    = isset( $address['state'] ) ? $address['state'] : '';

				$venue->venue_lat = number_format( floatval( eo_get_venue_lat( $venue_id ) ), 6 );
				$venue->venue_lng = number_format( floatval( eo_get_venue_lng( $venue_id ) ), 6 );

				// update Civi location - or create if it doesn't exist
				$location = $this->plugin->civi->update_location( $venue );

				// store in EO venue
				$this->plugin->eo_venue->store_civi_location( $venue->term_id, $location );

			}

			// increment offset option
			update_option( '_civi_eo_venue_eo_to_civi_offset', (string) $data['to'] );

		} else {

			// set finished flag
			$data['finished'] = 'true';

			// delete the option to start from the beginning
			delete_option( '_civi_eo_venue_eo_to_civi_offset' );

		}

		// send data to browser
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of CiviCRM locations to EO venues.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_locations_to_venues() {

		// init AJAX return
		$data = array();

		// if the offset value doesn't exist
		if ( 'fgffgs' == get_option( '_civi_eo_venue_civi_to_eo_offset', 'fgffgs' ) ) {

			// start at the beginning
			$offset = 0;
			add_option( '_civi_eo_venue_civi_to_eo_offset', '0' );

		} else {

			// use the existing value
			$offset = intval( get_option( '_civi_eo_venue_civi_to_eo_offset', '0' ) );

		}

		// init CiviCRM
		if ( $this->plugin->civi->is_active() ) {

			// get CiviCRM locations
			$locations = civicrm_api( 'loc_block', 'get', array(
				'version' => '3',
				'return' => 'all',
				'options' => array(
					'limit' => $this->step_counts['tax'],
					'offset' => $offset,
				),
			));

		} else {

			// do not allow progress
			$locations['is_error'] = 1;

		}

		// if we get results
		if (
			$locations['is_error'] == 0 AND
			isset( $locations['values'] ) AND
			count( $locations['values'] ) > 0
		) {

			// set finished flag
			$data['finished'] = 'false';

			// are there less items than the step count?
			if ( count( $locations['values'] ) < $this->step_counts['venue'] ) {
				$diff = count( $locations['values'] );
			} else {
				$diff = $this->step_counts['venue'];
			}

			// set from and to flags
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// loop
			foreach( $locations['values'] AS $location ) {

				// update EO venue - or create if it doesn't exist
				$this->plugin->eo_venue->update_venue( $location );

			}

			// increment offset option
			update_option( '_civi_eo_venue_civi_to_eo_offset', (string) $data['to'] );

		} else {

			// set finished flag
			$data['finished'] = 'true';

			// delete the option to start from the beginning
			delete_option( '_civi_eo_venue_civi_to_eo_offset' );

		}

		// send data to browser
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of EO events to CiviCRM events.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_events_eo_to_civi() {

		// init AJAX return
		$data = array();

		// if the offset value doesn't exist
		if ( 'fgffgs' == get_option( '_civi_eo_event_eo_to_civi_offset', 'fgffgs' ) ) {

			// start at the beginning
			$offset = 0;
			add_option( '_civi_eo_event_eo_to_civi_offset', '0' );

		} else {

			// use the existing value
			$offset = intval( get_option( '_civi_eo_event_eo_to_civi_offset', '0' ) );

		}

		// get "primary" events (i.e. not ordered by occurrence)
		$events = eo_get_events( array(
			'numberposts' => $this->step_counts['event'],
			'offset' => $offset,
			'group_events_by' => 'series',
		) );

		// if we get results
		if ( count( $events ) > 0 ) {

			// set finished flag
			$data['finished'] = 'false';

			// are there less items than the step count?
			if ( count( $events ) < $this->step_counts['event'] ) {
				$diff = count( $events );
			} else {
				$diff = $this->step_counts['event'];
			}

			// set from and to flags
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// prevent recursion
			remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_created' ), 10 );
			remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_updated' ), 10 );
			remove_action( 'civicrm_post', array( $this->plugin->civi, 'event_deleted' ), 10 );

			// loop
			foreach( $events AS $event ) {

				// get dates for this event
				$dates = $this->plugin->eo->get_all_dates( $event->ID );

				// update CiviEvent - or create if it doesn't exist
				$this->plugin->civi->update_civi_events( $event, $dates );

			}

			// restore hooks
			add_action( 'civicrm_post', array( $this->plugin->civi, 'event_created' ), 10, 4 );
			add_action( 'civicrm_post', array( $this->plugin->civi, 'event_updated' ), 10, 4 );
			add_action( 'civicrm_post', array( $this->plugin->civi, 'event_deleted' ), 10, 4 );

			// increment offset option
			update_option( '_civi_eo_event_eo_to_civi_offset', (string) $data['to'] );

		} else {

			// set finished flag
			$data['finished'] = 'true';

			// delete the option to start from the beginning
			delete_option( '_civi_eo_event_eo_to_civi_offset' );

		}

		// send data to browser
		$this->send_data( $data );

	}



	/**
	 * Stepped synchronisation of CiviCRM events to EO events.
	 *
	 * @since 0.2.4
	 */
	public function stepped_sync_events_civi_to_eo() {

		// init AJAX return
		$data = array();

		// if the offset value doesn't exist
		if ( 'fgffgs' == get_option( '_civi_eo_event_civi_to_eo_offset', 'fgffgs' ) ) {

			// start at the beginning
			$offset = 0;
			add_option( '_civi_eo_event_civi_to_eo_offset', '0' );

		} else {

			// use the existing value
			$offset = intval( get_option( '_civi_eo_event_civi_to_eo_offset', '0' ) );

		}

		// init CiviCRM
		if ( $this->plugin->civi->is_active() ) {

			// get CiviCRM events
			$events = civicrm_api( 'event', 'get', array(
				'version' => 3,
				'is_template' => 0,
				'options' => array(
					'limit' => $this->step_counts['event'],
					'offset' => $offset,
				),
			) );

		} else {

			// do not allow progress
			$events['is_error'] = 1;

		}

		// if we get results
		if (
			$events['is_error'] == 0 AND
			isset( $events['values'] ) AND
			count( $events['values'] ) > 0
		) {

			// set finished flag
			$data['finished'] = 'false';

			// are there less items than the step count?
			if ( count( $events['values'] ) < $this->step_counts['event'] ) {
				$diff = count( $events['values'] );
			} else {
				$diff = $this->step_counts['event'];
			}

			// set from and to flags
			$data['from'] = intval( $offset );
			$data['to'] = $data['from'] + $diff;

			// loop
			foreach( $events['values'] AS $civi_event ) {

				// update a single EO event - or create if it doesn't exist
				$event_id = $this->plugin->eo->update_event( $civi_event );

				// get occurrences
				$occurrences = eo_get_the_occurrences_of( $event_id );

				// in this context, a CiviEvent can only have an EO event with a
				// single occurrence associated with it, so use first item
				$keys = array_keys( $occurrences );
				$occurrence_id = array_pop( $keys );

				// store correspondences
				$this->store_event_correspondences( $event_id, array( $occurrence_id => $civi_event['id'] ) );

			}

			// increment offset option
			update_option( '_civi_eo_event_civi_to_eo_offset', (string) $data['to'] );

		} else {

			// set finished flag
			$data['finished'] = 'true';

			// delete the option to start from the beginning
			delete_option( '_civi_eo_event_civi_to_eo_offset' );

		}

		// send data to browser
		$this->send_data( $data );

	}



	//##########################################################################



	/*
	----------------------------------------------------------------------------
	Correspondences are stored using existing data structures. This imposes some
	limitations on us. Ideally, I suppose, this plugin would define its own table
	for the correspondences, but the existing tables will work.

	(a) A CiviEvent needs to know which post ID and which occurrence ID it is synced with.
	(b) An EO event (post) needs to know the CiviEvents which are synced with it.
	(c) An EO occurrence needs to know which CiviEvent it is synced with.

	So, given that CiviCRM seems to have no meta storage for CiviEvents, use a
	WordPress option to store this data. We can now query the data by CiviEvent ID
	and retrieve post ID and occurrence ID. The array looks like:

	array(
		$civi_event_id => array(
			'post_id' => $post_id,
			'occurrence_id' => $occurrence_id,
		),
		$civi_event_id => array(
			'post_id' => $post_id,
			'occurrence_id' => $occurrence_id,
		),
		...
	)

	In the reverse situation, we store an array of correspondences as post meta.
	We will need to know the post ID to get it. The array looks like:

	array(
		$occurrence_id => $civi_event_id,
		$occurrence_id => $civi_event_id,
		$occurrence_id => $civi_event_id,
		...
	)

	In practice, however, if the sequence changes, then EO regenerates the
	occurrences anyway, so our correspondences need to be rebuilt when that
	happens. This makes the occurrence_id linkage useful only when sequences are
	broken.

	There is an additional "orphans" array, so that when occurrences are added
	(or added back) to a sequence, the corresponding CiviEvent may be reconnected
	as long as none of its date and time data has changed.
	----------------------------------------------------------------------------
	*/



	/**
	 * Clears all CiviEvents <-> Event Organiser event data.
	 *
	 * @since 0.1
	 */
	public function clear_all_correspondences() {

		// construct args for all event posts
		$args = array(
			'post_type' => 'event',
			'numberposts' => -1,
		);

		// get all event posts
		$all_events = get_posts( $args );

		// did we get any?
		if ( count( $all_events ) > 0 ) {

			// loop
			foreach( $all_events AS $event ) {

				// delete post meta
				delete_post_meta( $post_id, '_civi_eo_civicrm_events' );
				delete_post_meta( $post_id, '_civi_eo_civicrm_events_disabled' );

			}

		}

		// overwrite event_disabled array
		$this->option_save( 'civi_eo_civi_event_disabled', array() );

		// overwrite EO to CiviCRM data
		$this->option_save( 'civi_eo_civi_event_data', array() );

	}



	/**
	 * Rebuilds all CiviEvents <-> Event Organiser event data.
	 *
	 * @since 0.1
	 */
	public function rebuild_event_correspondences() {

		// only applies to version 0.1
		if ( CIVICRM_WP_EVENT_ORGANISER_VERSION != '0.1' ) return;

		// only rely on the EO event correspondences, because of a bug in the
		// 0.1 version of the plugin which overwrote the civi_to_eo array
		$eo_to_civi = $this->get_all_eo_to_civi_correspondences();

		// kick out if we get none
		if ( count( $eo_to_civi ) === 0 ) return;

		// init CiviCRM correspondence array to be stored as option
		$civi_correspondences = array();

		// loop through the data
		foreach( $eo_to_civi AS $event_id => $civi_event_ids ) {

			// get occurrences
			$occurrences = eo_get_the_occurrences_of( $event_id );

			// init EO correspondence array
			$eo_correspondences = array();

			// init counter
			$n = 0;

			// loop through them
			foreach( $occurrences AS $occurrence_id => $data ) {

				// add CiviEvent ID to EO correspondences
				$eo_correspondences[$occurrence_id] = $civi_event_ids[$n];

				// add EO event ID to CiviCRM correspondences
				$civi_correspondences[$civi_event_ids[$n]] = array(
					'post_id' => $event_id,
					'occurrence_id' => $occurrence_id,
				);

				// increment counter
				$n++;

			}

			// replace our post meta
			update_post_meta( $event_id, '_civi_eo_civicrm_events', $eo_correspondences );

		}

		// replace our option
		$this->option_save( 'civi_eo_civi_event_data', $civi_correspondences );

	}



	/**
	 * Store CiviEvents <-> Event Organiser event data.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param array $correspondences CiviEvent IDs, keyed by EO occurrence ID
	 * @param array $unlinked CiviEvent IDs that have been orphaned from an EO event
	 */
	public function store_event_correspondences( $post_id, $correspondences, $unlinked = array() ) {

		// an EO event needs to know the IDs of all the CiviEvents, keyed by EO occurrence ID
		update_post_meta( $post_id, '_civi_eo_civicrm_events', $correspondences );

		// init array with stored value (or empty array)
		$civi_event_data = $this->option_get( 'civi_eo_civi_event_data', array() );

		// each CiviEvent needs to know the IDs of the EO post and the EO occurrence
		if ( count( $correspondences ) > 0 ) {

			// construct array
			foreach( $correspondences AS $occurrence_id => $civi_event_id ) {

				// add post ID and occurrence ID, keyed by CiviEvent ID
				$civi_event_data[$civi_event_id] = array(
					'post_id' => $post_id,
					'occurrence_id' => $occurrence_id,
				);

			}

		}

		// store updated array as option
		$this->option_save( 'civi_eo_civi_event_data', $civi_event_data );

		// finally, store orphaned CiviEvents
		$this->store_orphaned_events( $post_id, $unlinked );

	}



	/**
	 * Get all event correspondences.
	 *
	 * @since 0.1
	 *
	 * @return array $correspondences All CiviEvent - Event Organiser correspondences
	 */
	public function get_all_event_correspondences() {

		// init return
		$correspondences = array();

		// add "CiviCRM to EO"
		$correspondences['civi_to_eo'] = $this->get_all_civi_to_eo_correspondences();

		// add "EO to CiviCRM"
		$correspondences['eo_to_civi'] = $this->get_all_eo_to_civi_correspondences();

		// --<
		return $correspondences;

	}



	/**
	 * Get all Event Organiser events for all CiviEvents.
	 *
	 * @since 0.1
	 *
	 * @return array $eo_event_data all CiviEvent IDs
	 */
	public function get_all_civi_to_eo_correspondences() {

		// store once
		static $eo_event_data;

		// have we done this?
		if ( ! isset( $eo_event_data ) ) {

			// get option
			$eo_event_data = $this->option_get( 'civi_eo_civi_event_data', array() );

		}

		// --<
		return $eo_event_data;

	}



	/**
	 * Get all CiviEvents for all Event Organiser events.
	 *
	 * @since 0.1
	 *
	 * @return array $civi_event_data All CiviEvent IDs
	 */
	public function get_all_eo_to_civi_correspondences() {

		// init civi data
		$civi_event_data = array();

		// construct args for all event posts
		$args = array(
			'post_type' => 'event',
			'numberposts' => -1,
		);

		// get all event posts
		$all_events = get_posts( $args );

		// did we get any?
		if ( count( $all_events ) > 0 ) {

			// loop
			foreach( $all_events AS $event ) {

				// get post meta and add to return array
				$civi_event_data[$event->ID] = $this->get_civi_event_ids_by_eo_event_id( $event->ID );

			}

		}

		// --<
		return $civi_event_data;

	}



	/**
	 * Delete the correspondence between an Event Organiser occurrence and a CiviEvent.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param int $occurrence_id The numeric ID of the EO event occurrence
	 */
	public function clear_event_correspondence( $post_id, $occurrence_id ) {

		// get CiviEvent ID
		$civi_event_id = $this->get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id );

		// get all CiviEvent data held in option
		$civi_event_data = $this->get_all_civi_to_eo_correspondences();

		// if we have a CiviEvent ID for this EO occurrence
		if ( $civi_event_id !== false ) {

			// unset the item with this key in the option array
			unset( $civi_event_data[$civi_event_id] );

			// store updated array
			$this->option_save( 'civi_eo_civi_event_data', $civi_event_data );

		}

		// get existing "live"
		$correspondences = $this->get_civi_event_ids_by_eo_event_id( $post_id );

		// is the CiviEvent in the "live" array?
		if ( in_array( $civi_event_id, $correspondences ) ) {

			// ditch the current CiviEvent ID
			$correspondences = array_diff( $correspondences, array( $civi_event_id ) );

			// update the meta value
			update_post_meta( $post_id, '_civi_eo_civicrm_events', $correspondences );

			// no need to go further
			return;

		}

		// get existing "orphans"
		$orphans = $this->get_orphaned_events_by_eo_event_id( $post_id );

		// is the CiviEvent in the "orphans" array?
		if ( in_array( $civi_event_id, $orphans ) ) {

			// ditch the current CiviEvent ID
			$orphans = array_diff( $orphans, array( $civi_event_id ) );

			// update the meta value
			update_post_meta( $post_id, '_civi_eo_civicrm_events_disabled', $orphans );

		}

	}



	/**
	 * Delete all correspondences between an Event Organiser event and CiviEvents.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 */
	public function clear_event_correspondences( $post_id ) {

		// get CiviEvent IDs from post meta
		$civi_event_ids = $this->get_civi_event_ids_by_eo_event_id( $post_id );

		// get CiviEvent data held in option
		$civi_event_data = $this->get_all_civi_to_eo_correspondences();

		// if we have some CiviEvent IDs for this EO event
		if ( count( $civi_event_ids ) > 0 ) {

			// loop
			foreach( $civi_event_ids AS $civi_event_id ) {

				// unset the item with this key in the option array
				unset( $civi_event_data[$civi_event_id] );

			}

			// store updated array
			$this->option_save( 'civi_eo_civi_event_data', $civi_event_data );

		}

		// now we can delete the array held in post meta
		delete_post_meta( $post_id, '_civi_eo_civicrm_events' );

		// also delete the array of orphans held in post meta
		delete_post_meta( $post_id, '_civi_eo_civicrm_events_disabled' );

	}



	/**
	 * Get Event Organiser event ID for a CiviEvent event ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_event_id The numeric ID of a CiviEvent event
	 * @return mixed $eo_event_id The numeric ID of the Event Organiser event (or false on failure)
	 */
	public function get_eo_event_id_by_civi_event_id( $civi_event_id ) {

		// init return
		$eo_event_id = false;

		// get all correspondences
		$eo_event_data = $this->get_all_civi_to_eo_correspondences();

		// if we get some...
		if ( count( $eo_event_data ) > 0 ) {

			// do we have the key?
			if ( isset( $eo_event_data[$civi_event_id] ) ) {

				// get keyed value
				$eo_event_id = $eo_event_data[$civi_event_id]['post_id'];

			}

		}

		// --<
		return $eo_event_id;

	}



	/**
	 * Get Event Organiser occurrence ID for a CiviEvent event ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_event_id The numeric ID of a CiviEvent event
	 * @return mixed $eo_occurrence_id The numeric ID of the Event Organiser occurrence (or false on failure)
	 */
	public function get_eo_occurrence_id_by_civi_event_id( $civi_event_id ) {

		// init return
		$eo_occurrence_id = false;

		// get all correspondences
		$eo_event_data = $this->get_all_civi_to_eo_correspondences();

		// if we get some...
		if ( count( $eo_event_data ) > 0 ) {

			// do we have the key?
			if ( isset( $eo_event_data[$civi_event_id] ) ) {

				// get keyed value
				$eo_occurrence_id = $eo_event_data[$civi_event_id]['occurrence_id'];

			}

		}

		// --<
		return $eo_occurrence_id;

	}



	/**
	 * Get CiviEvent IDs (keyed by occurrence ID) for an Event Organiser event ID.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @return array $civi_event_ids All CiviEvent IDs for the post, keyed by occurrence ID
	 */
	public function get_civi_event_ids_by_eo_event_id( $post_id ) {

		// get the meta value
		$civi_event_ids = get_post_meta( $post_id, '_civi_eo_civicrm_events', true );

		// if it's not yet set it will be an empty string, so cast as array
		if ( $civi_event_ids === '' ) { $civi_event_ids = array(); }

		// --<
		return $civi_event_ids;

	}



	/**
	 * Get CiviEvent ID for an Event Organiser event occurrence.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param int $occurrence_id The numeric ID of the EO event occurrence
	 * @return mixed $civi_event_id The CiviEvent ID (or false otherwise)
	 */
	public function get_civi_event_id_by_eo_occurrence_id( $post_id, $occurrence_id ) {

		// get the meta value
		$civi_event_ids = $this->get_civi_event_ids_by_eo_event_id( $post_id );

		// return false if none present
		if ( count( $civi_event_ids ) === 0 ) return false;

		// get value
		$civi_event_id = isset( $civi_event_ids[$occurrence_id] ) ? $civi_event_ids[$occurrence_id]: false;

		// --<
		return $civi_event_id;

	}



	/**
	 * Check if a CiviEvent is part of an Event Organiser event sequence.
	 *
	 * @since 0.3
	 *
	 * @param int $civi_event_id The CiviEvent ID
	 * @return bool True if CiviEvent is part of an EO event sequence, false otherwise
	 */
	public function is_civi_event_in_eo_sequence( $civi_event_id ) {

		// get the EO event ID for this CiviEvent
		$eo_post_id = $this->get_eo_event_id_by_civi_event_id( $civi_event_id );

		// if there is one
		if ( $eo_post_id !== false ) {

			// get the corresponding CiviEvents
			$civi_event_ids = $this->get_civi_event_ids_by_eo_event_id( $eo_post_id );

			// does the EO event have multiple CiviEvents?
			if ( count( $civi_event_ids ) > 1 ) {

				// yes, this CiviEvent is part of a series
				return true;

			}

		}

		// fallback
		return false;

	}



	//##########################################################################



	/**
	 * Store orphaned CiviEvents.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param array $unlinked CiviEvent IDs that have been orphaned from an EO event
	 */
	public function store_orphaned_events( $post_id, $orphans ) {

		// get existing orphans before we update
		$existing = $this->get_orphaned_events_by_eo_event_id( $post_id );

		// an EO event needs to know the IDs of all the orphaned CiviEvents
		update_post_meta( $post_id, '_civi_eo_civicrm_events_disabled', $orphans );

		// get the values that are not present in new orphans
		$to_remove = array_diff( $existing, $orphans );

		// get the values that are not present in existing
		$to_add = array_diff( $orphans, $existing );

		// init array with stored value (or empty array)
		$civi_event_disabled = $this->option_get( 'civi_eo_civi_event_disabled', array() );

		// do we have any orphans to add?
		if ( count( $to_add ) > 0 ) {

			// construct array
			foreach( $to_add AS $civi_event_id ) {

				// add post ID, keyed by CiviEvent ID
				$civi_event_disabled[$civi_event_id] = $post_id;

			}

		}

		// do we have any orphans to remove?
		if ( count( $to_remove ) > 0 ) {

			// construct array
			foreach( $to_remove AS $civi_event_id ) {

				// delete it from the data array
				unset( $civi_event_disabled[$civi_event_id] );

			}

		}

		// store updated array as option
		$this->option_save( 'civi_eo_civi_event_disabled', $civi_event_disabled );

	}



	/**
	 * Make a single occurrence orphaned.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post = EO event
	 * @param int $occurrence_id The numeric ID of the EO event occurrence
	 * @param int $civi_event_id The numeric ID of the orphaned CiviEvent
	 */
	public function occurrence_orphaned( $post_id, $occurrence_id, $civi_event_id ) {

		// get existing orphans for this post
		$existing_orphans = $this->get_orphaned_events_by_eo_event_id( $post_id );

		// get existing "live" correspondences
		$correspondences = $this->get_civi_event_ids_by_eo_event_id( $post_id );

		// add the current orphan
		$existing_orphans[] = $civi_event_id;

		// safely remove it from live
		if ( isset( $correspondences[$occurrence_id] ) ) {
			unset( $correspondences[$occurrence_id] );
		}

		// store updated correspondences and orphans
		$this->store_event_correspondences( $post_id, $correspondences, $existing_orphans );

	}



	/**
	 * Get orphaned CiviEvents by EO event ID.
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The numeric ID of the WP post = EO event
	 * @return array $civi_event_ids Array of orphaned CiviEvent IDs
	 */
	public function get_orphaned_events_by_eo_event_id( $post_id ) {

		// get the meta value
		$civi_event_ids = get_post_meta( $post_id, '_civi_eo_civicrm_events_disabled', true );

		// if it's not yet set it will be an empty string, so cast as array
		if ( $civi_event_ids === '' ) { $civi_event_ids = array(); }

		// --<
		return $civi_event_ids;

	}



	/**
	 * Get all Event Organiser event IDs for all orphaned CiviEvents.
	 *
	 * @since 0.1
	 *
	 * @return array $civi_event_disabled All CiviEvent IDs
	 */
	public function get_eo_event_ids_for_orphans() {

		// return option
		return $this->option_get( 'civi_eo_civi_event_disabled', array() );

	}



	/**
	 * Get EO event ID by orphaned CiviEvent ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_event_id The numeric ID of the CiviEvent
	 * @return int $eo_event_id The numeric ID of the WP post = EO event
	 */
	public function get_eo_event_id_by_orphaned_event_id( $civi_event_id ) {

		// init return
		$eo_event_id = false;

		// get all orphan data
		$eo_event_data = $this->get_eo_event_ids_for_orphans();

		// if we get some...
		if ( count( $eo_event_data ) > 0 ) {

			// do we have the key?
			if ( isset( $eo_event_data[$civi_event_id] ) ) {

				// get keyed value
				$eo_event_id = $eo_event_data[$civi_event_id];

			}

		}

		// --<
		return $eo_event_id;

	}



	//##########################################################################



	/**
	 * Debugging method that shows all events.
	 *
	 * @since 0.1
	 */
	public function show_eo_civi_events() {

		// construct args for all event posts
		$args = array(
			'post_type' => 'event',
			'numberposts' => -1,
		);

		// get all event posts
		$all_events = get_posts( $args );

		// get all EO events
		$all_eo_events = eo_get_events();

		// get all CiviCRM Events
		$all_civi_events = $this->plugin->civi->get_all_civi_events();

		// init
		$delete = array();

		// delete all?
		if ( 1 === 2 ) {

			// error check
			if ( $all_civi_events['is_error'] == '0' ) {

				// do we have any?
				if (
					is_array( $all_civi_events['values'] )
					AND
					count( $all_civi_events['values'] ) > 0
				) {

					// get all event IDs
					$all_civi_event_ids = array_keys( $all_civi_events['values'] );

					// delete all CiviEvents!
					$delete = $this->plugin->civi->delete_civi_events( $all_civi_event_ids );

				}

			}

		}

		error_log( print_r( array(
			'method' => __METHOD__,
			'all_events' => $all_events,
			'all_eo_events' => $all_eo_events,
			'all_civi_events' => $all_civi_events,
			'delete' => $delete,
		), true ) );

		die();

	}



	//##########################################################################



	/**
	 * Disallow "no category" in EO Event category box.
	 *
	 * @since 0.1
	 *
	 * @return bool false
	 */
	public function force_taxonomy() {

		// disable
		return false;

	}



	/**
	 * Debugging method to show event taxonomies.
	 *
	 * @since 0.1
	 */
	public function show_eo_civi_taxonomies() {

		// get all CiviEvent types
		$civi_types = $this->plugin->civi->get_event_types();

		// get all EO event category terms
		$eo_types = $this->plugin->eo->get_event_categories();

		error_log( print_r( array(
			'method' => __METHOD__,
			'civi_types' => $civi_types,
			'eo_types' => $eo_types,
		), true ) );
		die();

	}



	//##########################################################################



	/**
	 * Debugging method to show all venues and locations.
	 *
	 * @since 0.1
	 */
	public function show_venues_locations() {

		// get all venues
		$all_venues = eo_get_venues();

		// get all CiviCRM Event locations
		$all_locations = $this->plugin->civi->get_all_locations();

		/*
		// delete all CiviCRM Event locations
		$this->plugin->civi->delete_all_locations();

		// clear all EO Event location IDs
		if ( count( $all_venues ) > 0 ) {

			// loop
			foreach( $all_venues AS $venue ) {

				// clear all
				$this->plugin->eo_venue->clear_civi_location( $venue->term_id );
				$this->plugin->eo_venue->clear_venue_components( $venue->term_id );

			}

		}
		*/

		error_log( print_r( array(
			'method' => __METHOD__,
			'all_venues' => $all_venues,
			'all_locations' => $all_locations,
		), true ) );
		die();

	}



	//##########################################################################



	/**
	 * Get an option.
	 *
	 * @since 0.1
	 *
	 * @param string $key The option name
	 * @param mixed $default The default option value if none exists
	 * @return mixed $value
	 */
	public function option_get( $key, $default = null ) {

		// if multisite and network activated
		if ( $this->is_network_activated() ) {

			// get site option
			$value = get_site_option( $key, $default );

		} else {

			// get option
			$value = get_option( $key, $default );

		}

		// --<
		return $value;

	}



	/**
	 * Save an option.
	 *
	 * @since 0.1
	 *
	 * @param string $key The option name
	 * @param mixed $value The value to save
	 */
	public function option_save( $key, $value ) {

		// if multisite and network activated
		if ( $this->is_network_activated() ) {

			// update site option
			update_site_option( $key, $value );

		} else {

			// update option
			update_option( $key, $value );

		}

	}



	/**
	 * Delete an option.
	 *
	 * @since 0.1
	 *
	 * @param string $key The option name
	 */
	public function option_delete( $key ) {

		// if multisite and network activated
		if ( $this->is_network_activated() ) {

			// delete site option
			delete_site_option( $key );

		} else {

			// delete option
			delete_option( $key );

		}

	}



	/**
	 * Test if this plugin is network activated.
	 *
	 * @since 0.1
	 *
	 * @return bool $is_network_active True if network activated, false otherwise
	 */
	public function is_network_activated() {

		// only need to test once
		static $is_network_active;

		// have we done this already?
		if ( isset( $is_network_active ) ) return $is_network_active;

		// if not multisite, it cannot be
		if ( ! is_multisite() ) {

			// set flag
			$is_network_active = false;

			// kick out
			return $is_network_active;

		}

		// make sure plugin file is included when outside admin
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		// get path from 'plugins' directory to this plugin
		$this_plugin = plugin_basename( CIVICRM_WP_EVENT_ORGANISER_FILE );

		// test if network active
		$is_network_active = is_plugin_active_for_network( $this_plugin );

		// --<
		return $is_network_active;

	}



	/**
	 * Send JSON data to the browser.
	 *
	 * @since 0.2.4
	 *
	 * @param array $data The data to send.
	 */
	private function send_data( $data ) {

		// is this an AJAX request?
		if ( defined( 'DOING_AJAX' ) AND DOING_AJAX ) {

			// set reasonable headers
			header('Content-type: text/plain');
			header("Cache-Control: no-cache");
			header("Expires: -1");

			// echo
			echo json_encode( $data );

			// die
			exit();

		}

	}

} // class ends



