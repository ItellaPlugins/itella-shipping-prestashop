<input type="hidden" name="itella_pickup_point_id" id="itella_pickup_point_id" value="{$selected}" />

<div class="container" id="itella-ps17-extra">
  <div class="col-xs-12">
    <div class="presta_extra_content">
        <div id="itella-extra"></div>
    </div>
  </div>
</div>
<script>
  var itella_locations = JSON.parse({$pickup_points|@json_encode nofilter});
</script>
<script>
  var itella_country = JSON.parse('{$itella_send_to nofilter}');
  {literal}
    ItellaModule.init();
  {/literal}
</script>