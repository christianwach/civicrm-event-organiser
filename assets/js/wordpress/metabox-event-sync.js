/**
 * "Event Metabox" Javascript.
 *
 * Implements sync functionality on the plugin's "Event Metabox".
 *
 * @package CiviCRM_Event_Organiser
 */

/**
 * Create "Event Metabox" object.
 *
 * This works as a "namespace" of sorts, allowing us to hang properties, methods
 * and "sub-namespaces" from it.
 *
 * @since 0.5.3
 */
var CEO_Event_Metabox = CEO_Event_Metabox || {};

/**
 * Pass the jQuery shortcut in.
 *
 * @since 0.5.3
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Create Settings Singleton.
	 *
	 * @since 0.5.3
	 */
	CEO_Event_Metabox.settings = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Settings.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.5.3
		 */
		this.init = function() {

			// Init localisation.
			me.init_localisation();

			// Init settings.
			me.init_settings();

		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.5.3
		 */
		this.dom_ready = function() {

		};

		// Init localisation array.
		me.localisation = [];

		/**
		 * Init localisation from settings object.
		 *
		 * @since 0.5.3
		 */
		this.init_localisation = function() {
			if ( 'undefined' !== typeof CEO_Metabox_Settings ) {
				me.localisation = CEO_Metabox_Settings.localisation;
			}
		};

		/**
		 * Getter for localisation.
		 *
		 * @since 0.5.3
		 *
		 * @param {String} The identifier for the desired localisation string.
		 * @return {String} The localised string.
		 */
		this.get_localisation = function( key, identifier ) {
			return me.localisation[key][identifier];
		};

		// Init settings array.
		me.settings = [];

		/**
		 * Init settings from settings object.
		 *
		 * @since 0.5.3
		 */
		this.init_settings = function() {
			if ( 'undefined' !== typeof CEO_Metabox_Settings ) {
				me.settings = CEO_Metabox_Settings.settings;
			}
		};

		/**
		 * Getter for retrieving a setting.
		 *
		 * @since 0.5.3
		 *
		 * @param {String} The identifier for the desired setting.
		 * @return The value of the setting.
		 */
		this.get_setting = function( identifier ) {
			return me.settings[identifier];
		};

	};

	/**
	 * Create Accordion Singleton.
	 *
	 * @since 0.5.3
	 */
	CEO_Event_Metabox.accordion = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Settings.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.5.3
		 */
		this.init = function() {
		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.5.3
		 */
		this.dom_ready = function() {

			/**
			 * Toggle accordion depending on state of "Enable Online Registration" checkbox.
			 *
			 * @since 0.5.3
			 *
			 * @param {Object} e The click event object.
			 */
			$('#civi_eo_event_reg').click( function(e) {

				var current_on;

				// Get checked state.
				current_on = $(this).prop( 'checked' );

				// Toggle visibility of registration section.
				if ( current_on ) {
					$('.civi_eo_event_reg_toggle').slideDown( 'slow' );
				} else {
					$('.civi_eo_event_reg_toggle').slideUp( 'slow' );
				}

			});

			/**
			 * Toggle accordion depending on state of "Confirmation Email" checkbox.
			 *
			 * @since 0.5.3
			 *
			 * @param {Object} e The click event object.
			 */
			$('#civi_eo_event_send_email').click( function(e) {

				var current_on;

				// Get checked state.
				current_on = $(this).prop( 'checked' );

				// Toggle visibility of "Confirmation Email" section.
				if ( current_on ) {
					$('.civi_eo_event_send_email_toggle').show();
				} else {
					$('.civi_eo_event_send_email_toggle').hide();
				}

			});

			/**
			 * Toggle "Delete unused CiviCRM Events" information.
			 *
			 * Depends on the state of the Event Organiser "Check to edit this event and
			 * its recurrences" checkbox.
			 *
			 * @since 0.8.2
			 *
			 * @param {Object} e The click event object.
			 */
			$('#eo-event-recurrring-notice').click( function(e) {

				var current_on;

				// Get checked state.
				current_on = $(this).prop( 'checked' );

				// Toggle visibility of "Delete unused CiviCRM Events" information.
				if ( current_on ) {
					$('.ceo_multiple_delete_unused').show();
				} else {
					$('.ceo_multiple_delete_unused').hide();
				}

			});

			/**
			 * Toggle "Delete unused CiviCRM Events" checkbox.
			 *
			 * Depends on the state of the "Sync Event to CiviCRM" checkbox.
			 *
			 * @since 0.8.2
			 *
			 * @param {Object} e The click event object.
			 */
			$('#civi_eo_event_sync').click( function(e) {

				var current_on;

				console.log( 'there', $('.ceo-delete-unused-toggle').length );

				// Bail if there is no checkbox.
				if ( ! $('.ceo-delete-unused-toggle').length ) {
					return;
				}

				// Get checked state.
				current_on = $(this).prop( 'checked' );

				// Toggle visibility of "Delete unused CiviCRM Events" checkbo.
				if ( current_on ) {
					$('.ceo-delete-unused-toggle').show();
				} else {
					$('.ceo-delete-unused-toggle').hide();
				}

			});

		};

	};

	// Call init methods.
	CEO_Event_Metabox.settings.init();
	CEO_Event_Metabox.accordion.init();

} )( jQuery );

/**
 * Trigger dom_ready methods where necessary.
 *
 * @since 0.5.3
 */
jQuery(document).ready(function($) {

	// The DOM is loaded now.
	CEO_Event_Metabox.settings.dom_ready();
	CEO_Event_Metabox.accordion.dom_ready();

});
