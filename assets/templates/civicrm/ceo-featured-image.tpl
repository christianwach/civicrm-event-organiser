{* template block that contains the new fields *}
<table>
  <tr class="ceo_attachment_id">
    <td class="label"></td>
    <td class="ceo_attachment_td">
      <div class="ceo_attachment" style="position: relative;">
        {$ceo_attachment_markup}
      </div>
      <button id="ceo-feature-image-switcher" class="button ceo_attachment_id">{$ceo_attachment_id_button}</button><br>
      <span class="description">{$ceo_attachment_id_help}</span>
    </td>
  </tr>
</table>

{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
  {literal}

  // If there's a sync checkbox,
  if ( cj('#ceo_event_sync_checkbox').length ) {

    // Insert after checkbox.
    cj('tr.ceo_attachment_id').insertAfter('tr.ceo_event_sync');

    // Hide until it is checked.
    var sync_on = cj('#ceo_event_sync_checkbox').prop('checked');
    if ( ! sync_on ) {
      cj('tr.ceo_attachment_id').hide();
    }

    /**
     * Toggle visibility of Feature Image markup.
     *
     * @since 0.8.2
     *
     * @param {Object} e The click event object.
     */
    cj('#ceo_event_sync_checkbox').on( 'click', function(e) {
      var sync_on = cj(this).prop('checked');
      if ( ! sync_on ) {
        jQuery('tr.ceo_attachment_id').hide();
      } else {
        jQuery('tr.ceo_attachment_id').show();
      }
    });

  } else {

    // Insert after title.
    cj('tr.ceo_attachment_id').insertAfter('.crm-event-manage-eventinfo-form-block-title');

  }

  /**
   * Trigger dom_ready methods where necessary.
   *
   * @since 0.6.3
   */
  jQuery(document).ready( function($) {
    if ( 'undefined' !== typeof CEO_Feature_Image ) {
      CEO_Feature_Image.settings.dom_ready();
      CEO_Feature_Image.switcher.dom_ready();
    }
  });

  {/literal}
</script>
