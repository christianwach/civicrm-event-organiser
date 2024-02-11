<?php
/**
 * Event Links template.
 *
 * Handles markup for the Event Links metabox.
 *
 * @package CiviCRM_WP_Event_Organiser
 * @since 0.3.6
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/wordpress/metaboxes/metabox-event-links.php -->
<style>
.civi_eo_event_list li {
	margin: 0.5em 0;
	padding: 0.5em 0 0.5em 0;
	border-top: 1px solid #eee;
	border-bottom: 1px solid #eee;
}
</style>

<ul class="civi_eo_event_list">
	<?php foreach ( $links as $item ) : ?>
		<li><?php echo $item; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></li>
	<?php endforeach; ?>
</ul>

<?php

/**
 * After Links list.
 *
 * @since 0.3.6
 * @deprecated 0.8.0 Use the {@see 'ceo/event/metabox/event/links/after'} filter instead.
 */
do_action_deprecated( 'civicrm_event_organiser_event_links_meta_box_after', [ $event ], '0.8.0', 'ceo/event/metabox/event/links/after' );

/**
 * After Links list.
 *
 * @since 0.8.0
 *
 * @param object $event The Event Organiser Event object.
 */
do_action( 'ceo/event/metabox/event/links/after', $event );
