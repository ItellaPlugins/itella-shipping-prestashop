<div class="panel clearfix">
	{foreach from=$moduleMenu item=menuItem}
		<a class="btn btn-{if $menuItem.active}primary{else}default{/if}" href="{$menuItem.url|escape:'htmlall':'UTF-8'}">
			{$menuItem.label|escape:'htmlall':'UTF-8'}
		</a>
	{/foreach}
</div>

{if $call_url}
<script type="text/template" id="modal-template">
  <div class="bootstrap modal fade" id="itella_modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <a class="close" data-dismiss="modal" >&times;</a>
          <h3>%%HEADING%%</h3>
        </div>
        <div class="modal-body">
          <p>%%QUESTION%%</p>
          <p id="store_info"></p>
          <p>%%CALL_NOTE%%</p>
          <p>%%PICK_TIME%%</p>
        </div>
        <div class="modal-footer">
          <a href="#" id="confirm_modal_left_button" class="btn btn-success">%%LEFT_BTN_TXT%%</a>
          <a href="#" id="confirm_modal_right_button" class="btn btn-danger">%%RIGHT_BTN_TXT%%</a>
        </div>
      </div>
    </div>
  </div>
</script>

<script>
var stores = '{$stores}';
var itella_manifest_id = 0;
if(stores) {
  stores = JSON.parse(stores);
} else {
  stores = [];
}

var itella_modal = false;

$(document).ready(function () {
  create_itella_modal();
  itella_modal.modal({ show: false });
  //itella_modal.modal('hide');
  itella_modal.find('#id_itella_store').on('change', function(e){
    store = findStoreInfo(this.value);
    if (!store) {
      return false;
    }
    console.log(store);
    itella_modal.find('#store_info').html(
      '<i><p>'
      + store.address + '<br/>'
      + store.postcode + ' ' + store.city + ', ' + store.country_code + '<br/>'
      //+ store.pick_start + ' - ' + store.pick_finish + '<br/>' //TODO: Istrinti
      + store.phone
      + '</p></i>'
    );
    itella_modal.find('#call_time_from').val(store.pick_start);
    itella_modal.find('#call_time_to').val(store.pick_finish);
  });
  itella_modal.find('#id_itella_store').trigger('change');
  $(document).on('click', 'a[data-manifest]', function(e) {
    e.preventDefault();
    itella_manifest_id = this.dataset.manifest || 0;
    itella_modal.modal('show');
  });
  itella_modal.find('#call_date').datepicker({
    dateFormat: 'yy-mm-dd',
    minDate: 0
  });
  // $('#call_courier_btn').on('click', function(e){
  //   itella_modal.modal('show');
  // });

});

function create_itella_modal() {
  var heading = '{l s="Call Smartposti Courier" mod="itellashipping"}';
  var question = '{l s="Please select store to call courier to:" mod="itellashipping"}' + '<select id="id_itella_store" class="chosen">';
  stores.forEach(function(store) {
    question += '<option value="'+store.id_itella_store+'" '+ (store.is_default == 1 ? 'selected' : '') +'>' + store.title + '</option>';
  });
  question += '</select>';
  var call_note = '{l s="Pickup note" mod="itellashipping"}' + ':<input type="text" id="call_message" value="{$call_message}" />';
  
  var pickup_full_time = '{l s="Pickup time" mod="itellashipping"}' + ':<br/>';
  pickup_full_time += '<input type="text" id="call_date" class="fixed-width-xl" value="' + getFormatedDate(1) + '" style="display:inline-block;"/>';
  pickup_full_time += ' <select id="call_time_from" class="fixed-width-sm" style="display:inline-block;">';
  getAllCallTimes().forEach(function(time) {
    pickup_full_time += '<option value="' + time + '">' + time + '</option>';
  });
  pickup_full_time += '</select>';
  pickup_full_time += ' - <select id="call_time_to" class="fixed-width-sm" style="display:inline-block;">';
  getAllCallTimes().forEach(function(time) {
    pickup_full_time += '<option value="' + time + '">' + time + '</option>';
  });
  pickup_full_time += '</select>';

  var left_button_txt = '{l s="Send request" mod="itellashipping"}';
  var right_button_txt = '{l s="Cancel" mod="itellashipping"}';
  var confirmModal = $('#itella_modal');
  if (confirmModal.length == 0) {
    var templateHtml = $('#modal-template').html();
    templateHtml = templateHtml
      .replace('%%HEADING%%', heading)
      .replace('%%QUESTION%%', question)
      .replace('%%CALL_NOTE%%', call_note)
      .replace('%%PICK_TIME%%', pickup_full_time)
      .replace('%%LEFT_BTN_TXT%%', left_button_txt)
      .replace('%%RIGHT_BTN_TXT%%', right_button_txt);
    confirmModal = $(templateHtml);
  }
  confirmModal.find('#confirm_modal_left_button').click(function () {
    sendCall($('#id_itella_store').val(), itella_manifest_id, {
      call_message: $('#call_message').val(),
      call_date: $('#call_date').val(),
      call_time_from: $('#call_time_from').val(),
      call_time_to: $('#call_time_to').val()
    });
    confirmModal.modal('hide');
  });
  confirmModal.find('#confirm_modal_right_button').click(function () {
    confirmModal.modal('hide');
  });
  
  itella_modal = confirmModal;
}

function getAllCallTimes() {
  var times = [];
  for (var h=0; h<24; h++) {
    for (var m=0; m<60; m+=30) {
      const formattedH = String(h).padStart(2, '0');
      const formattedM = String(m).padStart(2, '0');
      times.push(formattedH + ':' + formattedM);
    }
  }
  return times;
}

function getFormatedDate(add_days = 0) {
  var date = new Date();
  if (add_days != 0) {
    date.setDate(date.getDate() + add_days);
  }
  var yyyy = date.getFullYear();
  var mm = String(date.getMonth() + 1).padStart(2, '0');
  var dd = String(date.getDate()).padStart(2, '0');
  return yyyy + '-' + mm + '-' + dd;;
}

function findStoreInfo(id_store) {
  for(var i=0; i < stores.length; i++) {
    if (stores[i].id_itella_store == id_store) {
      return stores[i];
    }
  }
  return false;
}

function sendCall(address_id, manifest_id, params = {}) {
  if (!address_id) {
    showErrorMessage('{l s="No store selected" mod="itellashipping"}');
  }
  if (!manifest_id) {
    showErrorMessage('{l s="No manifest selected" mod="itellashipping"}');
  }

  params.id_itella_store = address_id;
  params.id_manifest = manifest_id;
  params.ajax = 1;

  $.ajax({
    type: "POST",
    //url: "{$call_url}&ajax=1&id_itella_store=" + address_id + "&id_manifest=" + manifest_id,
    url: "{$call_url}",
    async: false,
    //processData: false, //TODO: Istrinti jeigu neprireiks
    //contentType: false, //TODO: Istrinti jeigu neprireiks
    cache: false,
    data: params,
    dataType: "json",
    success: function (res) {
      if (typeof res['error'] != 'undefined') {
        showErrorMessage(res['error']);
        return false;
      }
      showSuccessMessage(res['success']);
    },
    error: function (res) {
      showErrorMessage('{l s="Failed to request Call courier" mod="itellashipping"}');
    }
  });
}

</script>
{/if}