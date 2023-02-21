<?php
/**
 * CiviCRM Event Organiser Template functions.
 *
 * These functions may be used in your theme files. Most rely on being called
 * during The Loop. Please refer to the docblocks of each function to see usage
 * instructions.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.2.2
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
 * @param int $post_id The numeric ID of the WP Post.
 */
function civicrm_event_organiser_register_links( $post_id = null ) {

	// Get links array.
	$links = civicrm_event_organiser_get_register_links( $post_id );

	// Show them if we have any.
	if ( ! empty( $links ) ) {

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
			echo '<strong>' . __( 'Registration Links', 'civicrm-event-organiser' ) . ':</strong>';

			// Show links.
			echo $list;

			// Finish up.
			echo '</li>' . "\n";

		} else {

			// Show link.
			echo $list . "\n";

		}

	}

}

// Add action for the above.
add_action( 'eventorganiser_additional_event_meta', 'civicrm_event_organiser_register_links' );

/**
 * Get the Registration links for an Event Organiser Event.
 *
 * @since 0.3
 *
 * @param int $post_id The numeric ID of the WP Post.
 * @return array $links The HTML links to the CiviCRM Registration pages.
 */
function civicrm_event_organiser_get_register_links( $post_id = null ) {

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
	$post_id = absint( empty( $post_id ) ? get_the_ID() : $post_id );

	// Bail if not present.
	if ( empty( $post_id ) ) {
		return $links;
	}

	// Get plugin reference.
	$plugin = civicrm_eo();

	// Get CiviCRM Events.
	$civi_events = $plugin->mapping->get_civi_event_ids_by_eo_event_id( $post_id );

	// Sanity check.
	if ( empty( $civi_events ) ) {
		return $links;
	}

	// Did we get more than one?
	$multiple = ( count( $civi_events ) > 1 ) ? true : false;

	// Loop through them.
	foreach ( $civi_events as $civi_event_id ) {

		// Get the full CiviCRM Event.
		$civi_event = $plugin->civi->event->get_event_by_id( $civi_event_id );
		if ( $civi_event === false ) {
			continue;
		}

		// Skip to next if Registration is not open.
		if ( $plugin->civi->registration->is_registration_closed( $civi_event ) ) {
			continue;
		}

		// Get link for the Registration page.
		$url = $plugin->civi->registration->get_registration_link( $civi_event );

		// Skip to next if empty.
		if ( empty( $url ) ) {
			continue;
		}

		/**
		 * Filter Registration URL.
		 *
		 * @since 0.3
		 *
		 * @param string $url The raw URL to the CiviCRM Registration page.
		 * @param array $civi_event The array of data that represents a CiviCRM Event.
		 * @param int $post_id The numeric ID of the WP Post.
		 */
		$url = apply_filters( 'civicrm_event_organiser_registration_url', $url, $civi_event, $post_id );

		// Set different link text for single and multiple Occurrences.
		if ( $multiple ) {

			// Get Occurrence ID for this CiviCRM Event.
			$occurrence_id = $plugin->mapping->get_eo_occurrence_id_by_civi_event_id( $civi_event_id );

			// Define text.
			$text = sprintf(
				/* translators: %s: The formatted Event Occurrence. */
				__( 'Register for %s', 'civicrm-event-organiser' ),
				eo_format_event_occurrence( $post_id, $occurrence_id )
			);

		} else {
			$text = __( 'Register', 'civicrm-event-organiser' );
		}

		// Construct link if we get one.
		$link = '<a class="civicrm-event-organiser-register-link" href="' . $url . '">' . $text . '</a>';

		/**
		 * Filter Registration link.
		 *
		 * @since 0.3
		 *
		 * @param string $link The HTML link to the CiviCRM Registration page.
		 * @param string $url The raw URL to the CiviCRM Registration page.
		 * @param string $text The text content of the link.
		 * @param int $post_id The numeric ID of the WP Post.
		 */
		$links[] = apply_filters( 'civicrm_event_organiser_registration_link', $link, $url, $text, $post_id );

	}

	// --<
	return $links;

}
