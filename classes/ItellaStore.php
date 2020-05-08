<?php

class ItellaStore extends ObjectModel
{
  public $id_itella_store;

  public $title;

  public $postcode;

  public $city;

  public $phone;

  public $country_code;

  public $address;

  public $pick_start;

  public $pick_finish;

  public $id_shop;

  public $is_default;

  public $active;

  /** @var array Class variables and their validation types */
  public static $definition = array(
    'primary' => 'id_itella_store',
    'table' => 'itella_store',
    'fields' => array(
      'title' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true),
      'postcode' => array('type' => self::TYPE_STRING, 'validate' => 'isPostCode', 'required' => true),
      'city' => array('type' => self::TYPE_STRING, 'validate' => 'isCityName', 'required' => true),
      'phone' => array('type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'required' => true),
      'country_code' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true),
      'address' => array('type' => self::TYPE_STRING, 'validate' => 'isAddress', 'required' => true),
      'pick_start' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => false),
      'pick_finish' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => false),
      'id_shop' => array('type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true),
      'is_default' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
      'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true),
    )
  );


  public function __construct($id_name_table = null, $id_lang = null, $id_shop = null)
  {
    parent::__construct($id_name_table, $id_lang, $id_shop);
  }

  public static function getStores($activeOnly = false)
  {
    return Db::getInstance()->executeS(
      '
      SELECT *
      FROM `' . _DB_PREFIX_ . pSQL(self::$definition['table']) . '`
      WHERE id_shop = ' . Context::getContext()->shop->id
        . ($activeOnly ? ' AND `active`=1' : '')
    );
  }

  public static function hasDefault()
  {
    return Db::getInstance()->getValue(
      '
      SELECT 1
      FROM `' . _DB_PREFIX_ . pSQL(self::$definition['table']) . '`
      WHERE id_shop = ' . Context::getContext()->shop->id . ' AND is_default=1'
    );
  }

  public function getFormatedAddress()
  {
    return $this->address . ', ' . $this->postcode . ' ' . $this->city . ', ' . $this->country_code;
  }

  public function save($null_values = false, $auto_date = true)
  {
    return parent::save($null_values, $auto_date);
  }

  public function add($auto_date = true, $null_values = false)
  {
    //check for default if none exists make this one default
    if ($this->active && !self::hasDefault()) {
      $this->is_default = 1;
    }

    return parent::add($auto_date, $null_values);
  }

  public function update($null_values = false)
  {
    return parent::update($null_values);
  }
}
