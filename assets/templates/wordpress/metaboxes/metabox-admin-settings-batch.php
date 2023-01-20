<?php
/**
 * Settings Page "Batch Processing" template.
 *
 * Handles markup for the Settings Page "Batch Processing" meta box.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.7
 */

?><!-- assets/templates/wordpress/metaboxes/metabox-admin-settings-batch.php -->
<?php if ( $metabox['args']['completed'] === false ) : ?>

	<div id="wpcv_woocivi_products">

		<h4><?php echo $metabox['args']['title']; ?></h4>

		<?php if ( ! empty( $metabox['args']['description'] ) ) : ?>
			<p><?php echo $metabox['args']['description']; ?></p>
		<?php endif; ?>

		<?php if ( $metabox['args']['batch-offset'] !== false ) : ?>
			<?php submit_button( esc_html__( 'Stop', 'civicrm-event-organiser' ), 'secondary', $metabox['args']['stop_button_class'], false ); ?>
		<?php endif; ?>

		<?php
		submit_button( $metabox['args']['button_title'], 'primary', $metabox['args']['button_class'], false,
			[
				'data-security' => esc_attr( wp_create_nonce( $metabox['args']['nonce_name'] ) ),
			]
		);
		?>

		<div id="product-progress-bar"><div class="progress-label"></div></div>

	</div>

<?php endif; ?>
