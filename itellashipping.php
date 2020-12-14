<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

class ItellaShipping extends CarrierModule
{
  const CONTROLLER_STORES = 'AdminItellashippingItellaStore';
  const CONTROLLER_MANIFEST = 'AdminItellashippingItellaManifest';
  const CONTROLLER_MANIFEST_DONE = 'AdminItellashippingItellaManifestDone';

  private static $_name = 'itellashipping';
  private static $_classMap = array(
    'ItellaCart' => 'classes/ItellaCart.php',
    'ItellaStore' => 'classes/ItellaStore.php',
    'ItellaManifest' => 'classes/ItellaManifest.php',
  );
  protected $_carriers = array(
    'ITELLA_COURIER_ID' => 'Itella courier',
    'ITELLA_PICKUP_POINT_ID' => 'Itella pickup point'
  );

  private $_postErrors = array();

  protected $_hooks = array(
    'ActionAdminControllerSetMedia',
    'displayBackOfficeHeader',
    'displayAdminOrder',

    'header',
    'actionCarrierUpdate', // hookUpdateCarrier
    'DisplayCarrierList',
    'DisplayCarrierExtraContent',
    'OrderConfirmation',

    'displayBeforeCarrier',

    'actionCarrierProcess',
    'orderDetailDisplayed',
    'actionValidateStepComplete'
  );

  // For easier access when filling values into form
  private $_configKeys = array(
    //'ITELLA_TEST_MODE' => 'ITELLA_TEST_MODE',
    'ITELLA_API_USER_2317' => 'ITELLA_API_USER_2317',
    'ITELLA_API_PASS_2317' => 'ITELLA_API_PASS_2317',
    'ITELLA_API_CONTRACT_2317' => 'ITELLA_API_CONTRACT_2317',
    'ITELLA_API_USER_2711' => 'ITELLA_API_USER_2711',
    'ITELLA_API_PASS_2711' => 'ITELLA_API_PASS_2711',
    'ITELLA_API_CONTRACT_2711' => 'ITELLA_API_CONTRACT_2711',
    'ITELLA_COD_MODULES' => 'ITELLA_COD_MODULES',
    //'ITELLA_LABEL_NUM' => 'ITELLA_LABEL_NUM',
    'ITELLA_COD_BIC' => 'ITELLA_COD_BIC',
    'ITELLA_COD_IBAN' => 'ITELLA_COD_IBAN',
  );

  private $_sender_keys = array(
    'ITELLA_SENDER_NAME',
    'ITELLA_SENDER_STREET',
    'ITELLA_SENDER_POSTCODE',
    'ITELLA_SENDER_CITY',
    'ITELLA_SENDER_COUNTRY_CODE',
    'ITELLA_SENDER_PHONE',
    'ITELLA_SENDER_EMAIL',
  );

  private $_advanced_keys = array(
    'ITELLA_CALL_EMAIL_LT',
    'ITELLA_CALL_EMAIL_LV',
    'ITELLA_CALL_EMAIL_EE',
    'ITELLA_CALL_EMAIL_FI',
  );

  private $switch = array();

  public function __construct()
  {
    $this->name = self::$_name;
    $this->tab = 'shipping_logistics';
    $this->version = '1.1.5';
    $this->author = 'Mijora.lt';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.8');
    $this->bootstrap = true;

    parent::__construct();

    $this->displayName = $this->l('Itella Shipping');
    $this->description = $this->l('Itella shipping module');

    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

    self::checkForClass('ItellaStore');

    if (self::isInstalled($this->name)) {
      $id_carrier_list = self::getAllCarriers(true); // get only IDs

      // Check if module is configured
      $warning = array();
      if (
        !in_array((int) (Configuration::get('ITELLA_CARRIER_ID')), $id_carrier_list) &&
        !in_array((int) (Configuration::get('ITELLA_PICKUP_CARRIER_ID')), $id_carrier_list)
      ) {
        $warning[] = $this->l('"Itella carriers"');
      }

      if (
        !Configuration::get($this->_configKeys['ITELLA_API_USER_2317']) ||
        !Configuration::get($this->_configKeys['ITELLA_API_USER_2711'])
      ) {
        $warning[] = $this->l('"Both Itella API users"');
      }

      if (
        !Configuration::get($this->_configKeys['ITELLA_API_PASS_2317']) ||
        !Configuration::get($this->_configKeys['ITELLA_API_PASS_2711'])
      ) {
        $warning[] = $this->l('"Both Itella API password"');
      }
      if (count($warning)) {
        $this->warning .= implode(', ', $warning) . $this->l('must be configured to use this module.');
      }

      $this->updateLocations();
    }
  }

  public function install()
  {

    if (Shop::isFeatureActive()) {
      Shop::setContext(Shop::CONTEXT_ALL);
    }

    $this->registerTabs();

    if (
      !parent::install() ||
      !$this->hooks('register') ||
      !$this->createTables()
    ) {
      return false;
    }

    foreach (Language::getLanguages(false) as $lng) {
      if ($lng['iso_code'] == 'lt') {
        $this->_carriers['ITELLA_COURIER_ID'] = 'Itella pristatymas į nurodytą adresą';
        $this->_carriers['ITELLA_PICKUP_POINT_ID'] = 'Itella pristatymas į atsiėmimo tašką';
        break;
      }
    }

    foreach ($this->_carriers as $key => $title) {
      if (!$this->createCarrier($key, $title)) {
        return false;
      }
    }

    // set defaults
    Configuration::updateValue('ITELLA_CALL_EMAIL_SUBJECT', 'E-com order booking');
    Configuration::updateValue('ITELLA_CALL_EMAIL_LT', 'smartship.routing.lt@itella.com');
    Configuration::updateValue('ITELLA_CALL_EMAIL_LV', 'smartship.routing.lv@itella.com');
    return true;
  }

  /**
   * Provides list of Admin controllers info
   *
   * @return array BackOffice Admin controllers
   */
  private function getModuleTabs()
  {
    return array(
      self::CONTROLLER_STORES => array(
        'title' => $this->l('Stores'),
        'parent_tab' => -1
      ),
      self::CONTROLLER_MANIFEST => array(
        'title' => $this->l('Itella'),
        'parent_tab' => (int) Tab::getIdFromClassName('AdminParentShipping')
      ),
      self::CONTROLLER_MANIFEST_DONE => array(
        'title' => $this->l('Itella generated manifests'),
        'parent_tab' => -1
      )
    );
  }

  /**
   * Registers module Admin tabs (controllers)
   */
  private function registerTabs()
  {
    $tabs = $this->getModuleTabs();

    if (empty($tabs)) {
      return true; // Nothing to register
    }

    foreach ($tabs as $controller => $tabData) {
      $tab = new Tab();
      $tab->active = 1;
      $tab->class_name = $controller;
      $tab->name = array();
      $languages = Language::getLanguages(false);

      foreach ($languages as $language) {
        $tab->name[$language['id_lang']] = $tabData['title'];
      }

      $tab->id_parent = $tabData['parent_tab'];
      $tab->module = $this->name;
      if (!$tab->save()) {
        $this->displayError($this->l('Error while creating tab ') . $tabData['title']);
        return false;
      }
    }
    return true;
  }

  /**
   * Deletes module Admin controllers
   * Used for module uninstall
   *
   * @return bool Module Admin controllers deleted successfully
   *
   * @throws PrestaShopDatabaseException
   * @throws PrestaShopException
   */
  private function deleteTabs()
  {
    $tabs = $this->getModuleTabs();

    if (empty($tabs)) {
      return true; // Nothing to remove
    }

    foreach (array_keys($tabs) as $controller) {
      $idTab = (int) Tab::getIdFromClassName($controller);
      $tab = new Tab((int) $idTab);

      if (!Validate::isLoadedObject($tab)) {
        continue; // Nothing to remove
      }

      if (!$tab->delete()) {
        $this->displayError($this->l('Error while uninstalling tab') . ' ' . $tab->name);
        return false;
      }
    }

    return true;
  }

  public function createTables()
  {

    $sql = array(
      'itella_cart' => 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itella_cart` (
        `id_cart` int(10) unsigned NOT NULL,
        `is_oversized` tinyint(1) NOT NULL DEFAULT 0,
        `is_call_before_delivery` tinyint(1) NOT NULL DEFAULT 0,
        `is_fragile` tinyint(1) NOT NULL DEFAULT 0,
        `packs` int(10) unsigned NOT NULL DEFAULT 1,
        `is_cod` tinyint(1) NOT NULL DEFAULT 0,
        `weight` decimal(10,2) DEFAULT NULL,
        `volume` decimal(10,2) DEFAULT NULL,
        `cod_amount` decimal(10,2) DEFAULT NULL,
        `is_pickup` tinyint(1) NOT NULL DEFAULT 0,
        `id_pickup_point` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
        `label_number` text COLLATE utf8_unicode_ci NULL,
        `error` text COLLATE utf8_unicode_ci DEFAULT NULL,
        `id_itella_manifest` int(10) unsigned DEFAULT NULL,
        PRIMARY KEY (`id_cart`)
      ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;',

      'itella_store' => 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itella_store` (
        `id_itella_store` INT(10) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `postcode` varchar(255) NOT NULL,
        `city` varchar(255) NOT NULL,
        `phone` varchar(255) NOT NULL,
        `country_code` varchar(255) NOT NULL,
        `address` varchar(255) NOT NULL,
        `pick_start` varchar(255) NOT NULL,
        `pick_finish` varchar(255) NOT NULL,
        `id_shop` int(11) NOT NULL,
        `is_default` tinyint(1) NOT NULL DEFAULT "0",
        `active` tinyint(1) NOT NULL DEFAULT "1",
        PRIMARY KEY (`id_itella_store`)
      ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;',

      'itella_manifest' => 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itella_manifest` (
        `id_itella_manifest` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `id_shop` int(10) unsigned DEFAULT NULL,
        `date_add` datetime NOT NULL,
        PRIMARY KEY (`id_itella_manifest`)
      ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
    );

    foreach ($sql as $query) {
      try {
        $res_query = Db::getInstance()->execute($query);

        if ($res_query === false) {
          return false;
        }
      } catch (Exception $e) {
        return false;
      }
    }

    return true;
  }

  public function deleteTables()
  {
    $sql = array(
      'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itella_cart`',
      'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itella_store`',
      'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itella_manifest`'
    );

    foreach ($sql as $query) {
      try {
        $res_query = Db::getInstance()->execute($query);
      } catch (Exception $e) {
      }
    }

    return true;
  }

  public function uninstall()
  {
    if (
      !parent::uninstall() ||
      !$this->hooks('unregister')
    ) {
      return false;
    }

    $this->deleteTabs();
    $this->deleteTables();

    foreach (array_keys($this->_carriers) as $key) {
      if (!$this->deleteCarrier($key)) {
        return false;
      }
    }

    return true;
  }

  public function hooks($action = 'register')
  {
    if (!in_array($action, array('register', 'unregister'))) {
      throw new Exception("Unsupported hook action - allowed only ['register', 'unregister']");
    }
    $action .= 'Hook';
    foreach ($this->_hooks as $hook) {
      if (!$this->$action($hook)) {
        return false;
      }
    }
    return true;
  }

  public function createCarrier($key, $name)
  {
    $carrier = new Carrier();
    $carrier->name = $name;
    $carrier->delay[Configuration::get('PS_LANG_DEFAULT')] = '1-2 business days';
    $carrier->is_module = true;
    $carrier->external_module_name = $this->name;
    $carrier->need_range = true;
    $carrier->range_behavior = 0;
    $carrier->shipping_external = true;
    $carrier->shipping_handling = false;
    //$carrier->limited_countries = array('lt');
    $carrier->url = '';
    $carrier->active = true;
    $carrier->deleted = 0;

    if (!$carrier->add()) {
      return false;
    }

    $groups = Group::getGroups(true);
    foreach ($groups as $group) {
      Db::getInstance()->insert('carrier_group', array(
        'id_carrier' => (int) $carrier->id,
        'id_group' => (int) $group['id_group']
      ));
    }

    $rangePrice = new RangePrice();
    $rangePrice->id_carrier = (int) $carrier->id;
    $rangePrice->delimiter1 = '0';
    $rangePrice->delimiter2 = '1000';
    $rangePrice->add();

    $rangeWeight = new RangeWeight();
    $rangeWeight->id_carrier = (int) $carrier->id;
    $rangeWeight->delimiter1 = '0';
    $rangeWeight->delimiter2 = '1000';
    $rangeWeight->add();

    $zones = Zone::getZones(true);
    foreach ($zones as $zone) {
      Db::getInstance()->insert(
        'carrier_zone',
        array('id_carrier' => (int) $carrier->id, 'id_zone' => (int) $zone['id_zone'])
      );
      Db::getInstance()->insert(
        'delivery',
        array('id_carrier' => (int) $carrier->id, 'id_range_price' => (int) $rangePrice->id, 'id_range_weight' => NULL, 'id_zone' => (int) $zone['id_zone'], 'price' => '0')
      );
      Db::getInstance()->insert(
        'delivery',
        array('id_carrier' => (int) $carrier->id, 'id_range_price' => NULL, 'id_range_weight' => (int) $rangeWeight->id, 'id_zone' => (int) $zone['id_zone'], 'price' => '0')
      );
    }
    try {
      copy(dirname(__FILE__) . '/logo.png', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
    } catch (Exception $e) {
    }

    Configuration::updateValue($key, $carrier->id);
    Configuration::updateValue($key . '_REFERENCE', $carrier->id);

    return true;
  }

  private function updateDefaultCarrier()
  {
    $carriers = self::getAllCarriers();
    foreach ($carriers as $carrier) {
      if ($carrier['external_module_name'] != $this->name && $carrier['active'] && !$carrier['deleted']) {
        Configuration::updateValue('PS_CARRIER_DEFAULT', $carrier['id_carrier']);
        break;
      }
    }
  }

  public function deleteCarrier($key)
  {
    $itella_carrier = new Carrier((int) (Configuration::get($key)));
    if (!$itella_carrier) {
      return true; // carrier doesnt exist, no further action needed
    }

    if (Configuration::get('PS_CARRIER_DEFAULT') == (int) $itella_carrier->id) {
      $this->updateDefaultCarrier();
    }

    // soft delete carrier
    $itella_carrier->active = 0;
    $itella_carrier->deleted = 1;

    if (!$itella_carrier->update()) {
      return false;
    }

    return true;
  }

  protected function validateMainSettings()
  {
    $errors = array();
    $required = $this->l('is required');
    $bad_format = $this->l('bad format');

    if (empty(Tools::getValue($this->_configKeys['ITELLA_API_USER_2317'])) || empty(Tools::getValue($this->_configKeys['ITELLA_API_USER_2711']))) {
      $errors[] = $this->l('Both API users') . ' ' . $required;
    }

    if (empty(Tools::getValue($this->_configKeys['ITELLA_API_PASS_2317'])) || empty(Tools::getValue($this->_configKeys['ITELLA_API_PASS_2711']))) {
      $errors[] = $this->l('Both API passwords') . ' ' . $required;
    }

    if (empty(Tools::getValue($this->_configKeys['ITELLA_API_CONTRACT_2317'])) || empty(Tools::getValue($this->_configKeys['ITELLA_API_CONTRACT_2711']))) {
      $errors[] = $this->l('Both API contracts') . ' ' . $required;
    }

    if (Tools::getValue('ITELLA_COD_ENABLED')) {
      if (empty(Tools::getValue($this->_configKeys['ITELLA_COD_BIC']))) {
        $errors[] = $this->l('For C.O.D BIC') . ' ' . $required;
      }

      if (empty(Tools::getValue($this->_configKeys['ITELLA_COD_IBAN']))) {
        $errors[] = $this->l('For C.O.D IBAN') . ' ' . $required;
      }
    }

    return $errors;
  }

  public function saveMainSettings()
  {
    $errors = $this->validateMainSettings();
    $output = null;

    if (!empty($errors)) {
      $output .= $this->displayError($errors);
    } else {
      $cod_modules = array();
      // save settings
      foreach ($this->_configKeys as $key) {
        // skip cod modules as those will be read from $_POST
        if ($key === $this->_configKeys['ITELLA_COD_MODULES']) {
          continue;
        }

        $value = strval(Tools::getValue($key));
        Configuration::updateValue($key, $value);
      }

      // checkbox settings handling
      foreach ($_POST as $key => $value) {
        if (strpos($key, $this->_configKeys['ITELLA_COD_MODULES'] . '_') !== false) {
          $cod_modules[] = str_replace($this->_configKeys['ITELLA_COD_MODULES'] . '_', '', $key);
        }
      }

      Configuration::updateValue($this->_configKeys['ITELLA_COD_MODULES'], implode(',', $cod_modules));

      $output .= $this->displayConfirmation($this->l('Settings updated'));
    }

    return $output;
  }

  protected function validateSenderSettings()
  {
    $errors = array();
    $required = $this->l('is required');
    $bad_format = $this->l('bad format');

    if (empty(Tools::getValue('ITELLA_SENDER_NAME'))) {
      $errors[] = $this->l('Sender name') . ' ' . $required;
    }
    if (empty(Tools::getValue('ITELLA_SENDER_STREET'))) {
      $errors[] = $this->l('Sender street') . ' ' . $required;
    }
    if (empty(Tools::getValue('ITELLA_SENDER_POSTCODE'))) {
      $errors[] = $this->l('Sender postcode') . ' ' . $required;
    }
    if (empty(Tools::getValue('ITELLA_SENDER_CITY'))) {
      $errors[] = $this->l('Sender city') . ' ' . $required;
    }
    if (empty(Tools::getValue('ITELLA_SENDER_COUNTRY_CODE'))) {
      $errors[] = $this->l('Sender country code') . ' ' . $required;
    }
    if (empty(Tools::getValue('ITELLA_SENDER_PHONE'))) {
      $errors[] = $this->l('Sender mobile phone') . ' ' . $required;
    }
    if (empty(Tools::getValue('ITELLA_SENDER_EMAIL'))) {
      $errors[] = $this->l('Sender email') . ' ' . $required;
    }

    return $errors;
  }

  public function saveSenderSettings()
  {
    $errors = $this->validateSenderSettings();
    $output = null;

    if (!empty($errors)) {
      $output .= $this->displayError($errors);
    } else {
      // save settings
      foreach ($this->_sender_keys as $key) {
        $value = strval(Tools::getValue($key));
        Configuration::updateValue($key, $value);
      }
      $output .= $this->displayConfirmation($this->l('Sender settings updated'));
    }

    return $output;
  }

  protected function validateAdvacedSettings()
  {
    $errors = array();
    $required = $this->l('is required');

    if (empty(Tools::getValue('ITELLA_CALL_EMAIL_SUBJECT'))) {
      $errors[] = $this->l('Courrier call email subject') . ' ' . $required;
    }
    if (empty(Tools::getValue('ITELLA_CALL_EMAIL_LT'))) {
      $errors[] = $this->l('LT courrier call email') . ' ' . $required;
    }
    if (empty(Tools::getValue('ITELLA_CALL_EMAIL_LV'))) {
      $errors[] = $this->l('LV courrier call email') . ' ' . $required;
    }

    return $errors;
  }

  public function saveAdvancedSettings()
  {
    $errors = $this->validateAdvacedSettings();
    $output = null;

    if (!empty($errors)) {
      $output .= $this->displayError($errors);
    } else {
      // save settings
      foreach ($this->_advanced_keys as $key) {
        $value = strval(Tools::getValue($key));
        Configuration::updateValue($key, $value);
      }
      Configuration::updateValue('ITELLA_CALL_EMAIL_SUBJECT', strval(Tools::getValue('ITELLA_CALL_EMAIL_SUBJECT')));
      $output .= $this->displayConfirmation($this->l('Advanced settings updated'));
    }

    return $output;
  }

  public function getContent()
  {
    $output = null;

    if (Tools::isSubmit('submit' . $this->name . 'main')) {
      $output .= $this->saveMainSettings();
    }

    if (Tools::isSubmit('submit' . $this->name . 'forceupdate')) {
      $this->updateLocations(true);
    }

    if (Tools::isSubmit('submit' . $this->name . 'sender')) {
      $output .= $this->saveSenderSettings();
    }

    if (Tools::isSubmit('submit' . $this->name . 'advanced')) {
      $output .= $this->saveAdvancedSettings();
    }

    $this->switch = array(
      array(
        'value' => 1,
        'label' => $this->l('Enabled')
      ),
      array(
        'value' => 0,
        'label' => $this->l('Disabled')
      )
    );

    return $output
      . $this->displayMenu()
      . $this->displayMainSettings()
      . $this->displaySenderSettings()
      . $this->displayItellaInfo()
      . $this->displayAdvancedSettings();
  }

  /**
   * Displays module menu
   *
   * @return string
   *
   * @throws SmartyException
   */
  public function displayMenu()
  {
    $menu = array(
      array(
        'label' => $this->l('Module settings'),
        'url' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name,
        'active' => Tools::getValue('controller') != self::CONTROLLER_STORES
      ),
      array(
        'label' => $this->l('Stores'),
        'url' => $this->context->link->getAdminLink(self::CONTROLLER_STORES),
        'active' => Tools::getValue('controller') == self::CONTROLLER_STORES
      )
    );

    $this->context->smarty->assign(array(
      'moduleMenu' => $menu
    ));

    return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/menu.tpl');
  }

  public function displaySenderSettings()
  {
    // Get default language
    $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

    // Init Fields form array
    $fieldsForm[0]['form'] = [
      'legend' => array(
        'title' => $this->l('Sender Settings'),
      ),
      'input' => array(
        array(
          'type' => 'text',
          'label' => $this->l('Name'),
          'name' => 'ITELLA_SENDER_NAME',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Street'),
          'name' => 'ITELLA_SENDER_STREET',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Postcode'),
          'name' => 'ITELLA_SENDER_POSTCODE',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('City'),
          'name' => 'ITELLA_SENDER_CITY',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Country Code'),
          'name' => 'ITELLA_SENDER_COUNTRY_CODE',
          'options' => array(
            'query' => $options = array(
              array(
                'id_option' => 'LT',
                'name' => 'LT - Lithuania'
              ),
              array(
                'id_option' => 'LV',
                'name' => 'LV - Latvia'
              ),
              array(
                'id_option' => 'EE',
                'name' => 'EE - Estonia'
              ),
              array(
                'id_option' => 'FI',
                'name' => 'FI - Finland'
              ),
            ),
            'id' => 'id_option',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Mob. Phone'),
          'name' => 'ITELLA_SENDER_PHONE',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Email'),
          'name' => 'ITELLA_SENDER_EMAIL',
          'size' => 20,
          'required' => true
        ),
      ),
      'submit' => [
        'title' => $this->l('Save'),
        'class' => 'btn btn-default pull-right'
      ]
    ];

    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

    // Language
    $helper->default_form_language = $defaultLang;
    $helper->allow_employee_form_lang = $defaultLang;

    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->submit_action = 'submit' . $this->name . 'sender';

    // load saved settings
    foreach ($this->_sender_keys as $key) {
      $helper->fields_value[$key] = Configuration::get($key);
    }

    return $helper->generateForm($fieldsForm);
  }

  public function displayMainSettings()
  {
    // Get default language
    $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

    $cod_modules = array();
    foreach (PaymentModule::getInstalledPaymentModules() as $value) {
      $cod_modules[] = array(
        'id' => $value['name'],
        'name' => $value['name']
      );
    }

    // Init Fields form array
    $fieldsForm[0]['form'] = [
      'legend' => array(
        'title' => $this->l('API Settings'),
      ),
      'input' => array(
        // array(
        //   'type' => 'switch',
        //   'is_bool' => true,
        //   'label' => $this->l('Test mode'),
        //   'name' => $this->_configKeys['ITELLA_TEST_MODE'],
        //   'values' => $this->switch
        // ),
        array(
          'type' => 'html',
          'label' => $this->l('2317 Product Credentials'),
          'name' => 'itella_2317_creds_html',
          'html_content' => '',
        ),
        array(
          'type' => 'text',
          'label' => $this->l('API user'),
          'name' => $this->_configKeys['ITELLA_API_USER_2317'],
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('API password'),
          'name' => $this->_configKeys['ITELLA_API_PASS_2317'],
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('API contract'),
          'name' => $this->_configKeys['ITELLA_API_CONTRACT_2317'],
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'html',
          'label' => $this->l('2711 Product Credentials'),
          'name' => 'itella_2711_creds_html',
          'html_content' => '',
        ),
        array(
          'type' => 'text',
          'label' => $this->l('API user'),
          'name' => $this->_configKeys['ITELLA_API_USER_2711'],
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('API password'),
          'name' => $this->_configKeys['ITELLA_API_PASS_2711'],
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('API contract'),
          'name' => $this->_configKeys['ITELLA_API_CONTRACT_2711'],
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'hidden',
          'name' => 'ITELLA_COD_ENABLED',
        ),
        array(
          'type' => 'checkbox',
          'label' => $this->l('C.O.D. Modules'),
          'name' => $this->_configKeys['ITELLA_COD_MODULES'],
          'desc' => $this->l('Select payment modules that are for C.O.D.'),
          'values' => array(
            'query' => $cod_modules,
            'id' => 'id',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'text',
          'label' => $this->l('BIC'),
          'name' => $this->_configKeys['ITELLA_COD_BIC'],
          'form_group_class' => 'hide',
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('IBAN'),
          'name' => $this->_configKeys['ITELLA_COD_IBAN'],
          'form_group_class' => 'hide',
          'required' => true
        ),
      ),
      'submit' => [
        'title' => $this->l('Save'),
        'class' => 'btn btn-default pull-right'
      ]
    ];

    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

    // Language
    $helper->default_form_language = $defaultLang;
    $helper->allow_employee_form_lang = $defaultLang;

    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit' . $this->name . 'main';
    $helper->toolbar_btn = [
      'save' => [
        'desc' => $this->l('Save'),
        'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
          '&token=' . Tools::getAdminTokenLite('AdminModules'),
      ],
      'back' => [
        'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
        'desc' => $this->l('Back to list')
      ]
    ];

    // load saved settings
    foreach ($this->_configKeys as $key) {
      // skip cod modules checkboxes
      if ($key === $this->_configKeys['ITELLA_COD_MODULES']) {
        continue;
      }

      $helper->fields_value[$key] = Configuration::get($key);
    }

    // check cod module boxes
    $enabled_cod_modules = explode(',', Configuration::get($this->_configKeys['ITELLA_COD_MODULES']));

    $helper->fields_value['ITELLA_COD_ENABLED'] = $enabled_cod_modules ? 1 : 0;

    if (!$enabled_cod_modules) {
      $enabled_cod_modules = array();
    }

    foreach ($enabled_cod_modules as $cod_module) {
      $helper->fields_value[$this->_configKeys['ITELLA_COD_MODULES'] . '_' . $cod_module] = true;
    }

    return $helper->generateForm($fieldsForm);
  }

  public function displayItellaInfo()
  {
    $helper = new HelperForm();

    $helper->show_toolbar = false;
    $helper->module = $this;
    $helper->default_form_language = $this->context->language->id;
    $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

    $helper->submit_action = 'submit' . $this->name . 'forceupdate';
    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
      . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');


    $helper->tpl_vars = array(
      'languages' => $this->context->controller->getLanguages(),
      'id_language' => $this->context->language->id,
    );

    $last_update = Configuration::getGlobalValue('ITELLA_LAST_UPDATE');
    if ($last_update === false) {
      $last_update = $this->l('Never updated');
    } else {
      $last_update = date('Y-m-d H:i:s', $last_update);
      $dir = _PS_MODULE_DIR_ . $this->name . "/locations/";
      $locations = array('LT' => 0, 'LV' => 0, 'EE' => 0, 'FI' => 0);
      foreach (array_keys($locations) as $key) {
        if (file_exists($dir . 'locations_' . $key . '.json')) {
          $points = $this->loadItellaLocations($key);
          if ($points) {
            $locations[$key] = count(json_decode($points, true));
          }
        }
      }
    }

    $info_form = array(
      'form' => array(
        'legend' => array(
          'title' => $this->l('Pickup Points')
        ),
        'input' => array(
          array(
            'type' => 'html',
            'label' => $this->l('Last update time:'),
            'name' => 'itella_last_update_html',
            'html_content' => '<label class="control-label"><b>' . $last_update . '</b></label>',
          ),
          array(
            'type' => 'html',
            'label' => 'LT ' . $this->l('Total pickup points:'),
            'name' => 'itella_total_lt_locations',
            'html_content' => '<label class="control-label"><b>' . $locations['LT'] . '</b></label>',
          ),
          array(
            'type' => 'html',
            'label' => 'LV ' . $this->l('Total pickup points:'),
            'name' => 'itella_total_lv_locations',
            'html_content' => '<label class="control-label"><b>' . $locations['LV'] . '</b></label>',
          ),
          array(
            'type' => 'html',
            'label' => 'EE ' . $this->l('Total pickup points:'),
            'name' => 'itella_total_ee_locations',
            'html_content' => '<label class="control-label"><b>' . $locations['EE'] . '</b></label>',
          ),
          array(
            'type' => 'html',
            'label' => 'FI ' . $this->l('Total pickup points:'),
            'name' => 'itella_total_fi_locations',
            'html_content' => '<label class="control-label"><b>' . $locations['FI'] . '</b></label>',
          ),
        ),
        'submit' => [
          'title' => $this->l('Update'),
          'class' => 'btn btn-default pull-right'
        ]
      ),
    );

    return $helper->generateForm(array($info_form));
  }

  public function displayAdvancedSettings()
  {
    // Get default language
    $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

    // Init Fields form array
    $fieldsForm[0]['form'] = [
      'legend' => array(
        'title' => $this->l('Advanced Settings'),
      ),
      'input' => array(
        array(
          'type' => 'text',
          'label' => $this->l('Itella email subject'),
          'name' => 'ITELLA_CALL_EMAIL_SUBJECT',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Itella LT email'),
          'name' => 'ITELLA_CALL_EMAIL_LT',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Itella LV email'),
          'name' => 'ITELLA_CALL_EMAIL_LV',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Itella EE email'),
          'name' => 'ITELLA_CALL_EMAIL_EE',
          'size' => 20,
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Itella FI email'),
          'name' => 'ITELLA_CALL_EMAIL_FI',
          'size' => 20,
          'required' => true
        ),
      ),
      'submit' => [
        'title' => $this->l('Save'),
        'class' => 'btn btn-default pull-right'
      ]
    ];

    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

    // Language
    $helper->default_form_language = $defaultLang;
    $helper->allow_employee_form_lang = $defaultLang;

    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->submit_action = 'submit' . $this->name . 'advanced';

    // load saved settings
    foreach ($this->_advanced_keys as $key) {
      $helper->fields_value[$key] = Configuration::get($key);
    }
    $helper->fields_value['ITELLA_CALL_EMAIL_SUBJECT'] = Configuration::get('ITELLA_CALL_EMAIL_SUBJECT');

    return $helper->generateForm($fieldsForm);
  }

  public function getOrderShippingCostExternal($params)
  {
    return 0;
  }

  public function getOrderShippingCost($params, $shipping_cost)
  {
    return $shipping_cost;
  }

  public function loadItellaLocations($country = 'LT')
  {
    if (!in_array($country, array('LT', 'LV', 'EE', 'FI'))) {
      return false;
    }
    $terminals_json_file_dir = _PS_MODULE_DIR_ . $this->name . "/locations/locations_" . $country . ".json";

    if (!file_exists($terminals_json_file_dir)) {
      return false;
    }

    $terminals_file = fopen($terminals_json_file_dir, "r");
    $terminals = fread($terminals_file, filesize($terminals_json_file_dir) + 10);
    fclose($terminals_file);

    return $terminals;
  }

  /**
   * Checks if such location exists
   * 
   * @param int $id
   * @param false|array $country can be set as array of countries to search in
   * 
   * @return false|array false if not found, otherwise location info array
   */
  public function isLocation($id, $country = false)
  {
    $available = array('LT', 'LV', 'EE', 'FI');
    // if false search all available
    if (!$country) {
      $country = $available;
    }

    if (!is_array($country)) {
      $country = array($country);
    }

    foreach ($country as $code) {
      if (!in_array(strtoupper($code), $available)) {
        continue;
      }
      $locations = json_decode($this->loadItellaLocations($code), true);
      foreach ($locations as $loc) {
        if ($loc['pupCode'] == $id) {
          return $loc;
        }
      }
    }
    return false;
  }

  public function hookupdateCarrier($params)
  {
    $id_carrier_old = (int) ($params['id_carrier']);
    $id_carrier_new = (int) ($params['carrier']->id);

    foreach ($this->_carriers as $key => $value) {
      if ($id_carrier_old == (int) (Configuration::get($key)))
        Configuration::updateValue($key, $id_carrier_new);
    }
  }

  public function hookHeader($params)
  {
    if (!$this->active)
      return;

    if (in_array($this->context->controller->php_self, array('order', 'order-opc'))) {

      Media::addJsDef(array(
        'itella_ps_version' => implode('.', explode('.', _PS_VERSION_, -2)),
        'itella_carrier_courier_id' => Configuration::get('ITELLA_COURIER_ID'),
        'itella_carrier_pickup_id' => Configuration::get('ITELLA_PICKUP_POINT_ID'),
        'itella_controller_url' => $this->context->link->getModuleLink('itellashipping', 'front'),
        'itella_token' => Tools::getToken(false),
        'itella_images_url' => $this->_path . 'views/images/',
        'itella_translation' => json_encode(array(
          'modal_header' => $this->l('Pickup points'),
          'selector_header' => $this->l('Pickup point'),
          'workhours_header' => $this->l('Workhours'),
          'contacts_header' => $this->l('Contacts'),
          'search_placeholder' => $this->l('Enter postcode'),
          'select_pickup_point' => $this->l('Select a pickup point'),
          'no_pickup_points' => $this->l('No points to select'),
          'select_btn' => $this->l('select'),
          'back_to_list_btn' => $this->l('reset search'),
          'nothing_found' => $this->l('Nothing found'),
          'select_pickup_point_btn' => $this->l('Select pickup point'),
          'no_information' => $this->l('No information'),
          'error_leaflet' => $this->l('Leaflet is required for Itella-Mapping'),
          'error_missing_mount_el' => $this->l('No mount supplied to itellaShipping')
        ))
      ));
      if (version_compare(_PS_VERSION_, '1.7.0', '>=')) {
        $this->context->controller->registerStylesheet('modules-itella-leaflet-css', 'modules/' . $this->name . '/views/css/leaflet.css');
        $this->context->controller->registerStylesheet('modules-itella-MarkerCluster-css', 'modules/' . $this->name . '/views/css/MarkerCluster.css');
        $this->context->controller->registerStylesheet('modules-itella-MarkerCluster.Default-css', 'modules/' . $this->name . '/views/css/MarkerCluster.Default.css');
        $this->context->controller->registerStylesheet('modules-itella-css', 'modules/' . $this->name . '/views/css/itella-mapping.css');
        $this->context->controller->registerStylesheet('modules-itella-custom-css', 'modules/' . $this->name . '/views/css/custom.css');
        $this->context->controller->registerJavascript('modules-itella-leaflet-js', 'modules/' . $this->name . '/views/js/leaflet.js');
        $this->context->controller->registerJavascript('modules-itella-mapping-js', 'modules/' . $this->name . '/views/js/itella-mapping.js');
        $this->context->controller->registerJavascript('modules-itella-front-js', 'modules/' . $this->name . '/views/js/front.js');
      } else {
        $this->context->controller->addCSS($this->_path . 'views/css/leaflet.css');
        $this->context->controller->addCSS($this->_path . 'views/css/MarkerCluster.css');
        $this->context->controller->addCSS($this->_path . 'views/css/MarkerCluster.Default.css');
        $this->context->controller->addCSS($this->_path . 'views/css/itella-mapping.css');
        $this->context->controller->addCSS($this->_path . 'views/css/custom.css');
        $this->context->controller->addJS($this->_path . 'views/js/leaflet.js');
        $this->context->controller->addJS($this->_path . 'views/js/itella-mapping.js');
        $this->context->controller->addJS($this->_path . 'views/js/front.js');
      }
    }
  }

  public function hookDisplayBeforeCarrier($params)
  {
    //
  }

  public function hookDisplayCarrierList($params)
  {
    return $this->hookDisplayCarrierExtraContent($params);
  }

  public function hookDisplayCarrierExtraContent($params)
  {

    if (version_compare(_PS_VERSION_, '1.7', '>=')) {
      $id_carrier = $params['carrier']['id'];
      $template = 'views/templates/hook/pickup.tpl';
    } else {
      $id_carrier = $params['cart']->id_carrier;
      $template = 'views/templates/hook/pickup_16.tpl';
    }

    if ($id_carrier != (int) (Configuration::get('ITELLA_PICKUP_POINT_ID')))
      return '';


    $ps_version = substr(_PS_VERSION_, 0, 3);

    // check if point was selected previously
    $selected_pickup_point_id = '';
    if (isset($params['cart']->id)) {
      $sql = 'SELECT id_pickup_point FROM ' . _DB_PREFIX_ . 'itella_cart WHERE id_cart="' . $params['cart']->id . '"';
      $selected_pickup_point_id = Db::getInstance()->getValue($sql);
    }

    $address = new Address($params['cart']->id_address_delivery);
    $country = new Country();

    global $smarty;
    $smarty->assign(
      array(
        'pickup_points' => $this->loadItellaLocations($country->getIsoById($address->id_country)),
        'selected' => $selected_pickup_point_id ? $selected_pickup_point_id : '',
        'itella_send_to' => json_encode($country->getIsoById($address->id_country))
      )
    );

    return $this->display(__FILE__, $template);
  }

  public function hookOrderConfirmation($params)
  {
    // $order = $params['order'];
    // if ($order->id_carrier != (int) (Configuration::get('ITELLA_PICKUP_POINT_ID'))) {
    //   return '';
    // }

    // $sql = 'SELECT id_pickup_point FROM ' . _DB_PREFIX_ . 'itella_cart WHERE id_cart="' . $order->id_cart . '"';
    // $selected_pickup = Db::getInstance()->executeS($sql)[0];

    // $this->context->smarty->assign(array(
    //   'itella_order' => json_encode($params),
    //   'itella_selected_pickup' => $selected_pickup ? $selected_pickup['id_pickup_point'] : '',
    // ));

    // return $this->display(__FILE__, 'confirmation.tpl');
    return '';
  }

  public function hookDisplayOrderDetail($params)
  {
    // $order = $params['order'];
    // if ($order->id_carrier != (int) (Configuration::get('ITELLA_PICKUP_POINT_ID'))) {
    //   return '';
    // }

    // $sql = 'SELECT id_pickup_point FROM ' . _DB_PREFIX_ . 'itella_cart WHERE id_cart="' . $order->id_cart . '"';
    // $selected_pickup = Db::getInstance()->executeS($sql);

    // $this->context->smarty->assign(array(
    //   'itella_order' => json_encode($params),
    //   'itella_obj' => json_encode($sql),
    //   'itella_selected_pickup' => $selected_pickup ? $selected_pickup[0]['id_pickup_point'] : '',
    // ));

    // return $this->display(__FILE__, 'confirmation.tpl');

    return '';
  }

  public function hookActionAdminControllerSetMedia($params)
  {
    if (version_compare(_PS_VERSION_, '1.7', '>=')) {
      if (Tools::getValue('configure') == $this->name || Tools::getValue('controller') == 'AdminOrders') {
        Media::addJsDef(array(
          'itellaMassLabelTitle' => $this->l("Print Itella labels"),
          'itellaNoOrdersWarn' => $this->l("No orders selected"),
          'itellaMassLabelUrl' => self::useHttps($this->context->link->getModuleLink($this->name, "ajax", array("action" => "massgenlabel")))
        ));
        $this->context->controller->addJs($this->_path . 'views/js/itella_admin.js');
      }
    }
  }

  public function hookDisplayBackOfficeHeader($params)
  {
    if (version_compare(_PS_VERSION_, '1.7', '<')) {
      return '
        <script type="text/javascript">
          var itellaNoOrdersWarn = "' . $this->l("No orders selected") . '";
          var itellaMassLabelTitle = "' . $this->l("Print Itella labels") . '";
          var itellaMassLabelUrl = "' . self::useHttps($this->context->link->getModuleLink($this->name, "ajax", array("action" => "massgenlabel"))) . '";
        </script>
        <script type="text/javascript" src="' . (__PS_BASE_URI__) . 'modules/' . $this->name . '/views/js/itella_admin.js"></script>
      ';
    }
  }

  /**
   * Checks if supplied id belongs to Itella active carriers, if second argument is TRUE checks to reference id
   * 
   * @param int $id_carrier
   * @param bool $check_reference
   */
  public function isItellaCarrier($id_carrier, $check_reference = false)
  {
    $itella_carriers = array();
    foreach ($this->_carriers as $key => $value) {
      if ($check_reference) {
        $key .= '_REFERENCE';
      }
      $itella_carriers[] = (int) Configuration::get($key);
    }
    return in_array((int) $id_carrier, $itella_carriers);
  }

  public function hookDisplayAdminOrder($params)
  {
    $order = new Order((int) $params['id_order']);
    $carrier = new Carrier($order->id_carrier);

    if (!$this->isItellaCarrier($carrier->id_reference, true)) {
      return '';
    }

    self::checkForClass('ItellaCart');

    $itellaCart = new ItellaCart();
    $itella_cart_info = $itellaCart->getOrderItellaCartInfo($order->id_cart);

    if (!$itella_cart_info) {
      $itellaCart->saveOrder($order);
      $itella_cart_info = $itellaCart->getOrderItellaCartInfo($order->id_cart);
    }

    // check that cart info matches selected method
    if ($itella_cart_info['is_pickup'] == 0 && $carrier->id_reference == Configuration::get('ITELLA_PICKUP_POINT_ID_REFERENCE')) {
      $itellaCart->updateCarrier($order->id_cart, 1); // set to pickuppoint
      $itella_cart_info['is_pickup'] = 1;
    } elseif ($itella_cart_info['is_pickup'] == 1 && $carrier->id_reference == Configuration::get('ITELLA_COURIER_ID_REFERENCE')) {
      $itellaCart->updateCarrier($order->id_cart, 0); // set to courier
      $itella_cart_info['is_pickup'] = 0;
    }

    $label_url = '';
    if (file_exists(_PS_MODULE_DIR_ . $this->name . '/pdf/' . $order->id . '.pdf')) {
      $label_url = Tools::getHttpHost(true) . __PS_BASE_URI__ . '/modules/' . $this->name . '/pdf/' . $order->id . '.pdf';
    }

    $address = new Address($order->id_address_delivery);
    $country = new Country();
    $pickup_points = json_decode($this->loadItellaLocations($country->getIsoById($address->id_country)), true);

    usort($pickup_points, function ($a, $b) {
      if ($a['address']['municipality'] == $b['address']['municipality']) {
        return ($a['publicName'] < $b['publicName']) ? -1 : 1;
      }
      return ($a['address']['municipality'] < $b['address']['municipality']) ? -1 : 1;
    });

    $this->smarty->assign(array(
      'itella_params' => json_encode(array(
        'order_car' => $carrier->id_reference,
        'config_car' => Configuration::get('ITELLA_PICKUP_POINT_ID_REFERENCE'),
        'current_car' => Configuration::get('ITELLA_PICKUP_POINT_ID')
      )),
      'label_url' => $label_url,
      'base_label_url' => Tools::getHttpHost(true) . __PS_BASE_URI__ . '/modules/' . $this->name . '/pdf/',
      'orderItellaCartInfo' => $itella_cart_info,
      'order_id' => $order->id,
      'cart_id' => $order->id_cart,
      'cod_amount' => $order->total_paid_tax_incl,
      'itella_error' => ($itella_cart_info['error'] != '' ? $this->displayError($itella_cart_info['error']) : false),
      'itella_pickup_points' => $pickup_points,
      'itella_module_url' => $this->context->link->getModuleLink($this->name, 'ajax', array('action' => 'savecart')),
      'itella_generate_label_url' => $this->context->link->getModuleLink($this->name, 'ajax', array('action' => 'genlabel', 'id_order' => $order->id, 'token' => Tools::getToken())),
      'itella_print_label_url' => $this->context->link->getModuleLink($this->name, 'ajax', array('action' => 'printLabel', 'id_order' => $order->id, 'token' => Tools::getToken())),
    ));

    return $this->display(__FILE__, 'views/templates/admin/blockinorder.tpl');
  }

  public function updateLocations($forced = false)
  {
    $dir = _PS_MODULE_DIR_ . $this->name . "/locations/";
    $last_update = Configuration::getGlobalValue('ITELLA_LAST_UPDATE');
    if ($last_update === false || !$this->locationsFilesExist($dir)) {
      $last_update = 0;
    }

    // update once per day
    if ($last_update == 0 || ($last_update + 24 * 3600) < time() || $forced) {
      require_once _PS_MODULE_DIR_ . 'itellashipping/vendor/itella-api/vendor/autoload.php';
      Configuration::updateGlobalValue('ITELLA_LAST_UPDATE', time());
      $loc = new \Mijora\Itella\Locations\PickupPoints('https://locationservice.posti.com/api/2/location');

      foreach (array('LT', 'LV', 'EE', 'FI') as $country) {
        $itellaLoc = $loc->getLocationsByCountry($country);
        $loc->saveLocationsToJSONFile($dir . 'locations_' . $country . '.json', json_encode($itellaLoc));
      }
    }
  }

  private function locationsFilesExist($dir)
  {
    foreach (array('LT', 'LV', 'EE', 'FI') as $country) {
      if (!is_file($dir . 'locations_' . $country . '.json')) {
        return false;
      }
    }

    return true;
  }

  /**
   * NO LONGER USED
   * Generates tracking number for Itella. Automatically increases counter
   */
  public function getLabel()
  {
    $code = str_pad(Configuration::get('ITELLA_LABEL_CODE'), 4, '0', STR_PAD_LEFT);
    $contract = str_pad(Configuration::get('ITELLA_CONTRACT'), 6, '0', STR_PAD_LEFT);
    $counter = Configuration::get('ITELLA_COUNTER');

    Configuration::updateValue('ITELLA_COUNTER', $counter + 1);
    $counter = str_pad($counter, 11, '0', STR_PAD_LEFT);

    return $code . $contract . $counter;
  }


  // Static functions
  public static function getAllCarriers($id_only = false)
  {
    $carriers = Carrier::getCarriers(
      Context::getContext()->language->id,
      true,
      false,
      false,
      NULL,
      PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE
    );
    if ($id_only) {
      $id_list = array();
      foreach ($carriers as $carrier)
        $id_list[] = $carrier['id_carrier'];
      return $id_list;
    }

    return $carriers;
  }

  public static function checkForClass($className)
  {
    if (!class_exists($className)) {
      if (isset(self::$_classMap[$className])) {
        require_once _PS_MODULE_DIR_ . self::$_name . '/' . self::$_classMap[$className];
      }
    }
  }

  public static function useHttps($url)
  {
    if (empty($_SERVER['HTTPS'])) {
      return $url;
    } elseif ($_SERVER['HTTPS'] == "on") {
      return str_replace('http://', 'https://', $url);
    } else {
      return $url;
    }
  }
}
