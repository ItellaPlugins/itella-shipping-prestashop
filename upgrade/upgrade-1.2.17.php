<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_17($module)
{
    $new_logo = _PS_MODULE_DIR_ . 'itellashipping/views/images/logo_square.png';
    $itella_carriers = $module->getItellaCarriers();
    foreach ( $itella_carriers as $carrier ) {
        if ( ! isset($carrier['id_carrier']) ) {
            continue;
        }
        if ( replaceCarrierLogo((int) $carrier['id_carrier'], $new_logo) ) {
            createNewTmpLogo($carrier['id_carrier']);
        }
    }
}

function replaceCarrierLogo($id_carrier, $new_logo_path)
{
    $destination = _PS_SHIP_IMG_DIR_ . $id_carrier . '.jpg';
    if ( ! is_writable(dirname($destination)) ) {
        return false;
    }

    if (file_exists($new_logo_path)) {
        if (copy($new_logo_path, $destination)) {
            return true;
        }
    }
    return false;
}

function createNewTmpLogo($id_carrier)
{
    $tmp = _PS_TMP_IMG_DIR_ . '/carrier_mini_' . (int) $id_carrier . '.jpg';
    $org = _PS_SHIP_IMG_DIR_ . (int) $id_carrier . '.jpg';
    
    if ( ! file_exists($org) || ! is_writable(dirname($tmp)) ) {
        return false;
    }

    for ( $i = 1; $i < 9; $i++ ) {
        $old_tmp_logo = _PS_TMP_IMG_DIR_ . '/carrier_mini_' . (int) $id_carrier . '_' . $i . '.jpg';
        if ( file_exists($old_tmp_logo) ) {
            unlink($old_tmp_logo);
        }
    }
    if ( ! ImageManager::resize($org, $tmp, 75, 75) ) {
        return false;
    }

    return true;
}
