<?php
/**
 * Shortcodes Class.
 *
 * Handles plugin Shortcodes.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes Class.
 *
 * This class provides Shortcodes for this plugin.
 *
 * @since 0.6.3
 */
class CEO_WordPress_Shortcodes {

	/**
	 * Plugin object.
	 *
	 * @since 0.6.3
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * WordPress object.
	 *
	 * @since 0.8.0
	 * @access public
	 * @var CEO_WordPress
	 */
	public $wordpress;

	/**
	 * Constructor.
	 *
	 * @since 0.6.3
	 *
	 * @param CEO_WordPress $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin    = $parent->plugin;
		$this->wordpress = $parent;

		// Register Shortcodes when WordPress class has loaded.
		add_action( 'ceo/wordpress/loaded', [ $this, 'register_shortcodes' ] );

	}

	/**
	 * Registers this plugin's Shortcodes.
	 *
	 * @since 0.6.3
	 */
	public function register_shortcodes() {

		// Register Shortcodes.
		add_shortcode( 'ceo_register_link', [ $this, 'register_link_render' ] );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Adds the CiviCRM Register link to an Event via a Shortcode.
	 *
	 * @since 0.6.3
	 *
	 * @param array  $attr The saved Shortcode attributes.
	 * @param string $content The enclosed content of the Shortcode.
	 * @return string $markup The HTML markup for the Shortcode.
	 */
	public function register_link_render( $attr, $content = null ) {

		// Init defaults.
		$defaults = [
			'event_id'     => null, // Defaults to the current Event.
			'wrap'         => null, // Defaults to no wrapper element. Can be "div" or "button".
			'wrap_class'   => null, // Defaults to no classes on wrapper element.
			'anchor_class' => null, // Defaults to no classes on anchor element.
			'title'        => null, // Defaults to "Register" for single Events.
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
			if ( 'button' === $wrapper || 'div' === $wrapper ) {
				$element = $wrapper;
			}
		}

		// Get the wrapper classes if the attribute exists.
		$wrap_classes = null;
		if ( ! empty( $shortcode_atts['wrap_class'] ) ) {
			$class = trim( $shortcode_atts['wrap_class'] );
			if ( ! empty( $class ) ) {
				$wrap_classes = $class;
			}
		}

		// Get the anchor classes if the attribute exists.
		$anchor_classes = null;
		if ( ! empty( $shortcode_atts['anchor_class'] ) ) {
			$class = trim( $shortcode_atts['anchor_class'] );
			if ( ! empty( $class ) ) {
				$anchor_classes = $class;
			}
		}

		// Get the title if the attribute exists.
		$title = null;
		if ( ! empty( $shortcode_atts['title'] ) ) {
			$att = trim( $shortcode_atts['title'] );
			if ( ! empty( $att ) ) {
				$title = $att;
			}
		}

		// Init return.
		$markup = '';

		// Get links array.
		$links_data = civicrm_event_organiser_get_register_links( $post_id, $title, $anchor_classes );
		if ( empty( $links_data ) ) {
			return $markup;
		}

		// Wrap links if required.
		$links_data = $this->link_data_wrap( $links_data, $wrap_classes, $element );

		// Is it recurring?
		if ( eo_recurs() ) {

			// Extract links array.
			$links = wp_list_pluck( $links_data, 'link' );

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
			$link_data = array_pop( $links_data );
			foreach ( $link_data as $link ) {
				$markup .= $link['link'];
			}

		}

		// --<
		return $markup;

	}

	/**
	 * Wraps links with elements and classes as defined in the Shortcode.
	 *
	 * @since 0.8.2
	 *
	 * @param array  $links_data The array of Registration link data.
	 * @param array  $wrap_classes The array CSS classes.
	 * @param string $element The wrapper element.
	 * @return array $links_data The modified array of Registration link data.
	 */
	private function link_data_wrap( $links_data, $wrap_classes, $element ) {

		// Wrap links if required.
		if ( empty( $element ) ) {
			return $links_data;
		}

		// Format classes if provided.
		$classes = '';
		if ( ! empty( $wrap_classes ) ) {
			$classes = explode( ' ', $wrap_classes );
			array_walk(
				$classes,
				function( &$item ) {
					$item = esc_attr( $item );
				}
			);
			$classes = implode( ' ', $classes );
		}

		// Handle button element.
		if ( 'button' === $element ) {
			array_walk(
				$links_data,
				function( &$item, $key ) use ( $element, $classes ) {
					foreach ( $item as $index => $link ) {
						if ( ! empty( $link['meta'] ) && 'active' === $link['meta'] ) {
							if ( ! empty( $classes ) ) {
								$link['link'] = '<button type="button" class="' . $classes . '">' . $link['link'] . '</button>';
							} else {
								$link['link'] = '<button type="button">' . $link['link'] . '</button>';
							}
						} else {
							if ( ! empty( $link['meta'] ) && 'registration_closed' === $link['meta'] ) {
								$link['link'] = '<p class="ceo-registration-closed">' . $link['link'] . '</p>';
							}
							if ( ! empty( $link['meta'] ) && 'is_registered' === $link['meta'] ) {
								$link['link'] = '<p class="ceo-contact-is-registered">' . $link['link'] . '</p>';
							}
						}
						$item[ $index ] = $link;
					}
				}
			);
		}

		// Handle div element.
		if ( 'div' === $element ) {
			array_walk(
				$links_data,
				function( &$item, $key ) use ( $element, $classes ) {
					foreach ( $item as $index => $link ) {
						if ( ! empty( $link['meta'] ) && 'active' === $link['meta'] ) {
							if ( ! empty( $classes ) ) {
								$link['link'] = '<div class="' . $classes . '">' . $link['link'] . '</div>';
							} else {
								$link['link'] = '<div>' . $link['link'] . '</div>';
							}
						} else {
							if ( ! empty( $link['meta'] ) && 'registration_closed' === $link['meta'] ) {
								$link['link'] = '<p class="ceo-registration-closed">' . $link['link'] . '</p>';
							}
							if ( ! empty( $link['meta'] ) && 'is_registered' === $link['meta'] ) {
								$link['link'] = '<p class="ceo-contact-is-registered">' . $link['link'] . '</p>';
							}
						}
						$item[ $index ] = $link;
					}
				}
			);
		}

		// --<
		return $links_data;

	}

}
