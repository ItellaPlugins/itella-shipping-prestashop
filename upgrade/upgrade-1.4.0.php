<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_4_0($module)
{
    $old_api_user = Configuration::get('ITELLA_API_USER_2317');
    $old_api_pass = Configuration::get('ITELLA_API_PASS_2317');
    $old_api_contract = Configuration::get('ITELLA_API_CONTRACT_2317');
    $new_api_user = Configuration::get('ITELLA_API_USER');
    $new_api_pass = Configuration::get('ITELLA_API_PASS');
    $new_api_contract = Configuration::get('ITELLA_API_CONTRACT');

    if ( ! $new_api_user && ! empty($old_api_user) ) {
        Configuration::updateValue('ITELLA_API_USER', $old_api_user);
    }
    if ( ! $new_api_pass && ! empty($old_api_pass) ) {
        Configuration::updateValue('ITELLA_API_PASS', $old_api_pass);
    }
    if ( ! $new_api_contract && ! empty($old_api_contract) ) {
        Configuration::updateValue('ITELLA_API_CONTRACT', $old_api_contract);
    }
    
    if ( ! Configuration::get('ITELLA_API_PP_SERVICE') ) {
        Configuration::updateValue('ITELLA_API_PP_SERVICE', ItellaShipment::getDefaultServiceCode('pickup'));
    }
    if ( ! Configuration::get('ITELLA_API_C_SERVICE') ) {
        Configuration::updateValue('ITELLA_API_C_SERVICE', ItellaShipment::getDefaultServiceCode('courier'));
    }

    return true;
}
