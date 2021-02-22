<?php
/**
 * Term Description Class.
 *
 * Replicates the functionality of WooDojo HTML Term Description plugin.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.2.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Event Organiser Term Description Class.
 *
 * This class replicates the functionality of WooDojo HTML Term Description
 * plugin since that plugin has now been withdrawn. It was described thus:
 *
 * "The WooDojo HTML term description feature adds the ability to use html in
 * term descriptions, as well as a visual editor to make input easier."
 *
 * The difference here is that only the Event Organiser custom taxonomy for
 * Event Categories is affected.
 *
 * @since 0.2.1
 */
class CiviCRM_WP_Event_Organiser_Term_Description {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;



	/**
	 * Initialises this object.
	 *
	 * @since 0.2.1
	 */
	public function __construct() {

		// Register hooks on admin init.
		add_action( 'admin_init', [ $this, 'register_hooks' ] );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.2.1
	 *
	 * @param object $parent The parent object.
	 */
	public function set_references( $parent ) {

		// Store reference.
		$this->plugin = $parent;

	}



	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.2.1
	 */
	public function register_hooks() {

		// Look for an existing WooDojo HTML Term Description install.
		if ( class_exists( 'WooDojo_HTML_Term_Description' ) ) {
			return;
		}

		// Bail if user doesn't have the "unfiltered_html" capability.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return;
		}

		// Allow HTML in term descriptions.
		remove_filter( 'pre_term_description', 'wp_filter_kses' );
		remove_filter( 'term_description', 'wp_kses_data' );

		// Add TinyMCE to the Event Organiser taxonomy.
		add_action( 'event-category_edit_form_fields', [ $this, 'render_field_edit' ], 1, 2 );
		add_action( 'event-category_add_form_fields', [ $this, 'render_field_add' ], 1, 1 );

	}



	//##########################################################################



	/**
	 * Add the WYSIWYG editor to the "edit" field.
	 *
	 * @since 0.2.1
	 *
	 * @param $tag The WordPress tag.
	 * @param $taxonomy The WordPress taxonomy.
	 */
	public function render_field_edit( $tag, $taxonomy ) {

		$settings = [
			'quicktags' 	=> [ 'buttons' => 'em,strong,link' ],
			'textarea_name'	=> 'description',
			'quicktags' 	=> true,
			'tinymce' 		=> true,
			'editor_css'	=> '<style>#wp-html-description-editor-container .wp-editor-area { height: 250px; }</style>'
		];

		?>
		<tr>
			<th scope="row" valign="top"><label for="description"><?php _ex( 'Description', 'Taxonomy Description', 'civicrm-event-organiser' ); ?></label></th>
			<td><?php wp_editor( htmlspecialchars_decode( $tag->description ), 'html-description', $settings ); ?>
			<span class="description"><?php _e( 'The description is not prominent by default, however some themes may show it.', 'civicrm-event-organiser' ); ?></span></td>
			<script type="text/javascript">
				// Remove the non-html field
				jQuery( 'textarea#description' ).closest( '.form-field' ).remove();
			</script>
		</tr>
		<?php

	}



	/**
	 * Add the WYSIWYG editor to the "add" field.
	 *
	 * @since 0.2.1
	 *
	 * @param $taxonomy The WordPress taxonomy.
	 */
	public function render_field_add( $taxonomy ) {

		$settings = [
			'quicktags' 	=> [ 'buttons' => 'em,strong,link' ],
			'textarea_name'	=> 'description',
			'quicktags' 	=> true,
			'tinymce' 		=> true,
			'editor_css'	=> '<style>#wp-html-tag-description-editor-container .wp-editor-area { height: 150px; }</style>'
		];

		?>
		<div>
			<label for="tag-description"><?php _ex( 'Description', 'Taxonomy Description', 'civicrm-event-organiser' ); ?></label>
			<?php wp_editor( '', 'html-tag-description', $settings ); ?>
			<p class="description"><?php _e( 'The description is not prominent by default, however some themes may show it.', 'civicrm-event-organiser' ); ?></p>
			<script type="text/javascript">
				// Remove the non-html field
				jQuery( 'textarea#tag-description' ).closest( '.form-field' ).remove();

				jQuery(function() {
					// Trigger save
					jQuery( '#addtag' ).on( 'mousedown', '#submit', function() {
				   		tinyMCE.triggerSave();
				    });
			    });

			</script>
		</div>
		<?php

	}



} // Class ends.
