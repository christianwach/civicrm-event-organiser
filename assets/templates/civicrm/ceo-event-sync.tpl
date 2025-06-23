{* template block that contains the new fields *}
<table>
  <tr class="ceo_event_sync">
    <td>&nbsp;</td>
    <td class="ceo_event_sync">
      {$form.ceo_event_sync_checkbox.html}
      {$form.ceo_event_sync_checkbox.label}
    </td>
  </tr>
</table>

{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
  {literal}

  // jQuery will not move an item unless it is wrapped.
  cj('tr.ceo_event_sync').insertAfter('.crm-event-manage-eventinfo-form-block-title');

  {/literal}
</script>
