<?php
/**
 * Batch Processing Class.
 *
 * Handles Batch Processing functionality.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Event Organiser Batch Processing Class.
 *
 * This class provides Batch Processing functionality.
 *
 * @since 0.7
 */
class CiviCRM_WP_Event_Organiser_Admin_Batch {

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

		// Boot when parent is loaded.
		add_action( 'ceo/admin/settings/loaded', [ $this, 'initialise' ] );

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
		do_action( 'ceo/admin/batch/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.7
	 */
	public function register_hooks() {

	}

	// -------------------------------------------------------------------------

} // Class ends.
