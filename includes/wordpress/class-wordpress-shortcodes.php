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
	 * Event data array.
	 *
	 * Holds data about Events, keyed by Post ID.
	 *
	 * This array gets added to when either of the Shortcodes gets called, so that
	 * database queries are minimised.
	 *
	 * @since 0.8.6
	 * @access public
	 * @var array
	 */
	public $data = [];

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

		// Make sure we have a Post ID.
		$post_id = intval( empty( $post_id ) ? get_the_ID() : $post_id );

		// Is it recurring?
		if ( eo_recurs( $post_id ) ) {

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
			$markup .= '<strong>' . esc_html__( 'Registration Links', 'civicrm-event-organiser' ) . ':</strong>';

			// Show links.
			$markup .= $list;

			// Finish up.
			$markup .= '</li>' . "\n";

		} else {

			// Show link.
			$links   = wp_list_pluck( $links_data, 'link' );
			$markup .= implode( ' ', $links );

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
		if ( empty( $element ) ) {
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
				function( &$item, $key ) use ( $element, $classes ) {

					// First wrap the link if there is one.
					if ( ! empty( $item['link'] ) && ! empty( $item['meta'] ) && in_array( 'active', $item['meta'] ) ) {
						if ( ! empty( $classes ) ) {
							$item['link'] = '<button type="button" class="' . $classes . '">' . $item['link'] . '</button>';
						} else {
							$item['link'] = '<button type="button">' . $item['link'] . '</button>';
						}
					}

					// Next prepend link with messages.
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
			);
		}

		// Handle div element.
		if ( 'div' === $element ) {
			array_walk(
				$links_data,
				function( &$item, $key ) use ( $element, $classes ) {

					// First wrap the link if there is one.
					if ( ! empty( $item['link'] ) && ! empty( $item['meta'] ) && in_array( 'active', $item['meta'] ) ) {
						if ( ! empty( $classes ) ) {
							$item['link'] = '<div class="' . $classes . '">' . $item['link'] . '</div>';
						} else {
							$item['link'] = '<div>' . $item['link'] . '</div>';
						}
					}

					// Next prepend link with messages.
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
			);
		}

		// --<
		return $links_data;

	}

	/**
	 * Parses a given Event Organiser Event.
	 *
	 * @since 0.8.6
	 *
	 * @param int    $post_id The numeric ID of the WP Post.
	 * @param string $title The link title when the Event does not recur.
	 * @param string $classes The space-delimited classes to add to the links.
	 * @return array $data The array of HTML links to the CiviCRM Registration pages with metadata for each link.
	 */
	public function links_get( $post_id, $title = null, $classes = null ) {

		// Return data if already parsed.
		if ( isset( $this->data[ $post_id ] ) ) {
			return $this->data[ $post_id ];
		}

		// Bail if there are no CiviCRM Event IDs.
		$civi_event_ids = $this->plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );
		if ( empty( $civi_event_ids ) ) {
			return [];
		}

		// Init data array for this Post ID.
		$this->data[ $post_id ] = [];

		// Did we get more than one?
		$multiple = ( count( $civi_event_ids ) > 1 ) ? true : false;

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
				'link'     => '',
				'messages' => [],
				'meta'     => [],
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

					// Define text.
					$text = sprintf(
						/* translators: %s: The formatted Event Occurrence. */
						esc_html__( 'Register for %s', 'civicrm-event-organiser' ),
						eo_format_event_occurrence( $post_id, $occurrence_id )
					);

				} else {

					// Default title.
					$text = esc_html__( 'Register', 'civicrm-event-organiser' );

					// Use custom title if provided.
					if ( ! empty( $title ) ) {
						$text = esc_html( $title );
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
			$this->data[ $post_id ][ $civi_event_id ] = $info;

		}

		// --<
		return $this->data[ $post_id ];

	}

}
