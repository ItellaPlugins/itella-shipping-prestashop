<div class="row itella">
  <div class="col-lg-6 col-md-6 col-xs-12 panel">

    <div class="panel-heading">
      <img src="{$module_dir}logo.png" class="itella-logo" alt="Itella Logo">
        {l s='Itella Shipping' mod='itellashipping'}
    </div>

    {if $itella_error}
    {$itella_error}
    {/if}

    <form action="{$itella_module_url}" method="post" id="itella_order_form">

      <div class="row">
        <div class="col-md-6 col-xs-12">
          <div class="field-row">
            <span>{l s="Packets (total)" mod='itellashipping'}:</span>
            <span>
              <select name="packs" id="itella-packs" class="">
                {for $amount=1 to 10}
                  <option value="{$amount}" {if isset($orderItellaCartInfo.packs) && $orderItellaCartInfo.packs==$amount} selected="selected" {/if}>{$amount}</option>
                {/for}
              </select>
            </span>
          </div>
        </div>
        <div class="col-md-6 col-xs-12">
          <div class="field-row">
            <span>{l s="Weight (kg)" mod='itellashipping'}:</span>
            <span>
              <input type="text"
                     name="weight" {if isset($orderItellaCartInfo.weight)} value="{$orderItellaCartInfo.weight}" {else} value="1" {/if}/>
            </span>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 col-xs-12">
          <div class="field-row">
            <span>{l s="C.O.D" mod='itellashipping'}:</span>
            <span>
              <select name="is_cod" id="itella-cod">
                <option value="0">{l s='No' mod='itellashipping'}</option>
                <option value="1" {if $orderItellaCartInfo.is_cod} selected {/if}>{l s='Yes' mod='itellashipping' }</option>
              </select>
            </span>
          </div>
        </div>
        <div class="col-md-6 col-xs-12">
          <div class="field-row">
            <span>{l s="C.O.D. amount" mod='itellashipping'}:</span>
            <span>
              <input type="text" name="cod_amount" id="itella-cod-amount"
                     value="{if $cod_amount}{$cod_amount}{/if}" disabled="disabled"/>
            </span>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-xs-12">
          <div class="field-row">
            <span>{l s="Carrier" mod='itellashipping'}:</span>
            <span>
              <select name="is_pickup" id="itella-carrier" class="chosen">
                <option value="0">{l s='Courier' mod='itellashipping'}</option>
                <option value="1" {if $orderItellaCartInfo.is_pickup} selected {/if}>{l s='Pickup Point' mod='itellashipping'}</option>
              </select>
            </span>
          </div>
        </div>
      </div>

      <div class="row pickup-point-container">
        <div class="col-xs-12">
          <div class="field-row">
            <span>{l s="Pickup point" mod='itellashipping'}:</span>
            <span>
              <select name="id_pickup_point" class="chosen">
                <option value="0">{l s='Select pickup point' mod='itellashipping'}</option>
                {foreach from=$itella_pickup_points item=pickup}
                  {if $pickup.address.postalCodeName != null && $pickup.address.postalCodeName != ''}
                    {assign var=address value=$pickup.address.postalCodeName}
                  {else}
                    {assign var=address value=$pickup.address.municipality}
                  {/if}
                  {if $pickup.address.address != null && $pickup.address.address != ''}
                    {assign var=address value="{$address} - {$pickup.address.address}, {$pickup.address.postalCode}"}
                  {else}
                    {assign var=address value="{$address} - {$pickup.address.streetName} {$pickup.address.streetNumber}, {$pickup.address.postalCode}"}
                  {/if}
                  <option value="{$pickup.pupCode}" {if isset($orderItellaCartInfo.id_pickup_point) && $orderItellaCartInfo.id_pickup_point==$pickup.pupCode} selected="selected" {/if}>{$address} ({$pickup.publicName})</option>
                {/foreach}
              </select>
            </span>
          </div>
        </div>
      </div>

      <div class="row extra-services-container">
        <div class="col-xs-12">

          <div class="itella-extra-header">
            <div class="row">
              <div class="col-xs-12">
                <span>{l s="Extra services" mod='itellashipping'}:</span>
              </div>
            </div>
          </div>
          

          <div class="row">
            <div class="col-xs-12">
              <label class="checkbox-inline">
                <input type="checkbox" value="is_oversized" name="itella_extra[]" {if $orderItellaCartInfo.is_oversized} checked="checked" {/if}>
                {l s="Oversized" mod='itellashipping'}
              </label>
              <label class="checkbox-inline">
                <input type="checkbox" value="is_call_before_delivery" name="itella_extra[]" {if $orderItellaCartInfo.is_call_before_delivery} checked="checked" {/if}>
                {l s="Call before delivery" mod='itellashipping'}
              </label>
              <label class="checkbox-inline">
                <input type="checkbox" value="is_fragile" name="itella_extra[]" {if $orderItellaCartInfo.is_fragile} checked="checked" {/if}>
                {l s="Fragile" mod='itellashipping'}
              </label>
              <label class="checkbox-inline" id="multi_parcel_chb">
              <input type="hidden" id="itella-multi" name="itella_extra[]" value="is_multi" disabled="disabled">
                <input disabled="disabled" type="checkbox" checked>
                {l s="Multi parcel" mod='itellashipping'}
              </label>
            </div>
          </div>
        </div>
      </div>

    </form>
    <div class="response alert">
    </div>
    <div class="panel-footer itella-footer">
      <span>
        <a href="{$itella_print_label_url}" data-disabled="{if $orderItellaCartInfo.label_number != ''}false{else}true{/if}" target="_blank" name="itella_print_label" id="itella_print_label_btn" class="btn btn-success"><i class="icon-file-pdf-o"></i> {l s="Print" mod='itellashipping'}</a>
      </span>
      <span>
        <button type="submit" name="itella_save_cart_info" id="itella_save_cart_info_btn" class="btn btn-success"><i class="icon-save"></i> {l s="Save" mod='itellashipping'}</button>
        <button type="submit" name="itella_generate_label" id="itella_generate_label_btn" class="btn btn-success"><i class="icon-tag"></i> {l s="Generate label" mod='itellashipping'}</button>
      </span>
    </div>
  </div>
</div>

{literal}
  <style type="text/css">
    .itella {
      margin: 0;
    }

    .itella-logo {
      height: 50%;
      padding-right: 5px;
    }

    .itella .panel-footer {
      height: auto!important;
      display: flex;
      justify-content: space-between;
    }

    .itella .panel-footer .btn {
      text-transform: uppercase;
      font-weight: 900;
    }

    .itella-extra-header {
      padding-top: 1em;
      padding-bottom: 0.5em;
      font-size: 1.1em;
      font-weight: 900;
    }

    #itella_print_label_btn,
    #itella_save_cart_info_btn,
    #itella_generate_label_btn {
      border: none;
      margin-bottom: 1em;
    }

    #itella_print_label_btn {
      background-color: #0b7489;
    }

    #itella_print_label_btn[data-disabled="true"] {
      -webkit-box-shadow: none;
      box-shadow: none;
      cursor: not-allowed;
      filter: alpha(opacity=65);
      opacity: .65;
      pointer-events: none;
    }

    #itella_save_cart_info_btn {
      background-color: #829191;
      margin-right: 1em;
    }

    #itella_generate_label_btn {
      background-color: #6eb257;
    }

    .itella .panel-footer .btn [class^=icon-] {
      padding-right: 5px;
    }

    .itella .field-row {
      margin-bottom: 1em;
    }

    .itella .response {
      margin-top: 1em;
    }
  </style>
{/literal}


<script type="text/javascript">
  $(document).ready(function () {
    var itella_label = '{$orderItellaCartInfo["label_number"]}';
    var itella_form = document.getElementById('itella_order_form');
    var packs = document.getElementById('itella-packs');
    var is_cod = document.getElementById('itella-cod');
    var cod_amount = document.getElementById('itella-cod-amount');
    var is_pickup = document.getElementById('itella-carrier');
    var is_multi = document.getElementById('itella-multi');
    var $pickup_points = $('.itella select[name="id_pickup_point"]');
    var $extra_services = $('.itella .extra-services-container');
    var $response = $('.itella .response');
    var itella_buttons = {
      print: document.getElementById('itella_print_label_btn'),
      save: document.getElementById('itella_save_cart_info_btn'),
      generate: document.getElementById('itella_generate_label_btn')
    }
console.log('label',itella_label);
    toggleCodAmount();
    togglePickupPoints();
    toggleMultiParcel();
    $response.hide();

    $(packs).on('change', function(e){
      toggleMultiParcel();
    });
    $(is_cod).on('change', function(e){
      toggleCodAmount();
    });
    $(is_pickup).on('change', function(e){
      togglePickupPoints();
    });

    $(itella_buttons.print).on('click', function(e) {
      if (typeof itella_buttons.print.dataset.disabled != 'undefined' && itella_buttons.print.dataset.disabled == 'true') {
        e.preventDefault();
      }
    });

    $(itella_buttons.save).on('click', function(e){
      e.preventDefault();
      
      if (is_pickup.value == '1' && $pickup_points.val() == 0) {
        warning('{l s="Pickup point not selected" mod="itellashipping"}');
        return false;
      }
      let form_data = new FormData(itella_form);
      //disableButtons();
      saveItellaCart(form_data);
    });

    $(itella_buttons.generate).on('click', function(e){
      e.preventDefault();

      let form_data = new FormData(itella_form);
      //disableButtons();
      generateItellaLabel(form_data);
    });

    function toggleCodAmount() {
      cod_amount.disabled = is_cod.value == '0';
    }

    function togglePickupPoints() {
      if (is_pickup.value == '1') {
        $('.pickup-point-container').show();
        disableExtraServices();
      } else {
        $('.pickup-point-container').hide();
        enableExtraServices();
      }
    }

    function disableExtraServices() {
      // reset packs to 1
      packs.value = 1;
      $(packs).trigger('change');
      // disable COD
      is_cod.value = 0;
      $(is_cod).trigger('change');

      packs.disabled = true;
      is_cod.disabled = true;
      is_multi.disabled = true;

      $extra_services.hide();
    }

    function enableExtraServices() {
      packs.disabled = false;
      is_cod.disabled = false;
      is_multi.disabled = false;
      toggleMultiParcel();
      $extra_services.show();
    }

    function toggleMultiParcel() {
      var $multi = $('#multi_parcel_chb');
      if (parseInt(packs.value) > 1) {
        $multi.show();
        is_multi.disabled = false;
      } else {
        $multi.hide();
        is_multi.disabled = true;
      }
    }

    function saveItellaCart(form_data) {
      form_data.set('ajax', 1);
      form_data.set('token', '{getAdminToken tab="AdminOrders"}');
      form_data.set('id_order', '{$order_id}');
      form_data.set('id_cart', '{$cart_id}');
      
      disableButtons();
      $.ajax({
        type: "POST",
        url: "{$itella_module_url}",
        async: false,
        processData: false,
        contentType: false,
        cache: false,
        dataType: "json",
        data: form_data,
        success: function (res) {
            console.log(res);
            if (typeof res.errors != 'undefined') {
              showResponse(res.errors, 'danger');
            } else {
              showResponse([res.success], 'success');
              window.location.href = location.href;
            }
        },
        complete: function (jqXHR, status) {
          enableButtons();
        }
      });
    }

    function generateItellaLabel(form_data) {
      form_data.set('ajax', 1);
      form_data.set('token', '{getAdminToken tab="AdminOrders"}');
      form_data.set('id_order', '{$order_id}');
      form_data.set('id_cart', '{$cart_id}');
      
      disableButtons();
      $.ajax({
        type: "POST",
        url: "{$itella_generate_label_url}",
        async: false,
        processData: false,
        contentType: false,
        cache: false,
        //dataType: "json",
        data: form_data,
        success: function (res) {
          console.log(res);
          res = JSON.parse(res);
          if (typeof res.errors != 'undefined') {
            showResponse(res.errors, 'danger');
            console.log(res);
            return false;
          } else {
            console.log(res);
            showResponse(res.success, 'success');
            itella_label = true;
          }
        },
        complete: function(jqXHR, status) {
          enableButtons();
        }
      });
    }

    function disableButtons() {
      itella_buttons.save.disabled = true;
      itella_buttons.generate.disabled = true;
      itella_buttons.print.dataset.disabled = true;
    }

    function enableButtons() {
      itella_buttons.save.disabled = false;
      itella_buttons.generate.disabled = false;
      if (itella_label) {
        itella_buttons.print.dataset.disabled = false;
      }
    }

    window.itella_disable = disableButtons;
    window.itella_enable = enableButtons;

    function warning(text) {
      if (!!$.prototype.fancybox) {
        $.fancybox.open([
          {
            type: 'inline',
            autoScale: true,
            minHeight: 30,
            content: '<p class="fancybox-error">' + text + '</p>'
          }],
          {
            padding: 0
          });
      } else {
        alert(text);
      }
    }

    function showResponse(msg, type) {
      //console.log(msgs, type);
      $response.removeClass('alert-danger alert-success');
      $response.addClass('alert-' + type);
      $response.html('');
      var ol = document.createElement('ol');
      //msgs.forEach(function (txt) {
        var li = document.createElement('li');
        li.innerText = msg;
        ol.appendChild(li);
      //});
      $response.append(ol);
      $response.show();
    }
  });
</script>