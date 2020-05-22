<input type="hidden" name="itella_pickup_point_id" id="itella_pickup_point_id" value="{$selected}" />

<div class="row" id="itella-ps17-extra">
  <div class="col-sm-9 col-xs-12">
    <div class="row">
      <div class="col-xs-2"></div>
      <div class="col-xs-10 presta_extra_content">
        <div id="itella-extra"></div>
      </div>
    </div>
  </div>
</div>
<script>
  var itella_locations = JSON.parse({$pickup_points|@json_encode nofilter});
</script>
<script>
  var itella_country = JSON.parse('{$itella_send_to nofilter}');
  {literal}
  // prestashop 1.7 registers JS at the end of file
  document.addEventListener('DOMContentLoaded', init);

  function init() {
    ItellaModule.init();
  }
  {/literal}
</script>