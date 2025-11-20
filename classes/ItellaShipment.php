<?php
require_once _PS_MODULE_DIR_ . 'itellashipping/vendor/itella-api/vendor/autoload.php';

use Mijora\Itella\ItellaException;
use Mijora\Itella\Helper as ItellaHelper;
use Mijora\Itella\Shipment\Shipment;
use Mijora\Itella\Shipment\Party;
use Mijora\Itella\Shipment\GoodsItem;
use Mijora\Itella\Shipment\AdditionalService;

class ItellaShipment
{
    protected $module;

    public function __construct()
    {
        $this->module = new ItellaShipping();
    }

    public static function isInternational( $receiver_country )
    {
        $local_countries = array('LT', 'LV', 'EE', 'FI');
        return (! in_array(strtoupper($receiver_country), $local_countries));
    }

    public static function getAllServiceCodes()
    {
        // First value of each type is default
        return array(
            'pickup' => array(
                'locker' => Shipment::PRODUCT_PARCEL_CONNECT,
                'postal' => Shipment::PRODUCT_POSTAL_PARCEL
            ),
            'courier' => array(
                'express' => Shipment::PRODUCT_EXPRESS_BUSINESS_DAY,
                'home' => Shipment::PRODUCT_HOME_PARCEL
            )
        );
    }

    public static function getDefaultServiceCode( $type )
    {
        $all_service_codes = self::getAllServiceCodes();
        if ( ! isset($all_service_codes[$type]) ) {
            return '';
        }

        foreach ( $all_service_codes[$type] as $service_code ) {
            return $service_code;
        }
        return '';
    }

    public static function getInternationalServiceCode()
    {
        return Shipment::PRODUCT_EXPRESS_BUSINESS_DAY;
    }

    public static function isPickupService( $service_code )
    {
        $all_services = self::getAllServiceCodes();
        return (isset($all_services['pickup']) && in_array($service_code, $all_services['pickup']));
    }

    public static function get_available_additional_services( $main_service_code )
    {
        $additional_services_codes = AdditionalService::getCodesByProduct($main_service_code);
        if ( ! $additional_services_codes ) {
            return array();
        }

        $map = array(
            AdditionalService::COD => 'cod',
            AdditionalService::MULTI_PARCEL => 'multiparcel',
            AdditionalService::FRAGILE => 'fragile',
            AdditionalService::CALL_BEFORE_DELIVERY => 'call_before_delivery',
            AdditionalService::OVERSIZED => 'oversized'
        );

        $additional_services = array();

        foreach ( $additional_services_codes as $code ) {
            if ( isset($map[$code]) ) {
                $additional_services[$map[$code]] = $code;
            }
        }

        return $additional_services;
    }

    public function registerShipment( $id_order )
    {
        if ( ! ItellaShipping::checkForClass('ItellaCart') ) {
            return ['error' => sprintf($this->module->l('Failed to load %s class'), 'ItellaCart')];
        }

        $id_order = (int) $id_order;
        if ( $id_order <= 0 ) {
            return ['error' => $this->module->l('Incorrect Order ID')];
        }

        $order = new \Order($id_order);
        $address = new \Address((int) $order->id_address_delivery);
        $country = new \Country($address->id_country);
        $customer = new \Customer((int) $order->id_customer);
        $itellaCart = new ItellaCart();
        $data = $itellaCart->getOrderItellaCartInfo($order->id_cart);

        if ( ! $data ) {
            return ['error' => $this->module->l('Order must be saved')];
        }

        try {
            // Determine product code
            if ( self::isInternational($country->iso_code) ) {
                $product_code = self::getInternationalServiceCode();
            } else if ( $data['is_pickup'] == 1 ) {
                $product_code = \Configuration::get('ITELLA_API_PP_SERVICE');
                if ( ! $product_code ) {
                    $product_code = self::getDefaultServiceCode('pickup');
                }
            } else {
                $product_code = \Configuration::get('ITELLA_API_C_SERVICE');
                if (!$product_code) {
                    $product_code = self::getDefaultServiceCode('courier');
                }
            }

            // Create and configure sender
            $sender = new Party(Party::ROLE_SENDER);
            $sender
                ->setContract(\Configuration::get('ITELLA_API_CONTRACT')) // supplied by Itella with user and pass
                ->setName1(\Configuration::get('ITELLA_SENDER_NAME'))
                ->setStreet1(\Configuration::get('ITELLA_SENDER_STREET'))
                ->setPostCode(\Configuration::get('ITELLA_SENDER_POSTCODE'))
                ->setCity(\Configuration::get('ITELLA_SENDER_CITY'))
                ->setCountryCode(\Configuration::get('ITELLA_SENDER_COUNTRY_CODE'))
                ->setContactMobile(\Configuration::get('ITELLA_SENDER_PHONE'))
                ->setContactEmail(\Configuration::get('ITELLA_SENDER_EMAIL'))
            ;

            // Create and configure receiver
            $phone = ($address->phone_mobile) ? $address->phone_mobile : $address->phone;
            $receiver = new Party(Party::ROLE_RECEIVER);
            $receiver
                ->setName1($address->firstname . ' ' . $address->lastname)
                ->setStreet1($address->address1 . $address->address2)
                ->setPostCode($address->postcode)
                ->setCity($address->city)
                ->setCountryCode($country->iso_code)
                ->setContactMobile($phone)
                ->setContactEmail($customer->email)
            ;

            // Prepare items
            $items = array();
            $weight = ($data['packs'] > 1) ? (float) $data['weight'] / $data['packs'] : (float) $data['weight'];
            for ( $total = 0; $total < $data['packs']; $total++ ) {
                $item = new GoodsItem();
                $item
                    ->setGrossWeight($weight) // kg
                    //->setVolume(0.1) // m3
                ;
                $items[] = $item;
            }

            // Create manualy assigned additional services (multiparcel and pickup point services auto created by lib)
            $extra_service_codes = self::get_available_additional_services($product_code);
            $extra = array();
            foreach ( $extra_service_codes as $es_key => $es_code ) {
                if ( ! isset($data['is_' . $es_key]) || ! $data['is_' . $es_key] ) {
                    continue;
                }
                $es_values = array();
                if ( $es_key === 'cod' ) {
                    $es_values = array(
                        'amount' => $data['cod_amount'],
                        'account' => Configuration::get('ITELLA_COD_IBAN'),
                        'reference' => ItellaHelper::generateCODReference($id_order),
                        'codbic' => Configuration::get('ITELLA_COD_BIC')
                    );
                }
                $extra[] = new AdditionalService($es_code, $es_values);
            }

            // Create shipment object
            $shipment = $this->createShipmentObject();
            $shipment
                ->setProductCode($product_code) // should always be set first
                ->setShipmentNumber($id_order) // shipment number 
                //->setShipmentDateTime(date('c')) // when package will be ready (just use current time)
                ->setSenderParty($sender) // Sender class object
                ->setReceiverParty($receiver) // Receiver class object
                ->addAdditionalServices($extra) // set additional services
                ->addGoodsItems($items)
            ;

            if ( isset($data['comment']) && ! empty($data['comment']) ) {
                $shipment->setComment($data['comment']);
            }

            if ( self::isPickupService($product_code) ) {
                $shipment->setPickupPoint($data['id_pickup_point']);
            }

            // Register shipment
            $tracking_number = $shipment->registerShipment();

            // Update ItellaCart with tracking nunmber
            $itellaCart->updateItellaCartTrackNumber($data['id_cart'], $tracking_number);

            // Save tracking number(s) to order carrier as well
            $order->setWsShippingNumber($tracking_number);
            $order->shipping_number = $tracking_number;
            $order->update();

            ItellaShipping::changeOrderStatus($id_order, ItellaShipping::getCustomOrderState());

            return [
                'success' => $this->module->l('Shipment registered'),
                'tracking_number' => $tracking_number
            ];
        } catch ( ItellaException $e ) {
            $itellaCart->saveError($data['id_cart'], $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getLabel( $id_order )
    {
        $id_order = (int) $id_order;
        if ( $id_order <= 0 ) {
            return ['error' => $this->module->l('Incorrect Order ID')];
        }

        $order = new \Order($id_order);
        $carrier = new \Carrier($order->id_carrier);

        if ( ! ItellaShipping::isItellaCarrier($carrier) ) {
            return ['error' => $this->module->l('Not Smartposti Order')];
        }

        $tracking_number = $order->getWsShippingNumber();
        if ( ! $tracking_number ) {
            return ['error' => $this->module->l('Order shipment appears to not be registered yet')];
        }

        try {
            $shipment = $this->createShipmentObject();
            $pdf_base64 = $shipment->downloadLabels($tracking_number);
            $pdf = base64_decode($pdf_base64);
            if ( ! $pdf ) {
                return ['error' => $this->module->l('Downloaded label data is empty')];
            }
            return [
                'success' => $this->module->l('Label successfully received'),
                'pdf' => $pdf
            ];
        } catch ( ItellaException $e ) {
            return ['error' => 'Exception: ' . $e->getMessage()];
        }
    }

    private function createShipmentObject()
    {
        $shipment = new Shipment(\Configuration::get('ITELLA_API_USER'), \Configuration::get('ITELLA_API_PASS'));
        $shipment
            ->setRoutingClient('BAL-PRESTA')
        ;

        return $shipment;
    }
}
