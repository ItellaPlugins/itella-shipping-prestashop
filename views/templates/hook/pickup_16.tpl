<input type="hidden" name="itella_pickup_point_id" id="itella_pickup_point_id" value="{$selected}" />

<div id="itella-extra" class="presta16_extra_content">
</div>
<script>
    var itella_locations = JSON.parse({$pickup_points|@json_encode nofilter});
</script>
<script>
    var itella_country = JSON.parse('{$itella_send_to nofilter}');
    {literal}
    var itella_locations_filters = {
        {/literal}
        exclude_outdoors: {$filter_outdoors}
        {literal}
    };
    // initialize here as front.js is registered during checkout page load
    ItellaModule.init();
    {/literal}
</script>