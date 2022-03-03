## Itella Shipping module for Prestashop

Supports:
- Prestashop 1.6
- Prestashop 1.7

Updating:
Simplest way is to download newest version and using FTP upload files. Please always make a backup of currently used module files in case something goes wrong.
If module used has modifications (customized to work with one page checkout for example), please contact support to help upgrade module without breaking customized parts. Alternatively changes between version can be seen on github https://github.com/ItellaPlugins/itella-shipping-prestashop 

## steasycheckout support requirements!
In order to validate if terminal is selected, steasycheckout front.js file *MUST* be edited to have additional code.

File: steasycheckout/views/js/front.js

to var steco_delivery object add
```
'itellashipping': function(){
    var itellaCarrier = $(steco_delivery.deliveryFormSelector + ' .delivery-option :radio:checked').closest('.delivery-option').next().find('#itella_pickup_point_id');
    if (itellaCarrier.length && itellaCarrier.val() == 0)
      return false;
    return true;
}
```

inside steco_payment objects `toggleOrderButton` function just before `this.collapseOptions();` line add
```
if(!steco_delivery.itellashipping()){
    show = false;
    if(complete)
        steco_payment.showIncompleteMessage($('.steco_delivery'));
}
```
