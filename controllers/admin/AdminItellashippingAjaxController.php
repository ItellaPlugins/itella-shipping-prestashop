<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

require_once _PS_MODULE_DIR_ . 'itellashipping/vendor/itella-api/vendor/autoload.php';

use Mijora\Itella\ItellaException;
use Mijora\Itella\Helper as ItellaHelper;
use Mijora\Itella\Shipment\Party;
use Mijora\Itella\Shipment\GoodsItem;
use Mijora\Itella\Shipment\AdditionalService;
use Mijora\Itella\Shipment\Shipment;

class AdminItellashippingAjaxController extends ModuleAdminController
{

  public function __construct()
  {
    if (!Context::getContext()->employee->isLoggedBack()) {
      exit('Restricted.');
    }

    parent::__construct();

    $this->parseActions();
  }

  private function parseActions()
  {
    $action = Tools::getValue('action');

    switch ($action) {
      case 'savecart':
        $this->saveCart();
        break;
      case 'printLabel':
        $id_order = Tools::getValue('id_order', NULL);
        $this->printLabel($id_order);
        break;
      case 'genlabel':
        $id_order = Tools::getValue('id_order', NULL);
        try {
          echo $this->generateLabel($id_order);
        } catch (\Exception $th) {
          $error_msg = $th->getMessage();
          ItellaShipping::checkForClass('ItellaCart');
          $itellaCart = new ItellaCart();
          $itellaCart->saveError($id_order, $error_msg);
          echo json_encode(array('errors' => $error_msg));
        }
        break;
      case 'massgenlabel':
        $this->massGenerateLabel();
        break;
      case 'devtest':
        $this->devtest();
        break;
    }
    die();
  }

  protected function devtest()
  {
    //
  }

  protected function isItellaOrder($carrier)
  {
    if (
      $carrier->id_reference == Configuration::get('ITELLA_COURIER_ID_REFERENCE') ||
      $carrier->id_reference == Configuration::get('ITELLA_PICKUP_POINT_ID_REFERENCE')
    ) {
      return true;
    }

    return false;
  }

  protected function printLabel($id_order)
  {
    if (!$id_order) {
      echo "No order ID given";
      die;
    }

    $order = new Order($id_order);
    $carrier = new Carrier($order->id_carrier);

    if (!$this->isItellaOrder($carrier)) {
      echo "Not Smartposti order.";
      die;
    }

    $tracking_number = $order->getWsShippingNumber();

    if (!$tracking_number) {
      echo "Order appears to not be registered yet.";
      die;
    }

    $product_code = ItellaHelper::getProductIdFromTrackNum($tracking_number);

    try {
      $shipment = new Shipment(Configuration::get('ITELLA_API_USER_' . $product_code), Configuration::get('ITELLA_API_PASS_' . $product_code));
      $pdf_base64 = $shipment->downloadLabels($tracking_number);
      $pdf = base64_decode($pdf_base64);
      if ($pdf) { // check if its not empty
        $filename = time();
        $path = _PS_MODULE_DIR_ . $this->module->name . '/pdf/' . $filename . '.pdf';
        $is_saved = file_put_contents($path, $pdf);
        $filename = 'labels.pdf';
        if (!$is_saved) { // make sure it was saved
          throw new ItellaException("Failed to save label pdf to: " . $path);
        }

        // make sure there is nothing before headers
        if (ob_get_level()) ob_end_clean();
        header("Content-Type: application/pdf; name=\" " . $filename . ".pdf\"");
        header("Content-Transfer-Encoding: binary");
        // disable caching on client and proxies, if the download content vary
        header("Expires: 0");
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");
        readfile($path);
      } else {
        throw new ItellaException("Downloaded label data is empty.");
      }
    } catch (ItellaException $e) {
      echo "Exception: <br>\n"
        . str_replace("\n", "<br>\n", $e->getMessage()) . "<br>\n";
      die;
    }
  }

  protected function massGenerateLabel()
  {
    $orderIds = array();
    if (!empty($_POST['order_ids'])) {
      $orderIds = $_POST['order_ids'];
    } else if (!empty($_GET['order_ids'])) {
      $orderIds = explode(',', $_GET['order_ids']);
    }
    if (empty($orderIds) || !is_array($orderIds)) {
      echo json_encode(array('errors' => $this->module->l('No order IDs sent.')));
      exit();
    }

    echo json_encode(array('success' => $orderIds));
    exit();
  }

  protected function generateLabel($id_order = false)
  {
    ItellaShipping::checkForClass('ItellaCart');
    if (!$id_order) {
      $id_order = Tools::getValue('id_order', NULL);
    }

    $order = new Order((int) $id_order);
    $address = new Address((int) $order->id_address_delivery);
    $customer = new Customer((int) $order->id_customer);
    $itellaCart = new ItellaCart();
    $data = $itellaCart->getOrderItellaCartInfo($order->id_cart);

    if (!$data) {
      return json_encode(array('errors' => $this->l('Order must be saved')));
    }

    try {
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
          'reference' => ItellaHelper::generateCODReference($id_order),
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
      $itellaCart->updateItellaCartTrackNumber($data['id_cart'], $tracking_number);
      // save tracking number(s) to order carrier as well
      $order->setWsShippingNumber($tracking_number);
      $order->shipping_number = $tracking_number;
      $order->update();

      ItellaShipping::changeOrderStatus($id_order, ItellaShipping::getCustomOrderState());

      return json_encode(array('success' => 'Smartposti API: Order registered.', 'filename' => $id_order . '.pdf', 'tracking_number' => $tracking_number));
    } catch (ItellaException $e) {
      $itellaCart->saveError($data['id_cart'], $e->getMessage());
      return json_encode(array('errors' => $e->getMessage()));
    }
  }

  protected function saveCart()
  {
    ItellaShipping::checkForClass('ItellaCart');

    $itellaCart = new ItellaCart();
    $result = $itellaCart->updateItellaCart();
    
    // update order carrier
    if (!isset($result['errors'])) {
      // check that carrier hasnt changed
      $order = new Order((int) $result['id_order']);
      $order_carrier = new OrderCarrier((int) $order->getIdOrderCarrier());
      $changed = false;
      if ($result['data']['is_pickup'] && $order->id_carrier != Configuration::get('ITELLA_PICKUP_POINT_ID')) {
        $order->id_carrier = Configuration::get('ITELLA_PICKUP_POINT_ID');
        $order_carrier->id_carrier = Configuration::get('ITELLA_PICKUP_POINT_ID');
        $changed = true;
      } elseif (!$result['data']['is_pickup'] && $order->id_carrier != Configuration::get('ITELLA_COURIER_ID')) {
        $order->id_carrier = Configuration::get('ITELLA_COURIER_ID');
        $order_carrier->id_carrier = Configuration::get('ITELLA_COURIER_ID');
        $changed = true;
      }

      if ($changed) {
        $order_carrier->update();
        // Only prestashop 1.7 has carrier change functionality
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
          $this->context->currency = isset($this->context->currency) ? $this->context->currency : new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
          $order->refreshShippingCost();
        }
        $order->update();
      }

      $result['order_carrier'] = $order_carrier;
    }

    exit(json_encode($result));
  }
}
