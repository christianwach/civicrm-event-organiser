/**
 * Admin "Settings" Javascript.
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
		if ( current_on ) {
			$('.ceo_confirm_email_sub_setting').show();
		} else {
			$('.ceo_confirm_email_sub_setting').hide();
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

			// Toggle depending on checked status.
			var current_on = $(this).prop( 'checked' );
			if ( current_on ) {
				$('.ceo_confirm_email_sub_setting').show();
			} else {
				$('.ceo_confirm_email_sub_setting').hide();
			}

		});

		// Enable Select2 on "Limit CiviCRM Profiles for Event Registration".
		$('#civi_eo_event_allowed_profiles').select2();

   	});

} )( jQuery );
