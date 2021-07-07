/**
 * CiviCRM Event Organiser "Feature Image" Javascript.
 *
 * Implements "Feature Image" functionality on CiviCRM Event pages.
 *
 * @package CiviCRM_Event_Organiser
 */

/**
 * Create CiviCRM Event Organiser "Feature Image" object.
 *
 * This works as a "namespace" of sorts, allowing us to hang properties, methods
 * and "sub-namespaces" from it.
 *
 * @since 0.6.3
 */
var CEO_Feature_Image = CEO_Feature_Image || {};



/**
 * Pass the jQuery shortcut in.
 *
 * @since 0.6.3
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Create Settings Singleton.
	 *
	 * @since 0.6.3
	 */
	CEO_Feature_Image.settings = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Settings.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.6.3
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
		 * @since 0.6.3
		 */
		this.dom_ready = function() {

		};

		// Init localisation array.
		me.localisation = [];

		/**
		 * Init localisation from settings object.
		 *
		 * @since 0.6.3
		 */
		this.init_localisation = function() {
			if ( 'undefined' !== typeof CEO_Feature_Image_Settings ) {
				me.localisation = CEO_Feature_Image_Settings.localisation;
			}
		};

		/**
		 * Getter for localisation.
		 *
		 * @since 0.6.3
		 *
		 * @param {String} The identifier for the desired localisation string.
		 * @return {String} The localised string.
		 */
		this.get_localisation = function( key ) {
			return me.localisation[key];
		};

		// Init settings array.
		me.settings = [];

		/**
		 * Init settings from settings object.
		 *
		 * @since 0.6.3
		 */
		this.init_settings = function() {
			if ( 'undefined' !== typeof CEO_Feature_Image_Settings ) {
				me.settings = CEO_Feature_Image_Settings.settings;
			}
		};

		/**
		 * Getter for retrieving a setting.
		 *
		 * @since 0.6.3
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
	 * @since 0.6.3
	 */
	CEO_Feature_Image.switcher = new function() {

		// prevent reference collisions
		var me = this;

		/**
		 * Initialise Switcher.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.6.3
		 */
		this.init = function() {

		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.6.3
		 */
		this.dom_ready = function() {

			// set up instance
			me.setup();

			// enable listeners
			me.listeners();

		};

		/**
		 * Set up Switcher instance.
		 *
		 * @since 0.6.3
		 */
		this.setup = function() {

			var src, spinner;

			// Init AJAX spinner.
			src = CEO_Feature_Image.settings.get_setting( 'loading' );
			spinner = '<img src="' + src + '" id="feature-image-loading" style="position: absolute; top: 40%; left: 6%;" />';
			$('.ceo_attachment img.wp-post-image').after( spinner );
			$('#feature-image-loading').hide();

		};

		/**
		 * Initialise listeners.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.6.3
		 */
		this.listeners = function() {

			// Declare vars.
			var button = $('button.ceo_attachment_id');

			/**
			 * Add a click event listener to button.
			 *
			 * @param {Object} event The event object.
			 */
			button.on( 'click', function( event ) {

				// Prevent link action.
				if ( event.preventDefault ) {
					event.preventDefault();
				}

				// Prevent bubbling.
				if ( event.stopPropagation ) {
					event.stopPropagation();
				}

				var file_frame, // wp.media file_frame
					button_id_array, post_id;

				// Determine Post ID from the ID of the button.
				button_id_array = $(this).attr( 'id' ).split( '-' );
				if ( button_id_array.length === 5 ) {
					post_id = button_id_array[4];
				} else {
					post_id = 0;
				}

				console.log( 'post_id', post_id );

				// Sanity check.
				if ( file_frame ) {
					file_frame.open();
					return;
				}

				// Init WP Media.
				file_frame = wp.media.frames.file_frame = wp.media({
					title: CEO_Feature_Image.settings.get_localisation( 'title' ),
					button: {
						text: CEO_Feature_Image.settings.get_localisation( 'button' )
					},
					multiple: false
				});

				// Add callback for image selection.
				file_frame.on( 'select', function() {

					// Grab attachment data.
					var attachment = file_frame.state().get( 'selection' ).first().toJSON();

					// Show spinner.
					$('#feature-image-loading').show();

					// Show placeholder if not shown.
					$('.ceo_attachment img.wp-post-image').show();

					// Set the value of our hidden input.
					$('input[name="ceo_attachment_id"]').val( attachment.id );

					// Send the ID to the server.
					me.send( post_id, attachment.id );

				});

				// Open modal.
				file_frame.open();

				// --<
				return false;

			});

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 0.6.3
		 *
		 * @param {Integer} post_id The numeric ID of the post.
		 * @param {Integer} attachment_id The numeric ID of the attachment.
		 */
		this.send = function( post_id, attachment_id ) {

			// Declare vars.
			var url, data;

			// URL to post to.
			url = CEO_Feature_Image.settings.get_setting( 'ajax_url' );

			// Data to pass.
			data = {
				action: 'ceo_feature_image',
				post_id: post_id,
				attachment_id: attachment_id
			};

			// Use jQuery post.
			$.post( url, data,

				// Callback.
				function( response, textStatus ) {

					// Update on success, otherwise show error.
					if ( textStatus == 'success' ) {
						me.update( response );
					} else {
						if ( console.log ) {
							console.log( textStatus );
						}
					}

				},

				// Expected format.
				'json'

			);

		};

		/**
		 * Receive data from an AJAX request.
		 *
		 * @since 0.6.3
		 *
		 * @param {Array} data The data received from the server.
		 */
		this.update = function( data ) {

			// Bail if not successful.
			if ( data.success === 'false' ) {
				return;
			}

			// Convert to jQuery object.
			if ( $.parseHTML ) {
				markup = $( $.parseHTML( data.markup ) );
			} else {
				markup = $(data.markup);
			}

			// Switch image.
			$('.ceo_attachment img.wp-post-image').replaceWith( markup );

			// Hide spinner.
			$('#feature-image-loading').hide();

		};

	};

	// Init settings.
	CEO_Feature_Image.settings.init();

	// Init Feature Image switcher.
	CEO_Feature_Image.switcher.init();

} )( jQuery );



/**
 * Trigger dom_ready methods where necessary.
 *
 * @since 0.6.3
 */
jQuery(document).ready(function($) {

	// The DOM is loaded now.
	//CEO_Feature_Image.settings.dom_ready();

	// The DOM is loaded now.
	//CEO_Feature_Image.switcher.dom_ready();

}); // End document.ready()



