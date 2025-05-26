<?php
require_once _PS_MODULE_DIR_ . 'itellashipping/vendor/itella-api/vendor/autoload.php';

use PrestaShop\PrestaShop\Adapter\Entity\PrestaShopException;
use Mijora\Itella\Pdf\Manifest;
use Mijora\Itella\CallCourier;
use Mijora\Itella\ItellaException;

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
      (SELECT GROUP_CONCAT(o.id_order SEPARATOR ", ") 
       FROM `' . _DB_PREFIX_ . 'itella_cart` ic 
       JOIN `' . _DB_PREFIX_ . 'orders` o ON ic.id_cart = o.id_cart
       WHERE ic.id_itella_manifest = a.id_itella_manifest) as manifest_total
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

    $call_message = Configuration::get('ITELLA_CALL_MESSAGE');
    if (!$call_message) $call_message = '';

    $this->context->smarty->assign(array(
      'moduleMenu' => $menu,
      'stores' => json_encode($stores),
      'call_url' => $this->context->link->getAdminLink($this->controller_name, true),
      'call_message' => $call_message
    ));

    return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'itellashipping/views/templates/admin/manifest_menu.tpl');
  }

  public function getShopNameById($id)
  {
    $shop = new Shop($id);
    return $shop->name;
  }

  public function viewAccess($disable = false)
  {
    return true;        
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
      
      ItellaShipping::checkForClass('ItellaStore');
      $storeObj = new ItellaStore(Tools::getValue('id_itella_store'));
      if (!Validate::isLoadedObject($storeObj)) {
        echo json_encode(array('error' => $this->l('Failed to retrieve store data. Check the "Stores" page in the module settings.')));
        exit();
      }
      ItellaShipping::checkForClass('ItellaManifest');
      $manifest = new ItellaManifest(Tools::getValue('id_manifest'));
      if (!Validate::isLoadedObject($manifest)) {
        echo json_encode(array('error' => $this->l('Failed to retrieve manifest data')));
        exit();
      }

      $isTest = (Configuration::get('ITELLA_TEST_MODE') == 1);
      $username = Configuration::get('ITELLA_API_USER_2317');
      $password = Configuration::get('ITELLA_API_PASS_2317');
      if (empty($username)) {
        $username = Configuration::get('ITELLA_API_USER_2711');
        $password = Configuration::get('ITELLA_API_PASS_2711');
      }
      $email_send_to = Configuration::get('ITELLA_CALL_EMAIL_' . strtoupper($storeObj->country_code));
      $email_subject = Configuration::get('ITELLA_CALL_EMAIL_SUBJECT');
      $sender_name = Configuration::get('ITELLA_SENDER_NAME');
      $sender_code = Configuration::get('ITELLA_SENDER_CODE');

      $call_message = Tools::getValue('call_message');
      $call_date = Tools::getValue('call_date');
      $call_time_from = Tools::getValue('call_time_from');
      $call_time_to = Tools::getValue('call_time_to');

      $errors = array();
      $success = array();

      try {
        $caller = new CallCourier($email_send_to, $isTest);
        $caller
          ->setUsername($username)
          ->setPassword($password)
          ->setSenderEmail($this->context->employee->email)
          ->setSubject($email_subject)
          ->setPickUpAddress(array(
            'sender' => $sender_name,
            'address_1' => $storeObj->address,
            'postcode' => $storeObj->postcode,
            'city' => $storeObj->city,
            'country' => strtoupper($storeObj->country_code),
            'contact_phone' => $storeObj->phone,
          ))
          ->setPickUpParams(array(
            'date' => $call_date,
            'time_from' => $call_time_from,
            'time_to' => $call_time_to,
            'info_general' => $call_message,
            'id_sender' => $sender_code,
          ))
          ->setAttachment($manifest->getManifestString(), true)
          ->setItems($manifest->getManifestItems())
          ->disableMethod('email');

        $result_api = $caller->callCourier();

        if (!empty($result_api['errors'])) {
          $errors = array_merge($errors, $result_api['errors']);
        }
        if (!empty($result_api['success'])) {
          $success = array_merge($success, $result_api['success']);
        }
        if (empty($result_api['errors']) && empty($result_api['success'])) {
          $errors[] = $this->l('An unknown result was received from API library');
        }
      } catch (\ItellaException $e) {
        $errors[] = $this->l('Call courier failed with:') . ' ' . $e->getMessage();
      }

      try {
        if (!$email_send_to) {
          throw new \Exception($this->l('Courier service email not set'));
        }

        $file_attachement = array(
          'content' => $manifest->getManifestString(),
          'name' => 'manifest.pdf',
          'mime' => 'application/pdf'
        );

        $id_lang = Language::getIdByIso('en');
        if (!$id_lang) {
          $id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        }

        $dir_mail = $this->module->getLocalPath() . 'mails/';

        $data = array(
          '{title}' => $email_subject,
          '{body}' => $caller->buildMailBody()
        );

        $mail_sent = Mail::Send(
          $id_lang,
          'itella_call_courier',
          ($isTest ? 'TEST CALL - ' : '') . $email_subject,
          $data,
          $email_send_to,
          'Smartposti Courier Service',
          $this->context->employee->email,
          $sender_name,
          $file_attachement,
          null,
          $dir_mail,
          false,
          (int) $storeObj->id_shop
        );

        $success[] = $this->l('Courier call email sent');
      } catch (\Exception $e) {
        $errors[] = $this->l('Failed to send courier call email') . ': ' . $e->getMessage();
      }

      $return = array();
      if (empty($success) && !empty($errors)) {
        $return['error'] = implode('<br/>', $errors);
      } else if (!empty($errors)) {
        $return['success'] = implode('<br/>', $success) . '<br/><br/><span style="color:#f55;">' . implode('<br/>', $errors) . '</span>';
      } else if (!empty($success)) {
        $return['success'] = implode('<br/>', $success);
      } else {
        $return['error'] = $this->l('An unknown result was received from the call');
      }

      echo json_encode($return);
      exit();
    }
    echo json_encode(array('error' => $this->l('Bad store address ID')));
    exit();
  }

  private function sendCallEmail($caller, $send_to, $manifest_string, $shop_id)
  {
    $id_lang = Language::getIdByIso('en');
    if (!$id_lang) {
      $id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
    }

    $isTest = (Configuration::get('ITELLA_TEST_MODE') == 1);

    $file_attachement = array(
      'content' => $manifest_string,
      'name' => 'manifest.pdf',
      'mime' => 'application/pdf'
    );
    $dir_mail = $this->module->getLocalPath() . 'mails/';

    try {
      $data = array(
        '{title}' => Configuration::get('ITELLA_CALL_EMAIL_SUBJECT'),
        '{body}' => $caller->buildMailBody()
      );

      $mail_sent = Mail::Send(
        $id_lang,
        'itella_call_courier',
        ($isTest ? 'TEST CALL - ' : '') . Configuration::get('ITELLA_CALL_EMAIL_SUBJECT'),
        $data,
        $send_to,
        'Smartposti Courier Service',
        $this->context->employee->email,
        Configuration::get('ITELLA_SENDER_NAME'),
        $file_attachement,
        null,
        $dir_mail,
        false,
        (int) $shop_id
      );
      if (!$mail_sent) {
        return array('error' => $this->l('Call courier failed'));
      }
    } catch (\Exception $e) {
      return array('error' => $this->l('Call courier failed with:') . ' ' . $e->getMessage());
    }

    return array('success' => $this->l('Courier called successfully'));
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
