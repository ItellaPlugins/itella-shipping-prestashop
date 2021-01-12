<div class="panel clearfix">
	{foreach from=$moduleMenu item=menuItem}
		<a class="btn btn-{if $menuItem.active}primary{else}default{/if}" href="{$menuItem.url|escape:'htmlall':'UTF-8'}">
			{$menuItem.label|escape:'htmlall':'UTF-8'}
		</a>
	{/foreach}
</div>

{if $call_url}
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
    itella_modal.find('#store_info').html(
      '<p>'+store.title+'</p>' +
      '<p>'+store.address+'</p>' +
      '<p>'+store.postcode+' '+store.city+', '+store.country_code+'</p>' +
      //'<p>'+store.pick_start+' - '+store.pick_finish+'</p>' +
      '<p>'+store.phone+'</p>'
    );
  });
  itella_modal.find('#id_itella_store').trigger('change');
  $(document).on('click', 'a[data-manifest]', function(e) {
    e.preventDefault();
    itella_manifest_id = this.dataset.manifest || 0;
    itella_modal.modal('show');
  });
  // $('#call_courier_btn').on('click', function(e){
  //   itella_modal.modal('show');
  // });

});

function create_itella_modal() {
  var heading = '{l s="Call Smartpost Courier" mod="itellashipping"}';
  var question = '{l s="Please select store to call courier to:" mod="itellashipping"}' + '<select id="id_itella_store" class="chosen">';
  stores.forEach(function(store) {
    question += '<option value="'+store.id_itella_store+'" '+ (store.is_default == 1 ? 'selected' : '') +'>' + store.title + '</option>';
  });
  question += '</select>';
  var left_button_txt = '{l s="Send request" mod="itellashipping"}';
  var right_button_txt = '{l s="Cancel" mod="itellashipping"}';
  var confirmModal = $('#itella_modal');
  if (confirmModal.length == 0)
    confirmModal =
      $('<div class="bootstrap modal hide fade" id="itella_modal">' +
        '<div class="modal-dialog">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<a class="close" data-dismiss="modal" >&times;</a>' +
        '<h3>' + heading + '</h3>' +
        '</div>' +
        '<div class="modal-body">' +
        '<p>' + question + '</p>' +
        {* '<div class="form-group">' +
        '    <label for="recipient-name" class="col-form-label">Recipient:</label>' +
        '    <select class="form-control" id="recipient-name"></select>' +
        '</div>' + *}
        '<p id="store_info"></p>' +
        '</div>' +
        '<div class="modal-footer">' +
        '<a href="#" id="confirm_modal_left_button" class="btn btn-success">' +
        left_button_txt +
        '</a>' +
        '<a href="#" id="confirm_modal_right_button" class="btn btn-danger">' +
        right_button_txt +
        '</a>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>');
  confirmModal.find('#confirm_modal_left_button').click(function () {
    sendCall($('#id_itella_store').val(), itella_manifest_id);
    confirmModal.modal('hide');
  });
  confirmModal.find('#confirm_modal_right_button').click(function () {
    confirmModal.modal('hide');
  });
  
  itella_modal = confirmModal;
}

function findStoreInfo(id_store) {
  for(var i=0; i < stores.length; i++) {
    if (stores[i].id_itella_store == id_store) {
      return stores[i];
    }
  }
  return false;
}

function sendCall(address_id, manifest_id) {
  if (!address_id) {
    showErrorMessage('{l s="No store selected" mod="itellashipping"}');
  }
  if (!manifest_id) {
    showErrorMessage('{l s="No manifest selected" mod="itellashipping"}');
  }

  $.ajax({
    type: "POST",
    url: "{$call_url}&ajax=1&id_itella_store=" + address_id + "&id_manifest=" + manifest_id,
    async: false,
    processData: false,
    contentType: false,
    cache: false,
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