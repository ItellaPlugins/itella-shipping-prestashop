<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
    
function upgrade_module_1_2_2($module)
{
    Configuration::updateValue('ITELLA_SELECTOR_TYPE', 0); // default is map

    return Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'itella_cart` ADD `comment` text COLLATE utf8_unicode_ci DEFAULT NULL AFTER `error`');
}