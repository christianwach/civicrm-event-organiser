<?php
/**
 * Event Links template.
 *
 * Handles markup for the Event Links metabox.
 *
 * @package CiviCRM_Event_Organiser
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/wordpress/metaboxes/metabox-event-links.php -->
<?php if ( ! empty( $links ) ) : ?>
	<style>
	.civi_eo_event_list li {
		margin: 0;
		padding: 0.5em 0 0.5em 0;
		border-bottom: 1px solid #eee;
	}
	.civi_eo_event_list li:first-child {
		border-top: 1px solid #eee;
	}
	</style>

	<ul class="civi_eo_event_list">
		<?php foreach ( $links as $item ) : ?>
			<li><span class="dashicons dashicons-external"></span> <a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
<?php else : ?>
	<p>
		<?php esc_html_e( 'This Event does not have any corresponding Events in CiviCRM.', 'civicrm-event-organiser' ); ?>
	</p>
<?php endif; ?>

<?php

/**
 * After Links list.
 *
 * @since 0.3.6
 * @deprecated 0.8.0 Use the {@see 'ceo/event/metabox/event/links/after'} filter instead.
 *
 * @param WP_Post $event The Event Organiser Event object.
 */
do_action_deprecated( 'civicrm_event_organiser_event_links_meta_box_after', [ $event ], '0.8.0', 'ceo/event/metabox/event/links/after' );

/**
 * After Links list.
 *
 * @since 0.8.0
 *
 * @param WP_Post $event The Event Organiser Event object.
 */
do_action( 'ceo/event/metabox/event/links/after', $event );
