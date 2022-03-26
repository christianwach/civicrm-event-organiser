<?php
/**
 * Multisite Admin Class.
 *
 * Handles Multisite Admin functionality.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser Multisite Admin Class.
 *
 * This class provides Multisite Admin functionality.
 *
 * @since 0.7
 */
class CiviCRM_WP_Event_Organiser_Admin_Multisite {

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
	 * Network admin page reference.
	 *
	 * @since 0.7
	 * @access public
	 * @var array $network_page The reference to the netowrk admin page.
	 */
	public $network_page;

	/**
	 * Network Settings Page slug.
	 *
	 * @since 0.7
	 * @access public
	 * @var string $network_page_slug The slug of the network Settings Page.
	 */
	public $network_page_slug = 'ceo_network';



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
		$this->admin = $parent;
		$this->settings = $parent->settings;

		// Boot when parent is loaded.
		add_action( 'ceo/admin/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.7
	 */
	public function initialise() {

		// Store references.
		$this->settings = $parent->settings;

		// Register hooks.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.7
		 */
		do_action( 'ceo/admin/multisite/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.7
	 */
	public function register_hooks() {

		/*
		// Maybe show a warning if Settings need updating.
		add_action( 'network_admin_notices', [ $this->admin, 'upgrade_warning' ] );

		// Maybe show upgrade warning.
		add_filter( 'ceo/admin/notice/show', [ $this, 'upgrade_warning_filter' ], 10, 3 );

		// Maybe filter the URL of the Settings Page in Admin Notices.
		add_filter( 'ceo/admin/notice/url', [ $this, 'upgrade_warning_url_filter' ] );
		*/

		// Add admin page to Settings menus.
		add_action( 'network_admin_menu', [ $this, 'admin_menu' ], 20 );

		// Add our meta boxes.
		add_action( 'add_meta_boxes', [ $this, 'meta_boxes_add' ], 11 );

		// Filter access capabilities.
		add_filter( 'ceo/admin/page/settings/cap', [ $this, 'caps_filter' ] );

		// Filter settings screens.
		add_filter( 'ceo/admin/page/settings/screens', [ $this, 'settings_screens_filter' ] );

		// Maybe filter the Settings Page Tab URLs.
		add_filter( 'ceo/admin/settings/tab_urls', [ $this, 'page_tab_urls_filter' ] );

		// Maybe filter the URL of the Settings Page.
		add_filter( 'ceo/admin/page/settings/url', [ $this, 'page_settings_url_filter' ] );

		// Maybe filter the Submit URL of the Settings Page.
		add_filter( 'ceo/admin/page/settings/submit_url', [ $this, 'page_settings_submit_url_filter' ] );

	}

	// -------------------------------------------------------------------------

	/**
	 * Determine when to show the Admin Notice.
	 *
	 * @since 0.7
	 *
	 * @param bool $show_notice The existing value. False by default.
	 * @param bool $is_settings_screen True if on our Settings Page, or false otherwise.
	 * @param string $screen_id The ID of the current screen.
	 * @return bool $show_notice True if the Admin Notice should be shown, false otherwise.
	 */
	public function upgrade_warning_filter( $show_notice, $is_settings_screen, $screen_id ) {

		// True if this is our Network Settings Page.
		if ( $screen_id === 'settings_page_' . $this->network_page_slug . '-network' ) {
			return false;
		}

		/*
		 * When CiviCRM is network-activated and CAU is hiding it on sub-sites
		 * then we need to check whether this plugin is activated on just this
		 * site or network-wide.
		 */

		// TODO: Show warning in correct places in Multisite.
		if ( $this->plugin->is_civicrm_main_site_only() && ! is_main_site() ) {
			return false;
		}

		// --<
		return $show_notice;

	}

	/**
	 * Filter the Settings Page URL in Admin Notices.
	 *
	 * @since 0.7
	 *
	 * @param array $url The default Settings Page URL in Admin Notices.
	 * @return array $url The modified Settings Page URL in Admin Notices.
	 */
	public function upgrade_warning_url_filter( $url ) {

		// Build URL to Network Settings Page when needed.
		if ( is_network_admin() ) {
			$url = network_admin_url( add_query_arg( 'page', $this->network_page_slug, 'settings.php' ) );
		}

		// --<
		return $url;

	}

	// -------------------------------------------------------------------------

	/**
	 * Add network admin menu item(s) for this plugin.
	 *
	 * @since 0.7
	 */
	public function admin_menu() {

		// Bail if not Multisite or not WordPress Network Admin.
		if ( ! is_multisite() || ! is_network_admin() ) {
			return;
		}

		// Bail if User can't access Network Options.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// Add network admin page to Network Settings menu.
		$this->network_page = add_submenu_page(
			'settings.php', // Parent page.
			__( 'CiviCRM Event Organiser: Settings', 'civicrm-event-organiser' ), // Page title.
			__( 'CiviCRM Event Organiser', 'civicrm-event-organiser' ), // Menu title.
			'manage_network_options', // Required caps.
			$this->network_page_slug, // Slug name.
			[ $this->admin, 'page_settings' ], // Callback.
			10
		);

		// Register our form submit hander.
		add_action( 'load-' . $this->network_page, [ $this->admin, 'settings_update_router' ] );

		// Add help text and enqueue WordPress scripts.
		add_action( 'admin_head-' . $this->network_page, [ $this->admin, 'admin_head' ], 50 );

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

		// Bail if not the Screen ID we want.
		if ( $screen_id != 'settings_page_' . $this->network_page_slug . '-network' ) {
			return;
		}

		// Check Multisite user permissions.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// Pass to Admin method.
		$this->admin->meta_boxes_add( $screen_id );

	}

	// -------------------------------------------------------------------------

	/**
	 * Restrict access to site Settings Page.
	 *
	 * @since 0.7
	 *
	 * @param string $capability The existing access capability.
	 * @return string $capability The modified access capability.
	 */
	public function caps_filter( $capability ) {

		// Assign network admin capability.
		$capability = 'manage_network_options';

		// --<
		return $capability;

	}

	/**
	 * Filter the Settings Page screens.
	 *
	 * @since 0.7
	 *
	 * @param array $settings_screens The default array of Settings Page screens.
	 * @return array $settings_screens The modified array of Settings Page screens.
	 */
	public function settings_screens_filter( $settings_screens ) {

		// Add our Network Settings Screen.
		$settings_screens[] = 'settings_page_' . $this->network_page_slug . '-network';

		// --<
		return $settings_screens;

	}

	/**
	 * Filter the Settings Page Tab URLs.
	 *
	 * @since 0.7
	 *
	 * @param array $urls The default Settings Page Tab URLs.
	 * @return array $urls The modified Settings Page Tab URLs.
	 */
	public function page_tab_urls_filter( $urls ) {

		// Build URL to Network Settings Page when needed.
		if ( is_network_admin() ) {
			$urls['settings'] = network_admin_url( add_query_arg( 'page', $this->network_page_slug, 'settings.php' ) );
		}

		// --<
		return $urls;

	}

	/**
	 * Filter the Settings Page URL.
	 *
	 * We can't always use the method in this class for retrieving the registered
	 * URL because it is only registered in Network Admin and we need to access
	 * the URL on all admin screens.
	 *
	 * @since 0.7
	 *
	 * @param string $url The default Settings Page URL.
	 * @return string $url The modified Settings Page URL.
	 */
	public function page_settings_url_filter( $url ) {

		/*
		 * When CiviCRM is network-activated and CAU is hiding it on sub-sites
		 * then we need to check whether this plugin is activated on just this
		 * site or network-wide.
		 */

		// Build URL to Network Settings Page when needed.
		if ( $this->plugin->is_civicrm_main_site_only() ) {
			$url = network_admin_url( add_query_arg( 'page', $this->network_page_slug, 'settings.php' ) );
		}

		// --<
		return $url;

	}

	/**
	 * Filter the Settings Page Submit URL.
	 *
	 * @since 0.7
	 *
	 * @param string $url The default Settings Page Submit URL.
	 * @return string $url The modified Settings Page Submit URL.
	 */
	public function page_settings_submit_url_filter( $url ) {

		// Build URL to Network Settings Page when needed.
		if ( is_network_admin() ) {
			$url = network_admin_url( add_query_arg( 'page', $this->network_page_slug, 'settings.php' ) );
		}

		// --<
		return $url;

	}

} // Class ends.
