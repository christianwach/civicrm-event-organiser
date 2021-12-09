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

?><!-- assets/templates/event-links-metabox.php -->
<style>
.civi_eo_event_list li {
	margin: 0.5em 0;
	padding: 0.5em 0 0.5em 0;
	border-top: 1px solid #eee;
	border-bottom: 1px solid #eee;
}
</style>

<ul class="civi_eo_event_list">
	<?php foreach ( $links as $link ) : ?>
		<li><?php echo $link; ?></li>
	<?php endforeach; ?>
</ul>

<?php

/**
 * Broadcast end of metabox.
 *
 * @since 0.3.6
 *
 * @param object $event The EO Event object.
 */
do_action( 'civicrm_event_organiser_event_links_meta_box_after', $event );
