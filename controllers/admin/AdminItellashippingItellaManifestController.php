<?php

use PrestaShop\PrestaShop\Adapter\Entity\PrestaShopException;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\FormHelper;

require_once _PS_MODULE_DIR_ . 'itellashipping/vendor/itella-api/vendor/autoload.php';

use Mijora\Itella\ItellaException;
use Mijora\Itella\Shipment\Shipment;
use Mijora\Itella\CallCourier;
use Mijora\Itella\Helper as ItellaHelper;
use Mijora\Itella\Pdf\PDFMerge;

class AdminItellashippingItellaManifestController extends ModuleAdminController
{

  /** @var bool Is bootstrap used */
  public $bootstrap = true;

  private $total_orders = 0;

  /**
   * AdminOmnivaltShippingStoresController class constructor
   *
   * @throws PrestaShopException
   * @throws SmartyException
   */
  public function __construct()
  {
    $this->list_no_link = true;
    $this->className = 'Order';
    $this->table = 'order';
    parent::__construct();
    ItellaShipping::checkForClass('ItellaManifest');
    $this->toolbar_title = $this->l('Smartposti Manifest - Ready Orders');
    $this->_select = '
    oc.tracking_number as label_number,
    CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) AS `customer`,
    osl.`name` AS `osname`,
    os.`color`,
    a.id_order AS id_print,
    a.id_order AS id_label_print,
    IF((SELECT so.id_cart FROM `' . _DB_PREFIX_ . 'itella_cart` so WHERE so.id_itella_manifest = im.id_itella_manifest LIMIT 1) > 0, 0, 1) as new
		';
    $this->_join = '
    LEFT JOIN `' . _DB_PREFIX_ . 'order_carrier` oc ON (oc.`id_order` = a.`id_order`)
    LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.`id_customer` = a.`id_customer`)
    LEFT JOIN `' . _DB_PREFIX_ . 'carrier` carrier ON (carrier.`id_carrier` = a.`id_carrier`)
    LEFT JOIN `' . _DB_PREFIX_ . 'itella_cart` ic ON (ic.`id_cart` = a.`id_cart`)
    LEFT JOIN `' . _DB_PREFIX_ . 'itella_manifest` im ON (ic.`id_itella_manifest` = im.`id_itella_manifest`)
    LEFT JOIN `' . _DB_PREFIX_ . 'order_state` os ON (os.`id_order_state` = a.`current_state`)
    LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = ' . (int) $this->context->language->id . ')
    ';

    $this->_sql = '
      SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` a
      LEFT JOIN `' . _DB_PREFIX_ . 'itella_cart` ic ON (ic.`id_cart` = a.`id_cart`)
      WHERE 1 ' . Shop::addSqlRestrictionOnLang('a') .  ' AND (ic.id_itella_manifest IS NULL OR ic.id_itella_manifest = 0)
    '; // AND ic.label_number <> ""
    $this->total_orders = DB::getInstance()->getValue($this->_sql);

    $this->_where = ' AND carrier.id_reference IN ('
      . Configuration::get('ITELLA_COURIER_ID_REFERENCE') . ','
      . Configuration::get('ITELLA_PICKUP_POINT_ID_REFERENCE')
      . ') AND (ic.id_itella_manifest IS NULL OR ic.id_itella_manifest = 0)';
    //  AND ic.label_number <> ""
    $statuses = OrderState::getOrderStates((int) $this->context->language->id);
    foreach ($statuses as $status) {
      $this->statuses_array[$status['id_order_state']] = $status['name'];
    }

    if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
      $this->errors[] = $this->l('Select shop');
    } else {
      $this->content .= $this->displayMenu();
      $this->readyOrdersList();
    }
  }

  /**
   * @throws SmartyException
   */
  private function displayMenu()
  {
    $menu = array(
      array(
        'label' => $this->l('Ready Orders'),
        'url' => $this->context->link->getAdminLink($this->controller_name, true),
        'active' => Tools::getValue('controller') == $this->controller_name
      ),
      array(
        'label' => $this->l('Generated Manifests'),
        'url' => $this->context->link->getAdminLink('AdminItellashippingItellaManifestDone', true),
        'active' => false
      )
    );

    ItellaShipping::checkForClass('ItellaStore');
    $storeObj = new ItellaStore();
    $stores = ItellaStore::getStores();

    $this->context->smarty->assign(array(
      'moduleMenu' => $menu,
      'stores' => json_encode($stores),
      'call_url' => false // dont need js handling call courier functionality
    ));

    return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'itellashipping/views/templates/admin/manifest_menu.tpl');
  }

  public function getShopNameById($id)
  {
    $shop = new Shop($id);
    return $shop->name;
  }

  protected function readyOrdersList()
  {
    $this->fields_list = array(
      'id_order' => array(
        'title' => $this->l('ID'),
        'align' => 'text-center',
        'class' => 'fixed-width-xs',
        'search' => false,
      ),
      'id_shop' => array(
        'type' => 'text',
        'title' => $this->l('Shop'),
        'align' => 'center',
        'search' => false,
        'havingFilter' => false,
        'orderby' => false,
        'callback' => 'getShopNameById',
      ),
      'osname' => array(
        'title' => $this->l('Status'),
        'type' => 'select',
        'color' => 'color',
        'list' => $this->statuses_array,
        'filter_key' => 'os!id_order_state',
        'filter_type' => 'int',
        'order_key' => 'osname',
      ),
      'customer' => array(
        'title' => $this->l('Customer'),
        'havingFilter' => true,
      ),
      'label_number' => array(
        'type' => 'text',
        'title' => $this->l('Tracking number'),
        'havingFilter' => false,
      )
    );

    $this->fields_list['id_label_print'] = array(
      'title' => $this->l('PDF'),
      'align' => 'text-center',
      'search' => false,
      'orderby' => false,
      'callback' => 'labelBtn',
    );

    // if ($this->total_orders == 1) {
    //   $this->fields_list['id_print'] = array(
    //     'title' => $this->l('Generate manifest'),
    //     'align' => 'text-right',
    //     'search' => false,
    //     'callback' => 'generateBtn',
    //   );
    // }

    $this->actions = array('none');

    $this->bulk_actions = array(
      'registerItella' => array(
        'text' => $this->l('Generate Labels'),
        'icon' => 'icon-save'
      ),
      'generateItellaLabel' => array(
        'text' => $this->l('Print Labels'),
        'icon' => 'icon-tag'
      ),
      'printItellaManifest' => array(
        'text' => $this->l('Print Manifest'),
        'icon' => 'icon-file-pdf-o'
      ),
    );
  }

  /**
   * Generates button to imitate bulkAction
   */
  public function generateBtn($id)
  {
    $order = new Order((int) $id);
    if (!$order->getWsShippingNumber()) {
      return false;
    }
    return '<span class="btn-group-action">
                <span class="btn-group">
                    <a class="btn btn-default" href="' . self::$currentIndex . '&token=' . $this->token . '&submitBulkprintItellaManifestorder' . '&orderBox[]=' . $id . '"><i class="icon-file-pdf-o"></i>&nbsp;' . $this->l('Print manifest') . '
                    </a>
                </span>
            </span>';
  }

  public function labelBtn($id)
  {
    $order = new Order((int) $id);
    if (!$order->getWsShippingNumber()) {
      return '<span class="btn-group-action">
                <span class="btn-group">
                  <a class="btn btn-default" target="_blank" href="' . self::$currentIndex . '&token=' . $this->token . '&submitBulkregisterItellaorder' . '&orderBox[]=' . $id . '"><i class="icon-save"></i>&nbsp;' . $this->l('Generate Label') . '
                  </a>
                </span>
            </span>';
    }
    return '<span class="btn-group-action">
                <span class="btn-group">
                    <a class="btn btn-default" target="_blank" href="' . self::$currentIndex . '&token=' . $this->token . '&submitBulkgenerateItellaLabelorder' . '&orderBox[]=' . $id . '"><i class="icon-tag"></i>&nbsp;' . $this->l('Label') . '
                    </a>
                
                    <a class="btn btn-default" href="' . self::$currentIndex . '&token=' . $this->token . '&submitBulkprintItellaManifestorder' . '&orderBox[]=' . $id . '"><i class="icon-file-pdf-o"></i>&nbsp;' . $this->l('Manifest') . '
                    </a>
                </span>
            </span>';
  }

  public function renderList()
  {
    switch (Shop::getContext()) {
      case Shop::CONTEXT_GROUP:
        $this->_where .= ' AND a.`id_shop` IN(' . implode(',', Shop::getContextListShopID()) . ')';
        break;

      case Shop::CONTEXT_SHOP:
        $this->_where .= Shop::addSqlRestrictionOnLang('a');
        break;

      default:
        break;
    }
    $this->_use_found_rows = false;

    unset($this->toolbar_btn['new']);

    return parent::renderList();
  }

  public function processBulkregisterItella()
  {
    $orderIds = Tools::getValue('orderBox');
    if (!is_array($orderIds) || empty($orderIds)) {
      $this->errors[] = $this->l('No orders selected');
      return false;
    }

    $this->informations[] = "Trying to register orders: " . implode(', ', $orderIds);

    ItellaShipping::checkForClass('ItellaCart');
    $itellaCart = new ItellaCart();
    $skipped = array();
    $saved = array();
    $registered = array();
    foreach ($orderIds as $id_order) {
      $order = new Order($id_order);
      if ($order->getWsShippingNumber()) { // skip registered orders
        $skipped[] = $id_order;
        continue;
      }

      $itella_cart_info = $itellaCart->getOrderItellaCartInfo($order->id_cart);

      if (!$itella_cart_info || (int) $itella_cart_info['is_cod'] === -1) {
        $itellaCart->saveOrder($order);
        $saved[] = $id_order;
        // $itella_cart_info = $itellaCart->getOrderItellaCartInfo($order->id_cart);
      }

      $result = $this->registerLabel($id_order);

      if (isset($result['errors'])) {
        $this->errors[] = $result['errors'];
        $itellaCart->saveError($order->id_cart, $result['errors']);
      }

      if (isset($result['success'])) {
        $registered[] = $id_order . ': ' . $result['success'] . ' ' . $result['tracking_number'];
      }
    }

    $this->informations[] = $this->l("Skipped orders (allready registered):") . ' ' . implode(', ', $skipped);
    $this->informations[] = $this->l("Saved orders:") . ' ' . implode(', ', $saved);
    $this->informations[] = $this->l("Registered orders:");
    $this->informations = array_merge($this->informations, $registered);
  }

  private function registerLabel( $id_order )
  {
    if ( ! ItellaShipping::checkForClass('ItellaShipment') ) {
      return array('errors' => sprintf($this->l('Failed to load %s class'), 'ItellaShipment'));
    }
    if ( ! $id_order ) {
      return array('errors' => $this->l('Order ID missing'));
    }

    $ItellaShipment = new ItellaShipment();

    $result = $ItellaShipment->registerShipment($id_order);
    if ( isset($result['error']) ) {
      return array('errors' => $result['error']);
    }

    $success_msg = (isset($result['success'])) ? $result['success'] : $this->l('Success');
    $tracking_number = (isset($result['tracking_number'])) ? $result['tracking_number'] : '';

    return array(
      'success' => $success_msg,
      'tracking_number' => $tracking_number
    );
  }

  public function processBulkgenerateItellaLabel()
  {
    $orderIds = Tools::getValue('orderBox');
    if (!is_array($orderIds) || empty($orderIds)) {
      $this->errors[] = $this->l('No orders selected');
      return false;
    }

    $track = $this->isOrdersValid($orderIds);

    if (!$track) {
      return false;
    }

    $this->downloadLabels($track);
  }

  protected function isOrdersValid(&$orders)
  {
    $warnings = array();
    $track = array();
    foreach ($orders as $id_order) {
      $order = new Order((int) $id_order);
      $tracking_number = $order->getWsShippingNumber();
      //$cart_data = $itella_cart->getOrderItellaCartInfo($order->id_cart);
      if (!$tracking_number) {
        $warnings[] = $id_order;
      } else {
        $track[] = $tracking_number;
      }
    }
    if ($warnings) {
      $this->warnings[] = $this->l('Order(s) needs label(s):') . ' ' . implode(', ', $warnings);
      $this->warnings[] = $this->l('Press this link to generate labels:') . ' <a href="'
        . $this->context->link->getAdminLink('AdminItellashippingItellaManifest', true, array(), array('orderBox' => $warnings, 'submitBulkregisterItellaorder' => ''))
        . '">' . $this->l('Generate Label(s)') . '</a>';
      return false;
    }

    return $track;
  }

  protected function downloadLabels($tracking_numbers)
  {
    if (!is_array($tracking_numbers)) {
      $tracking_numbers = array($tracking_numbers);
    }
    try {
      // sort tracking numbers by product code
      $track = array();
      foreach($tracking_numbers as $tr_num) {
        $product_code = ItellaHelper::getProductIdFromTrackNum($tr_num);
        if (!ItellaHelper::keyExists($product_code, $track)) {
          $track[$product_code] = array();
        }
        $track[$product_code][] = $tr_num;
      }

      // download labels
      $temp_name = time();
      $temp_files = array();
      foreach($track as $key => $tr_numbers) {
        if (!$tr_numbers) {
          continue;
        }
        $shipment = new Shipment(Configuration::get('ITELLA_API_USER'), Configuration::get('ITELLA_API_PASS'));
        $shipment->setRoutingClient('BAL-PRESTA');
        $result = base64_decode($shipment->downloadLabels($tr_numbers));
        if ($result) { // check if its not empty and save temporary for merging
          $pdf_path = _PS_MODULE_DIR_ . $this->module->name . '/pdf/' . $temp_name . '-' . $key . '.pdf';
          file_put_contents($pdf_path, $result);
          $temp_files[] = $pdf_path;
        }
      }

      // merge downloaded labels
      $merger = new PDFMerge();
      $merger->setFiles($temp_files); // pass array of paths to pdf files
      $merger->merge();

      // remove downloaded labels ()
      foreach($temp_files as $file) {
        if (is_file($file)) {
          unlink($file);
        }
      }
      /**
       * Second param:
       * I: send the file inline to the browser (default).
       * D: send to the browser and force a file download with the name given by name.
       * F: save to a local server file with the name given by name.
       * S: return the document as a string (name is ignored).
       * FI: equivalent to F + I option
       * FD: equivalent to F + D option
       * E: return the document as base64 mime multi-part email attachment (RFC 2045)
       */
      $merger->Output('labels.pdf','I');
    } catch (ItellaException $e) {
      echo "Exception: <br>\n"
        . str_replace("\n", "<br>\n", $e->getMessage()) . "<br>\n";
      die;
    }
  }

  public function processBulkprintItellaManifest()
  {
    $orderIds = Tools::getValue('orderBox');
    if (!is_array($orderIds) || empty($orderIds)) {
      $this->errors[] = $this->l('No orders selected');
      return false;
    }

    $track = $this->isOrdersValid($orderIds);

    if (!$track) {
      return false;
    }

    $manifest = new ItellaManifest();
    $manifest->date_add = time();
    $manifest->id_shop = Context::getContext()->shop->id;
    $result = $manifest->save();

    if (!$result) {
      $this->errors[] = $this->l('Failed to save manifest');
      return false;
    }

    $id_manifest = $manifest->id;
    $orderIds = implode(',', $orderIds);
    $sql = 'UPDATE `' . _DB_PREFIX_ . 'itella_cart` SET id_itella_manifest = ' . $id_manifest . ' WHERE id_cart IN (
          SELECT id_cart FROM `' . _DB_PREFIX_ . 'orders` WHERE id_order IN (' . $orderIds . ')
        )';
    $result = DB::getInstance()->execute($sql, false);
    if (!$result) {
      $this->errors[] = $this->l('Failed to update itella cart with manifest ID');
      return false;
    }

    Tools::redirectAdmin($this->context->link->getAdminLink('AdminItellashippingItellaManifestDone', true) . '&conf=4');
  }
}
