<?php
/**
 * This file is part of the official Clearpay module for PrestaShop.
 *
 * @author    Clearpay <integrations@clearpay.com>
 * @copyright 2020 Clearpay
 * @license   proprietary
 */

use Afterpay\SDK\HTTP\Request\CreateCheckout;
use Afterpay\SDK\MerchantAccount as ClearpayMerchantAccount;

require_once('AbstractController.php');

/**
 * Class ClearpayRedirectModuleFrontController
 */
class ClearpayPaymentModuleFrontController extends AbstractController
{
    /** @var string $language */
    protected $language;

    /**
     * Default API Version per region
     *
     * @var array
     */
    public $defaultApiVersionPerRegion = array(
        'AU' => 'v2',
        'CA' => 'v2',
        'ES' => 'v1',
        'GB' => 'v2',
        'NZ' => 'v2',
        'US' => 'v2',
    );

    /**
     * @param $region
     * @return string
     */
    public function getApiVersionPerRegion($region = '')
    {
        if (isset($this->defaultApiVersionPerRegion[$region])) {
            return $this->defaultApiVersionPerRegion[$region];
        }
        return json_encode(array($region));
    }

    /**
     * @return mixed
     * @throws \Afterpay\SDK\Exception\InvalidArgumentException
     * @throws \Afterpay\SDK\Exception\NetworkException
     * @throws \Afterpay\SDK\Exception\ParsingException
     */
    public function postProcess()
    {
        $paymentObjData = array();
        $context = Context::getContext();
        $paymentObjData['currency'] = $context->currency->iso_code;
        $paymentObjData['region'] = Configuration::get('CLEARPAY_REGION');

        /** @var Cart $paymentObjData['cart'] */
        $paymentObjData['cart'] = $context->cart;
        $paymentObjData['shippingAddress'] = new Address($paymentObjData['cart']->id_address_delivery);
        $shippingCountryObj = new Country($paymentObjData['shippingAddress']->id_country);
        $paymentObjData['shippingCountryCode'] = $shippingCountryObj->iso_code;
        $shippingStateObj = new State($paymentObjData['shippingAddress']->id_state);
        $paymentObjData['shippingStateCode'] = '';
        if (!empty($paymentObjData['shippingAddress']->id_state)) {
            $paymentObjData['shippingStateCode'] = $shippingStateObj->iso_code;
        }

        $paymentObjData['billingAddress'] = new Address($paymentObjData['cart']->id_address_invoice);
        $paymentObjData['billingCountryCode'] = Country::getIsoById($paymentObjData['billingAddress']->id_country);
        $billingStateObj = new State($paymentObjData['billingAddress']->id_state);
        $paymentObjData['billingStateCode'] = '';
        if (!empty($paymentObjData['billingAddress']->id_state)) {
            $paymentObjData['billingStateCode'] = $billingStateObj->iso_code;
        }
        $paymentObjData['countryCode'] = $this->getCountryCode($paymentObjData);

        $paymentObjData['discountAmount'] = $paymentObjData['cart']->getOrderTotal(true, Cart::ONLY_DISCOUNTS);

        /** @var Carrier $paymentObjData['carrier'] */
        $paymentObjData['carrier'] = new Carrier($paymentObjData['cart']->id_carrier);

        /** @var Customer $paymentObjData['customer'] */
        $paymentObjData['customer'] = $context->customer;

        if (!$paymentObjData['cart']->id) {
            Tools::redirect('index.php?controller=order');
        }

        $paymentObjData['urlToken'] = Tools::strtoupper(md5(uniqid(rand(), true)));

        $paymentObjData['koUrl'] = $context->link->getPageLink(
            'order',
            null,
            null,
            array('step'=>3)
        );
        $paymentObjData['cancelUrl'] = (!empty(Configuration::get('CLEARPAY_URL_KO'))) ?
            Configuration::get('CLEARPAY_URL_KO') : $paymentObjData['koUrl'];
        $paymentObjData['publicKey'] = Configuration::get('CLEARPAY_PUBLIC_KEY');
        $paymentObjData['secretKey'] = Configuration::get('CLEARPAY_SECRET_KEY');
        $paymentObjData['environment'] = Configuration::get('CLEARPAY_ENVIRONMENT');

        $query = array(
            'id_cart' => $paymentObjData['cart']->id,
            'key' => $paymentObjData['cart']->secure_key,
        );
        $paymentObjData['okUrl'] = _PS_BASE_URL_SSL_.__PS_BASE_URI__
            .'index.php?canonical=true&fc=module&module=clearpay&controller=notify'
            .'&token='.$paymentObjData['urlToken'] . '&' . http_build_query($query)
        ;
        \Afterpay\SDK\Model::setAutomaticValidationEnabled(true);
        $clearpayPaymentObj = new CreateCheckout();
        $clearpayMerchantAccount = new ClearpayMerchantAccount();
        $clearpayMerchantAccount
            ->setMerchantId($paymentObjData['publicKey'])
            ->setSecretKey($paymentObjData['secretKey'])
            ->setApiEnvironment($paymentObjData['environment'])
        ;
        if (!is_null($paymentObjData['countryCode'])) {
            $clearpayMerchantAccount->setCountryCode($paymentObjData['countryCode']);
        }

        $clearpayPaymentObj
            ->setMerchant(array(
                'redirectConfirmUrl' => $paymentObjData['okUrl'],
                'redirectCancelUrl' => $paymentObjData['cancelUrl']
            ))
            ->setMerchantAccount($clearpayMerchantAccount)
            ->setAmount(
                Clearpay::parseAmount($paymentObjData['cart']->getOrderTotal(true, Cart::BOTH)),
                $paymentObjData['currency']
            )
            ->setTaxAmount(
                Clearpay::parseAmount(
                    $paymentObjData['cart']->getOrderTotal(true, Cart::BOTH)
                    -
                    $paymentObjData['cart']->getOrderTotal(false, Cart::BOTH)
                ),
                $paymentObjData['currency']
            )
            ->setConsumer(array(
                'phoneNumber' => $paymentObjData['billingAddress']->phone,
                'givenNames' => $paymentObjData['customer']->firstname,
                'surname' => $paymentObjData['customer']->lastname,
                'email' => $paymentObjData['customer']->email
            ))
            ->setBilling(array(
                'name' => $paymentObjData['billingAddress']->firstname . " " .
                    $paymentObjData['billingAddress']->lastname,
                'line1' => $paymentObjData['billingAddress']->address1,
                'line2' => $paymentObjData['billingAddress']->address2,
                'suburb' => $paymentObjData['billingAddress']->city,
                'area1' => $paymentObjData['billingAddress']->city,
                'state' => $paymentObjData['billingStateCode'],
                'region' => $paymentObjData['billingStateCode'],
                'postcode' => $paymentObjData['billingAddress']->postcode,
                'countryCode' => $paymentObjData['billingCountryCode'],
                'phoneNumber' => $paymentObjData['billingAddress']->phone
            ))
            ->setShipping(array(
                'name' => $paymentObjData['shippingAddress']->firstname . " " .
                    $paymentObjData['shippingAddress']->lastname,
                'line1' => $paymentObjData['shippingAddress']->address1,
                'line2' => $paymentObjData['shippingAddress']->address2,
                'suburb' => $paymentObjData['shippingAddress']->city,
                'area1' => $paymentObjData['shippingAddress']->city,
                'state' => $paymentObjData['shippingStateCode'],
                'region' => $paymentObjData['shippingStateCode'],
                'postcode' => $paymentObjData['shippingAddress']->postcode,
                'countryCode' => $paymentObjData['shippingCountryCode'],
                'phoneNumber' => $paymentObjData['shippingAddress']->phone
            ))
            ->setShippingAmount(
                Clearpay::parseAmount($paymentObjData['cart']->getTotalShippingCost()),
                $paymentObjData['currency']
            )
            ->setCourier(array(
                'shippedAt' => '',
                'name' => $paymentObjData['carrier']->name,
                'tracking' => '',
                'priority' => 'STANDARD'
            ));

        if (!empty($paymentObjData['discountAmount'])) {
            $clearpayPaymentObj->setDiscounts(array(
                array(
                    'displayName' => 'Shop discount',
                    'amount' => array(
                        Clearpay::parseAmount($paymentObjData['discountAmount']),
                        $paymentObjData['currency']
                    )
                )
            ));
        }

        $items = $paymentObjData['cart']->getProducts();
        $products = array();
        foreach ($items as $item) {
            $products[] = array(
                'name' => utf8_encode($item['name']),
                'sku' => $item['reference'],
                'quantity' => (int) $item['quantity'],
                'price' => array(
                    'amount' => Clearpay::parseAmount($item['price_wt']),
                    'currency' => $paymentObjData['currency']
                )
            );
        }
        $clearpayPaymentObj->setItems($products);

        $apiVersion = $this->getApiVersionPerRegion($paymentObjData['region']);
        if ($apiVersion === 'v1') {
            $clearpayPaymentObj = $this->addPaymentV1Options($clearpayPaymentObj, $paymentObjData);
        } else {
            $clearpayPaymentObj = $this->addPaymentV2Options($clearpayPaymentObj, $paymentObjData);
        }

        $header = $this->module->name . '/' . $this->module->version
            . '(Prestashop/' . _PS_VERSION_ . '; PHP/' . phpversion() . '; Merchant/' . $paymentObjData['publicKey']
            . ') ' . _PS_BASE_URL_SSL_.__PS_BASE_URI__;
        $clearpayPaymentObj->addHeader('User-Agent', $header);
        $clearpayPaymentObj->addHeader('Country', $paymentObjData['countryCode']);

        $url = $paymentObjData['cancelUrl'];
        if (!$clearpayPaymentObj->isValid()) {
            $this->saveLog($clearpayPaymentObj->getValidationErrors(), 2);
            return Tools::redirect($url);
        }

        $endPoint = '/' . $apiVersion . '/';
        $endPoint .= ($apiVersion === 'v2') ? "checkouts": "orders";
        $clearpayPaymentObj->setUri($endPoint);

        $clearpayPaymentObj->send();
        $errorMessage = 'empty response';
        if ($clearpayPaymentObj->getResponse()->getHttpStatusCode() >= 400
            || isset($clearpayPaymentObj->getResponse()->getParsedBody()->errorCode)
        ) {
            if (isset($clearpayPaymentObj->getResponse()->getParsedBody()->message)) {
                $errorMessage = $clearpayPaymentObj->getResponse()->getParsedBody()->message;
            }
            $errorMessage .= $this->l('. Status code: ')
                . $clearpayPaymentObj->getResponse()->getHttpStatusCode()
            ;
            $this->saveLog(
                $this->l('Error received when trying to create a order: ') .
                $errorMessage . '. URL: ' . $clearpayPaymentObj->getApiEnvironmentUrl().$clearpayPaymentObj->getUri(),
                2
            );

            return Tools::redirect($url);
        }

        try {
            $url = $clearpayPaymentObj->getResponse()->getParsedBody()->redirectCheckoutUrl;
            $orderId = $clearpayPaymentObj->getResponse()->getParsedBody()->token;
            $cartId = pSQL($paymentObjData['cart']->id);
            $orderId = pSQL($orderId);
            $urlToken = pSQL($paymentObjData['urlToken']);
            $countryCode = pSQL($paymentObjData['countryCode']);
            $sql = "INSERT INTO `" . _DB_PREFIX_ . "clearpay_order` (`id`, `order_id`, `token`, `country_code`) 
            VALUES ('$cartId','$orderId', '$urlToken', '$countryCode')";
            $result = Db::getInstance()->execute($sql);
            if (!$result) {
                throw new \Exception('Unable to save clearpay-order-id in database: '. $sql);
            }
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 3);
            $url = $paymentObjData['cancelUrl'];
        }

        return Tools::redirect($url);
    }

    /**
     * @param CreateCheckout $clearpayPaymentObj
     * @param array $paymentObjData
     * @return CreateCheckout
     */
    private function addPaymentV1Options(CreateCheckout $clearpayPaymentObj, $paymentObjData)
    {
        $clearpayPaymentObj->setTotalAmount(
            Clearpay::parseAmount($paymentObjData['cart']->getOrderTotal(true, Cart::BOTH)),
            $paymentObjData['currency']
        );
        return $clearpayPaymentObj;
    }

    /**
     * @param CreateCheckout $clearpayPaymentObj
     * @param array $paymentObjData
     * @return CreateCheckout
     */
    private function addPaymentV2Options(CreateCheckout $clearpayPaymentObj, $paymentObjData)
    {
        $clearpayPaymentObj->setAmount(
            Clearpay::parseAmount($paymentObjData['cart']->getOrderTotal(true, Cart::BOTH)),
            $paymentObjData['currency']
        );
        return $clearpayPaymentObj;
    }

    /**
     * @param array $paymentObjData
     * @return string|null
     */
    private function getCountryCode($paymentObjData)
    {
        $allowedCountries = json_decode(Configuration::get('CLEARPAY_ALLOWED_COUNTRIES'));
        $lang = Language::getLanguage($this->context->language->id);
        $langArray = explode("-", $lang['language_code']);
        if (count($langArray) != 2 && isset($lang['locale'])) {
            $langArray = explode("-", $lang['locale']);
        }
        $language = Tools::strtoupper($langArray[count($langArray)-1]);
        // Prevent null language detection
        if (in_array(Tools::strtoupper($language), $allowedCountries)) {
            return $language;
        }

        $shippingAddress = new Address($paymentObjData['cart']->id_address_delivery);
        if ($shippingAddress) {
            $language = Country::getIsoById($paymentObjData['shippingAddress']->id_country);
            if (in_array(Tools::strtoupper($language), $allowedCountries)) {
                return $language;
            }
        }
        $billingAddress = new Address($paymentObjData['cart']->id_address_invoice);
        if ($billingAddress) {
            $language = Country::getIsoById($paymentObjData['billingAddress']->id_country);
            if (in_array(Tools::strtoupper($language), $allowedCountries)) {
                return $language;
            }
        }
        return null;
    }
}
