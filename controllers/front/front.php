<?php
if (!defined('_PS_VERSION_')) {
  return;
}
class ItellashippingFrontModuleFrontController extends ModuleFrontController
{

  public function initContent()
  {
    if (Tools::getValue('itella_token') != Tools::getToken(false)) {
      die(Tools::jsonEncode('BAD TOKEN'));
    }

    $carrierId = Tools::getValue('carrier_id');

    if (
      $carrierId != Configuration::get('ITELLA_COURIER_ID') &&
      $carrierId != Configuration::get('ITELLA_PICKUP_POINT_ID')
    ) {
      die(Tools::jsonEncode('NOT ITELLA SERVICE'));
    }

    $cart = array(
      'is_pickup' => pSQL((Configuration::get('ITELLA_PICKUP_POINT_ID') == $carrierId ? 1 : 0)),
      'id_pickup_point' => pSQL(trim(Tools::getValue('selected_id')))
    );

    if (
      (!isset($cart['id_pickup_point']) || empty($cart['id_pickup_point']))
    ) {
      die(Tools::jsonEncode('NO TERMINAL'));
    }

    if (!Db::getInstance()->getValue("SELECT 1 FROM " . _DB_PREFIX_ . "itella_cart WHERE id_cart = " . pSQL($this->context->cart->id))) {
      $cart['id_cart'] = pSQL($this->context->cart->id);
      $result = Db::getInstance()->insert('itella_cart', $cart);
    } else {
      $result = Db::getInstance()->update('itella_cart', $cart, 'id_cart = ' . pSQL($this->context->cart->id));
    }

    die(Tools::jsonEncode(array(
      'msg' => 'OK', //'cart_data'=>$cart, 'carrier_id'=>$carrierId,
      'savedCarrier' => Configuration::get('ITELLA_PICKUP_POINT_ID'),
      'result' => $result
    )));
  }
}
