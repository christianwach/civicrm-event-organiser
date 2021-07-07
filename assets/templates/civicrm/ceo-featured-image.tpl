{* template block that contains the new fields *}
<table>
  <tr class="ceo_attachment_id">
    <td class="label"></td>
    <td class="ceo_attachment_td">
      <div class="ceo_attachment" style="position: relative;">
        {$ceo_attachment_markup}
      </div>
      {$form.ceo_attachment_id}
      <button id="{$ceo_attachment_id_button_id}" class="button ceo_attachment_id">{$ceo_attachment_id_button}</button><br>
      <span class="description">{$ceo_attachment_id_help}</span>
    </td>
  </tr>
</table>

{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
  {literal}

  // jQuery will not move an item unless it is wrapped.
  cj('tr.ceo_attachment_id').insertAfter('.crm-event-manage-eventinfo-form-block-title');

  /**
   * Trigger dom_ready methods where necessary.
   *
   * @since 0.6.3
   */
  jQuery(document).ready( function($) {

    // The DOM is loaded now.
    if ( 'undefined' !== typeof CEO_Feature_Image ) {
      CEO_Feature_Image.settings.dom_ready();
      CEO_Feature_Image.switcher.dom_ready();
    }

  }); // end document.ready()

  {/literal}
</script>
