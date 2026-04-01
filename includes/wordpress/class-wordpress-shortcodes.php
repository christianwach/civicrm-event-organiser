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
		add_shortcode( 'ceo_register_remaining', [ $this, 'remaining_render' ] );
		add_shortcode( 'ceo_register_messages', [ $this, 'messages_render' ] );
		add_shortcode( 'ceo_register_link', [ $this, 'link_render' ] );

		// Register Shortcode compatibility.
		add_filter( 'eo_placeholder_template_patterns', [ $this, 'template_patterns' ], 10, 2 );
		add_filter( 'eo_placeholder_template_replacement', [ $this, 'template_replacement' ], 10, 2 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Renders the CiviCRM Registration remaining participant count via a Shortcode.
	 *
	 * @since 0.8.6
	 *
	 * @param array  $attr The saved Shortcode attributes.
	 * @param string $content The enclosed content of the Shortcode.
	 * @return string $markup The HTML markup for the Shortcode.
	 */
	public function remaining_render( $attr, $content = null ) {

		// Init defaults.
		$defaults = [
			'event_id' => null, // Defaults to the current Event.
			'class'    => null, // Defaults to no classes on wrapper element.
			'format'   => null, // Defaults to full text. Use "raw" to render just the number.
		];

		// Parse attributes.
		$shortcode_atts = shortcode_atts( $defaults, $attr, 'ceo_register_remaining' );

		// Set a Post ID if the attribute exists.
		$post_id = null;
		if ( ! empty( $shortcode_atts['event_id'] ) ) {
			$post_id = (int) trim( $shortcode_atts['event_id'] );
		}

		// Default to the current Event.
		$post_id = intval( empty( $post_id ) ? get_the_ID() : $post_id );

		// Get the format if the attribute exists.
		$format = null;
		if ( ! empty( $shortcode_atts['format'] ) ) {
			$att = trim( $shortcode_atts['format'] );
			if ( ! empty( $att ) ) {
				$format = $att;
			}
		}

		// Init return.
		$markup = '';

		// Get links array.
		$links_data = civicrm_event_organiser_get_register_links( $post_id );
		if ( empty( $links_data ) ) {
			return $markup;
		}

		// Process array into markup.
		$markup = $this->remaining_process( $links_data, $post_id, $format );

		// --<
		return $markup;

	}

	/**
	 * Processes the CiviCRM Registration remaining participant count markup.
	 *
	 * @since 0.8.7
	 *
	 * @param array  $links The array of HTML links to the CiviCRM Registration pages with metadata for each link.
	 * @param int    $post_id The numeric ID of the Event.
	 * @param string $format The format of the returned string - passing "raw" renders just the number.
	 * @return string $markup The rendered HTML markup.
	 */
	private function remaining_process( $links, $post_id, $format = '' ) {

		// Init return.
		$markup = '';

		// Process messages.
		array_walk(
			$links,
			function( &$item, $key ) {

				// Wrap messages.
				if ( ! empty( $item['remaining_message'] ) && false !== $item['remaining_count'] ) {

					// Define classes.
					$class_common = 'ceo-event-remaining-message';
					$class_count  = 'ceo-event-remaining-count-' . $item['remaining_count'];

					// Wrap in span.
					$item['remaining_message'] = '<span class="' . $class_common . ' ' . $class_count . ' ">' . $item['remaining_message'] . '</span>';

				}

			}
		);

		// Extract remaining array.
		$remaining_messages = array_filter( wp_list_pluck( $links, 'remaining_message' ) );
		$remaining_count    = array_filter( wp_list_pluck( $links, 'remaining_count' ), 'strlen' );

		// Is it recurring?
		if ( eo_recurs( $post_id ) ) {

			// Combine into list.
			if ( 'raw' === $format ) {
				$list = implode( '</li>' . "\n" . '<li class="civicrm-event-register-remaining">', $remaining_count );
			} else {
				$list = implode( '</li>' . "\n" . '<li class="civicrm-event-register-remaining">', $remaining_messages );
			}

			// Top and tail.
			$list = '<li class="civicrm-event-register-remaining">' . $list . '</li>' . "\n";

			// Wrap in unordered list.
			$markup .= '<ul class="civicrm-event-register-remaining">' . $list . '</ul>';

		} else {

			// Render markup.
			if ( 'raw' === $format ) {
				$markup .= implode( ' ', $remaining_count );
			} else {
				$markup .= implode( ' ', $remaining_messages );
			}

		}

		// --<
		return $markup;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Adds the CiviCRM Registration messages to an Event via a Shortcode.
	 *
	 * @since 0.8.6
	 *
	 * @param array  $attr The saved Shortcode attributes.
	 * @param string $content The enclosed content of the Shortcode.
	 * @return string $markup The HTML markup for the Shortcode.
	 */
	public function messages_render( $attr, $content = null ) {

		// Init defaults.
		$defaults = [
			'event_id' => null, // Defaults to the current Event.
			'class'    => null, // Defaults to no classes on wrapper element.
		];

		// Parse attributes.
		$shortcode_atts = shortcode_atts( $defaults, $attr, 'ceo_register_messages' );

		// Set a Post ID if the attribute exists.
		$post_id = null;
		if ( ! empty( $shortcode_atts['event_id'] ) ) {
			$post_id = (int) trim( $shortcode_atts['event_id'] );
		}

		// Default to the current Event.
		$post_id = intval( empty( $post_id ) ? get_the_ID() : $post_id );

		// Init return.
		$markup = '';

		// Get links array.
		$links_data = civicrm_event_organiser_get_register_links( $post_id );
		if ( empty( $links_data ) ) {
			return $markup;
		}

		// Process array into markup.
		$markup = $this->messages_process( $links_data, $post_id );

		// --<
		return $markup;

	}

	/**
	 * Processes the CiviCRM Registration messages markup.
	 *
	 * @since 0.8.7
	 *
	 * @param array $links_data The array of HTML links to the CiviCRM Registration pages with metadata for each link.
	 * @param int   $post_id The numeric ID of the Event.
	 * @return string $markup The rendered HTML markup.
	 */
	private function messages_process( $links_data, $post_id ) {

		// Init return.
		$markup = '';

		// Process messages.
		array_walk(
			$links_data,
			function( &$item, $key ) {

				// Standalone messages.
				if ( ! empty( $item['meta'] ) && in_array( 'waitlist', $item['meta'] ) ) {
					$item['link'] = '<span class="ceo-event-has-waitlist">' . implode( ' ', $item['messages'] ) . '</span>';
				}
				if ( ! empty( $item['meta'] ) && in_array( 'is_registered', $item['meta'] ) ) {
					$item['link'] = '<span class="ceo-contact-is-registered">' . implode( ' ', $item['messages'] ) . '</span>';
				}
				if ( ! empty( $item['meta'] ) && in_array( 'event_full', $item['meta'] ) ) {
					$item['link'] = '<span class="ceo-event-is-full">' . implode( ' ', $item['messages'] ) . '</span>';
				}
				if ( ! empty( $item['meta'] ) && in_array( 'registration_closed', $item['meta'] ) ) {
					$item['link'] = '<span class="ceo-registration-closed">' . implode( ' ', $item['messages'] ) . '</span>';
				}

				// Valid Register links have no messages.
				if ( empty( $item['messages'] ) ) {
					$item['link'] = '';
				}

			}
		);

		// Extract links array.
		$links = array_filter( wp_list_pluck( $links_data, 'link' ) );

		// Is it recurring?
		if ( eo_recurs( $post_id ) ) {

			// Combine into list.
			$list = implode( '</li>' . "\n" . '<li class="civicrm-event-register-link">', $links );

			// Top and tail list items.
			$list = '<li class="civicrm-event-register-link">' . $list . '</li>' . "\n";

			// Wrap in unordered list.
			$markup .= '<ul class="civicrm-event-register-links">' . $list . '</ul>';

		} else {

			// Show messages.
			$markup .= implode( ' ', $links );

		}

		// --<
		return $markup;

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
	public function link_render( $attr, $content = null ) {

		// Init defaults.
		$defaults = [
			'event_id'     => null, // Defaults to the current Event.
			'wrap'         => null, // Defaults to no wrapper element. Can be "div" or "button".
			'wrap_class'   => null, // Defaults to no classes on wrapper element.
			'anchor_class' => null, // Defaults to no classes on anchor element.
			'title'        => null, // Defaults to "Register" for single Events.
			'waitlist'     => null, // Defaults to "Waitlist" for single Events.
			'messages'     => null, // Defaults to showing messages.
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

		// Get the link text if the attribute exists.
		$title = null;
		if ( ! empty( $shortcode_atts['title'] ) ) {
			$att = trim( $shortcode_atts['title'] );
			if ( ! empty( $att ) ) {
				$title = $att;
			}
		}

		// Get the waitlist link text if the attribute exists.
		$waitlist = null;
		if ( ! empty( $shortcode_atts['waitlist'] ) ) {
			$att = trim( $shortcode_atts['waitlist'] );
			if ( ! empty( $att ) ) {
				$waitlist = $att;
			}
		}

		// Set the Show Messages" attribute.
		$show_messages = true;
		if ( ! empty( $shortcode_atts['messages'] ) ) {
			$att = trim( $shortcode_atts['messages'] );
			if ( ! empty( $att ) && in_array( $att, [ 'false', 'no' ] ) ) {
				$show_messages = false;
			}
		}

		// Init return.
		$markup = '';

		// Get links array.
		$links_data = civicrm_event_organiser_get_register_links( $post_id, $title, $anchor_classes, $waitlist );
		if ( empty( $links_data ) ) {
			return $markup;
		}

		// Wrap links if required.
		$links_data = $this->link_data_wrap( $links_data, $wrap_classes, $element, $show_messages );

		// Make sure we have a Post ID.
		$post_id = intval( empty( $post_id ) ? get_the_ID() : $post_id );

		// Process array into markup.
		$markup = $this->link_process( $links_data, $post_id );

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
	 * @param bool   $show_messages Whether or not to merge messages into the link text.
	 * @return array $links_data The modified array of Registration link data.
	 */
	private function link_data_wrap( $links_data, $wrap_classes, $element, $show_messages = true ) {

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

		// Wrap links if required.
		if ( empty( $element ) && true === $show_messages ) {
			array_walk(
				$links_data,
				function( &$item, $key ) use ( $element, $classes ) {

					// Prepend link with messages.
					if ( ! empty( $item['meta'] ) && in_array( 'waitlist', $item['meta'] ) ) {
						$item['link'] = '<span class="ceo-event-has-waitlist">' . implode( ' ', $item['messages'] ) . '</span> ' . $item['link'];
					}
					if ( ! empty( $item['meta'] ) && in_array( 'is_registered', $item['meta'] ) ) {
						$item['link'] = '<span class="ceo-contact-is-registered">' . implode( ' ', $item['messages'] ) . '</span> ' . $item['link'];
					}

					// Standalone messages.
					if ( ! empty( $item['meta'] ) && in_array( 'registration_closed', $item['meta'] ) ) {
						$item['link'] = '<span class="ceo-registration-closed">' . implode( ' ', $item['messages'] ) . '</span>';
					}
					if ( ! empty( $item['meta'] ) && in_array( 'event_full', $item['meta'] ) ) {
						$item['link'] = '<span class="ceo-event-is-full">' . implode( ' ', $item['messages'] ) . '</span>';
					}

				}
			);
		}

		// Handle button element.
		if ( 'button' === $element ) {
			array_walk(
				$links_data,
				function( &$item, $key ) use ( $element, $classes, $show_messages ) {

					// Wrap the link if there is one.
					if ( ! empty( $item['link'] ) && ! empty( $item['meta'] ) && in_array( 'active', $item['meta'] ) ) {
						if ( ! empty( $classes ) ) {
							$item['link'] = '<button type="button" class="' . $classes . '">' . $item['link'] . '</button>';
						} else {
							$item['link'] = '<button type="button">' . $item['link'] . '</button>';
						}
					}

					// Only merge messages if requested.
					if ( true === $show_messages ) {

						// Prepend link with messages.
						if ( ! empty( $item['meta'] ) && in_array( 'waitlist', $item['meta'] ) ) {
							$item['link'] = '<p class="ceo-event-has-waitlist">' . implode( ' ', $item['messages'] ) . '</p>' . $item['link'];
						}
						if ( ! empty( $item['meta'] ) && in_array( 'is_registered', $item['meta'] ) ) {
							$item['link'] = '<p class="ceo-contact-is-registered">' . implode( ' ', $item['messages'] ) . '</p>' . $item['link'];
						}

						// Standalone messages.
						if ( ! empty( $item['meta'] ) && in_array( 'registration_closed', $item['meta'] ) ) {
							$item['link'] = '<p class="ceo-registration-closed">' . implode( ' ', $item['messages'] ) . '</p>';
						}
						if ( ! empty( $item['meta'] ) && in_array( 'event_full', $item['meta'] ) ) {
							$item['link'] = '<p class="ceo-event-is-full">' . implode( ' ', $item['messages'] ) . '</p>';
						}

					}

				}
			);
		}

		// Handle div element.
		if ( 'div' === $element ) {
			array_walk(
				$links_data,
				function( &$item, $key ) use ( $element, $classes, $show_messages ) {

					// Wrap the link if there is one.
					if ( ! empty( $item['link'] ) && ! empty( $item['meta'] ) && in_array( 'active', $item['meta'] ) ) {
						if ( ! empty( $classes ) ) {
							$item['link'] = '<div class="' . $classes . '">' . $item['link'] . '</div>';
						} else {
							$item['link'] = '<div>' . $item['link'] . '</div>';
						}
					}

					// Only merge messages if requested.
					if ( true === $show_messages ) {

						// Prepend link with messages.
						if ( ! empty( $item['meta'] ) && in_array( 'waitlist', $item['meta'] ) ) {
							$item['link'] = '<p class="ceo-event-has-waitlist">' . implode( ' ', $item['messages'] ) . '</p>' . $item['link'];
						}
						if ( ! empty( $item['meta'] ) && in_array( 'is_registered', $item['meta'] ) ) {
							$item['link'] = '<p class="ceo-contact-is-registered">' . implode( ' ', $item['messages'] ) . '</p>' . $item['link'];
						}

						// Standalone messages.
						if ( ! empty( $item['meta'] ) && in_array( 'registration_closed', $item['meta'] ) ) {
							$item['link'] = '<p class="ceo-registration-closed">' . implode( ' ', $item['messages'] ) . '</p>';
						}
						if ( ! empty( $item['meta'] ) && in_array( 'event_full', $item['meta'] ) ) {
							$item['link'] = '<p class="ceo-event-is-full">' . implode( ' ', $item['messages'] ) . '</p>';
						}

					}

				}
			);
		}

		// --<
		return $links_data;

	}

	/**
	 * Processes the CiviCRM Register link markup.
	 *
	 * @since 0.8.7
	 *
	 * @param array $links_data The array of HTML links to the CiviCRM Registration pages with metadata for each link.
	 * @param int   $post_id The numeric ID of the Event.
	 * @return string $markup The rendered HTML markup.
	 */
	private function link_process( $links_data, $post_id ) {

		// Init return.
		$markup = '';

		// Extract links array.
		$links = array_filter( wp_list_pluck( $links_data, 'link' ) );

		// Is it recurring?
		if ( eo_recurs( $post_id ) ) {

			// Combine into list.
			$list = implode( '</li>' . "\n" . '<li class="civicrm-event-register-link">', $links );

			// Top and tail list items.
			$list = '<li class="civicrm-event-register-link">' . $list . '</li>' . "\n";

			// Wrap in unordered list.
			$markup .= '<ul class="civicrm-event-register-links">' . $list . '</ul>';

		} else {

			// Show link.
			$markup .= implode( ' ', $links );

		}

		// --<
		return $markup;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Parses a given Event Organiser Event.
	 *
	 * @since 0.8.6
	 *
	 * @param int    $post_id The numeric ID of the WP Post.
	 * @param string $title The link title when the Event does not recur.
	 * @param string $classes The space-delimited classes to add to the links.
	 * @param string $waitlist The link title when the Event does not recur and is full and there is a waitlist.
	 * @return array $data The array of HTML links to the CiviCRM Registration pages with metadata for each link.
	 */
	public function links_get( $post_id, $title = null, $classes = null, $waitlist = null ) {

		// Bail if there are no CiviCRM Event IDs.
		$civi_event_ids = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );
		if ( empty( $civi_event_ids ) ) {
			return [];
		}

		// Init data array for this Post ID.
		$data = [];

		// Did we get more than one?
		$multiple = count( $civi_event_ids ) > 1 ? true : false;

		// Get the Contact ID for the current User.
		$contact_id = $this->plugin->ufmatch->contact_id_get_by_user_id( get_current_user_id() );

		// Let's do a single query for all the CiviCRM Events.
		$civi_events = $this->plugin->civi->event->get_events_by_ids( $civi_event_ids );

		// Loop through them.
		foreach ( $civi_events as $civi_event ) {

			/*
			 * Init info array.
			 *
			 * There can only be one registratrion link per event.
			 * There can be multiple messages per event.
			 * There can be multiple meta per event.
			 */
			$info = [
				'link'              => '',
				'messages'          => [],
				'meta'              => [],
				'remaining_count'   => false,
				'remaining_message' => '',
			];

			// Init closed flag.
			$closed = false;

			// Cast Event ID as integer.
			$civi_event_id = (int) $civi_event['id'];

			// Check if the Event is full.
			if ( $this->plugin->civi->registration->is_full( $civi_event ) ) {

				// Check if there is a Waitlist.
				if ( isset( $civi_event['has_waitlist'] ) && true === $civi_event['has_waitlist'] ) {

					// Set different text for single and multiple Occurrences.
					if ( $multiple ) {

						// Get Occurrence ID for this CiviCRM Event.
						$occurrence_id = $this->plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

						$message = sprintf(
							/* translators: %s: The formatted Event Occurrence. */
							esc_html__( 'This event on %s is currently full. However you can register now and get added to a waiting list. You will be notified if spaces become available.', 'civicrm-event-organiser' ),
							eo_format_event_occurrence( $post_id, $occurrence_id )
						);

					} else {
						$message = esc_html__( 'This event is currently full. However you can register now and get added to a waiting list. You will be notified if spaces become available.', 'civicrm-event-organiser' );
						if ( ! empty( $civi_event['waitlist_text'] ) ) {
							$message = $civi_event['waitlist_text'];
						}
					}

					// Add meta to info array.
					$info['meta'][] = 'waitlist';

				} else {

					// Set different text for single and multiple Occurrences.
					if ( $multiple ) {
						$message = sprintf(
							/* translators: %s: The formatted Event Occurrence. */
							esc_html__( 'The event on %s is currently full.', 'civicrm-event-organiser' ),
							eo_format_event_occurrence( $post_id, $occurrence_id )
						);
					} else {
						$message = esc_html__( 'This event is currently full.', 'civicrm-event-organiser' );
					}

					// Add meta to info array.
					$info['meta'][] = 'event_full';

					// Update closed flag.
					$closed = true;

				}

				/**
				 * Filter the "event full" message text.
				 *
				 * @since 0.8.2
				 *
				 * @param string $message The text content.
				 * @param int    $post_id The numeric ID of the WP Post.
				 * @param int    $civi_event_id The numeric ID of the CiviCRM Event.
				 * @param int    $contact_id The numeric ID of the CiviCRM Contact.
				 */
				$message = apply_filters( 'ceo/theme/registration/full', $message, $post_id, $civi_event_id, $contact_id );

				// Add to info array.
				$info['messages'][] = $message;

			}

			// When this Contact is already registered.
			if ( ! empty( $contact_id ) && $this->plugin->civi->registration->is_registered( $civi_event_id, $contact_id ) ) {

				// Set different text for single and multiple Occurrences.
				if ( $multiple && false === $closed ) {

					// Get Occurrence ID for this CiviCRM Event.
					$occurrence_id = $this->plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

					// Define text.
					$message = sprintf(
						/* translators: %s: The formatted Event Occurrence. */
						esc_html__( 'You are already registered for %s.', 'civicrm-event-organiser' ),
						eo_format_event_occurrence( $post_id, $occurrence_id )
					);

				} else {
					$message = esc_html__( 'You are already registered for this event.', 'civicrm-event-organiser' );
				}

				/**
				 * Filter the "already registered" message text.
				 *
				 * @since 0.8.2
				 *
				 * @param string $message The text content.
				 * @param int    $post_id The numeric ID of the WP Post.
				 * @param int    $civi_event_id The numeric ID of the CiviCRM Event.
				 * @param int    $contact_id The numeric ID of the CiviCRM Contact.
				 */
				$message = apply_filters( 'ceo/theme/registration/text', $message, $post_id, $civi_event_id, $contact_id );

				/*
				 * Until we allow registration of other Contacts, we remove the waiting list
				 * message and meta.
				 *
				 * @todo Possibly enable registration of other Contacts.
				 */
				$info['messages'] = [];
				$info['meta']     = [];

				// Add to info array.
				$info['messages'][] = $message;
				$info['meta'][]     = 'is_registered';

				// Update closed flag.
				$closed = true;

			}

			// Assign the status of the Event if Registration is not open.
			if ( $this->plugin->civi->registration->is_registration_closed( $civi_event ) ) {

				// Get the Event's Registration status.
				$status = $this->plugin->civi->registration->get_registration_status( $civi_event );

				// Do not add item if Registration is not enabled.
				if ( false === $status ) {
					continue;
				}

				// Set different text for single and multiple Occurrences.
				if ( $multiple ) {

					// Get Occurrence ID for this CiviCRM Event.
					$occurrence_id = $this->plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

					if ( 'not-yet-open' === $status ) {

						// Use start date.
						$reg_start = new DateTime( $civi_event['registration_start_date'], eo_get_blog_timezone() );

						$message = sprintf(
							/* translators: 1: The formatted Event Occurrence, 2: The date, 3: The time. */
							esc_html__( 'Online registration for %1$s will open on %2$s at %3$s.', 'civicrm-event-organiser' ),
							eo_format_event_occurrence( $post_id, $occurrence_id ),
							wp_date( get_option( 'date_format' ), $reg_start->getTimestamp() ),
							wp_date( get_option( 'time_format' ), $reg_start->getTimestamp() )
						);

					} else {
						$message = sprintf(
							/* translators: %s: The formatted Event Occurrence. */
							esc_html__( 'Online registration has closed for %s.', 'civicrm-event-organiser' ),
							eo_format_event_occurrence( $post_id, $occurrence_id )
						);
					}

				} else {

					if ( 'not-yet-open' === $status ) {

						// Use start date.
						$reg_start = new DateTime( $civi_event['registration_start_date'], eo_get_blog_timezone() );

						$message = sprintf(
							/* translators: 1: The date, 2: The time. */
							esc_html__( 'Online registration will open on %1$s at %2$s.', 'civicrm-event-organiser' ),
							wp_date( get_option( 'date_format' ), $reg_start->getTimestamp() ),
							wp_date( get_option( 'time_format' ), $reg_start->getTimestamp() )
						);

					} else {
						$message = esc_html__( 'Online registration has closed.', 'civicrm-event-organiser' );
					}

				}

				/**
				 * Filter the "registration closed" message text.
				 *
				 * @since 0.8.2
				 *
				 * @param string $message The message content.
				 * @param int    $post_id The numeric ID of the WP Post.
				 * @param int    $civi_event_id The numeric ID of the CiviCRM Event.
				 * @param int    $contact_id The numeric ID of the CiviCRM Contact.
				 */
				$message = apply_filters( 'ceo/theme/registration/closed', $message, $post_id, $civi_event_id, $contact_id );

				// Add to info array.
				$info['messages'][] = $message;
				$info['meta'][]     = 'registration_closed';

				// Update closed flag.
				$closed = true;

			}

			// Get remaining places if there is a limit on participants.
			if ( ! empty( $civi_event['max_participants'] ) ) {

				$remaining = $this->plugin->civi->registration->get_remaining_participants( $civi_event_id );
				if ( 0 > $remaining ) {
					$remaining = 0;
				}

				// Set different text for single and multiple Occurrences.
				if ( $multiple ) {

					// Get Occurrence ID for this CiviCRM Event.
					$occurrence_id = $this->plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

					$message = sprintf(
						/* translators: 1: The number of remaining places, 2: The formatted Event Occurrence. */
						_n( '%1$d place remaining for %2$s.', '%1$d places remaining for %2$s.', $remaining, 'civicrm-event-organiser' ),
						esc_html( $remaining ),
						eo_format_event_occurrence( $post_id, $occurrence_id )
					);

				} else {
					$message = sprintf(
						/* translators: %d: The number of remaining places. */
						_n( '%d place remaining', '%d places remaining', $remaining, 'civicrm-event-organiser' ),
						esc_html( $remaining )
					);
				}

				// Add to info array.
				$info['remaining_count']   = (string) $remaining;
				$info['remaining_message'] = $message;

			}

			// Closed Events do not have a link.
			if ( false === $closed ) {

				// Get link for the Registration page.
				$url = $this->plugin->civi->registration->get_registration_link( $civi_event );
				if ( empty( $url ) ) {
					continue;
				}

				/**
				 * Filter Registration URL.
				 *
				 * @since 0.3
				 * @deprecated 0.8.0 Use the {@see 'ceo/theme/registration/url'} filter instead.
				 *
				 * @param string $url The raw URL to the CiviCRM Registration page.
				 * @param array  $civi_event The array of data that represents a CiviCRM Event.
				 * @param int    $post_id The numeric ID of the WP Post.
				 */
				$url = apply_filters_deprecated( 'civicrm_event_organiser_registration_url', [ $url, $civi_event, $post_id ], '0.8.0', 'ceo/theme/registration/url' );

				/**
				 * Filter the Registration URL.
				 *
				 * @since 0.8.0
				 *
				 * @param string $url The raw URL to the CiviCRM Registration page.
				 * @param array  $civi_event The array of data that represents a CiviCRM Event.
				 * @param int    $post_id The numeric ID of the WP Post.
				 * @param string $title The link title when the Event does not recur.
				 * @param string $classes The space-delimited classes to add to the link.
				 */
				$url = apply_filters( 'ceo/theme/registration/url', $url, $civi_event, $post_id, $title, $classes );

				// Set different link text for single and multiple Occurrences.
				if ( $multiple ) {

					// Get Occurrence ID for this CiviCRM Event.
					$occurrence_id = $this->plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

					// Change text when there is a waitlist.
					if ( in_array( 'waitlist', $info['meta'] ) ) {
						$text = sprintf(
							/* translators: %s: The formatted Event Occurrence. */
							esc_html__( 'Join the waitlist for %s', 'civicrm-event-organiser' ),
							eo_format_event_occurrence( $post_id, $occurrence_id )
						);
					} else {
						$text = sprintf(
							/* translators: %s: The formatted Event Occurrence. */
							esc_html__( 'Register for %s', 'civicrm-event-organiser' ),
							eo_format_event_occurrence( $post_id, $occurrence_id )
						);
					}

				} else {

					/*
					 * Change button text when there is a waitlist.
					 * Also override with custom button text if provided.
					 */
					if ( in_array( 'waitlist', $info['meta'] ) ) {
						$text = esc_html__( 'Waitlist', 'civicrm-event-organiser' );
						if ( ! empty( $waitlist ) ) {
							$text = esc_html( $waitlist );
						}
					} else {
						$text = esc_html__( 'Register', 'civicrm-event-organiser' );
						if ( ! empty( $title ) ) {
							$text = esc_html( $title );
						}
					}

				}

				// Format classes if provided.
				if ( ! empty( $classes ) ) {
					$classes = explode( ' ', $classes );
					array_walk(
						$classes,
						function( &$item ) {
							$item = esc_attr( $item );
						}
					);
					$classes = ' ' . implode( ' ', $classes );
				}

				// Construct link.
				$link = '<a class="civicrm-event-organiser-register-link' . $classes . '" href="' . esc_url( $url ) . '">' . $text . '</a>';

				/**
				 * Filter Registration link.
				 *
				 * @since 0.3
				 * @deprecated 0.8.0 Use the {@see 'ceo/theme/registration/link'} filter instead.
				 *
				 * @param string $link The HTML link to the CiviCRM Registration page.
				 * @param string $url The raw URL to the CiviCRM Registration page.
				 * @param string $text The text content of the link.
				 * @param int    $post_id The numeric ID of the WP Post.
				 */
				$link = apply_filters_deprecated( 'civicrm_event_organiser_registration_link', [ $link, $url, $text, $post_id ], '0.8.0', 'ceo/theme/registration/link' );

				/**
				 * Filter the Registration link.
				 *
				 * @since 0.8.0
				 *
				 * @param string $link The HTML link to the CiviCRM Registration page.
				 * @param string $url The raw URL to the CiviCRM Registration page.
				 * @param string $text The text content of the link.
				 * @param int    $post_id The numeric ID of the WP Post.
				 * @param string $classes The space-delimited classes to add to the link.
				 */
				$link = apply_filters( 'ceo/theme/registration/link', $link, $url, $text, $post_id, $classes );

				// Add to info array.
				$info['link']   = $link;
				$info['meta'][] = 'active';

			}

			// Assign to data.
			$data[ $civi_event_id ] = $info;

		}

		// --<
		return $data;

	}

	/**
	 * Filters the array of Event Organiser Placeholder Tag patterns.
	 *
	 * Note that this functionality only works with the Tadpole clone of Event Organiser.
	 *
	 * @since 0.8.7
	 *
	 * @param array  $patterns The array of Placeholder Tag patterns.
	 * @param string $template The "placeholder" template.
	 * @return array $patterns The modified array of Placeholder Tag patterns.
	 */
	public function template_patterns( $patterns, $template ) {

		// Add the CiviCRM patterns.
		$patterns[] = '/%(ceo_remaining)%/';
		$patterns[] = '/%(ceo_remaining_number)%/';
		$patterns[] = '/%(ceo_messages)%/';
		$patterns[] = '/%(ceo_link)%/';

		// --<
		return $patterns;

	}

	/**
	 * Filters the replacement string for an Event Organiser Placeholder Tag.
	 *
	 * Note that this functionality only works with the Tadpole clone of Event Organiser.
	 *
	 * @since 0.8.7
	 *
	 * @param string $replacement The replacement string.
	 * @param array  $matches The array of matched elements.
	 * @return string $replacement The modified replacement string.
	 */
	public function template_replacement( $replacement, $matches ) {

		global $post;

		// Default to the current Event.
		$post_id = intval( empty( $post->id ) ? get_the_ID() : $post->id );

		// Get links array.
		$links_data = civicrm_event_organiser_get_register_links( $post_id );
		if ( empty( $links_data ) ) {
			return $replacement;
		}

		switch ( $matches[1] ) {
			case 'ceo_remaining_number':
			case 'ceo_remaining':
				$format = null;
				if ( 'ceo_remaining_number' === $matches[1] ) {
					$format = 'raw';
				}
				$replacement = $this->remaining_process( $links_data, $post_id, $format );
				break;

			case 'ceo_messages':
				$replacement = $this->messages_process( $links_data, $post_id );
				break;

			case 'ceo_link':
				$replacement = $this->link_process( $links_data, $post_id );
				break;

		}

		// --<
		return $replacement;

	}

}
