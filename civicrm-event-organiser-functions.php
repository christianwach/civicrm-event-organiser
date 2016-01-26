<?php /*
--------------------------------------------------------------------------------
CiviCRM Event Organiser Template functions
--------------------------------------------------------------------------------

These functions may be used in your theme files. Most rely on being called
during The Loop. Please refer to the docblocks of each function to see usage
instructions.


/**
 * The default way to load CiviCRM Registration links into EO event meta.
 */
//add_action( 'eventorganiser_additional_event_meta', 'civicrm_event_organiser_registration_link' );



/**
 * Override the loading of CiviCRM Registration links into EO event meta.
 */
function cmw_child_theme_add_register_link() {
	echo '<li class="hello-world">' . civicrm_event_organiser_get_register_link( $post_id ) . '</li>';
}

// action for the above
add_action( 'eventorganiser_additional_event_meta', 'cmw_child_theme_add_register_link' );


--------------------------------------------------------------------------------
*/



/**
 * Add an item containing the Registration link for an EO Event to the EO event meta list.
 *
 * Because there have only been appropriate hooks in Event Organiser template
 * files since version 2.12.5, we leave it up to theme developers to add a call
 * to this function to their theme file or set it as a callback to an action in
 * The Loop.
 *
 * To do so in the default way:
 *
 * add_action( 'eventorganiser_additional_event_meta', 'civicrm_event_organiser_registration_link' );
 *
 * Or override with something like:
 *
 * function my_child_theme_add_register_link() {
 *     echo '<li class="hello-world">' . civicrm_event_organiser_get_register_link( $post_id ) . '</li>';
 * }
 * add_action( 'eventorganiser_additional_event_meta', 'my_child_theme_add_register_link' );
 *
 * @param int $post_id The numeric ID of the WP post
 */
function civicrm_event_organiser_registration_link( $post_id = null ) {
	echo '<li>' . civicrm_event_organiser_get_register_link( $post_id ) . '</li>';
}



/**
 * Echo the Registration link for an EO Event.
 *
 * @param int $post_id The numeric ID of the WP post
 */
function civicrm_event_organiser_register_link( $post_id = null ) {
	echo civicrm_event_organiser_get_register_link( $post_id );
}



/**
 * Get the Registration link for an EO Event.
 *
 * @param int $post_id The numeric ID of the WP post
 * @return string $link The HTML link to the CiviCRM Registration page
 */
function civicrm_event_organiser_get_register_link( $post_id = null ) {

	// init return
	$link = '';

	// define link text
	$text = __( 'Register', 'civicrm-event-organiser' );

	// get URL for the registration page
	$url = civicrm_event_organiser_get_register_url( $post_id );

	// construct link if we get one
	if ( ! empty( $url ) ) {
		$link = '<a class="civicrm-event-organiser-register-link" href="' . $url . '">' . $text . '</a>';
	}

	/**
	 * Filter registration link before returning.
	 *
	 * @param string $link The HTML link to the CiviCRM Registration page
	 * @param string $url The raw URL to the CiviCRM Registration page
	 * @param string $text The text content of the link
	 * @param int $post_id The numeric ID of the WP post
	 */
	return apply_filters( 'civicrm_event_organiser_register_link', $link, $url, $text, $post_id );

}



/**
 * Echo the Registration URL for an EO Event.
 *
 * @param int $post_id The numeric ID of the WP post
 */
function civicrm_event_organiser_register_url( $post_id = null ) {
	echo civicrm_event_organiser_get_register_url( $post_id );
}



/**
 * Get the Registration link for an EO Event.
 *
 * @param int $post_id The numeric ID of the WP post
 * @return string $url The raw URL to the CiviCRM Registration page
 */
function civicrm_event_organiser_get_register_url( $post_id = null ) {

	// init return
	$url = '';

	// bail if no CiviCRM init function
	if ( ! function_exists( 'civi_wp' ) ) return $url;

	// try and init CiviCRM
	if ( ! civi_wp()->initialize() ) return $url;

	// need the post ID
	$post_id = absint( empty( $post_id ) ? get_the_ID() : $post_id );

	// bail if not present
	if( empty( $post_id ) ) return $url;

	// get plugin reference
	$plugin = civicrm_eo();

	// get CiviEvents
	$civi_events = $plugin->db->get_civi_event_ids_by_eo_event_id( $post_id );

	// sanity check
	if ( empty( $civi_events ) ) return $url;

	// get the first CiviEvent, though any would do as they all have the same value
	$civi_event = $plugin->civi->get_event_by_id( array_shift( $civi_events ) );

	// get link for the registration page
	$url = $plugin->civi->get_registration_link( $civi_event );

	/**
	 * Filter registration URL before returning.
	 *
	 * @param string $url The raw URL to the CiviCRM Registration page
	 * @param string $link The HTML link to the CiviCRM Registration page
	 * @param string $text The text content of the link
	 * @param int $post_id The numeric ID of the WP post
	 */
	return apply_filters( 'civicrm_event_organiser_register_url', $url, $civi_event, $post_id );

}



