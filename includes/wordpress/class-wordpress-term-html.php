<?php
/**
 * Term Description Class.
 *
 * Replicates the functionality of WooDojo HTML Term Description plugin.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Term Description Class.
 *
 * This class replicates the functionality of WooDojo HTML Term Description
 * plugin since that plugin has now been withdrawn. It was described thus:
 *
 * "The WooDojo HTML Term Description feature adds the ability to use html in
 * Term Descriptions, as well as a visual editor to make input easier."
 *
 * The difference here is that only the Event Organiser custom Taxonomy for
 * Event Categories is affected.
 *
 * @since 0.2.1
 */
class CEO_WordPress_Term_Description {

	/**
	 * Plugin object.
	 *
	 * @since 0.2.1
	 * @access public
	 * @var CiviCRM_Event_Organiser
	 */
	public $plugin;

	/**
	 * WordPress object.
	 *
	 * @since 0.8.0
	 * @access public
	 * @var CEO_WordPress
	 */
	public $wordpress;

	/**
	 * Constructor.
	 *
	 * @since 0.2.1
	 *
	 * @param CEO_WordPress $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store references.
		$this->plugin    = $parent->plugin;
		$this->wordpress = $parent;

		// Register hooks on admin init.
		add_action( 'admin_init', [ $this, 'register_hooks' ] );

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

		// Allow HTML in Term Descriptions.
		remove_filter( 'pre_term_description', 'wp_filter_kses' );
		remove_filter( 'term_description', 'wp_kses_data' );

		// Add TinyMCE to the Event Organiser Taxonomy.
		add_action( 'event-category_edit_form_fields', [ $this, 'render_field_edit' ], 1, 2 );
		add_action( 'event-category_add_form_fields', [ $this, 'render_field_add' ], 1, 1 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Add the WYSIWYG editor to the "edit" field.
	 *
	 * @since 0.2.1
	 *
	 * @param WP_Term $tag The WordPress tag object.
	 * @param string  $taxonomy The WordPress Taxonomy.
	 */
	public function render_field_edit( $tag, $taxonomy ) {

		$settings = [
			'textarea_name' => 'description',
			'quicktags'     => true,
			'tinymce'       => true,
			'editor_css'    => '<style>#wp-html-description-editor-container .wp-editor-area { height: 250px; }</style>',
		];

		?>
		<tr>
			<th scope="row" valign="top"><label for="description"><?php echo esc_html_x( 'Description', 'Taxonomy Description', 'civicrm-event-organiser' ); ?></label></th>
			<td><?php wp_editor( htmlspecialchars_decode( $tag->description ), 'html-description', $settings ); ?>
			<span class="description"><?php esc_html_e( 'The description is not prominent by default, however some themes may show it.', 'civicrm-event-organiser' ); ?></span></td>
			<script type="text/javascript">
				// Remove the non-HTML field.
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
	 * @param string $taxonomy The WordPress Taxonomy.
	 */
	public function render_field_add( $taxonomy ) {

		$settings = [
			'textarea_name' => 'description',
			'quicktags'     => true,
			'tinymce'       => true,
			'editor_css'    => '<style>#wp-html-tag-description-editor-container .wp-editor-area { height: 150px; }</style>',
		];

		?>
		<div class="form-field term-description-wrap">
			<label for="tag-description"><?php echo esc_html_x( 'Description', 'Taxonomy Description', 'civicrm-event-organiser' ); ?></label>
			<?php wp_editor( '', 'html-tag-description', $settings ); ?>
			<p class="description"><?php esc_html_e( 'The description is not prominent by default, however some themes may show it.', 'civicrm-event-organiser' ); ?></p>
			<script type="text/javascript">
				// Remove the non-HTML field.
				jQuery( 'textarea#tag-description' ).closest( '.form-field' ).remove();
				// Trigger save.
				jQuery( function() {
					// This fires when submitted via keyboard.
					jQuery( '#addtag' ).on( 'keydown', '#submit', function() {
						tinyMCE.triggerSave();
					});
					// This does not fire when submitted via keyboard.
					jQuery( '#addtag' ).on( 'mousedown', '#submit', function() {
						tinyMCE.triggerSave();
					});
				});
			</script>
		</div>
		<?php

	}

}
