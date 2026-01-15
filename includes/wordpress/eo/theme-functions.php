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

	// Process messages.
	array_walk(
		$links_data,
		function( &$item, $key ) {

			// Prepend link with message.
			if ( ! empty( $item['meta'] ) && in_array( 'waitlist', $item['meta'] ) ) {
				$item['link'] = '<span class="ceo-event-has-waitlist">' . implode( ' ', $item['messages'] ) . '</span> ' . $item['link'];
			}
			if ( ! empty( $item['meta'] ) && in_array( 'is_registered', $item['meta'] ) ) {
				$item['link'] = '<span class="ceo-contact-is-registered">' . implode( ' ', $item['messages'] ) . '</span> ' . $item['link'];
			}

			// Standalone messages.
			if ( ! empty( $item['meta'] ) && in_array( 'event_full', $item['meta'] ) ) {
				$item['link'] = '<span class="ceo-event-is-full">' . implode( ' ', $item['messages'] ) . '</span>';
			}
			if ( ! empty( $item['meta'] ) && in_array( 'registration_closed', $item['meta'] ) ) {
				$item['link'] = '<span class="ceo-registration-closed">' . implode( ' ', $item['messages'] ) . '</span>';
			}

		}
	);

	// Extract links array.
	$links = wp_list_pluck( $links_data, 'link' );

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

	// Call link builder method.
	$links = $plugin->wordpress->shortcodes->links_get( $post_id, $title, $classes );

	// --<
	return $links;

}
