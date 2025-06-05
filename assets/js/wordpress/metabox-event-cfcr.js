/**
 * Caldera Forms CiviCRM Redirect "Switcher" Javascript.
 *
 * Implements functionality for the metabox's "Switcher" button.
 *
 * @package CiviCRM_Event_Organiser
 */

/**
 * Create "Caldera Forms CiviCRM Redirect" Switcher object.
 *
 * This works as a "namespace" of sorts, allowing us to hang properties, methods
 * and "sub-namespaces" from it.
 *
 * @since 0.5.3
 */
var CEO_Switcher = CEO_Switcher || {};

/**
 * Pass the jQuery shortcut in.
 *
 * @since 0.5.3
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Create Settings Object.
	 *
	 * @since 0.5.3
	 */
	CEO_Switcher.settings = new function() {

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
			if ( 'undefined' !== typeof CEO_CFCR_Settings ) {
				me.localisation = CEO_CFCR_Settings.localisation;
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
		this.get_localisation = function( identifier ) {
			return me.localisation[identifier];
		};

		// Init settings array.
		me.settings = [];

		/**
		 * Init settings from settings object.
		 *
		 * @since 0.5.3
		 */
		this.init_settings = function() {
			if ( 'undefined' !== typeof CEO_CFCR_Settings ) {
				me.settings = CEO_CFCR_Settings.settings;
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
	 * Create Switcher Object.
	 *
	 * @since 0.5.3
	 */
	CEO_Switcher.switcher = new function() {

		// Prevent reference collisions.
		var me = this;

		// Declare our active editor.
		me.active_editor = 'cfcr-redirect-switcher-field';

		// Store original modal data.
		me.original_button = '';
		me.original_title = '';
		me.original_top = '';

		/**
		 * Initialise Switcher.
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

			// Set up instance.
			me.setup();

			// Enable listeners.
			me.listeners();

		};

		/**
		 * Set up Switcher instance.
		 *
		 * @since 0.5.3
		 */
		this.setup = function() {

			var src, spinner;

			src = CEO_Switcher.settings.get_setting( 'loading' );
			spinner = '<img src="' + src + '" id="cfcr-redirect-loading" style="margin-top: 0.5em;" />';

			// Init AJAX spinner.
			$(spinner).prependTo( $('.civi_eo_event_cfcr_post_link') ).hide();

		};

		/**
		 * Initialise listeners.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.5.3
		 */
		this.listeners = function() {

			// Declare vars.
			var button = $( '.cfcr-redirect-switcher' ),
				deleter = $('.cfcr-delete');

			/**
			 * Add a click event listener to button.
			 *
			 * @param {Object} event The event object.
			 */
			button.on( 'click', function( event ) {

				// Set global AJAX identifer.
				$.ajaxSetup({
					data: { cfcr: 'true' },
				});

				// Hide link elements and set some styles.
				$('#wp-link #link-options, #wp-link p.howto').hide();
				me.original_top = $('#wp-link-wrap #most-recent-results, #wp-link-wrap #search-results').css( 'top' );
				$('#wp-link-wrap #most-recent-results, #wp-link-wrap #search-results').css( 'top', '36px' );

				// Clear recents and reinitialise.
				if ( $( '#most-recent-results > ul > li' ).length ) {
					$( '#most-recent-results > ul > li' ).remove();
					window.wpLink.init();
				}

				// Open the link modal.
				window.wpLink.open( me.active_editor );

				// Override title and button in modal.
				me.original_title = $('#link-modal-title').html();
				$('#link-modal-title').html( CEO_Switcher.settings.get_localisation( 'title' ) );
				me.original_button = $('#wp-link-submit').val();
				$('#wp-link-submit').val( CEO_Switcher.settings.get_localisation( 'button' ) );

			});

			/**
			 * Add a click event listener to deletion "link".
			 *
			 * @param {Object} event The event object.
			 */
			deleter.on( 'click', function( event ) {

				// Replace Post link.
				$('.civi_eo_event_redirect_post_link').html( CEO_Switcher.settings.get_localisation( 'no-selection' ) );

				// Empty the value of the hidden input.
				$('#civi_eo_event_redirect_post_id').val( 0 );

			});

			/**
			 * Add a listener to the wpLink modal close event.
			 *
			 * @param {Object} event The event object.
			 * @param {String} wrap The HTML of the input wrapper.
			 */
			$(document).on( 'wplink-close', function( event, wrap ) {

				// Check the active editor.
				if ( window.wpActiveEditor == me.active_editor ) {

					// Restore link elements and set some styles.
					$('#wp-link #link-options, #wp-link p.howto').show();
					$('#wp-link-wrap #most-recent-results, #wp-link-wrap #search-results').css( 'top', me.original_top );

					// Restore title and button in modal.
					$('#link-modal-title').text( me.original_title );
					$('#wp-link-submit').val( me.original_button );

					// Clear recents and reinitialise.
					if ( $( '#most-recent-results > ul > li' ).length ) {
						window.wpLink.init();
						$( '#most-recent-results > ul > li' ).remove();
					}

					// Reset global AJAX identifer.
					$.ajaxSetup({
						data: { cfcr: 'false' },
					});

				}

			});

			/**
			 * Add a click event listener to dialog submit button.
			 *
			 * @param {Object} event The event object.
			 */
			$('#wp-link-submit').on( 'click', function( event ) {

				var atts;

				// Bail if not our active editor.
				if ( window.wpActiveEditor != me.active_editor ) {
					return;
				}

				// Grab result.
				atts = window.wpLink.getAttrs();

				// Check that we have URL data.
				if ( atts.href ) {

					// Hide current Redirect.
					$('.civi_eo_event_redirect_post_link').hide();

					// Show spinner.
					$('#cfcr-redirect-loading').show();

					// Send the URL to the server.
					me.send( atts.href );

				}

			});

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 0.5.3
		 *
		 * @param {Array} data The data received from the server.
		 */
		this.update = function( data ) {

			var markup, teaser;

			// Bail on failure.
			if ( data.success != 'true' ) {
				return;
			}

			// Parse returned markup.
			if ( $.parseHTML ) {
				markup = $( $.parseHTML( data.markup ) );
			} else {
				markup = $(data.markup);
			}

			// Replace Post link.
			$('.civi_eo_event_redirect_post_link').html( markup );

			// Set the value of the hidden input.
			$('#civi_eo_event_redirect_post_id').val( data.post_id );

			// Hide spinner.
			$('#cfcr-redirect-loading').hide();

			// Show new Redirect.
			$('.civi_eo_event_redirect_post_link').show();

			// Re-run setup.
			//me.setup();

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 0.5.3
		 */
		this.send = function( post_url ) {

			// Use jQuery post.
			$.post(

				// URL to post to.
				CEO_Switcher.settings.get_setting( 'ajax_url' ),

				{

					// Token received by WordPress.
					action: 'url_to_post_id',

					// Send post ID.
					post_url: post_url

				},

				// Callback.
				function( data, textStatus ) {

					// If success.
					if ( textStatus == 'success' ) {

						// Update with our returned data.
						me.update( data );

					} else {

						// Show error.
						if ( console.log ) {
							console.log( textStatus );
						}

					}

				},

				// Expected format.
				'json'

			);

		};

	};

	// Init settings.
	CEO_Switcher.settings.init();

	// Init Switcher.
	CEO_Switcher.switcher.init();

} )( jQuery );

/**
 * Trigger dom_ready methods where necessary.
 *
 * @since 0.5.3
 */
jQuery(document).ready(function($) {

	// The DOM is loaded now.
	CEO_Switcher.settings.dom_ready();

	// The DOM is loaded now.
	CEO_Switcher.switcher.dom_ready();

});
