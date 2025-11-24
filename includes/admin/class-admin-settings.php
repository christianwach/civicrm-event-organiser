<?php
/**
 * Admin Settings Page Class.
 *
 * Handles Admin Settings Page functionality.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Admin Settings Page Class.
 *
 * A class that encapsulates Admin Settings Page functionality.
 *
 * @since 0.7
 */
class CEO_Admin_Settings {

	/**
	 * Plugin object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * Single site admin object.
	 *
	 * @since 0.7
	 * @access public
	 * @var CEO_Admin
	 */
	public $admin;

	/**
	 * Parent Page.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var string
	 */
	public $parent_page;

	/**
	 * Parent page slug.
	 *
	 * @since 0.7
	 * @access public
	 * @var string
	 */
	public $parent_page_slug = 'civi_eo_parent';

	/**
	 * Settings Page.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var string
	 */
	public $settings_page;

	/**
	 * Settings Page slug.
	 *
	 * @since 0.7
	 * @access public
	 * @var string
	 */
	public $settings_page_slug = 'civi_eo_settings';

	/**
	 * The name of the form nonce element.
	 *
	 * @since 0.8.0
	 * @access protected
	 * @var string
	 */
	protected $form_nonce_field = 'ceo_settings_nonce';

	/**
	 * The name of the form nonce value.
	 *
	 * @since 0.8.0
	 * @access protected
	 * @var string
	 */
	protected $form_nonce_action = 'ceo_settings_action';

	/**
	 * URLs array.
	 *
	 * @since 0.7
	 * @access public
	 * @var array
	 */
	public $urls;

	/**
	 * Constructor.
	 *
	 * @since 0.7
	 *
	 * @param CEO_Admin $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references to objects.
		$this->plugin = $parent->plugin;
		$this->admin  = $parent;

		// Boot when parent is loaded.
		add_action( 'ceo/admin/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialises this object.
	 *
	 * @since 0.2.4
	 */
	public function initialise() {

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
	 * Register WordPress hooks.
	 *
	 * @since 0.4.1
	 */
	public function register_hooks() {

		// Add menu item.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 30 );

		// Add our meta boxes.
		add_action( 'ceo/admin/settings/add_meta_boxes', [ $this, 'meta_boxes_add' ], 11, 1 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Adds the menu items.
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

		// Add scripts and styles.
		add_action( 'admin_print_styles-' . $this->parent_page, [ $this, 'page_settings_css' ] );
		add_action( 'admin_print_scripts-' . $this->parent_page, [ $this, 'page_settings_js' ] );

		// Add settings page.
		$this->settings_page = add_submenu_page(
			$this->parent_page_slug, // Parent slug.
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

		// Add scripts and styles.
		add_action( 'admin_print_styles-' . $this->settings_page, [ $this, 'page_settings_css' ] );
		add_action( 'admin_print_scripts-' . $this->settings_page, [ $this, 'page_settings_js' ] );

	}

	/**
	 * Tell WordPress to highlight the plugin's menu item, regardless of which
	 * actual admin screen we are on.
	 *
	 * @since 0.2.4
	 *
	 * @global string $plugin_page The current plugin page.
	 * @global array  $submenu_file The referenced submenu file.
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
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$plugin_page = $this->parent_page_slug;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu_file = $this->parent_page_slug;
		}

	}

	/**
	 * Adds WordPress scripts and help text.
	 *
	 * TODO: Add help text.
	 *
	 * @since 0.7
	 */
	public function admin_head() {

		// Enqueue WordPress scripts.
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'dashboard' );

	}

	// -----------------------------------------------------------------------------------

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
		 * @since 0.8.0 Renamed.
		 *
		 * @param string $screen_id The ID of the current screen.
		 */
		do_action( 'ceo/admin/settings/add_meta_boxes', $screen->id );

		// Grab columns.
		$columns = ( 1 === (int) $screen->get_columns() ? '1' : '2' );

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/pages/page-admin-settings.php';

	}

	/**
	 * Enqueue any styles needed by our admin "Settings" page.
	 *
	 * @since 0.7.2
	 */
	public function page_settings_css() {

		// Register Select2 styles.
		wp_register_style(
			'civi_eo_settings_select2',
			set_url_scheme( 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css' ),
			false,
			CIVICRM_WP_EVENT_ORGANISER_VERSION, // Version.
			'all' // Media.
		);

		// Enqueue Select2 styles.
		wp_enqueue_style( 'civi_eo_settings_select2' );

	}

	/**
	 * Enqueue required scripts on the "Settings" page.
	 *
	 * @since 0.7.2
	 */
	public function page_settings_js() {

		// Enqueue "Settings" page javascript.
		wp_enqueue_script(
			'civi_eo_settings',
			plugins_url( 'assets/js/wordpress/page-admin-settings.js', CIVICRM_WP_EVENT_ORGANISER_FILE ),
			[ 'jquery' ],
			CIVICRM_WP_EVENT_ORGANISER_VERSION, // Version.
			true // In footer.
		);

		// Register Select2.
		wp_register_script(
			'civi_eo_settings_select2',
			set_url_scheme( 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js' ),
			[ 'jquery' ],
			CIVICRM_WP_EVENT_ORGANISER_VERSION,
			false
		);

		// Enqueue Select2.
		wp_enqueue_script( 'civi_eo_settings_select2' );

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
		 * @param string $submit_url The Settings Page submit URL.
		 */
		$submit_url = apply_filters( 'ceo/admin/page/settings/submit_url', $submit_url );

		// --<
		return $submit_url;

	}

	// -----------------------------------------------------------------------------------

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

		// Create "General Event Settings" metabox.
		add_meta_box(
			'ceo_general',
			__( 'General Event Settings', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_general_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core' // Vertical placement: options are 'core', 'high', 'low'.
		);

		// Create "Online Registration Settings" metabox.
		add_meta_box(
			'ceo_registration',
			__( 'Online Registration Settings', 'civicrm-event-organiser' ),
			[ $this, 'meta_box_registration_render' ], // Callback.
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
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-settings-submit.php';
	}

	/**
	 * Render General Settings meta box on Admin screen.
	 *
	 * @since 0.7
	 */
	public function meta_box_general_render() {

		// Get all Participant Roles.
		$roles = $this->plugin->civi->registration->get_participant_roles_mapped();

		// Get default Participant Role.
		$default_role = $this->plugin->civi->registration->get_participant_role();

		// Get all Event Types.
		$types = $this->plugin->wordpress->taxonomy->get_event_types_mapped();

		// Get default Event Type.
		$default_type = $this->plugin->wordpress->taxonomy->get_default_event_type_value();

		// Get CiviCRM Event sync.
		$civicrm_event_sync = $this->plugin->mapping->get_civicrm_event_sync_mapped();

		// Get default CiviCRM Event sync.
		$default_civicrm_event_sync = $this->plugin->admin->option_get( 'civi_eo_event_default_civicrm_event_sync', 0 );

		// Check for possibly missing default CiviCRM Event sync setting.
		$civicrm_event_sync_required = false;
		if ( ! $this->admin->option_exists( 'civi_eo_event_default_civicrm_event_sync' ) ) {
			$civicrm_event_sync_required = true;
		}

		// Get Event Organiser Event sync.
		$eo_event_sync = $this->plugin->mapping->get_eo_event_sync_mapped();

		// Get default Event Organiser Event sync.
		$default_eo_event_sync = $this->plugin->admin->option_get( 'civi_eo_event_default_eo_event_sync', 1 );

		// Check for possibly missing default Event Organiser Event sync setting.
		$eo_event_sync_required = false;
		if ( ! $this->admin->option_exists( 'civi_eo_event_default_eo_event_sync' ) ) {
			$eo_event_sync_required = true;
		}

		// Get status sync.
		$status_sync = $this->plugin->mapping->get_status_sync_mapped();

		// Get existing setting. Defaults to "Do not sync".
		$default_status_sync = $this->plugin->admin->option_get( 'civi_eo_event_default_status_sync', 3 );

		// Check for possibly missing default Status Sync setting.
		$status_sync_required = false;
		if ( ! $this->admin->option_exists( 'civi_eo_event_default_status_sync' ) ) {
			$status_sync_required = true;
		}

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-settings-general.php';

	}

	/**
	 * Render Online Registration Settings meta box on Admin screen.
	 *
	 * @since 0.7.2
	 */
	public function meta_box_registration_render() {

		// Check for possibly missing default profile setting.
		$profile_required = false;
		if ( ! $this->admin->option_exists( 'civi_eo_event_default_profile' ) ) {
			$profile_required = true;
		}

		// Check for possibly missing default Confirmation Screen setting.
		$confirm_required = false;
		if ( ! $this->admin->option_exists( 'civi_eo_event_default_confirm' ) ) {
			$confirm_required = true;
		}

		// Check for possibly missing default Confirmation Screen page title setting.
		$confirm_title_required = false;
		if ( ! $this->admin->option_exists( 'civi_eo_event_default_confirm_title' ) ) {
			$confirm_title_required = true;
		}

		// Check for possibly missing default thank You page title setting.
		$thank_you_title_required = false;
		if ( ! $this->admin->option_exists( 'civi_eo_event_default_thank_you_title' ) ) {
			$thank_you_title_required = true;
		}

		// Check for possibly missing default Confirmation Email setting.
		$send_email_required = false;
		if ( ! $this->admin->option_exists( 'civi_eo_event_default_send_email' ) ) {
			$send_email_required = true;
		}

		// Get all Event Registration Profiles.
		$profiles_all = $this->plugin->civi->registration->get_registration_profiles();

		// Get the current "Limit Event Registration Profiles" setting.
		$profiles_allowed = $this->admin->option_get( 'civi_eo_event_allowed_profiles', [] );

		// Get Event Registration Profiles.
		$profiles = $this->plugin->civi->registration->get_registration_profiles_mapped();

		// Get default Profile ID.
		$default_profile = $this->plugin->civi->registration->get_registration_profile();

		// Get all Event Registration Dedupe Rules.
		$dedupe_rules = $this->plugin->civi->registration->get_registration_dedupe_rules();

		// Get default Dedupe Rule ID.
		$default_dedupe_rule = $this->plugin->civi->registration->get_registration_dedupe_rule();

		// Get the current Confirmation Screen enabled setting.
		$confirm_checked = 0;
		$confirm_enabled = $this->plugin->civi->registration->get_registration_confirm_enabled();
		if ( $confirm_enabled ) {
			$confirm_checked = 1;
		}

		// Set default values for Confirmation Screen sub-fields.
		$confirm_title = $this->plugin->civi->registration->get_registration_confirm_title();

		// Set default value for Thank You Screen page title.
		$thank_you_title = $this->plugin->civi->registration->get_registration_thank_you_title();

		// Get the current Confirmation Email setting.
		$send_email_checked = 0;
		$send_email_enabled = $this->plugin->civi->registration->get_registration_send_email_enabled();
		if ( $send_email_enabled ) {
			$send_email_checked = 1;
		}

		// Set default checks for Confirmation Email sub-fields.
		$send_email_from_name_required = false;
		$send_email_from_required      = false;

		// If Confirmation Email is enabled.
		if ( $send_email_enabled ) {

			// Check for possibly empty default Confirmation Email "From Name" setting.
			if ( empty( $this->admin->option_get( 'civi_eo_event_default_send_email_from_name', 'fgffgs' ) ) ) {
				$send_email_from_name_required = true;
			}

			// Check for possibly empty default Confirmation Email "From Email" setting.
			if ( empty( $this->admin->option_get( 'civi_eo_event_default_send_email_from', 'fgffgs' ) ) ) {
				$send_email_from_required = true;
			}

		}

		// Set default values for Confirmation Email sub-fields.
		$send_email_from_name = $this->plugin->civi->registration->get_registration_send_email_from_name();
		$send_email_from      = $this->plugin->civi->registration->get_registration_send_email_from();
		$send_email_cc        = $this->plugin->civi->registration->get_registration_send_email_cc();
		$send_email_bcc       = $this->plugin->civi->registration->get_registration_send_email_bcc();

		// Include template file.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'assets/templates/wordpress/metaboxes/metabox-admin-settings-registration.php';

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Performs actions when a form has been submitted.
	 *
	 * @since 0.2.4
	 * @since 0.7 Renamed.
	 */
	public function form_submitted() {

		// Was the "Settings" form submitted?
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$save = isset( $_POST['ceo_save'] ) ? sanitize_text_field( wp_unslash( $_POST['ceo_save'] ) ) : false;
		if ( ! empty( $save ) ) {
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

		// Check that we trust the source of the data.
		check_admin_referer( $this->form_nonce_action, $this->form_nonce_field );

		// Init vars.
		$role               = isset( $_POST['civi_eo_event_default_role'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_role'] ) ) : '0';
		$type               = isset( $_POST['civi_eo_event_default_type'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_type'] ) ) : '0';
		$profile            = isset( $_POST['civi_eo_event_default_profile'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_profile'] ) ) : '0';
		$dedupe             = isset( $_POST['civi_eo_event_default_dedupe'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_dedupe'] ) ) : '0';
		$confirm            = isset( $_POST['civi_eo_event_default_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_confirm'] ) ) : '';
		$confirm_title      = isset( $_POST['civi_eo_event_default_confirm_title'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_confirm_title'] ) ) : '';
		$thank_you_title    = isset( $_POST['civi_eo_event_default_thank_you_title'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_thank_you_title'] ) ) : '';
		$send_email         = isset( $_POST['civi_eo_event_default_send_email'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_send_email'] ) ) : '';
		$from_name          = isset( $_POST['civi_eo_event_default_send_email_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_send_email_from_name'] ) ) : '';
		$from               = isset( $_POST['civi_eo_event_default_send_email_from'] ) ? sanitize_email( wp_unslash( $_POST['civi_eo_event_default_send_email_from'] ) ) : '';
		$cc                 = isset( $_POST['civi_eo_event_default_send_email_cc'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_send_email_cc'] ) ) : '';
		$bcc                = isset( $_POST['civi_eo_event_default_send_email_bcc'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_send_email_bcc'] ) ) : '';
		$civicrm_event_sync = isset( $_POST['civi_eo_event_default_civievent_sync'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_civievent_sync'] ) ) : '0';
		$eo_event_sync      = isset( $_POST['civi_eo_event_default_eoevent_sync'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_eoevent_sync'] ) ) : '0';
		$status_sync        = isset( $_POST['civi_eo_event_default_status_sync'] ) ? sanitize_text_field( wp_unslash( $_POST['civi_eo_event_default_status_sync'] ) ) : '3';

		// Retrieve and sanitise Allowed Profiles array.
		$profiles_allowed = filter_input( INPUT_POST, 'civi_eo_event_allowed_profiles', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		if ( empty( $profiles_allowed ) ) {
			$profiles_allowed = [];
		} else {
			array_walk(
				$profiles_allowed,
				function( &$item ) {
					$item = (int) sanitize_text_field( wp_unslash( $item ) );
				}
			);
		}

		// Sanitise and save option.
		$default_role = (int) $role;
		$this->admin->option_save( 'civi_eo_event_default_role', $default_role );

		// Sanitise and save option.
		$default_type = (int) $type;
		$this->admin->option_save( 'civi_eo_event_default_type', $default_type );

		// Sanitise and save Allowed Profiles option.
		$this->admin->option_save( 'civi_eo_event_allowed_profiles', $profiles_allowed );

		// Sanitise and save option.
		$default_profile = (int) $profile;
		$this->admin->option_save( 'civi_eo_event_default_profile', $default_profile );

		// Save option.
		$default_dedupe = (int) $dedupe;
		$this->admin->option_save( 'civi_eo_event_default_dedupe', $dedupe );

		// Save Confirmation Page option.
		if ( '1' === $confirm ) {
			$this->admin->option_save( 'civi_eo_event_default_confirm', '1' );
		} else {
			$this->admin->option_save( 'civi_eo_event_default_confirm', '0' );
		}

		// Save Confirmation Screen page title option.
		$this->admin->option_save( 'civi_eo_event_default_confirm_title', $confirm_title );

		// Save Thank You Screen page title option.
		$this->admin->option_save( 'civi_eo_event_default_thank_you_title', $thank_you_title );

		// Save Confirmation Email option.
		if ( '1' === $send_email ) {
			$this->admin->option_save( 'civi_eo_event_default_send_email', '1' );
		} else {
			$this->admin->option_save( 'civi_eo_event_default_send_email', '0' );
		}

		// Save Confirmation Email "From Name" option.
		$this->admin->option_save( 'civi_eo_event_default_send_email_from_name', $from_name );

		// Sanitise and save Confirmation Email "From Email" option.
		if ( ! is_email( $from ) ) {
			$from = '';
		}
		$this->admin->option_save( 'civi_eo_event_default_send_email_from', $from );

		// Sanitise and save Confirmation Email "CC" option.
		$cc_value = '';
		if ( ! empty( $cc ) ) {
			$valid  = [];
			$emails = explode( ',', $cc );
			foreach ( $emails as $email ) {
				if ( is_email( sanitize_email( trim( $email ) ) ) ) {
					$valid[] = trim( $email );
				}
			}
			$cc_value = implode( ', ', array_unique( $valid ) );
		}
		$this->admin->option_save( 'civi_eo_event_default_send_email_cc', $cc_value );

		// Sanitise and save Confirmation Email "BCC" option.
		$bcc_value = '';
		if ( ! empty( $bcc ) ) {
			$valid  = [];
			$emails = explode( ',', $bcc );
			foreach ( $emails as $email ) {
				if ( is_email( sanitize_email( trim( $email ) ) ) ) {
					$valid[] = trim( $email );
				}
			}
			$bcc_value = implode( ', ', array_unique( $valid ) );
		}
		$this->admin->option_save( 'civi_eo_event_default_send_email_bcc', $bcc_value );

		// Sanitise and save CiviCRM Event sync option.
		$default_civicrm_event_sync = (int) $civicrm_event_sync;
		$this->admin->option_save( 'civi_eo_event_default_civicrm_event_sync', $default_civicrm_event_sync );

		// Sanitise and save Event Organiser Event sync option.
		$default_eo_event_sync = (int) $eo_event_sync;
		$this->admin->option_save( 'civi_eo_event_default_eo_event_sync', $default_eo_event_sync );

		// Sanitise and save Status Sync option.
		$default_status_sync = (int) $status_sync;
		$this->admin->option_save( 'civi_eo_event_default_status_sync', $default_status_sync );

		/**
		 * Broadcast end of settings update.
		 *
		 * @since 0.3.1
		 * @deprecated 0.8.0 Use the {@see 'ceo/admin/settings/updated'} filter instead.
		 */
		do_action_deprecated( 'civicrm_event_organiser_settings_updated', [], '0.8.0', 'ceo/admin/settings/updated' );

		/**
		 * Fires at the end of the Settings update.
		 *
		 * @since 0.8.0
		 */
		do_action( 'ceo/admin/settings/updated' );

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

}
