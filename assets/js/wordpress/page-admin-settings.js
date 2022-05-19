/**
 * CiviCRM Event Organiser "Settings" Javascript.
 *
 * Implements sync functionality on the plugin's "Settings" admin page.
 *
 * @package CiviCRM_Event_Organiser
 */

/**
 * Pass the jQuery shortcut in.
 *
 * @since 0.7.2
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Act on document ready.
	 *
	 * @since 0.7.2
	 */
	$(document).ready( function() {

		// Set initial visibility.
		var current_on = $('#civi_eo_event_default_send_email').prop( 'checked' );
		console.log( 'current_on', current_on );
		if ( current_on ) {
			$('.send-email-toggle').show();
		} else {
			$('.send-email-toggle').hide();
		}

		/**
		 * Toggle visibility of sections dependent on whether the Registration
		 * Confirmation Email is enabled or not.
		 *
		 * @since 0.7.2
		 *
		 * @param {Object} e The click event object
		 */
		$('#civi_eo_event_default_send_email').click( function(e) {

			var current_on;

			// Detect checked.
			current_on = $(this).prop( 'checked' );

			// Toggle.
			if ( current_on ) {
				$('.send-email-toggle').slideDown( 'slow' );
			} else {
				$('.send-email-toggle').slideUp( 'slow' );
			}

		});

	});

} )( jQuery );
