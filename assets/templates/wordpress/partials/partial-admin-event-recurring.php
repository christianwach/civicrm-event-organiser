<?php
/**
 * Event "Delete unused CiviCRM Events" template.
 *
 * Handles markup for the Event "Delete unused CiviCRM Events" checkbox.
 *
 * @package CiviCRM_Event_Organiser
 * @since 0.8.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- assets/templates/wordpress/partials/partial-admin-event-recurring.php -->
<style>
	body.js .ceo_multiple_delete_unused {
		display: none;
	}
</style>
<div class="ceo_multiple_delete_unused">
	<p class="description"><?php esc_html_e( 'If you change the sequence, make sure that you choose whether or not to delete the unused corresponding CiviCRM Events when you save this Event. If you choose not to delete them (for example because they have additional data associated with them) then they will be set to "disabled" instead.', 'civicrm-event-organiser' ); ?></p>
</div>
