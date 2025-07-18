<?php
/**
 * Manual Sync template.
 *
 * Handles markup for the Manual Sync admin page.
 *
 * @package CiviCRM_WP_Profile_Sync
 */

?><!-- assets/templates/wordpress/pages/page-admin-manual-sync.php -->
<div class="wrap">

	<h1><?php esc_html_e( 'CiviCRM Event Organiser', 'civicrm-event-organiser' ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
		<a href="<?php echo $urls['settings']; ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'civicrm-event-organiser' ); ?></a>
		<?php

		/**
		 * Allow others to add tabs.
		 *
		 * @since 0.7
		 *
		 * @param array $urls The array of subpage URLs.
		 * @param string The key of the active tab in the subpage URLs array.
		 */
		do_action( 'ceo/admin/settings/nav_tabs', $urls, 'manual-sync' );

		?>
	</h2>

	<?php if ( ! empty( $messages ) ) : ?>
		<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
		<?php echo $messages; ?>
	<?php endif; ?>

	<p><?php esc_html_e( 'Things can be a little complicated on initial setup because there can be data in WordPress or CiviCRM or both. The recommended procedure is to sync in the following order:', 'civicrm-event-organiser' ); ?></p>

	<ol>
		<li><?php esc_html_e( 'Sync Event Organiser Categories with CiviCRM Event Types', 'civicrm-event-organiser' ); ?></li>
		<li><?php esc_html_e( 'Sync Event Organiser Venues with CiviCRM Locations', 'civicrm-event-organiser' ); ?></li>
		<li><?php esc_html_e( 'Finally, sync Event Organiser Events with CiviCRM Events.', 'civicrm-event-organiser' ); ?></li>
	</ol>

	<p class="description"><?php esc_html_e( 'Please note: Manual Sync is intended for use on initial setup and may produce inconsistent results once you have linked Events. Always back up before using this feature.', 'civicrm-event-organiser' ); ?></p>

	<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
	<form method="post" id="ceo_acf_sync_form" action="<?php echo $this->settings->page_settings_submit_url_get(); ?>">

		<?php wp_nonce_field( $this->form_nonce_action, $this->form_nonce_field ); ?>
		<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>

		<div id="welcome-panel" class="welcome-panel hidden">

			<div class="welcome-panel-column-content">

				<p><?php esc_html_e( 'Things can be a little complicated on initial setup because there can be data in WordPress or CiviCRM or both. The recommended procedure is to sync in the following order:', 'civicrm-event-organiser' ); ?></p>

				<ol>
					<li><?php esc_html_e( 'Sync Event Organiser Categories with CiviCRM Event Types', 'civicrm-event-organiser' ); ?></li>
					<li><?php esc_html_e( 'Sync Event Organiser Venues with CiviCRM Locations', 'civicrm-event-organiser' ); ?></li>
					<li><?php esc_html_e( 'Finally, sync Event Organiser Events with CiviCRM Events.', 'civicrm-event-organiser' ); ?></li>
				</ol>

				<p class="description"><?php esc_html_e( 'Please note: Manual Sync is intended for use on initial setup and may produce inconsistent results once you have linked Events. Always back up before using this feature.', 'civicrm-event-organiser' ); ?></p>

			</div>

		</div>

		<div id="dashboard-widgets-wrap">

			<div id="dashboard-widgets" class="metabox-holder<?php echo esc_attr( $columns_css ); ?>">

				<div id="postbox-container-1" class="postbox-container">
					<?php do_meta_boxes( $screen->id, 'normal', '' ); ?>
				</div>

				<div id="postbox-container-2" class="postbox-container">
					<?php do_meta_boxes( $screen->id, 'side', '' ); ?>
				</div>

			</div><!-- #post-body -->
			<br class="clear">

		</div><!-- #poststuff -->

</div><!-- /.wrap -->
