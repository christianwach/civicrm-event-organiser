<?php
/**
 * Shortcodes Class.
 *
 * Handles plugin Shortcodes.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.6.3
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Event Organiser Shortcodes Class.
 *
 * This class provides Shortcodes for this plugin.
 *
 * @since 0.6.3
 */
class CiviCRM_WP_Event_Organiser_Shortcodes {

	/**
	 * Plugin object.
	 *
	 * @since 0.6.3
	 * @access public
	 * @var CiviCRM_WP_Event_Organiser
	 */
	public $plugin;

	/**
	 * Constructor.
	 *
	 * @since 0.6.3
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference.
		$this->plugin = $parent;

		// Add CiviCRM hooks when plugin is loaded.
		add_action( 'civicrm_wp_event_organiser_loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.6.3
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.6.3
	 */
	public function register_hooks() {

		// Register shortcodes.
		add_shortcode( 'ceo_register_link', [ $this, 'register_link_render' ] );

	}

	// -------------------------------------------------------------------------

	/**
	 * Adds the CiviCRM Register link to an Event via a Shortcode.
	 *
	 * @since 0.6.3
	 *
	 * @param array $attr The saved Shortcode attributes.
	 * @param str   $content The enclosed content of the Shortcode.
	 * @return str $markup The HTML markup for the Shortcode.
	 */
	public function register_link_render( $attr, $content = null ) {

		// Init defaults.
		$defaults = [
			'event_id' => null, // Default to the current Event.
			'wrap'     => null, // Default to previous markup.
		];

		// Parse attributes.
		$shortcode_atts = shortcode_atts( $defaults, $attr, 'ceo_register_link' );

		// Set a Post ID if the attribute exists.
		$post_id = null;
		if ( ! empty( $shortcode_atts['event_id'] ) ) {
			$post_id = (int) trim( $shortcode_atts['event_id'] );
		}

		// Get the HTML wrapper element if the attribute exists.
		$element = null;
		if ( ! empty( $shortcode_atts['wrap'] ) ) {
			$wrapper = trim( $shortcode_atts['wrap'] );
			if ( 'button' === $wrapper ) {
				$element = $wrapper;
			}
		}

		// Init return.
		$markup = '';

		// Get links array.
		$links = civicrm_event_organiser_get_register_links( $post_id );

		// Bail if there are none.
		if ( empty( $links ) ) {
			return $markup;
		}

		// Wrap links if required.
		if ( ! empty( $element ) ) {
			array_walk(
				$links,
				function( &$item ) use ( $element ) {
					$item = '<' . $element . ' type="' . $element . '">' . $item . '</' . $element . '>';
				}
			);
		}

		// Is it recurring?
		if ( eo_recurs() ) {

			// Combine into list.
			$list = implode( '</li>' . "\n" . '<li class="civicrm-event-register-link">', $links );

			// Top and tail.
			$list = '<li class="civicrm-event-register-link">' . $list . '</li>' . "\n";

			// Wrap in unordered list.
			$list = '<ul class="civicrm-event-register-links">' . $list . '</ul>';

			// Open a list item.
			$markup .= '<li class="civicrm-event-register-links">';

			// Show a title.
			$markup .= '<strong>' . __( 'Registration Links', 'civicrm-event-organiser' ) . ':</strong>';

			// Show links.
			$markup .= $list;

			// Finish up.
			$markup .= '</li>' . "\n";

		} else {

			// Show link.
			$markup .= array_pop( $links );

		}

		// --<
		return $markup;

	}

}
