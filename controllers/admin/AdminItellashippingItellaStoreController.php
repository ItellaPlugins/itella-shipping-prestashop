<?php

use PrestaShop\PrestaShop\Adapter\Entity\PrestaShopException;

class AdminItellashippingItellaStoreController extends ModuleAdminController
{
  const DEFAULT_PICK_START = '8:00';

  const DEFAULT_PICK_FINISH = '17:00';

  const DEFAULT_COMPANY_COUNTRY_CODE = 'LT';

  /** @var bool Is bootstrap used */
  public $bootstrap = true;

  /**
   * AdminOmnivaltShippingStoresController class constructor
   *
   * @throws PrestaShopException
   * @throws SmartyException
   */
  public function __construct()
  {
    $this->className = 'ItellaStore';
    $this->table = 'itella_store';
    $this->identifier = 'id_itella_store';

    parent::__construct();

    $this->displayMenu();
  }

  /**
   * @throws SmartyException
   */
  private function displayMenu()
  {
    if (!$this->module instanceof ItellaShipping) {
      return;
    }

    $this->content .= $this->module->displayMenu();
  }

  private function initForm()
  {
    $this->fields_form = array(
      'legend' => array(
        'title' => $this->l('Store address'),
        'icon' => 'icon-map-marker'
      ),
      'input' => array(
        array(
          'type' => 'text',
          'label' => $this->l('Title'),
          'name' => 'title',
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Address'),
          'name' => 'address',
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Postcode'),
          'name' => 'postcode',
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('City'),
          'name' => 'city',
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Country Code'),
          'name' => 'country_code',
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Phone'),
          'name' => 'phone',
          'required' => true
        ),
        // array(
        //   'type' => 'text',
        //   'label' => $this->l('Pick Start'),
        //   'name' => 'pick_start',
        //   'required' => true
        // ),
        // array(
        //   'type' => 'text',
        //   'label' => $this->l('Pick Finish'),
        //   'name' => 'pick_finish',
        //   'required' => true
        // ),
        array(
          'type' => 'hidden',
          'name' => 'id_shop',
          'required' => true
        ),
        array(
          'type' => 'switch',
          'is_boold' => true,
          'label' => $this->l('Active'),
          'name' => 'active',
          'values' => array(
            array(
              'value' => 1,
              'label' => $this->l('Enabled')
            ),
            array(
              'value' => 0,
              'label' => $this->l('Disabled')
            )
          ),
        ),
      ),
      'submit' => array(
        'title' => $this->l('Save')
      )
    );

    if (!$this->object || $this->object->id === null) {
      $country_id = Configuration::get('PS_COUNTRY_DEFAULT');
      $country = null;

      if ($country_id && $country_id > 0) {
        $country = new Country($country_id);
      }

      $country_code = isset($country->iso_code) ? $country->iso_code : self::DEFAULT_COMPANY_COUNTRY_CODE;

      $this->fields_value['pick_start'] = self::DEFAULT_PICK_START;
      $this->fields_value['pick_finish'] = self::DEFAULT_PICK_FINISH;
      $this->fields_value['country_code'] = $country_code;
      $this->fields_value['id_shop'] = $this->context->shop->id;
      $this->fields_value['active'] = true;
    }
  }

  public function renderForm()
  {
    $this->initForm();

    return parent::renderForm();
  }

  public function getShopNameById($id)
  {
    $shop = new Shop($id);
    return $shop->name;
  }

  private function initList()
  {
    $this->fields_list = array(
      'title' => array(
        'type' => 'text',
        'title' => $this->l('Title'),
        'align' => 'center',
        'havingFilter' => false
      ),
      'id_shop' => array(
        'type' => 'text',
        'title' => $this->l('Shop'),
        'align' => 'center',
        'search' => false,
        'havingFilter' => false,
        'callback' => 'getShopNameById'
      ),
      'city' => array(
        'title' => $this->l('City'),
        'align' => 'center'
      ),
      'address' => array(
        'title' => $this->l('Address'),
        'align' => 'center'
      ),
      // 'pick_start' => array(
      //   'title' => $this->l('Pick start'),
      //   'align' => 'center',
      //   'search' => false,
      //   'orderby' => false,
      //   'havingFilter' => false,
      // ),
      // 'pick_finish' => array(
      //   'title' => $this->l('Pick finish'),
      //   'align' => 'center',
      //   'search' => false,
      //   'orderby' => false,
      //   'havingFilter' => false,
      // ),
      'is_default' => array(
        'type' => 'bool',
        'title' => $this->l('Default'),
        'align' => 'center',
        'active' => 'isDefault',
        'orderby' => false,
        'havingFilter' => false,
      ),
      'active' => array(
        'type' => 'bool',
        'title' => $this->l('Active'),
        'align' => 'center',
        'active' => 'status',
        'orderby' => false,
        'havingFilter' => false,
      )
    );

    $this->actions = array('edit', 'delete');

    $this->bulk_actions = array(
      'delete' => array(
        'text' => $this->l('Delete selected'),
        'confirm' => $this->l('Delete selected items?'),
        'icon' => 'icon-trash'
      )
    );
  }

  public function renderList()
  {
    $this->initList();

    switch (Shop::getContext()) {
      case Shop::CONTEXT_GROUP:
        $this->_where = ' AND a.`id_shop` IN(' . implode(',', Shop::getContextListShopID()) . ')';
        break;

      case Shop::CONTEXT_SHOP:
        $this->_where = Shop::addSqlRestrictionOnLang('a');
        break;

      default:
        break;
    }
    $this->_use_found_rows = false;

    return parent::renderList();
  }

  public function postProcess()
  {
    if (isset($_GET['add' . $this->table])) {
      if (Shop::getContext() !== Shop::CONTEXT_SHOP) {
        Tools::redirectAdmin(self::$currentIndex . '&adderrors&token=' . $this->token);
      }
    } elseif (isset($_GET['adderrors'])) {
      $this->errors[] = $this->l('Please select a shop first!');
    } elseif (isset($_GET['isDefault' . $this->table])) {
      $this->processIsDefault();
    } else {
      parent::postProcess();
    }
  }

  public function processStatus()
  {
    $id = (int) Tools::getValue('id_' . $this->table);
    $store = new ItellaStore($id);
    if ($store->is_default) {
      $this->errors[] = $store->title . ' ' . $this->l('Address is set as default');
      return false;
    }
    return parent::processStatus();
  }

  public function processIsDefault()
  {
    $id = (int) Tools::getValue('id_' . $this->table);
    $store = new ItellaStore($id);
    if (!$store->active) {
      $this->errors[] = $store->title . ' ' . $this->l('To set address as default it must be active');
      return false;
    }
    $store->is_default = $store->is_default ? 0 : 1;

    // set all from same shop as not default
    $sql = 'UPDATE `' . _DB_PREFIX_ . $this->table . '` SET `is_default` = 0 WHERE id_shop = ' . $store->id_shop;
    Db::getInstance()->execute($sql, false);
    $store->save();
  }
}
