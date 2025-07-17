/**
 * Custom ACF Field Type - CiviCRM Price Set Field.
 *
 * @package CiviCRM_Event_Organiser
 */

/**
 * Register ACF Field Type.
 *
 * @since 0.8.2
 */
(function($, undefined){

	// Extend the Repeater Field model.
	var Field = acf.models.RepeaterField.extend({
		type: 'ceo_civicrm_price_set_quick',
	});

	// Register it.
	acf.registerFieldType( Field );

})(jQuery);

/**
 * Perform actions when dom_ready fires.
 *
 * @since 0.8.2
 */
jQuery(document).ready(function($) {

	/**
	 * Set up click handler for the "Default Fee Level" checkboxes.
	 *
	 * @since 0.8.2
	 *
	 * @param {Object} event The click event object.
	 */
	function ceo_acf_default_selector() {

		// Declare vars.
		var scope = $('.acf-field.ceo_civicrm_price_set_quick'),
			checkboxes = '.acf-input ul.acf-checkbox-list li label input';

		// Unbind first to allow repeated calls to this function.
		scope.off( 'click', checkboxes );

		/**
		 * Callback for clicks on the "Default Fee Level" checkboxes.
		 *
		 * @since 0.8.2
		 */
		scope.on( 'click', checkboxes, function( event ) {

			// Prevent bubbling.
			event.stopPropagation();

			// Declare vars.
			var container, buttons, checked;

			// Get container element.
			container = $(this).parents( 'table.acf-table tbody' );

			// Get checkbox elements.
			buttons = $( 'ul.acf-checkbox-list li label input', container );

			// Get existing checked value.
			checked = $(this).prop( 'checked' );

			// Set all checkboxes to unchecked.
			buttons.prop( 'checked', false );
			buttons.parent().removeClass( 'selected' );

			// Keep this checkbox checked unless disabling it.
			if ( checked ) {
				$(this).prop( 'checked', true );
				$(this).parent().addClass( 'selected' );
			}

		});

	}

	// Set up click handler immediately.
	ceo_acf_default_selector();

	/**
	 * Callback for clicks on the "Add Fee Level" button.
	 *
	 * @since 0.8.2
	 *
	 * @param {Object} event The click event object.
	 */
	$('.acf-field.ceo_civicrm_price_set_quick .acf-actions .acf-button.button-primary').click( function( event ) {

		// Reset click handler because the DOM has been added to.
		ceo_acf_default_selector();

	});

});
