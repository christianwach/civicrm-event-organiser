<?php
/**
 * Settings Page template.
 *
 * Handles markup for the Settings Page.
 *
 * @package CiviCRM_WP_Profile_Sync
 * @since 0.7
 */

?><!-- assets/templates/wordpress/pages/page-admin-settings.php -->
<div class="wrap">

	<h1><?php esc_html_e( 'CiviCRM Event Organiser', 'civicrm-event-organiser' ); ?></h1>

	<?php if ( $show_tabs ) : ?>
		<h2 class="nav-tab-wrapper">
			<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
			<a href="<?php echo $urls['settings']; ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'civicrm-event-organiser' ); ?></a>
			<?php

			/**
			 * Allow others to add tabs.
			 *
			 * @since 0.7
			 *
			 * @param array $urls The array of subpage URLs.
			 * @param string The key of the active tab in the subpage URLs array.
			 */
			do_action( 'ceo/admin/settings/nav_tabs', $urls, 'settings' );

			?>
		</h2>
	<?php else : ?>
		<hr />
	<?php endif; ?>

	<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
	<form method="post" id="ceo_settings_form" action="<?php echo $this->page_settings_submit_url_get(); ?>">

		<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php wp_nonce_field( 'ceo_settings_action', 'ceo_settings_nonce' ); ?>

		<div id="poststuff">

			<div id="post-body" class="metabox-holder columns-<?php echo esc_attr( $columns ); ?>">

				<!--<div id="post-body-content">
				</div>--><!-- #post-body-content -->

				<div id="postbox-container-1" class="postbox-container">
					<?php do_meta_boxes( $screen->id, 'side', null ); ?>
				</div>

				<div id="postbox-container-2" class="postbox-container">
					<?php do_meta_boxes( $screen->id, 'normal', null ); ?>
					<?php do_meta_boxes( $screen->id, 'advanced', null ); ?>
				</div>

			</div><!-- #post-body -->
			<br class="clear">

		</div><!-- #poststuff -->

	</form>

</div><!-- /.wrap -->
