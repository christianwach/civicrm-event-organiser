<?php
/**
 * Admin Settings Page Class.
 *
 * Handles Admin Settings Page functionality.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser Admin Settings Page Class.
 *
 * A class that encapsulates Admin Settings Page functionality.
 *
 * @since 0.7
 */
class CiviCRM_WP_Event_Organiser_Admin_Settings {

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
	 * Batch Processing object.
	 *
	 * Not yet implemented.
	 *
	 * @since 0.7
	 * @access public
	 * @var string $batch The Batch Processing object.
	 */
	public $batch;

	/**
	 * Parent Page.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var str $parent_page The parent page.
	 */
	public $parent_page;

	/**
	 * Parent page slug.
	 *
	 * @since 0.7
	 * @access public
	 * @var string $parent_page_slug The slug of the parent page.
	 */
	public $parent_page_slug = 'civi_eo_parent';

	/**
	 * Settings Page.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var str $settings_page The settings page.
	 */
	public $settings_page;

	/**
	 * Settings Page slug.
	 *
	 * @since 0.7
	 * @access public
	 * @var string $settings_page_slug The slug of the Settings Page.
	 */
	public $settings_page_slug = 'civi_eo_settings';



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
	 * Perform initialisation tasks.
	 *
	 * @since 0.2.4
	 */
	public function initialise() {

		// Include files.
		//$this->include_files();

		// Set up objects and references.
		//$this->setup_objects();

		// Register hooks.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.7
		 */
		do_action( 'ceo/admin/settings/loaded' );

	}

	/**
	 * Include files.
	 *
	 * @since 0.7
	 */
	public function include_files() {

		// Include Batch Processing class.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/admin/ceo-admin-batch.php';

	}

	/**
	 * Set up objects.
	 *
	 * @since 0.7
	 */
	public function setup_objects() {

		// Instantiate Batch Processing object.
		$this->batch = new CiviCRM_WP_Event_Organiser_Admin_Batch( $this );

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.4.1
	 */
	public function register_hooks() {

		// Add menu item.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 30 );

		// Add our meta boxes.
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes_add' ], 11, 1 );

	}

	// -------------------------------------------------------------------------

	/**
	 * Add admin pages for this plugin.
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

		// Add the admin page to the CiviCRM menu.
		$this->parent_page = add_submenu_page(
			'CiviCRM', // Parent slug.
			__( 'Settings: CiviCRM Event Organiser', 'civicrm-event-organiser' ), // Page title.
			__( 'Event Organiser', 'civicrm-event-organiser' ), // Menu title.
			'manage_options', // Required caps.
			$this->parent_page_slug, // Slug name.
			[ $this, 'page_settings' ], // Callback.
			30
		);

		// Register our form submit hander.
		add_action( 'load-' . $this->parent_page, [ $this, 'form_submitted' ] );

		// Add WordPress scripts and help text.
		add_action( 'admin_head-' . $this->parent_page, [ $this, 'admin_head' ], 50 );

		// Add settings page.
		$this->settings_page = add_submenu_page(
			$this->parent_page_slug, // Parent slug
			__( 'Settings: CiviCRM Event Organiser', 'civicrm-event-organiser' ), // Page title.
			__( 'Settings', 'civicrm-event-organiser' ), // Menu title.
			'manage_options', // Required caps.
			$this->settings_page_slug, // Slug name.
			[ $this, 'page_settings' ] // Callback.
		);

		// Register our form submit hander.
		add_action( 'load-' . $this->settings_page, [ $this, 'form_submitted' ] );

		// Add WordPress scripts and help text.
		add_action( 'admin_head-' . $this->settings_page, [ $this, 'admin_head' ], 50 );

		// Ensure correct menu item is highlighted.
		add_action( 'admin_head-' . $this->settings_page, [ $this, 'admin_menu_highlight' ], 50 );

	}

	/**
	 * Tell WordPress to highlight the plugin's menu item, regardless of which
	 * actual admin screen we are on.
	 *
	 * @since 0.2.4
	 *
	 * @global string $plugin_page The current plugin page.
	 * @global array $submenu_file The referenced submenu file.
	 */
	public function admin_menu_highlight() {

		global $plugin_page, $submenu_file;

		// Define subpages.
		$subpages = [
			$this->settings_page_slug,
		];

		/**
		 * Filter the list of subpages.
		 *
		 * @since 0.7
		 *
		 * @param array $subpages The existing list of subpages.
		 */
		$subpages = apply_filters( 'ceo/admin/settings/subpages', $subpages );

		// This tweaks the Settings subnav menu to show only one menu item.
		if ( in_array( $plugin_page, $subpages ) ) {
			$plugin_page = $this->parent_page_slug;
			$submenu_file = $this->parent_page_slug;
		}

	}

	/**
	 * Adds WordPress scripts and help text.
	 *
	 * @since 0.7
	 */
	public function admin_head() {

		// Enqueue WordPress scripts.
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'dashboard' );

		// TODO: Add help text here.

	}

	// -------------------------------------------------------------------------

	/**
	 * Get Settings Page Tab URLs.
	 *
	 * @since 0.7
	 *
	 * @return array $urls The array of Settings Page Tab URLs.
	 */
	public function page_tab_urls_get() {

		// Only calculate once.
		if ( isset( $this->urls ) ) {
			return $this->urls;
		}

		// Init return.
		$this->urls = [];

		// Get Settings Page URL.
		$this->urls['settings'] = menu_page_url( $this->settings_page_slug, false );

		/**
		 * Filter the list of URLs.
		 *
		 * @since 0.7
		 *
		 * @param array $urls The existing list of URLs.
		 */
		$this->urls = apply_filters( 'ceo/admin/settings/tab_urls', $this->urls );

		// --<
		return $this->urls;

	}

	/**
	 * Show our admin settings page.
	 *
	 * @since 0.2.4
	 */
	public function page_settings() {

		// Only allow network admins when network activated.
		if ( $this->admin->is_network_activated() ) {
			if ( ! is_super_admin() ) {
				wp_die( __( 'You do not have permission to access this page.', 'civicrm-event-organiser' ) );
			}
		}

		// Get Settings Page Tab URLs.
		$urls = $this->page_tab_urls_get();

		/**
		 * Do not show tabs by default but allow overrides.
		 *
		 * @since 0.7
		 *
		 * @param bool False by default - do not show tabs.
		 */
		$show_tabs = apply_filters( 'ceo/admin/settings/show_tabs', false );

		// Get current screen.
		$screen = get_current_screen();

		/**
		 * Allow meta boxes to be added to this screen.
		 *
		 * The Screen ID to use are:
		 *
		 * * "civicrm_page_civi_eo_parent"
		 * * "civicrm_page_civi_eo_settings"
		 *
		 * @since 0.7
		 *
		 * @param string $screen_id The ID of the current screen.
		 */
		do_action( 'add_meta_boxes', $screen->id, null );

		// Grab columns.
		$columns = ( 1 == $screen->get_columns() ? '1' : '2' );

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/pages/page-admin-settings.php';

	}

	/**
	 * Get our Settings Page screens.
	 *
	 * @since 0.7
	 *
	 * @return array $settings_screens The array of Settings Page screens.
	 */
	public function page_settings_screens_get() {

		// Define this plugin's Settings Page screen IDs.
		$settings_screens = [
			'civicrm_page_' . $this->parent_page_slug,
			'admin_page_' . $this->settings_page_slug,
		];

		/**
		 * Filter the Settings Page screens.
		 *
		 * @since 0.7
		 *
		 * @param array $settings_screens The default array of Settings Page screens.
		 */
		return apply_filters( 'ceo/admin/page/settings/screens', $settings_screens );

	}

	/**
	 * Get the URL for the Settings Page form action attribute.
	 *
	 * This happens to be the same as the Settings Page URL, but need not be.
	 *
	 * @since 0.7
	 *
	 * @return string $submit_url The URL for the Settings Page form action.
	 */
	public function page_settings_submit_url_get() {

		// Get Settings Page submit URL.
		$submit_url = menu_page_url( $this->settings_page_slug, false );

		/**
		 * Filter the Settings Page submit URL.
		 *
		 * @since 0.7
		 *
		 * @param array $submit_url The Settings Page submit URL.
		 */
		$submit_url = apply_filters( 'ceo/admin/page/settings/submit_url', $submit_url );

		// --<
		return $submit_url;

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

		// Get our Settings Page screens.
		$settings_screens = $this->page_settings_screens_get();
		if ( ! in_array( $screen_id, $settings_screens ) ) {
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

		// Create Submit metabox.
		add_meta_box(
			'submitdiv',
			__( 'Settings', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_submit_render' ], // Callback.
			$screen_id, // Screen ID.
			'side', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Create General Settings metabox.
		add_meta_box(
			'ceo_general',
			__( 'General Settings', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_general_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

	}

	/**
	 * Render Save Settings meta box on Admin screen.
	 *
	 * @since 0.7
	 */
	public function meta_box_submit_render() {

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-settings-submit.php';

	}

	/**
	 * Render General Settings meta box on Admin screen.
	 *
	 * @since 0.7
	 */
	public function meta_box_general_render() {

		// Check for possibly missing default profile setting.
		$profile_required = false;
		if ( 'fgffgs' == $this->admin->option_get( 'civi_eo_event_default_profile', 'fgffgs' ) ) {
			$profile_required = true;
		}

		// Check for possibly missing default confirmation page setting.
		$confirm_required = false;
		if ( 'fgffgs' == $this->admin->option_get( 'civi_eo_event_default_confirm', 'fgffgs' ) ) {
			$confirm_required = true;
		}

		// Get all Participant Roles.
		$roles = $this->plugin->civi->registration->get_participant_roles_select( $event = null );

		// Get all Event Types.
		$types = $this->plugin->taxonomy->get_event_types_select();

		// Get all Event Registration Profiles.
		$profiles = $this->plugin->civi->registration->get_registration_profiles_select();

		// Get the current confirmation page setting.
		$confirm_checked = '';
		$confirm_enabled = $this->plugin->civi->registration->get_registration_confirm_enabled();
		if ( $confirm_enabled ) {
			$confirm_checked = ' checked="checked"';
		}

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-settings-general.php';

	}

	// -------------------------------------------------------------------------

	/**
	 * Performs actions when a form has been submitted.
	 *
	 * @since 0.2.4
	 * @since 0.7 Renamed.
	 */
	public function form_submitted() {

		// Was the "Settings" form submitted?
		if ( isset( $_POST['ceo_save'] ) ) {
			$this->form_nonce_check();
			$this->form_settings_update();
			$this->form_redirect();
		}

	}

	/**
	 * Update plugin settings.
	 *
	 * @since 0.2.4
	 * @since 0.7 Renamed.
	 */
	public function form_settings_update() {

		// Rebuild broken correspondences in 0.1.
		$this->plugin->mapping->rebuild_event_correspondences();

		// Init vars.
		$civi_eo_event_default_role = '0';
		$civi_eo_event_default_type = '0';
		$civi_eo_event_default_profile = '0';
		$civi_eo_event_default_confirm = '';

		// Get variables.
		extract( $_POST );

		// Sanitise and save option.
		$civi_eo_event_default_role = (int) $civi_eo_event_default_role;
		$this->admin->option_save( 'civi_eo_event_default_role', $civi_eo_event_default_role );

		// Sanitise and save option.
		$civi_eo_event_default_type = (int) $civi_eo_event_default_type;
		$this->admin->option_save( 'civi_eo_event_default_type', $civi_eo_event_default_type );

		// Sanitise and save option.
		$civi_eo_event_default_profile = (int) $civi_eo_event_default_profile;
		$this->admin->option_save( 'civi_eo_event_default_profile', $civi_eo_event_default_profile );

		// Save option.
		if ( $civi_eo_event_default_confirm == '1' ) {
			$this->admin->option_save( 'civi_eo_event_default_confirm', '1' );
		} else {
			$this->admin->option_save( 'civi_eo_event_default_confirm', '0' );
		}

		/**
		 * Broadcast end of settings update.
		 *
		 * @since 0.3.1
		 */
		do_action( 'civicrm_event_organiser_settings_updated' );

	}

	/**
	 * Checks the nonce.
	 *
	 * @since 0.7
	 */
	private function form_nonce_check() {

		// Check that we trust the source of the data.
		check_admin_referer( 'ceo_settings_action', 'ceo_settings_nonce' );

	}

	/**
	 * Redirect to the Settings page with an extra param.
	 *
	 * @since 0.7
	 */
	private function form_redirect() {

		// Our array of arguments.
		$args = [
			'settings-updated' => 'true',
		];

		// Get Settings Page Tab URLs.
		$urls = $this->page_tab_urls_get();

		// Redirect to our admin page.
		wp_safe_redirect( add_query_arg( $args, $urls['settings'] ) );

	}

} // Class ends.
