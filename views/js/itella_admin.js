$(document).ready(function () {
  hookListeners();
  checkCODSettings();
  //massPrintLinks();
});

function massPrintLinks() {
  if (typeof help_class_name != 'undefined' && help_class_name == 'AdminOrders') {
    var bulk_actions_dropdown = $('.bulk-actions ul.dropdown-menu');
    bulk_actions_dropdown.append('<li><a href="#" onclick="sendItellaBulkAction($(this).closest(\'form\').get(0), \'' + itellaMassLabelUrl + '\',$(this),true);"><i class="icon-cloud-download"></i>&nbsp;' + itellaMassLabelTitle + '</a></li>');
  }
}

function hookListeners() {
  $(document).on('click', '#ITELLA_LABEL_NUM_SWITCH_on', function (e) {
    $('#ITELLA_LABEL_NUM').closest('.form-group').removeClass('hide');
  });
  $(document).on('click', '#ITELLA_LABEL_NUM_SWITCH_off', function (e) {
    $('#ITELLA_LABEL_NUM').closest('.form-group').addClass('hide');
  });

  $(document).on('click', '[name*="ITELLA_COD_MODULES"]', function (e) {
    checkCODSettings();
  });
}

function checkCODSettings() {
  if ($('[name*=ITELLA_COD_MODULES]:checked').length > 0) {
    $('#ITELLA_COD_BIC').closest('.form-group').removeClass('hide');
    $('#ITELLA_COD_IBAN').closest('.form-group').removeClass('hide');
    $('#ITELLA_COD_ENABLED').val(1);
  } else {
    $('#ITELLA_COD_BIC').closest('.form-group').addClass('hide');
    $('#ITELLA_COD_IBAN').closest('.form-group').addClass('hide');
    $('#ITELLA_COD_ENABLED').val(0);
  }
}

function sendItellaBulkAction(form, action, object, reload) {
  var order_ids = '';
  $("input[name='orderBox[]']:checked").each(function (index) {
    order_ids += $(this).val() + ',';
  });
  if (order_ids) {

    order_ids = order_ids.substring(0, order_ids.length - 1);

    object.attr('href', action + '&order_ids=' + order_ids);
    object.attr('target', '_blank');
    if (reload == 0) {
      setTimeout(function () {
        window.location.href = location.href;
      }, 5000);
    }
  } else {
    if (!!$.prototype.fancybox) {
      $.fancybox.open([
        {
          type: 'inline',
          autoScale: true,
          minHeight: 30,
          content: '<p class="fancybox-error">' + itellaNoOrdersWarn + '</p>'
        }],
        {
          padding: 0
        });
    } else {
      alert(itellaNoOrdersWarn);
    }
  }
  return false;
}

