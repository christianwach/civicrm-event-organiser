<?php
/**
 * Admin Multisite Class.
 *
 * Handles Multisite admin functionality.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Admin Multisite Class.
 *
 * This class provides Multisite admin functionality.
 *
 * @since 0.7
 */
class CEO_Admin_Multisite {

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
	 * Constructor.
	 *
	 * @since 0.7
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin = $parent->plugin;
		$this->admin  = $parent;

		// Boot when parent is loaded.
		add_action( 'ceo/admin/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.7
	 */
	public function initialise() {

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

		// Maybe show a warning if network-activated.
		add_action( 'network_admin_notices', [ $this, 'activation_warning' ] );

		// Filter access capabilities.
		add_filter( 'ceo/admin/settings/cap', [ $this, 'caps_filter' ] );

	}

	// -------------------------------------------------------------------------

	/**
	 * Determine when to show the Admin Notice.
	 *
	 * @since 0.7.1
	 */
	public function activation_warning() {

		// Check user permissions.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// Bail if this plugin is not network-activated.
		if ( ! $this->plugin->is_network_activated() ) {
			return;
		}

		// Show Admin Notice.
		echo '<div class="notice notice-error is-dismissible">' .
			'<p>' .
				esc_html__( 'CiviCRM Event Organiser should not be network-activated. Please activate it on individual sites instead.', 'civicrm-event-organiser' ) .
			'</p>' .
		'</div>';

	}

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
		$multisite_capability = 'manage_network_options';

		/**
		 * Filter the default multisite capability for access to Settings.
		 *
		 * @since 0.8.0
		 *
		 * @param string $multisite_capability The default multisite capability for access to Settings.
		 */
		$capability = apply_filters( 'ceo/admin/multisite/settings/cap', $multisite_capability );

		// --<
		return $capability;

	}

}
