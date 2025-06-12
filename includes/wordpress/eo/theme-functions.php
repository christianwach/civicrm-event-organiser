<?php
/**
 * Template functions.
 *
 * These functions may be used in your theme files. Most rely on being called
 * during The Loop. Please refer to the docblocks of each function to see usage
 * instructions.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Add a list of Registration links for an Event to the Event Organiser Event meta list.
 *
 * There have only been appropriate hooks in Event Organiser template files
 * since version 2.12.5, so installs with a prior version of Event Organiser
 * will have to manually add a call to this function in their template file(s).
 *
 * @since 0.3
 *
 * @param int    $post_id The numeric ID of the WP Post.
 * @param string $title The link title when the Event does not recur.
 * @param string $classes The space-delimited classes to add to the links.
 */
function civicrm_event_organiser_register_links( $post_id = null, $title = null, $classes = null ) {

	// Get links array.
	$links_data = civicrm_event_organiser_get_register_links( $post_id, $title, $classes );
	if ( empty( $links_data ) ) {
		return;
	}

	// Extract links array.
	$links = [];
	foreach ( $links_data as $link_data ) {
		$sub_links = wp_list_pluck( $link_data, 'link' );
		$links[]   = implode( ' ', $sub_links );
	}

	// Combine into list.
	$list = implode( '</li>' . "\n" . '<li class="civicrm-event-register-link">', $links );

	// Top and tail.
	$list = '<li class="civicrm-event-register-link">' . $list . '</li>' . "\n";

	// Is it recurring?
	if ( eo_recurs() ) {

		// Wrap in unordered list.
		$list = '<ul class="civicrm-event-register-links">' . $list . '</ul>';

		// Open a list item.
		echo '<li class="civicrm-event-register-links">';

		// Show a title.
		echo '<strong>' . esc_html__( 'Registration Links', 'civicrm-event-organiser' ) . ':</strong>';

		// Show links.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $list;

		// Finish up.
		echo '</li>' . "\n";

	} else {

		// Show link.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $list . "\n";

	}

}

// Add action for the above.
add_action( 'eventorganiser_additional_event_meta', 'civicrm_event_organiser_register_links' );

/**
 * Get the Registration links for an Event Organiser Event.
 *
 * The return array contains items which are arrays containing the  HTML link to the
 * CiviCRM Registration page with metadata for each link.
 *
 * @since 0.3
 * @since 0.8.2 Return array format changed.
 *
 * @param int    $post_id The numeric ID of the WP Post.
 * @param string $title The link title when the Event does not recur.
 * @param string $classes The space-delimited classes to add to the links.
 * @return array $links The array of HTML links to the CiviCRM Registration pages with metadata for each link.
 */
function civicrm_event_organiser_get_register_links( $post_id = null, $title = null, $classes = null ) {

	// Init return.
	$links = [];

	// Bail if no CiviCRM init function.
	if ( ! function_exists( 'civi_wp' ) ) {
		return $links;
	}

	// Try and init CiviCRM.
	if ( ! civi_wp()->initialize() ) {
		return $links;
	}

	// Need the Post ID.
	$post_id = intval( empty( $post_id ) ? get_the_ID() : $post_id );
	if ( empty( $post_id ) ) {
		return $links;
	}

	// Get plugin reference.
	$plugin = civicrm_eo();

	// Get CiviCRM Events.
	$civi_events = $plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );
	if ( empty( $civi_events ) ) {
		return $links;
	}

	// Did we get more than one?
	$multiple = ( count( $civi_events ) > 1 ) ? true : false;

	// Get the Contact ID for the current User.
	$contact_id = $plugin->ufmatch->contact_id_get_by_user_id( get_current_user_id() );

	// Loop through them.
	foreach ( $civi_events as $civi_event_id ) {

		// Get the full CiviCRM Event.
		$civi_event = $plugin->civi->event->get_event_by_id( $civi_event_id );
		if ( false === $civi_event ) {
			continue;
		}

		// Init closed flag.
		$closed = false;

		// Skip to next if Registration is not open.
		if ( $plugin->civi->registration->is_registration_closed( $civi_event ) ) {

			// Get the Event's Registration status.
			$status = $plugin->civi->registration->get_registration_status( $civi_event );

			// Set different text for single and multiple Occurrences.
			if ( $multiple ) {

				// Get Occurrence ID for this CiviCRM Event.
				$occurrence_id = $plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

				if ( 'not-yet-open' === $status ) {

					// Use start date.
					$reg_start = new DateTime( $civi_event['registration_start_date'], eo_get_blog_timezone() );

					$text = sprintf(
						/* translators: 1: The formatted Event Occurrence, 2: The date, 3: The time. */
						esc_html__( 'Online registration for %1$s will open on %2$s at %3$s.', 'civicrm-event-organiser' ),
						eo_format_event_occurrence( $post_id, $occurrence_id ),
						wp_date( get_option( 'date_format' ), $reg_start->getTimestamp() ),
						wp_date( get_option( 'time_format' ), $reg_start->getTimestamp() )
					);

				} else {
					$text = sprintf(
						/* translators: %s: The formatted Event Occurrence. */
						esc_html__( 'Online registration has closed for %s.', 'civicrm-event-organiser' ),
						eo_format_event_occurrence( $post_id, $occurrence_id )
					);
				}

			} else {

				if ( 'not-yet-open' === $status ) {

					// Use start date.
					$reg_start = new DateTime( $civi_event['registration_start_date'], eo_get_blog_timezone() );

					$text = sprintf(
						/* translators: 1: The date, 2: The time. */
						esc_html__( 'Online registration will open on %1$s at %2$s.', 'civicrm-event-organiser' ),
						wp_date( get_option( 'date_format' ), $reg_start->getTimestamp() ),
						wp_date( get_option( 'time_format' ), $reg_start->getTimestamp() )
					);

				} else {
					$text = esc_html__( 'Online registration has closed.', 'civicrm-event-organiser' );
				}

			}

			/**
			 * Filter the "registration closed" text.
			 *
			 * @since 0.8.2
			 *
			 * @param string $text The text content.
			 * @param int $post_id The numeric ID of the WP Post.
			 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
			 * @param int $contact_id The numeric ID of the CiviCRM Contact.
			 */
			$text = apply_filters( 'ceo/theme/registration/closed', $text, $post_id, $civi_event_id, $contact_id );

			// Add to return array.
			$links[ $civi_event_id ][] = [
				'link' => $text,
				'meta' => 'registration_closed',
			];

			// Update closed flag.
			$closed = true;

		}

		// Skip to next if this Contact is already registered.
		if ( ! empty( $contact_id ) && $plugin->civi->registration->is_registered( $civi_event_id, $contact_id ) ) {

			// Set different text for single and multiple Occurrences.
			if ( $multiple && false === $closed ) {

				// Get Occurrence ID for this CiviCRM Event.
				$occurrence_id = $plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

				// Define text.
				$text = sprintf(
					/* translators: %s: The formatted Event Occurrence. */
					esc_html__( 'You are already registered for %s.', 'civicrm-event-organiser' ),
					eo_format_event_occurrence( $post_id, $occurrence_id )
				);

			} else {
				$text = esc_html__( 'You are already registered for this event.', 'civicrm-event-organiser' );
			}

			/**
			 * Filter the "already registered" text.
			 *
			 * @since 0.8.2
			 *
			 * @param string $text The text content.
			 * @param int $post_id The numeric ID of the WP Post.
			 * @param int $civi_event_id The numeric ID of the CiviCRM Event.
			 * @param int $contact_id The numeric ID of the CiviCRM Contact.
			 */
			$text = apply_filters( 'ceo/theme/registration/text', $text, $post_id, $civi_event_id, $contact_id );

			// Add to return array.
			$links[ $civi_event_id ][] = [
				'link' => $text,
				'meta' => 'is_registered',
			];

			continue;

		}

		// Allows both of the above messages to be rendered.
		if ( true === $closed ) {
			continue;
		}

		// Get link for the Registration page.
		$url = $plugin->civi->registration->get_registration_link( $civi_event );
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
		 * @param array $civi_event The array of data that represents a CiviCRM Event.
		 * @param int $post_id The numeric ID of the WP Post.
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
			$occurrence_id = $plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

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
		 * @param int $post_id The numeric ID of the WP Post.
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

		// Add to return array.
		$links[ $civi_event_id ][] = [
			'link' => $link,
			'meta' => 'active',
		];

	}

	// --<
	return $links;

}
