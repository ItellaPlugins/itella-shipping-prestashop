var ItellaModule = new function () {
  var self = this;

  this.init = function () {
    if (typeof window.opc == 'undefined') {
      window.opc = false;
    }
    self.initHooks();
    self.checkPlacement();
    var itellaEl = document.getElementById('itella-extra');
    itellaEl.innerHTML = ''; // in case its not empty
    window.itella = new itellaMapping(itellaEl); //.parentNode);
    itella
      .setImagesUrl(itella_images_url)
      .setStrings(JSON.parse(itella_translation))
      .setCountry(itella_country);
    itella.init();
    terminals = itella_locations;
    itella.setLocations(terminals, true);
    itella.registerCallback(function (manual) {
      console.log(this.selectedPoint);
      var ajaxData = {};
      ajaxData.selected_id = this.selectedPoint.pupCode;
      ajaxData.carrier_id = $("input[name*='delivery_option[']:checked").val().split(',')[0];
      ajaxData.itella_token = itella_token;

      if (manual) {
        $.ajax(itella_controller_url,
          {
            data: ajaxData,
            type: "POST",
            dataType: "json",
          });
      }

      $('#itella_pickup_point_id').val(this.selectedPoint.id);
      self.validate(null, $("input[name*='delivery_option[']:checked"));
    });
    // set selected pickup point (modal will handle empty values)
    itella.setSelection($('#itella_pickup_point_id').val());

    self.validate(null, $("input[name*='delivery_option[']:checked"));
  }

  this.checkPlacement = function () {
    if (itella_ps_version == 1.6) {
      var $master_container = $("input[name*='delivery_option['][value*='" + itella_carrier_pickup_id + ",']").closest('td').next().next();
      $master_container.find('#itella-extra').remove();
      var $itella_extra = $('#itella-extra');
      if ($master_container.find('#itella-extra').length == 0) {
        $master_container.append($itella_extra);
      }
      return true;
    }

    if (itella_ps_version >= 1.7) {
      var $master_container = $("input[name*='delivery_option['][value*='" + itella_carrier_pickup_id + ",']").closest('.delivery-option').find('label');
      $master_container.find('#itella-ps17-extra').remove();
      var $itella_extra = $('#itella-ps17-extra');
      if ($master_container.find('#itella-ps17-extra').length == 0) {
        $master_container.append($itella_extra);
      }
      return true;
    }
  }

  this.initHooks = function () {
    // assume 1.7 and up will be same
    if (itella_ps_version >= 1.7) {
      $('form#js-delivery input[type="radio"][name*="delivery_option["]').on('click', function (e) {
        self.validate(e, this);
      });
    } else {
      // using 1.6 version
      $(document).on('click', '[name*="delivery_option["]', function (e) {
        if ($("input[name*='delivery_option[']:checked").val().split(',')[0] != itella_carrier_pickup_id) {
          $("input[name*='delivery_option['][value*='" + itella_carrier_pickup_id + ",']").closest('tr')
            .find('#itella-extra').remove();
        }
      });

      $(document).on('click', '.payment_module a', function (e) {
        self.validate(e, this);
      });

      $(document).on('click', 'button[name="processCarrier"]', function (e) {
        self.validate(e, this);
      });
    }
  }

  this.showWarning = function (text) {
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

  this.validate = function (event, el) {
    var selected_id = $("input[name*='delivery_option[']:checked").val().split(',')[0];
    var itella_selection = $('#itella_pickup_point_id').val();

    if (itella_ps_version >= 1.7) {
      $('button[name="confirmDeliveryOption"]').prop('disabled', false);
    }

    if (selected_id == itella_carrier_pickup_id) {
      if (!!itella_selection === false) {

        if (itella_ps_version >= 1.7) {
          $('button[name="confirmDeliveryOption"]').prop('disabled', true);
          return false;
        }

        if (itella_ps_version == 1.6 && opc) {
          if (event != null) {
            event.preventDefault();
            $("#carrier_area").get(0).scrollIntoView();
            self.showWarning(JSON.parse(itella_translation).select_pickup_point);
          }
          return false;
        }

        if (itella_ps_version == 1.6 && !opc) {
          if (event != null) {
            event.preventDefault();
            $(".delivery_options").get(0).scrollIntoView();
            self.showWarning(JSON.parse(itella_translation).select_pickup_point);
          }
          return false;
        }

        return false;
      }
    }
    return true;
  }
}
