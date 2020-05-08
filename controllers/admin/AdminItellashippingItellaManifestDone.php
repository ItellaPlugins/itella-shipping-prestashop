<?php
require_once _PS_MODULE_DIR_ . 'itellashipping/vendor/itella-api/vendor/autoload.php';

use PrestaShop\PrestaShop\Adapter\Entity\PrestaShopException;
use Mijora\Itella\Pdf\Manifest;
use Mijora\Itella\CallCourier;

class AdminItellashippingItellaManifestDoneController extends ModuleAdminController
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
    $this->className = 'ItellaManifest';
    $this->table = 'itella_manifest';
    parent::__construct();

    ItellaShipping::checkForClass('ItellaManifest');
    $this->_select = ' a.id_itella_manifest as id_manifest,
      (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'itella_cart` ic WHERE ic.id_itella_manifest = a.id_itella_manifest) as manifest_total
    ';

    if (Tools::isSubmit('printitella_manifest')) {
      if (Tools::isSubmit('id') && Tools::getValue('id') > 0) {
        $manifest = new ItellaManifest(Tools::getValue('id'));
        $manifest->printPdf();
        exit();
      }
    }

    if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
      $this->errors[] = $this->l('Select shop');
    } else {
      $this->content .= $this->displayMenu();
      $this->readyManifestList();
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
        'url' => $this->context->link->getAdminLink('AdminItellashippingItellaManifest', true),
        'active' => false
      ),
      array(
        'label' => $this->l('Generated Manifests'),
        'url' => $this->context->link->getAdminLink($this->controller_name, true),
        'active' => Tools::getValue('controller') == $this->controller_name
      )
    );

    ItellaShipping::checkForClass('ItellaStore');
    $storeObj = new ItellaStore();
    $stores = ItellaStore::getStores();

    $this->context->smarty->assign(array(
      'moduleMenu' => $menu,
      'stores' => json_encode($stores),
      'call_url' => $this->context->link->getAdminLink($this->controller_name, true),
    ));

    return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'itellashipping/views/templates/admin/manifest_menu.tpl');
  }

  public function getShopNameById($id)
  {
    $shop = new Shop($id);
    return $shop->name;
  }

  protected function readyManifestList()
  {
    $this->fields_list = array(
      'id_itella_manifest' => array(
        'title' => $this->l('ID'),
        'align' => 'text-center',
        'class' => 'fixed-width-xs',
        'search' => false,
        'orderby' => false
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
      'date_add' => array(
        'title' => $this->l('Date'),
        'align' => 'center',
        'type' => 'datetime',
        'filter_key' => 'a!date_add',
      ),
      'manifest_total' => array(
        'title' => $this->l('Orders in manifest'),
        'align' => 'text-center',
        'search' => false,
        'class' => 'fixed-width-xs',
      ),
    );

    $this->fields_list['id_manifest'] = array(
      'title' => $this->l('Actions'),
      'align' => 'text-right',
      'search' => false,
      'orderby' => false,
      'callback' => 'printBtn',
    );

    $this->actions = array('none');
  }

  public function printBtn($id)
  {
    return '<span class="btn-group-action">
                <span class="btn-group">
                    <a target="_blank" class="btn btn-default" href="' . self::$currentIndex . '&token=' . $this->token . '&manifestdone&ajax=1' . '&print' . $this->table . '&id=' . $id . '"><i class="icon-file-pdf-o"></i>&nbsp;' . $this->l('Print') . '
                    </a>
                </span>
            </span>

            <span class="btn-group-action">
                <span class="btn-group">
                    <a data-manifest="' . $id . '" class="btn btn-default" href="#"><i class="icon-file-pdf-o"></i>&nbsp;' . $this->l('Call Courier') . '
                    </a>
                </span>
            </span>';
  }

  public function displayAjax()
  {
    if (Tools::isSubmit('id_itella_store') && filter_var(Tools::getValue('id_itella_store'), FILTER_VALIDATE_INT)) {
      if (!Tools::isSubmit('id_manifest') || !filter_var(Tools::getValue('id_manifest'), FILTER_VALIDATE_INT)) {
        echo json_encode(array('error' => $this->l('Missing manifest ID')));
        exit();
      }
      $manifest = new ItellaManifest(Tools::getValue('id_manifest'));
      $manifest_base64 = $manifest->getManifestBase64();

      ItellaShipping::checkForClass('ItellaStore');
      $storeObj = new ItellaStore(Tools::getValue('id_itella_store'));
      $isTest = (Configuration::get('ITELLA_TEST_MODE') == 1);

      try {
        $send_to = Configuration::get('ITELLA_CALL_EMAIL_' . strtoupper($storeObj->country_code));
        if (!$send_to) {
          throw new \Exception($this->l('Courier service email not set'));
        }
        $mailer = new CallCourier($send_to, $isTest);
        $result = $mailer
          ->setSenderEmail($this->context->employee->email)
          ->setSubject('E-com order booking')
          ->setPickUpAddress(array(
            'sender' => Configuration::get('ITELLA_SENDER_NAME'),
            'address' => $storeObj->getFormatedAddress(),
            //'pickup_time' => $storeObj->pick_start . ' - ' . $storeObj->pick_finish,
            'contact_phone' => $storeObj->phone,
          ))
          ->setAttachment($manifest_base64, true)
          ->callCourier();
      } catch (\Exception $th) {
        echo json_encode(array('error' => $this->l('Call courier failed with:') . ' ' . $th->getMessage()));
        exit();
      }
      echo json_encode(array('success' => $this->l('Itella courier called')));
      exit();
    }
    echo json_encode(array('error' => $this->l('Bad store address ID')));
    exit();
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
}
