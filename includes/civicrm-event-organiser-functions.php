<?php

/**
 * CiviCRM Event Organiser Template functions.
 *
 * These functions may be used in your theme files. Most rely on being called
 * during The Loop. Please refer to the docblocks of each function to see usage
 * instructions.
 *
 * @since 0.2.2
 */



/**
 * Add a list of Registration links for an event to the EO event meta list.
 *
 * There have only been appropriate hooks in Event Organiser template files
 * since version 2.12.5, so installs with a prior version of Event Organiser
 * will have to manually add a call to this function in their template file(s).
 *
 * @since 0.3
 *
 * @param int $post_id The numeric ID of the WP post
 */
function civicrm_event_organiser_register_links( $post_id = null ) {

	// handle recurring events
	if ( eo_recurs() ) {

		// open a list item
		echo '<li class="civicrm-event-register-links">';

		// show a title
		echo '<strong>' . __( 'Registration Links', 'civicrm-event-organiser' ) . ':</strong>';

		// show links
		civicrm_event_organiser_registration_links( $post_id );

		// finish up
		echo '</li>' . "\n";

	} else {

		// just show link
		civicrm_event_organiser_registration_links( $post_id );

	}

}

// add action for the above
add_action( 'eventorganiser_additional_event_meta', 'civicrm_event_organiser_register_links' );



/**
 * Echo the Registration links for an EO Event.
 *
 * @since 0.3
 *
 * @param int $post_id The numeric ID of the WP post
 */
function civicrm_event_organiser_registration_links( $post_id = null ) {

	// get links array
	$links = civicrm_event_organiser_get_registration_links( $post_id );

	// show them if we have any
	if ( ! empty( $links ) ) {

		// combine into list
		$list = implode( '</li>' . "\n" . '<li class="civicrm-event-register-link">', $links );

		// top and tail
		$list = '<li class="civicrm-event-register-link">' . $list . '</li>' . "\n";

		// wrap in unordered list if multiple
		if ( count( $links ) > 1 ) {
			$list = '<ul class="civicrm-event-register-links">' . $list . '</ul>';
		}

		// print to screen
		echo $list . "\n";

	}

}



/**
 * Get the Registration links for an EO Event.
 *
 * @since 0.3
 *
 * @param int $post_id The numeric ID of the WP post
 * @return array $links The HTML links to the CiviCRM Registration pages
 */
function civicrm_event_organiser_get_registration_links( $post_id = null ) {

	// init return
	$links = array();

	// bail if no CiviCRM init function
	if ( ! function_exists( 'civi_wp' ) ) return $links;

	// try and init CiviCRM
	if ( ! civi_wp()->initialize() ) return $links;

	// need the post ID
	$post_id = absint( empty( $post_id ) ? get_the_ID() : $post_id );

	// bail if not present
	if( empty( $post_id ) ) return $links;

	// get plugin reference
	$plugin = civicrm_eo();

	// get CiviEvents
	$civi_events = $plugin->db->get_civi_event_ids_by_eo_event_id( $post_id );

	// sanity check
	if ( empty( $civi_events ) ) return $links;

	// did we get more than one?
	$multiple = ( count( $civi_events ) > 1 ) ? true : false;

	// if multiple
	if ( $multiple ) {

		// get all dates for this event
		$dates = $plugin->eo->get_all_dates( $post_id );

		// start counter
		$counter = 0;

	}

	// loop through them
	foreach( $civi_events AS $civi_event_id ) {

		// get the full CiviEvent
		$civi_event = $plugin->civi->get_event_by_id( $civi_event_id );

		// get link for the registration page
		$url = $plugin->civi->get_registration_link( $civi_event );

		/**
		 * Filter registration URL.
		 *
		 * @since 0.3
		 *
		 * @param string $url The raw URL to the CiviCRM Registration page
		 * @param array $civi_event The array of data that represents a CiviEvent
		 * @param int $post_id The numeric ID of the WP post
		 */
		$url = apply_filters( 'civicrm_event_organiser_registration_url', $url, $civi_event, $post_id );

		// set different link text for single and multiple occurrences
		if ( $multiple ) {

			// define text
			$text = sprintf(
				__( 'Register for %s', 'civicrm-event-organiser' ),
				eo_format_event_occurrence( $post_id, $dates[$counter]['occurrence_id'] )
			);

			// increment date counter
			$counter++;

		} else {
			$text = __( 'Register', 'civicrm-event-organiser' );
		}

		// construct link if we get one
		if ( ! empty( $url ) ) {
			$link = '<a class="civicrm-event-organiser-register-link" href="' . $url . '">' . $text . '</a>';
		}

		/**
		 * Filter registration link.
		 *
		 * @since 0.3
		 *
		 * @param string $link The HTML link to the CiviCRM Registration page
		 * @param string $url The raw URL to the CiviCRM Registration page
		 * @param string $text The text content of the link
		 * @param int $post_id The numeric ID of the WP post
		 */
		$links[] = apply_filters( 'civicrm_event_organiser_registration_link', $link, $url, $text, $post_id );

	}

	// --<
	return $links;

}



