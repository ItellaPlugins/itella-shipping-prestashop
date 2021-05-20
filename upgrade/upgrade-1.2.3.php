<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
    
function upgrade_module_1_2_3($module)
{
    $tab = new Tab();
    $tab->active = 1;
    $tab->class_name = ItellaShipping::CONTROLLER_ADMIN_AJAX;
    $tab->name = array();
    $languages = Language::getLanguages(false);

    foreach ($languages as $language) {
        $tab->name[$language['id_lang']] = 'ItellaAdminAjax';
    }

    $tab->id_parent = -1;
    $tab->module = $module->name;
    if (!$tab->save()) {
        return false;
    }
    return true;
}