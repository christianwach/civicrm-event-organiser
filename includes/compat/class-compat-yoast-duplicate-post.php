<?php
/**
 * Yoast Duplicate Post Class.
 *
 * Handles compatibility with the "Yoast Duplicate Post" plugin.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Yoast Duplicate Post compatibility Class.
 *
 * This class provides compatibility with the "Yoast Duplicate Post" plugin.
 *
 * @since 0.8.2
 */
class CEO_Compat_Yoast_Duplicate_Post {

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

		// Initialise once "Yoast Duplicate Post" has loaded.
		add_action( 'plugins_loaded', [ $this, 'initialise' ], 20 );

	}

	/**
	 * Initialises this object.
	 *
	 * @since 0.8.2
	 */
	public function initialise() {

		// Bail if "Yoast Duplicate Post" isn't detected.
		if ( ! defined( 'DUPLICATE_POST_CURRENT_VERSION' ) ) {
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

		// Do not copy the link(s) with the CiviCRM Event(s).
		add_filter( 'duplicate_post_meta_keys_filter', [ $this, 'meta_keys_remove' ], 20 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Removes the Event meta keys that store links to one or more CiviCRM Events.
	 *
	 * Only the Event meta needs to be skipped because no additional entries will
	 * have been created in the "global" CiviCRM --> Event Organiser array.
	 *
	 * @since 0.8.2
	 *
	 * @param array $meta_keys The list of meta field names.
	 * @return array $meta_keys The modified list of meta field names.
	 */
	public function meta_keys_remove( $meta_keys ) {

		// Skip the CiviCRM Events array held in post meta.
		$key = array_search( '_civi_eo_civicrm_events', $meta_keys );
		if ( ! empty( $key ) ) {
			unset( $meta_keys[ $key ] );
		}

		// Also skip the array of orphans held in post meta.
		$key = array_search( '_civi_eo_civicrm_events_disabled', $meta_keys );
		if ( ! empty( $key ) ) {
			unset( $meta_keys[ $key ] );
		}

		// --<
		return $meta_keys;

	}

}
