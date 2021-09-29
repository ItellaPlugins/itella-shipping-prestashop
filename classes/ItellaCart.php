<?php
require_once _PS_MODULE_DIR_ . 'itellashipping/vendor/itella-api/vendor/autoload.php';

use Mijora\Itella\ItellaException;
use Mijora\Itella\Helper;
use Mijora\Itella\Shipment\Party;
use Mijora\Itella\Shipment\GoodsItem;
use Mijora\Itella\Shipment\AdditionalService;
use Mijora\Itella\Shipment\Shipment;

class ItellaCart
{

  protected $db;
  protected $module;

  public function __construct()
  {
    $this->db = DB::getInstance();
    $this->module = new ItellaShipping();
  }

  public function getOrderItellaCartInfo($id_cart)
  {
    $sql = "SELECT * FROM " . _DB_PREFIX_ . "itella_cart WHERE id_cart=$id_cart";
    return $this->db->getRow($sql);
  }

  public function saveOrder($orderObj)
  {
    $cartObj = new Cart($orderObj->id_cart);
    $carrier = new Carrier($orderObj->id_carrier);
    $total_weight = $cartObj->getTotalWeight();
    $cod_modules = explode(',', Configuration::get('ITELLA_COD_MODULES'));
    $is_pickup = $carrier->id_reference == Configuration::get('ITELLA_PICKUP_POINT_ID_REFERENCE') ? 1 : 0;
    $cart = array(
      'id_cart' => $orderObj->id_cart,
      'packs' => 1,
      'weight' => $total_weight <= 0 ? 1 : $total_weight,
      'is_cod' => in_array($orderObj->module, $cod_modules) ? 1 : 0,
      'cod_amount' => $orderObj->total_paid_tax_incl,
      'is_pickup' => $is_pickup,
      'label_number' => '',
      'is_oversized' => 0,
      'is_call_before_delivery' => 0,
      'is_fragile' => 0,
      'error' => NULL,
      'id_itella_manifest' => NULL
    );

    $this->db->insert('itella_cart', $cart, true);
  }

  public function updateCarrier($id_cart, $is_pickup)
  {
    if (Db::getInstance()->getValue("SELECT 1 FROM " . _DB_PREFIX_ . "itella_cart WHERE id_cart = " . pSQL($id_cart))) {
      $this->db->execute("UPDATE " . _DB_PREFIX_ . "itella_cart SET `is_pickup` = " . pSQL($is_pickup) . " WHERE id_cart = " . pSQL($id_cart), false);
    }
  }

  public function updateItellaCart()
  {
    if (empty($_POST)) {
      return array('errors' => array($this->module->l('No data supplied to update ItellaCart', 'ItellaCart')));
    }

    $id_order = Tools::getValue('id_order', NULL);
    $id_cart = Tools::getValue('id_cart', NULL);
    $packs = Tools::getValue('packs', 1);
    $weight = Tools::getValue('weight', 1);
    $volume = Tools::getValue('volume', 0);
    $is_cod = Tools::getValue('is_cod', 0);
    $cod_amount = Tools::getValue('cod_amount', NULL);
    $is_pickup = Tools::getValue('is_pickup', NULL);
    $id_pickup_point = Tools::getValue('id_pickup_point', NULL);
    $itella_extra = Tools::getValue('itella_extra', NULL);
    $itella_comment = Tools::getValue('itella_comment', NULL);

    $weight = str_replace(',', '.', $weight);
    $volume = str_replace(',', '.', $volume);

    $errors = array();
    if (empty($id_cart) || !Validate::isUnsignedInt($id_cart)) {
      $errors[] = $this->module->l('Invalid cart ID', 'ItellaCart');
    }
    if (empty($packs) || !Validate::isUnsignedInt($packs) || (int) $packs < 1) {
      $errors[] = $this->module->l('Invalid packs number', 'ItellaCart');
    }
    if (!Validate::isUnsignedFloat($weight)) {
      $errors[] = $this->module->l('Invalid weight', 'ItellaCart');
    }
    if (!Validate::isUnsignedFloat($volume)) {
      $errors[] = $this->module->l('Invalid volume', 'ItellaCart');
    }

    if (!in_array($is_cod, array('0', '1'))) {
      $errors[] = $this->module->l('Invalid COD value', 'ItellaCart');
    }

    if ($is_cod == '1' && (empty($cod_amount) || !Validate::isFloat($cod_amount))) {
      $errors[] = $this->module->l('Invalid COD amount', 'ItellaCart') . $cod_amount . ' ' . $is_cod;
    }

    if (!in_array($is_pickup, array('0', '1'))) {
      $errors[] = $this->module->l('Invalid carrier value', 'ItellaCart');
    }

    if ($is_pickup == '1' && (!$this->module->isLocation($id_pickup_point))) {
      $errors[] = $this->module->l('Invalid pickup point ID', 'ItellaCart');
    }

    // terminate if there is errors at this point
    if (!empty($errors)) {
      return array('errors' => $errors);
    }
 
    $cart = array(
      'packs' => $is_pickup == 0 ? pSQL($packs) : 1,
      'weight' => pSQL($weight),
      'volume' => pSQL($volume),
      'is_cod' => pSQL($is_cod),
      'cod_amount' => pSQL($cod_amount),
      'is_pickup' => pSQL($is_pickup),
      'label_number' => '',
      'is_oversized' => 0,
      'is_call_before_delivery' => 0,
      'is_fragile' => 0,
      'error' => NULL,
      'comment' => pSQL($itella_comment),
      'id_itella_manifest' => NULL
    );

    if ($is_pickup == '1') {
      $cart['id_pickup_point'] = pSQL($id_pickup_point);
    }

    // check for extra services
    if (is_array($itella_extra)) {
      foreach ($itella_extra as $extra) {
        if ($is_pickup == 0 && in_array($extra, array('is_oversized', 'is_call_before_delivery', 'is_fragile'))) {
          $cart[$extra] = '1';
        }
      }
    }

    if (!Db::getInstance()->getValue("SELECT 1 FROM " . _DB_PREFIX_ . "itella_cart WHERE id_cart = " . pSQL($id_cart))) {
      $cart['id_cart'] = pSQL($id_cart);
      $result = Db::getInstance()->insert('itella_cart', $cart, true, false);
    } else {
      $result = Db::getInstance()->update('itella_cart', $cart, 'id_cart = ' . pSQL($id_cart), 0, true, false);
    }

    if (!$result) {
      return array('errors' => array($this->module->l('Failed to add/edit ItellaCart table', 'ItellaCart')), 'data' => $cart, 'id_order' => $id_order);
    }
    
    // reset tracking number in presta order
    $order = new Order((int) $id_order);
    $order->setWsShippingNumber('');

    return array('success' => $this->module->l('ItellaCart information updated', 'ItellaCart'), 'data' => $cart, 'id_order' => $id_order, 'id_cart' => $id_cart);
  }

  public function updateItellaCartTrackNumber($id_cart, $track_num, $reset_error = true)
  {
    if (Db::getInstance()->getValue("SELECT 1 FROM " . _DB_PREFIX_ . "itella_cart WHERE id_cart = " . pSQL($id_cart))) {
      $this->db->execute("UPDATE " . _DB_PREFIX_ . "itella_cart SET `label_number` = '" . pSQL($track_num) . "'" . ($reset_error ? ", `error` = ''" : "") . " WHERE id_cart = " . pSQL($id_cart), false);
    }
  }

  public function saveError($id_cart, $error_msg)
  {
    if (Db::getInstance()->getValue("SELECT 1 FROM " . _DB_PREFIX_ . "itella_cart WHERE id_cart = " . pSQL($id_cart))) {
      $this->db->execute("UPDATE " . _DB_PREFIX_ . "itella_cart SET `error` = '" . pSQL($error_msg) . "' WHERE id_cart = " . pSQL($id_cart), false);
    }
  }

  public function registerLabel($id_order)
  {
    if (!$id_order) {
      return array('errors' => $this->module->l('Order ID missing', 'ItellaCart'));
    }

    $order = new Order((int) $id_order);
    $address = new Address((int) $order->id_address_delivery);
    $customer = new Customer((int) $order->id_customer);

    $data = $this->getOrderItellaCartInfo($order->id_cart);
    // $data['weight'] = $data['weight'] > 0 ? $data['weight'] : '';
    // $data['volume'] = $data['volume'] > 0 ? $data['volume'] : '';

    $test_mode = (Configuration::get('ITELLA_TEST_MODE') == 1);
    $token_time = Configuration::get('ITELLA_TOKEN_TIME');
    $token = Configuration::get('ITELLA_TOKEN');

    try {
      //throw new ItellaException("TESTING ORDER: " . $id_order);
      // Determine product code
      $product_code = Shipment::PRODUCT_COURIER;
      if ($data['is_pickup'] == 1) {
        $product_code = Shipment::PRODUCT_PICKUP;
      }

      // Create and configure sender
      $sender = new Party(Party::ROLE_SENDER);
      $sender
        ->setContract(Configuration::get('ITELLA_API_CONTRACT_' . $product_code)) // supplied by Itella with user and pass
        ->setName1(Configuration::get('ITELLA_SENDER_NAME'))
        ->setStreet1(Configuration::get('ITELLA_SENDER_STREET'))
        ->setPostCode(Configuration::get('ITELLA_SENDER_POSTCODE'))
        ->setCity(Configuration::get('ITELLA_SENDER_CITY'))
        ->setCountryCode(Configuration::get('ITELLA_SENDER_COUNTRY_CODE'))
        ->setContactMobile(Configuration::get('ITELLA_SENDER_PHONE'))
        ->setContactEmail(Configuration::get('ITELLA_SENDER_EMAIL'));

      $country = new Country($address->id_country);
      // Create and configure receiver
      $phone = $address->phone;
      if ($address->phone_mobile) {
        $phone = $address->phone_mobile;
      }
      $receiver = new Party(Party::ROLE_RECEIVER);
      $receiver
        ->setName1($address->firstname . ' ' . $address->lastname)
        ->setStreet1($address->address1 . $address->address2)
        ->setPostCode($address->postcode)
        ->setCity($address->city)
        ->setCountryCode($country->iso_code)
        ->setContactMobile($phone)
        ->setContactEmail($customer->email);

      $items = array();
      $weight = $data['packs'] > 1 ? (float) $data['weight'] / $data['packs'] : (float) $data['weight'];
      for ($total = 0; $total < $data['packs']; $total++) {
        $item = new GoodsItem();
        $item
          ->setGrossWeight($weight) // kg
          //->setVolume(0.1) // m3
        ;
        $items[] = $item;
      }

      // Create manualy assigned additional services (multiparcel and pickup point services auto created by lib)
      $extra = array();
      if ($data['is_cod']) {
        $extra[] = new AdditionalService(AdditionalService::COD, array(
          'amount' => $data['cod_amount'],
          'account' => Configuration::get('ITELLA_COD_IBAN'),
          'reference' => Helper::generateCODReference($id_order),
          'codbic' => Configuration::get('ITELLA_COD_BIC')
        ));
      }
      if ($data['is_fragile']) {
        $extra[] = new AdditionalService(AdditionalService::FRAGILE);
      }
      if ($data['is_oversized']) {
        $extra[] = new AdditionalService(AdditionalService::OVERSIZED);
      }
      if ($data['is_call_before_delivery']) {
        $extra[] = new AdditionalService(AdditionalService::CALL_BEFORE_DELIVERY);
      }

      // Create shipment object
      $shipment = new Shipment(Configuration::get('ITELLA_API_USER_' . $product_code), Configuration::get('ITELLA_API_PASS_' . $product_code));
      $shipment
        ->setProductCode($product_code) // should always be set first
        ->setShipmentNumber($id_order) // shipment number 
        //->setShipmentDateTime(date('c')) // when package will be ready (just use current time)
        ->setSenderParty($sender) // Sender class object
        ->setReceiverParty($receiver) // Receiver class object
        ->addAdditionalServices($extra) // set additional services
        ->addGoodsItems($items);
      
      if (isset($data['comment']) && !empty($data['comment'])) {
        $shipment->setComment($data['comment']);
      }

      if ($product_code == Shipment::PRODUCT_PICKUP) {
        $shipment->setPickupPoint($data['id_pickup_point']);
      }

      // Register shipment
      $tracking_number = $shipment->registerShipment();
      // update ItellaCart with tracking nunmber
      $this->updateItellaCartTrackNumber($data['id_cart'], $tracking_number);
      // save tracking number(s) to order carrier as well
      $order->setWsShippingNumber($tracking_number);

      return array('success' => 'Order ID: ' . $id_order . ' registered.', 'tracking_number' => $tracking_number);
    } catch (ItellaException $e) {
      $this->saveError($data['id_cart'], $e->getMessage());
      return array('errors' => 'Order ID: ' . $id_order . ' Exception: ' . $e->getMessage());
    } catch (\Exception $e) {
      return array('errors' => 'Order ID: ' . $id_order . ' Exception: ' . $e->getMessage());
    }
  }
}
