<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_4_4($module)
{
    ItellaShipping::checkForClass('ItellaShipment');

    // Add tracking URL to existing module carriers which do not have it
    foreach (array('ITELLA_COURIER_ID', 'ITELLA_PICKUP_POINT_ID') as $key) {
        $id_carrier = (int) Configuration::get($key);
        if (!$id_carrier) {
            continue;
        }

        try {
            ItellaShipment::setCarrierTrackingUrl(new Carrier($id_carrier));
        } catch (Exception $e) {
            // Not critical, URL can be set manually in carrier settings
        }
    }

    return true;
}
