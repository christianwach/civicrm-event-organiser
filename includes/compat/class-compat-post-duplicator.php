<?php
/**
 * Post Duplicator Class.
 *
 * Handles compatibility with the "Post Duplicator" plugin.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Post Duplicator compatibility Class.
 *
 * This class provides compatibility with the "Post Duplicator" plugin.
 *
 * @since 0.7.5
 */
class CEO_Compat_Post_Duplicator {

	/**
	 * Plugin object.
	 *
	 * @since 0.7.5
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * Compatibility object.
	 *
	 * @since 0.8.0
	 * @access public
	 * @var CEO_Compat
	 */
	public $compat;

	/**
	 * Constructor.
	 *
	 * @since 0.7.5
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin = $parent->plugin;
		$this->compat = $parent;

		// Initialise once "Post Duplicator" has loaded.
		add_action( 'plugins_loaded', [ $this, 'initialise' ], 20 );

	}

	/**
	 * Initialises this object.
	 *
	 * @since 0.7.5
	 */
	public function initialise() {

		// Bail if "Post Duplicator" isn't detected.
		if ( ! defined( 'MTPHR_POST_DUPLICATOR_VERSION' ) ) {
			return;
		}

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.7.5
	 */
	public function register_hooks() {

		// Delete the link(s) with the CiviCRM Event(s).
		add_action( 'mtphr_post_duplicator_created', [ $this, 'meta_amend' ], 10, 3 );

	}

	// -------------------------------------------------------------------------

	/**
	 * Deletes the Event meta that links it to one or more CiviCRM Events.
	 *
	 * Only the Event meta needs to be deleted because no additional entries will
	 * have been created in the "global" CiviCRM --> Event Organiser array.
	 *
	 * @since 0.7.5
	 *
	 * @param int   $original_id The numeric ID of the original WordPress Post.
	 * @param int   $duplicate_id The numeric ID of the duplicate WordPress Post.
	 * @param array $settings The array of Post Duplicator settings.
	 */
	public function meta_amend( $original_id, $duplicate_id, $settings ) {

		// Bail if not an Event.
		$post_type = get_post_type( $duplicate_id );
		if ( 'event' !== $post_type ) {
			return;
		}

		// Delete the CiviCRM Events array held in post meta.
		delete_post_meta( $duplicate_id, '_civi_eo_civicrm_events' );

		// Also delete the array of orphans held in post meta.
		delete_post_meta( $duplicate_id, '_civi_eo_civicrm_events_disabled' );

	}

}
