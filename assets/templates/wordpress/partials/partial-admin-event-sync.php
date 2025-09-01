<?php
/**
 * Event "Sync to CiviCRM" template.
 *
 * Handles markup for the Event "Sync to CiviCRM" checkbox.
 *
 * @package CiviCRM_Event_Organiser
 * @since 0.8.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- assets/templates/wordpress/partials/partial-admin-event-sync.php -->
<style>
	.ceo-sync-to-civicrm .ceo-civicrm-logo {
		float: left;
		width: 20px;
		height: 20px;
		margin-right: 6px;
		background-repeat: no-repeat;
		background-position: center;
		background-size: 20px auto;
	}

	.ceo-sync-to-civicrm label {
		font-weight: 600;
		margin-right: 3px;
	}

	.ceo-sync-to-civicrm label.ceo-delete-unused {
		line-height: 2;
	}

	body.js .ceo-delete-unused-toggle {
		display: none;
	}
</style>
<div class="misc-pub-section ceo-sync-to-civicrm">
	<div class="ceo-civicrm-logo" style="background-image:url('<?php echo esc_attr( $civicrm_logo ); ?>');"></div>
	<label for="civi_eo_event_sync"><?php esc_html_e( 'Sync Event to CiviCRM', 'civicrm-event-organiser' ); ?></label>
	<input type="checkbox" id="civi_eo_event_sync" name="civi_eo_event_sync" value="1" />
	<?php if ( ! empty( $multiple_linked ) ) : ?>
		<br>
		<span class="ceo-delete-unused-toggle">
			<label for="civi_eo_event_delete_unused" class="ceo-delete-unused"><?php echo esc_html_e( 'Delete unused CiviCRM Events', 'civicrm-event-organiser' ); ?></label>
			<input type="checkbox" id="civi_eo_event_delete_unused" name="civi_eo_event_delete_unused" value="1" />
		</span>
	<?php endif; ?>
</div>
