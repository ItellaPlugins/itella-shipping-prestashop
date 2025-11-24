<?php

if (!defined('_PS_VERSION_')) {
  exit;
}

class ItellashippingAjaxModuleFrontController extends ModuleFrontController
{

  public function __construct()
  {
    $context = Context::getContext();

    $cookie = new Cookie('psAdmin');
    $employee = new Employee($cookie->id_employee);

    $context->employee = $employee;
    $context->cookie = $cookie;

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

  protected function printLabel( $id_order )
  {
    if ( ! ItellaShipping::checkForClass('ItellaShipment') ) {
      echo sprintf($this->module->l('Failed to load %s class'), 'ItellaShipment');
      die;
    }

    $ItellaShipment = new ItellaShipment();

    $result = $ItellaShipment->getLabel($id_order);
    if ( isset($result['error']) ) {
      echo $result['error'];
      die;
    }

    $pdf = (isset($result['pdf'])) ? $result['pdf'] : null;
    if ( ! $pdf ) {
      echo $this->module->l('PDF file not received');
      die;
    }

    $filename = time();
    $path = _PS_MODULE_DIR_ . $this->module->name . '/pdf/' . $filename . '.pdf';
    
    if ( ! file_put_contents($path, $pdf) ) {
      echo sprintf($this->module->l('Failed to save label PDF to: %s'), $path);
      die;
    }

    $filename = 'labels.pdf';

    if ( ob_get_level() ) ob_end_clean();

    header("Content-Type: application/pdf; name=\"" . $filename . "\"");
    header("Content-Transfer-Encoding: binary");
    // disable caching on client and proxies, if the download content vary
    header("Expires: 0");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    readfile($path);
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

  protected function generateLabel( $id_order = false )
  {
    if ( ! ItellaShipping::checkForClass('ItellaShipment') ) {
      return json_encode(array('errors' => sprintf($this->module->l('Failed to load %s class'), 'ItellaShipment')));
    }

    if ( ! $id_order ) {
      $id_order = Tools::getValue('id_order', NULL);
    }

    $ItellaShipment = new ItellaShipment();

    $result = $ItellaShipment->registerShipment($id_order);
    if ( isset($result['error']) ) {
      return json_encode(array('errors' => $result['error']));
    }

    $success_msg = (isset($result['success'])) ? $result['success'] : $this->module->l('Success');
    $tracking_number = (isset($result['tracking_number'])) ? $result['tracking_number'] : '';

    return json_encode(array(
      'success' => 'Smartposti API: ' . $success_msg,
      'filename' => $id_order . '.pdf',
      'tracking_number' => $tracking_number
    ));
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
          $this->context->currency = $this->context->currency ?? new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
          $order->refreshShippingCost();
        }
        $order->update();
      }

      $result['order_carrier'] = $order_carrier;
    }

    exit(json_encode($result));
  }
}
