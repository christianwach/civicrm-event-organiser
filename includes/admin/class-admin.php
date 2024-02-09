<?php
/**
 * Admin Class.
 *
 * Handles Admin functionality.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Event Organiser Admin Class.
 *
 * A class that encapsulates admin functionality.
 *
 * @since 0.1
 */
class CiviCRM_WP_Event_Organiser_Admin {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Settings Page object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $settings The Settings Page object.
	 */
	public $settings;

	/**
	 * Manual Sync Page object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $manual_sync The Manual Sync Page object.
	 */
	public $manual_sync;

	/**
	 * Multisite Settings Page object.
	 *
	 * @since 0.7
	 * @access public
	 * @var object $multisite The Multisite Settings Page object.
	 */
	public $multisite;

	/**
	 * Plugin version.
	 *
	 * @since 0.2.4
	 * @access public
	 * @var str $plugin_version The plugin version. (numeric string)
	 */
	public $plugin_version;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin = $parent;

		// Initialise.
		add_action( 'civicrm_wp_event_organiser_loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Perform initialisation tasks.
	 *
	 * @since 0.2.4
	 */
	public function initialise() {

		// Init settings.
		$this->initialise_settings();

		// Include files.
		$this->include_files();

		// Set up objects and references.
		$this->setup_objects();

		// Register hooks.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.7
		 */
		do_action( 'ceo/admin/loaded' );

	}

	/**
	 * Initialise settings.
	 *
	 * @since 0.7
	 */
	public function initialise_settings() {

		// Assign plugin version.
		$this->plugin_version = $this->option_get( 'civi_eo_version', false );

		// Do upgrade tasks.
		$this->upgrade_tasks();

		// Store version if there has been a change.
		if ( $this->plugin_version != CIVICRM_WP_EVENT_ORGANISER_VERSION ) {
			$this->store_version();
		}

	}

	/**
	 * Include files.
	 *
	 * @since 0.7
	 */
	public function include_files() {

		// Include Settings Page class.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/admin/class-admin-settings.php';

		// Include Manual Sync Page class.
		include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/admin/class-admin-manual-sync.php';

		// Maybe include Multisite Page class.
		if ( is_multisite() ) {
			include CIVICRM_WP_EVENT_ORGANISER_PATH . 'includes/admin/class-admin-multisite.php';
		}

	}

	/**
	 * Set up objects.
	 *
	 * @since 0.7
	 */
	public function setup_objects() {

		// Instantiate Settings Page object.
		$this->settings = new CiviCRM_WP_Event_Organiser_Admin_Settings( $this );

		// Instantiate Manual Sync Page object.
		$this->manual_sync = new CiviCRM_WP_Event_Organiser_Admin_Manual_Sync( $this );

		// Maybe instantiate Multisite Page object.
		if ( is_multisite() ) {
			$this->multisite = new CiviCRM_WP_Event_Organiser_Admin_Multisite( $this );
		}

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.4.1
	 */
	public function register_hooks() {

		// All delegated.

	}

	/**
	 * Perform tasks when an upgrade is required.
	 *
	 * @since 0.2.4
	 */
	public function upgrade_tasks() {

		// Bail if this is a new install.
		if ( $this->plugin_version === false ) {
			return;
		}

		// Bail if this not WordPress admin.
		if ( ! is_admin() ) {
			return;
		}

		// Show an admin notice for possibly missing default profile setting.
		$shown = false;
		if ( 'fgffgs' === $this->option_get( 'civi_eo_event_default_profile', 'fgffgs' ) ) {
			add_action( 'admin_notices', [ $this, 'upgrade_alert' ] );
			$shown = true;
		}

		// Show an admin notice for possibly missing default Confirmation Page setting.
		if ( $shown === false && 'fgffgs' === $this->option_get( 'civi_eo_event_default_confirm', 'fgffgs' ) ) {
			add_action( 'admin_notices', [ $this, 'upgrade_alert' ] );
			$shown = true;
		}

		// Show an admin notice for possibly missing default Confirmation Email setting.
		if ( $shown === false && 'fgffgs' === $this->option_get( 'civi_eo_event_default_send_email', 'fgffgs' ) ) {
			add_action( 'admin_notices', [ $this, 'upgrade_alert' ] );
			$shown = true;
		}

		// Show an admin notice for possibly missing default Confirmation Email "From Name" setting.
		if ( $shown === false && ! empty( $this->option_get( 'civi_eo_event_default_send_email' ) ) ) {
			if ( empty( $this->option_get( 'civi_eo_event_default_send_email_from_name' ) ) ) {
				add_action( 'admin_notices', [ $this, 'upgrade_alert' ] );
				$shown = true;
			}
		}

		// Show an admin notice for possibly missing default Confirmation Email "From Email" setting.
		if ( $shown === false && ! empty( $this->option_get( 'civi_eo_event_default_send_email' ) ) ) {
			if ( empty( $this->option_get( 'civi_eo_event_default_send_email_from' ) ) ) {
				add_action( 'admin_notices', [ $this, 'upgrade_alert' ] );
				$shown = true;
			}
		}

		// Show an admin notice for possibly missing default CiviCRM Event Sync setting.
		if ( $shown === false && 'fgffgs' === $this->option_get( 'civi_eo_event_default_civicrm_event_sync', 'fgffgs' ) ) {
			add_action( 'admin_notices', [ $this, 'upgrade_alert' ] );
			$shown = true;
		}

		// Show an admin notice for possibly missing default Status Sync setting.
		if ( $shown === false && 'fgffgs' === $this->option_get( 'civi_eo_event_default_status_sync', 'fgffgs' ) ) {
			add_action( 'admin_notices', [ $this, 'upgrade_alert' ] );
			$shown = true;
		}

		/*
		// Show an admin notice when a batch process is required.
		if ( $shown === false && 'fgffgs' !== $this->option_get( 'civi_eo_batch_process', 'fgffgs' ) ) {
			add_action( 'admin_notices', [ $this, 'upgrade_alert' ] );
		}
		*/

		// Maybe upgrade Taxonomy to use "term meta".
		if ( $this->plugin->taxonomy->can_query_by_term_meta() ) {
			if ( 'fgffgs' === $this->option_get( 'civi_eo_term_meta_enabled', 'fgffgs' ) ) {
				$this->plugin->taxonomy->upgrade();
				$this->option_save( 'civi_eo_term_meta_enabled', 'yes' );
			}
		}

	}

	/**
	 * Adds a message to admin pages when an upgrade is required.
	 *
	 * @since 0.2.4
	 */
	public function upgrade_alert() {

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

		// Get current screen.
		$screen = get_current_screen();
		if ( ! ( $screen instanceof WP_Screen ) ) {
			return;
		}

		// Get our Settings Page screens.
		$settings_screens = $this->settings->page_settings_screens_get();
		if ( in_array( $screen->id, $settings_screens ) ) {
			return;
		}

		// Get Settings Page Tab URLs.
		$urls = $this->settings->page_tab_urls_get();

		// Construct message.
		$message = sprintf(
			/* translators: 1: The opening anchor tag, 2: The closing anchor tag. */
			__( 'CiviCRM Event Organiser needs your attention. Please visit the %1$sSettings Page%2$s.', 'civicrm-event-organiser' ),
			'<a href="' . $urls['settings'] . '">',
			'</a>'
		);

		// Show it.
		echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';

	}

	/**
	 * Adds a message to admin pages when Event Organiser is not found.
	 *
	 * @since 0.4.1
	 */
	public function dependency_alert() {

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Construct message.
		$message = __( 'CiviCRM Event Organiser requires Event Organiser version 3 or higher.', 'civicrm-event-organiser' );

		// Show it.
		echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';

	}

	/**
	 * Stores the plugin version.
	 *
	 * @since 0.2.4
	 */
	public function store_version() {

		// Store version.
		$this->option_save( 'civi_eo_version', CIVICRM_WP_EVENT_ORGANISER_VERSION );

	}

	// -------------------------------------------------------------------------

	/**
	 * Gets an option.
	 *
	 * @since 0.1
	 *
	 * @param string $key The option name.
	 * @param mixed $default The default option value if none exists.
	 * @return mixed $value The value of the requested option.
	 */
	public function option_get( $key, $default = null ) {

		// Get local site option.
		$value = get_option( $key, $default );

		// --<
		return $value;

	}

	/**
	 * Saves an option.
	 *
	 * @since 0.1
	 *
	 * @param string $key The option name.
	 * @param mixed $value The value to save.
	 */
	public function option_save( $key, $value ) {

		// Update local site option.
		update_option( $key, $value );

	}

	/**
	 * Deletes an option.
	 *
	 * @since 0.1
	 *
	 * @param string $key The option name.
	 */
	public function option_delete( $key ) {

		// Delete local site option.
		delete_option( $key );

	}

	// -------------------------------------------------------------------------

	/**
	 * Tests if this plugin is network activated.
	 *
	 * @since 0.1
	 *
	 * @return bool $is_network_active True if network activated, false otherwise.
	 */
	public function is_network_activated() {

		// Only need to test once.
		static $is_network_active;

		// Have we done this already?
		if ( isset( $is_network_active ) ) {
			return $is_network_active;
		}

		// If not multisite, it cannot be.
		if ( ! is_multisite() ) {

			// Set flag.
			$is_network_active = false;

			// Kick out.
			return $is_network_active;

		}

		// Make sure plugin file is included when outside admin.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Get path from 'plugins' directory to this plugin.
		$this_plugin = plugin_basename( CIVICRM_WP_EVENT_ORGANISER_FILE );

		// Test if network active.
		$is_network_active = is_plugin_active_for_network( $this_plugin );

		// --<
		return $is_network_active;

	}

} // Class ends.
