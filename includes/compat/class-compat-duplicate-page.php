<?php
/**
 * Duplicate Page Class.
 *
 * Handles compatibility with the "Duplicate Page" plugin when it offers compatibility.
 *
 * @see https://wordpress.org/support/topic/rcp-plugin-integration/
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Duplicate Page compatibility Class.
 *
 * This class provides compatibility with the "Duplicate Page" plugin.
 *
 * @since 0.8.2
 */
class CEO_Compat_Duplicate_Page {

	/**
	 * Plugin object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * Compatibility object.
	 *
	 * @since 0.8.2
	 * @access public
	 * @var CEO_Compat
	 */
	public $compat;

	/**
	 * Constructor.
	 *
	 * @since 0.8.2
	 *
	 * @param CEO_Compat $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin = $parent->plugin;
		$this->compat = $parent;

		// Initialise once "Duplicate Page" has loaded.
		add_action( 'plugins_loaded', [ $this, 'initialise' ], 20 );

	}

	/**
	 * Initialises this object.
	 *
	 * @since 0.8.2
	 */
	public function initialise() {

		// Bail if "Duplicate Page" isn't detected.
		if ( ! defined( 'DUPLICATE_PAGE_PLUGIN_VERSION' ) ) {
			return;
		}

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.8.2
	 */
	public function register_hooks() {

		// Delete the link(s) with the CiviCRM Event(s).
		add_action( 'dp_duplicate_post', [ $this, 'meta_amend' ], 10, 3 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Deletes the Event meta that links it to one or more CiviCRM Events.
	 *
	 * Only the Event meta needs to be deleted because no additional entries will
	 * have been created in the "global" CiviCRM --> Event Organiser array.
	 *
	 * @since 0.8.2
	 *
	 * @param int $new_post_id The new Post ID.
	 * @param int $original_post_id The original Post ID.
	 */
	public function meta_amend( $new_post_id, $original_post_id ) {

		// Bail if not an Event.
		$post_type = get_post_type( $duplicate_id );
		if ( 'event' !== $post_type ) {
			return;
		}

		// Delete the CiviCRM Events array held in post meta.
		delete_post_meta( $new_post_id, '_civi_eo_civicrm_events' );

		// Also delete the array of orphans held in post meta.
		delete_post_meta( $new_post_id, '_civi_eo_civicrm_events_disabled' );

	}

}
