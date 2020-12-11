<?php

require_once _PS_MODULE_DIR_ . 'itellashipping/vendor/itella-api/vendor/autoload.php';

use Mijora\Itella\Pdf\Manifest;

class ItellaManifest extends ObjectModel
{
  public $id_itella_manifest;

  public $id_shop;

  public $date_add;

  private $_module;

  /** @var array Class variables and their validation types */
  public static $definition = array(
    'primary' => 'id_itella_manifest',
    'table' => 'itella_manifest',
    'fields' => array(
      'id_shop' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
      'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
    )
  );

  public function __construct($id_name_table = null, $id_lang = null, $id_shop = null)
  {
    //Shop::addTableAssociation('itella_store', array('type' => 'shop'));
    parent::__construct($id_name_table, $id_lang, $id_shop);

    $this->_module = new ItellaShipping();
  }

  private function buildManifest()
  {
    $sql = '
      SELECT * FROM `' . _DB_PREFIX_ . 'itella_cart` ic
      WHERE id_itella_manifest = ' . $this->id_itella_manifest;
    $result = DB::getInstance()->executeS($sql);
    //echo json_encode($result);
    
    $timestamp = strtotime($this->date_add);
    $pdf = new Manifest($timestamp);
    $pdf
      ->setStrings(array(
        'sender_address' => $this->_module->l('Sender address:', 'ItellaManifest'),
        'nr' => $this->_module->l('Nr.', 'ItellaManifest'),
        'track_num' => $this->_module->l('Tracking number', 'ItellaManifest'),
        'date' => $this->_module->l('Date', 'ItellaManifest'),
        'amount' => $this->_module->l('Amount', 'ItellaManifest'),
        'weight' => $this->_module->l('Weight (kg)', 'ItellaManifest'),
        'delivery_address' => $this->_module->l('Delivery address', 'ItellaManifest'),
        'courier' => $this->_module->l('Courier', 'ItellaManifest'),
        'sender' => $this->_module->l('Sender', 'ItellaManifest'),
        'name_lastname_signature' => $this->_module->l('name, lastname, signature', 'ItellaManifest'),
      ))
      ->setSenderName(Configuration::get('ITELLA_SENDER_NAME'))
      ->setSenderAddress(Configuration::get('ITELLA_SENDER_STREET'))
      ->setSenderPostCode(Configuration::get('ITELLA_SENDER_POSTCODE'))
      ->setSenderCity(Configuration::get('ITELLA_SENDER_CITY'))
      ->setSenderCountry(Configuration::get('ITELLA_SENDER_COUNTRY_CODE'));

    foreach ($result as $row) {
      $item = [array(
        'track_num' => implode(' ', explode(',', $row['label_number'])),
        'amount' => $row['packs'],
        'weight' => $row['weight'],
        'delivery_address' => $this->generateDeliveryAddress($row)
      )];
      $pdf->addItem($item);
    }

    return $pdf;
  }

  public function printPdf()
  {
    $pdf = $this->buildManifest();
    $pdf->printManifest('manifest.pdf');
  }

  public function getManifestBase64()
  {
    $pdf = $this->buildManifest();
    return $pdf->setToString(true)->setBase64(true)->printManifest('manifest.pdf');
  }

  public function getManifestString()
  {
    $pdf = $this->buildManifest();
    return $pdf->setToString(true)->setBase64(false)->printManifest('manifest.pdf');
  }

  protected function generateDeliveryAddress($itella_cart)
  {
    $cart = new Cart($itella_cart['id_cart']);
    $address = new Address($cart->id_address_delivery);
    $country = new Country();
    $country_code = $country->getIsoById($address->id_country);

    switch ($itella_cart['is_pickup']) {
      case '1':
        $loc = $this->_module->isLocation($itella_cart['id_pickup_point'], $country_code);
        $result = $address->firstname . ' ' . $address->lastname . ',<br/>';
        $address = $loc['address'];
        $result .= $this->_module->l('Pickup point:', 'ItellaManifest') . '<br/>' . $loc['labelName'] . '<br/>'
          . $address['streetName'] . ' '
          . $address['streetNumber'] . ', '
          . $address['postalCode'] . ' '
          . (empty($address['postalCodeName']) ? $address['municipality'] : $address['postalCodeName']) . ', '
          . $loc['countryCode'];
        return $result;
        break;

      default:
        // By default assume its a courier so address will be delivery address
        return $address->firstname . ' ' . $address->lastname . ', ' . $address->address1 . ' ' . $address->postcode . ' ' . $address->city . ', ' . $country_code;
        break;
    }
  }

  public function toString()
  {
    return 'ID: ' . $this->id_itella_manifest . ' | ID_SHOP: ' . $this->id_shop . ' | DATE: ' . $this->date_add;
  }

  public function save($null_values = false, $auto_date = true)
  {
    return parent::save($null_values, $auto_date);
  }

  public function add($auto_date = true, $null_values = false)
  {
    return parent::add($auto_date, $null_values);
  }

  public function update($null_values = false)
  {
    return parent::update($null_values);
  }
}
