<!-- assets/templates/event-links-metabox.php -->
<style>
.civi_eo_event_list li {
	margin: 0.5em 0;
	padding: 0.5em 0 0.5em 0;
	border-top: 1px solid #eee;
	border-bottom: 1px solid #eee;
}
</style>
<ul class="civi_eo_event_list">
	<?php foreach( $links AS $link ) : ?>
		<li><?php echo $link; ?></li>
	<?php endforeach; ?>
</ul>
