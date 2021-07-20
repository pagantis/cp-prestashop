<?php
/**
 * This file is part of the official Clearpay module for PrestaShop.
 *
 * @author    Clearpay <integrations@clearpay.com>
 * @copyright 2020 Clearpay
 * @license   proprietary
 */

use Afterpay\SDK\HTTP\Request as ClearpayRequest;
use Afterpay\SDK\HTTP\Request\ImmediatePaymentCapture as ClearpayImmediatePaymentCaptureRequest;
use Afterpay\SDK\MerchantAccount as ClearpayMerchant;
use Afterpay\SDK\HTTP\Request\CreateCheckout;
use PrestaShop\PrestaShop\Core\Crypto\Hashing;

require_once('AbstractController.php');

/**
 * Class ClearpayExpressModuleFrontController
 */
class ClearpayExpressModuleFrontController extends AbstractController
{
    /** Product Name */
    const PRODUCT_NAME = "Clearpay";

    /** Cart tablename */
    const CART_TABLE = 'clearpay_cart_process';

    /** Clearpay orders tablename */
    const ORDERS_TABLE = 'clearpay_order';

    /**
     * Seconds to expire a locked request
     */
    const CONCURRENCY_TIMEOUT = 1;

    /**
     * mismatch amount threshold in cents
     */
    const MISMATCH_AMOUNT_THRESHOLD = 1;

    /**
     * @var bool $mismatchError
     */
    protected $mismatchError = false;


    /**
     * @var bool $paymentDeclined
     */
    protected $paymentDeclined = false;

    /**
     * @var string $token
     */
    protected $token;

    /**
     * @var int $merchantOrderId
     */
    protected $merchantOrderId = null;

    /**
     * @var \Order $merchantOrder
     */
    protected $merchantOrder;

    /**
     * @var int $merchantCartId
     */
    protected $merchantCartId;

    /**
     * @var \Cart $merchantCart
     */
    protected $merchantCart;

    /**
     * @var string $clearpayOrderId
     */
    protected $clearpayOrderId;

    /**
     * @var string $clearpayCapturedPaymentId
     */
    protected $clearpayCapturedPaymentId;

    /**
     * @var ClearpayMerchant $clearpayMerchantAccount
     */
    protected $clearpayMerchantAccount;

    /**
     * @var Object $clearpayOrder
     */
    protected $clearpayOrder;

    /**
     * @var mixed $config
     */
    protected $config;
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
     * @var Object $jsonResponse
     */
    protected $jsonResponse;

    /** @var string $language */
    protected $language;

    /**
     * @param $func
     * @param $params
     * @return string
     */
    public function __call($func, $params)
    {
        if (in_array($func, array('l')) && !method_exists($this, $func)) {
            return $params[0];
        }
    }

    /**
     * @return mixed
     * @throws \Afterpay\SDK\Exception\InvalidArgumentException
     * @throws \Afterpay\SDK\Exception\NetworkException
     * @throws \Afterpay\SDK\Exception\ParsingException
     */
    public function postProcess()
    {
        if (Tools::getValue('action') === 'start_express_process') {
            header('Content-type: application/json; charset=utf-8');
            echo json_encode($this->createClearpayOrder());
            exit;
        }

        if (Tools::getValue('action') === 'get_shipping_methods') {
            header('Content-type: application/json; charset=utf-8');
            echo json_encode($this->getShippingMethods());
            exit;
        }
        if (Tools::getValue('action') === 'complete_order') {
            // use shippingOptionIdentifier order field to set the carrier
            $this->captureClearpayOrder();
        }
    }

    /**
     *
     */
    protected function createClearpayOrder()
    {
        try {
            $context = Context::getContext();
            $publicKey = Configuration::get('CLEARPAY_PUBLIC_KEY');
            $cart = $context->cart;
            $discountAmount = $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
            $cartUrl = $context->link->getPageLink(
                'cart',
                null,
                null,
                array('action' => 'show')
            );
            $countryCode = null;
            $allowedCountries = json_decode(Configuration::get('CLEARPAY_ALLOWED_COUNTRIES'));
            $lang = Language::getLanguage($context->language->id);

            $langArray = explode("-", $lang['language_code']);
            if (count($langArray) != 2 && isset($lang['locale'])) {
                $langArray = explode("-", $lang['locale']);
            }
            $language = Tools::strtoupper($langArray[count($langArray)-1]);
            if($language == "US") {
                $language = "GB";
            }
            // Prevent null language detection
            if (in_array(Tools::strtoupper($language), $allowedCountries)) {
                $countryCode = $language;
            }

            \Afterpay\SDK\Model::setAutomaticValidationEnabled(true);
            $clearpayPaymentObj = new CreateCheckout();
            $clearpayMerchantAccount = new ClearpayMerchant();
            $clearpayMerchantAccount
                ->setMerchantId($publicKey)
                ->setSecretKey( Configuration::get('CLEARPAY_SECRET_KEY'))
                ->setApiEnvironment(Configuration::get('CLEARPAY_ENVIRONMENT'))
            ;
            if (!is_null($countryCode)) {
                $clearpayMerchantAccount->setCountryCode($countryCode);
            }

            $clearpayPaymentObj
                ->setMode('express')
                ->setMerchant(array(
                    'popupOriginUrl' => $cartUrl
                ))
                ->setMerchantAccount($clearpayMerchantAccount)
                ->setTaxAmount(
                    Clearpay::parseAmount(
                        $cart->getOrderTotal(true, Cart::BOTH)
                        -
                        $cart->getOrderTotal(false, Cart::BOTH)
                    ),
                    $context->currency->iso_code
                );

            if (!empty($discountAmount)) {
                $clearpayPaymentObj->setDiscounts(array(
                    array(
                        'displayName' => 'Shop discount',
                        'amount' => array(
                            Clearpay::parseAmount($discountAmount),
                            $context->currency->iso_code
                        )
                    )
                ));
            }

            $items = $cart->getProducts();
            $products = array();
            foreach ($items as $item) {
                $products[] = array(
                    'name' => utf8_encode($item['name']),
                    'sku' => $item['reference'],
                    'quantity' => (int) $item['quantity'],
                    'price' => array(
                        'amount' => Clearpay::parseAmount($item['price_wt']),
                        'currency' => $context->currency->iso_code
                    )
                );
            }
            $clearpayPaymentObj->setItems($products);

            $apiVersion = $this->getApiVersionPerRegion(Configuration::get('CLEARPAY_REGION'));
            if ($apiVersion === 'v1') {
                $clearpayPaymentObj->setTotalAmount(
                    Clearpay::parseAmount($cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING)),
                    $context->currency->iso_code
                );
            } else {
                $clearpayPaymentObj->setAmount(
                    Clearpay::parseAmount($cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING)),
                    $context->currency->iso_code
                );
            }

            $header = $this->module->name . '/' . $this->module->version
                . ' (Prestashop/' . _PS_VERSION_ . '; PHP/' . phpversion() . '; Merchant/' . $publicKey
                . ') ' . _PS_BASE_URL_SSL_.__PS_BASE_URI__;
            $clearpayPaymentObj->addHeader('User-Agent', $header);
            $clearpayPaymentObj->addHeader('Country', $countryCode);
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 3);
                return array(
                    'success'  => false,
                    'message'  => $exception->getMessage(),
                    'redirect' => $cartUrl
                );
        }

        if (!$clearpayPaymentObj->isValid()) {

            $this->saveLog($clearpayPaymentObj->getValidationErrors(), 2);
            return array(
                'success'  => false,
                'message'  => $clearpayPaymentObj->getValidationErrors(),
                'redirect' => $cartUrl
            );
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
            $errorMessage .= '. Status code: ' . $clearpayPaymentObj->getResponse()->getHttpStatusCode();
            $logMessage = 'Error received when trying to create a order: ' .
                $errorMessage . '. URL: ' . $clearpayPaymentObj->getApiEnvironmentUrl().$clearpayPaymentObj->getUri();
            $this->saveLog(
                $logMessage,
                2
            );

            return array(
                'success'  => false,
                'message'  => $logMessage,
                'redirect' => $cartUrl
            );
        }

        try {
            $orderId = $clearpayPaymentObj->getResponse()->getParsedBody()->token;
            $cartId = pSQL($cart->id);
            $orderId = pSQL($orderId);
            $urlToken = Tools::strtoupper(md5(uniqid(rand(), true)));
            $countryCode = pSQL($countryCode);
            $sql = "INSERT INTO `" . _DB_PREFIX_ . "clearpay_order` (`id`, `order_id`, `token`, `country_code`) 
            VALUES ('$cartId','$orderId', '$urlToken', '$countryCode')";
            $result = Db::getInstance()->execute($sql);
            if (!$result) {
                throw new \Exception('Unable to save clearpay-order-id in database: '. $sql);
            }
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 3);
            return array(
                'success'  => false,
                'message'  => $exception->getMessage(),
                'redirect' => $cartUrl
            );
        }

        return array(
            'success' => true,
            'token'  => $orderId,
            'urlToken' => $urlToken,
        );
    }

    /**
     * @param $methodId
     * @return array
     */
    protected function getShippingMethods($methodId = null)
    {
        $context = Context::getContext();
        $availableCarriers = $this->getAvailableCarriers();
        $shippingMethods = array();
        foreach ($availableCarriers as $key => $availableCarrier) {
            $availableCarriers[$key]['shipping_cost'] = $context->cart->getOrderShippingCost($key);
            $currentMethod = array(
                "id" => $availableCarriers[$key]['id_carrier'],
                "name" => $availableCarriers[$key]['name'],
                "description" => $availableCarriers[$key]['delay'],
                "shippingAmount" => array(
                    "amount" => (string) $context->cart->getOrderShippingCost($key),
                    "currency" => $context->currency->iso_code
                ),
                "orderAmount" => array(
                    "amount" => (string) ($this->context->cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING) +
                        $context->cart->getOrderShippingCost($key)),
                    "currency" => $context->currency->iso_code
                )
            );
            if (!empty($methodId) && $methodId == $availableCarriers[$key]['id_carrier']) {
                return $currentMethod;
            }
            $shippingMethods[] = $currentMethod;
        }

        return $shippingMethods;
    }

    /**
     * @return array
     */
    private function getAvailableCarriers()
    {
        $sql = 'SELECT mc.`id_reference`
			FROM `'._DB_PREFIX_.'module_carrier` mc
			WHERE mc.`id_module` = '. $this->module->id;
        $moduleCarriers = Db::getInstance()->ExecuteS($sql);
        $returnCarriers = array();
        $allCarriers = Carrier::getCarriers($this->context->language->id, true);

        foreach ($moduleCarriers as $key => $reference) {
            foreach ($allCarriers as $carrier) {
                if ($carrier['id_carrier'] == $reference['id_reference']) {
                    $returnCarriers[$reference['id_reference']] = $carrier;
                }
            }
        }
        return $returnCarriers;
    }

    /**
     *
     */
    private function captureClearpayOrder ()
    {

        // Validations
        try {
            $this->prepareVariables();
            if (!empty($this->merchantOrderId)) {
                header('Content-type: application/json; charset=utf-8');
                echo json_encode(array(
                    'success'  => true,
                    'url'  => $this->getReturnUrl(false),
                ));
                exit;
            }
            $this->checkConcurrency();
            $this->getMerchantCart();
            $this->getClearpayOrderId();
            $this->getClearpayOrder();
            $this->validateAmount();
            $this->checkMerchantOrderStatus();
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 3);
            header('Content-type: application/json; charset=utf-8');
            echo json_encode(array(
                'success'  => false,
                'message'  => $exception->getMessage(),
                'url'  => $this->getReturnUrl(true),
            ));
            exit;
        }

        // Process Clearpay Order
        try {
            $this->captureClearpayPayment();
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 3);
            header('Content-type: application/json; charset=utf-8');
            echo json_encode(array(
                'success'  => false,
                'message'  => $exception->getMessage(),
                'url'  => $this->getReturnUrl(true),
            ));
            exit;
        }

        // Process Merchant Order
        try {
            $this->processMerchantOrder();
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 3);
            $this->rollbackMerchantOrder();
            header('Content-type: application/json; charset=utf-8');
            echo json_encode(array(
                'success'  => false,
                'message'  => $exception->getMessage(),
                'url'  => $this->getReturnUrl(true),
            ));
            exit;
        }

        try {
            $this->unblockConcurrency($this->merchantCartId);
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 3);
        }
        //all goes well
        header('Content-type: application/json; charset=utf-8');
        echo json_encode(array(
            'success'  => true,
            'url'  => $this->getReturnUrl(false),
        ));
        exit;
    }

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
     * Check the concurrency of the purchase
     *
     * @throws Exception
     */
    public function checkConcurrency()
    {
        $this->unblockConcurrency();
        $this->blockConcurrency($this->merchantCartId);
    }

    /**
     * Find and init variables needed to process payment
     *
     * @throws Exception
     */
    public function prepareVariables()
    {
        $this->token = Tools::getValue('urlToken');
        $this->merchantCartId = $this->context->cart->id;

        if ($this->merchantCartId == '') {
            throw new \Exception("Merchant cart id not provided in callback url");
        }

        $callbackOkUrl = $this->context->link->getPageLink('order-confirmation', null, null);

        $this->config = array(
            'urlOK' => $callbackOkUrl,
            'secureKey' => Tools::getValue('key'),
        );

        $this->config['publicKey'] = Configuration::get('CLEARPAY_PUBLIC_KEY');
        $this->config['privateKey'] = Configuration::get('CLEARPAY_SECRET_KEY');
        $this->config['environment'] = Configuration::get('CLEARPAY_ENVIRONMENT');
        $this->config['region'] = Configuration::get('CLEARPAY_REGION');
        $this->config['apiVersion'] = $this->getApiVersionPerRegion($this->config['region']);

        $this->merchantOrderId = $this->getMerchantOrderId();

        $countryCode = $this->getClearpayOrderCountryCode();
        $this->clearpayMerchantAccount = new ClearpayMerchant();
        $this->clearpayMerchantAccount
            ->setMerchantId($this->config['publicKey'])
            ->setSecretKey($this->config['privateKey'])
            ->setApiEnvironment($this->config['environment'])
        ;
        if (!is_null($countryCode)) {
            $this->clearpayMerchantAccount->setCountryCode($countryCode);
        }

        if (!($this->config['secureKey'] && Module::isEnabled(self::CODE))) {
            // This exception is only for Prestashop
            throw new \Exception('Can\'t process ' . self::PRODUCT_NAME . ' order, module may not be enabled');
        }
    }

    /**
     * Find prestashop Cart Id
     */
    public function getMerchantOrderId()
    {
        $table = _DB_PREFIX_.self::ORDERS_TABLE;
        $merchantCartId = (int)$this->merchantCartId;
        $token = pSQL($this->token);
        $sql = "select ps_order_id from `{$table}` where id = {$merchantCartId}
         and token = '{$token}'";

        return Db::getInstance()->getValue($sql);
    }

    /**
     * Retrieve the merchant order by id
     *
     * @throws Exception
     */
    public function getMerchantCart()
    {
        $this->merchantCart = new Cart($this->merchantCartId);
        if (!Validate::isLoadedObject($this->merchantCart)) {
            // This exception is only for Prestashop
            throw new \Exception('Unable to load cart with id' . $this->merchantCartId);
        }
        if ($this->merchantCart->secure_key != $this->config['secureKey']) {
            throw new \Exception('Secure Key is not valid');
        }
    }

    /**
     * Find Clearpay Order Id
     *
     * @throws Exception
     */
    private function getClearpayOrderId()
    {
        $token = pSQL($this->token);
        $sql = "select order_id from `" . _DB_PREFIX_ . "clearpay_order` where id = "
            .(int)$this->merchantCartId . " and token = '" . $token . "'";
        $this->clearpayOrderId = Db::getInstance()->getValue($sql);

        if (empty($this->clearpayOrderId)) {
            throw new \Exception(self::PRODUCT_NAME . ' order id not found on clearpay_orders table');
        }
    }

    /**
     * Find Clearpay country code
     *
     * @throws Exception
     */
    private function getClearpayOrderCountryCode()
    {
        $token = pSQL($this->token);
        $sql = "select country_code from `" . _DB_PREFIX_ . "clearpay_order` where id = "
            .(int)$this->merchantCartId . " and token = '" . $token . "'";
        return Db::getInstance()->getValue($sql);
    }

    /**
     * Find Clearpay Order in Orders Server using Clearpay SDK
     *
     * @throws Exception
     */
    private function getClearpayOrder()
    {
        $getOrderRequest = new ClearpayRequest();
        $uri = '/' . $this->config['apiVersion'] . '/';
        $uri .= ($this->config['apiVersion'] === 'v1') ? 'orders/' : 'checkouts/';
        $getOrderRequest
            ->setMerchantAccount($this->clearpayMerchantAccount)
            ->setUri($uri . $this->clearpayOrderId)
        ;
        $getOrderRequest->send();

        if ($getOrderRequest->getResponse()->getHttpStatusCode() >= 400) {
            throw new \Exception('Unable to retrieve order from ' . self::PRODUCT_NAME .
                ': ' . $this->clearpayOrderId);
        }
        $this->clearpayOrder = $getOrderRequest->getResponse()->getParsedBody();
    }

    /**
     * Check that the merchant order and the order in Clearpay have the same amount to prevent hacking
     *
     * @throws Exception
     */
    public function validateAmount()
    {
        if ($this->config['apiVersion'] === 'v1') {
            $cpAmount = $this->clearpayOrder->totalAmount->amount;
        } else {
            $cpAmount = $this->clearpayOrder->amount->amount;
        }

        $numberClearpayAmount = (integer) (100 * $cpAmount);
        $ClearpayShippingOption = $this->getShippingMethods($this->clearpayOrder->shippingOptionIdentifier);
        $numberMerchantAmount = (integer) (100 * $this->merchantCart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING)) +
            (integer) (100 * $ClearpayShippingOption['shippingAmount']['amount']);
        $merchantAmount = (string)($numberMerchantAmount/100);
        $amountDff =  $numberMerchantAmount - $numberClearpayAmount;

        if (abs($amountDff) > self::MISMATCH_AMOUNT_THRESHOLD) {
            $this->mismatchError = true;
            $amountMismatchError = 'Amount mismatch in PrestaShop Cart #'. $this->merchantCartId .
                ' compared with ' . self::PRODUCT_NAME . ' Order: ' . $this->clearpayOrderId .
                '. The Cart in PrestaShop has an amount of: ' . $merchantAmount . ' and in ' . self::PRODUCT_NAME .
                ' of: ' . (string) $cpAmount;

            $this->saveLog($amountMismatchError, 3);
            throw new \Exception($amountMismatchError);
        }

    }

    /**
     * Check that the merchant order was not previously processes and is ready to be paid
     *
     * @throws Exception
     */
    public function checkMerchantOrderStatus()
    {
        try {
            if ($this->merchantCart->orderExists() !== false) {
                throw new \Exception('The cart ' . $this->merchantCartId . ' is already an order, unable to
                create it');
            }

            // Double check
            $tableName = _DB_PREFIX_ . self::ORDERS_TABLE;
            $fieldName = 'ps_order_id';
            $token = pSQL($this->token);
            $clearpayOrderId = pSQL($this->clearpayOrderId);
            $sql = ('select ' . $fieldName . ' from `' . $tableName . '` where `id` = ' . (int)$this->merchantCartId
                . ' and `order_id` = \'' . $clearpayOrderId . '\''
                . ' and `token` = \'' . $token . '\''
                . ' and `' . $fieldName . '` is not null');
            $results = Db::getInstance()->ExecuteS($sql);
            if (is_array($results) && count($results) === 1) {
                $exceptionMessage = sprintf(
                    "Order was already created [cartId=%s][Token=%s][" . self::PRODUCT_NAME . "=%s]",
                    $this->merchantCartId,
                    $this->token,
                    $this->clearpayOrderId
                );
                throw new \Exception($exceptionMessage);
            }
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
        return true;
    }

    /**
     * Confirm the order in Clearpay
     *
     * @throws Exception
     */
    private function captureClearpayPayment()
    {
        $ClearpayShippingOption = $this->getShippingMethods($this->clearpayOrder->shippingOptionIdentifier);
        $numberMerchantAmount =  $this->merchantCart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING) +
            $ClearpayShippingOption['shippingAmount']['amount'];
        $context = Context::getContext();
        $immediatePaymentCaptureRequest = new ClearpayImmediatePaymentCaptureRequest(array(
            'token' => $this->clearpayOrder->token,
            'amount' => array(
                'amount' => $numberMerchantAmount,
                'currency' => $context->currency->iso_code
            )
        ));
        $immediatePaymentCaptureRequest->setMerchantAccount($this->clearpayMerchantAccount);
        $uri = '/' . $this->config['apiVersion'] . '/payments/capture/';
        $immediatePaymentCaptureRequest->setUri($uri);
        $immediatePaymentCaptureRequest->send();
        if ($immediatePaymentCaptureRequest->getResponse()->getHttpStatusCode() >= 400) {
            $this->paymentDeclined = true;
            throw new \Exception(
                self::PRODUCT_NAME . ' capture payment error, order token: ' . $this->token . '. ' .
                $immediatePaymentCaptureRequest->getResponse()->getParsedBody()->errorCode
            );
        }
        $this->clearpayCapturedPaymentId = $immediatePaymentCaptureRequest->getResponse()->getParsedBody()->id;
        if (!$immediatePaymentCaptureRequest->getResponse()->isApproved()) {
            $this->paymentDeclined = true;
            throw new \Exception(
                self::PRODUCT_NAME . ' capture payment error, the payment was not processed successfully'
            );
        }
    }

    /**
     * Process the merchant order and notify client
     *
     * @throws Exception
     */
    public function processMerchantOrder()
    {
        if ($this->config['apiVersion'] === 'v1') {
            $cpAmount = $this->clearpayOrder->totalAmount->amount;
        } else {
            $cpAmount = $this->clearpayOrder->amount->amount;
        }

        //creating the addresses objects
        $billingAddress = new Address();
        $cpAddress = $this->clearpayOrder->shipping;
        $city = '';
        if (isset($cpAddress->area1)) {
            $city = $cpAddress->area1;
        } elseif (isset($cpAddress->suburb)) {
            $city = $cpAddress->suburb;
        }
        $fullName = explode(' ', $cpAddress->name, 2);
        $country = null;
        if (isset($cpAddress->countryCode)) {
            $country = Country::getByIso(substr($cpAddress->countryCode, 0, 3));
        }
        if (empty($country)) {
            $country = Country::getByIso($this->context->language->iso_code);
        }
        $billingAddress->firstname = (isset($fullName[0])) ? $fullName[0] : '';
        $billingAddress->lastname = (isset($fullName[1])) ? $fullName[1] : '';
        $billingAddress->address1 = (isset($cpAddress->line1)) ? $cpAddress->line1 : '';
        $billingAddress->address2 = (isset($cpAddress->line2)) ? $cpAddress->line2 : '';
        $billingAddress->city = $city;
        $billingAddress->other = (isset($cpAddress->region)) ? $cpAddress->region : '';
        $billingAddress->iso_code = $this->context->language->id;
        $billingAddress->id_country = $country;
        $billingAddress->postcode = (isset($cpAddress->postcode)) ? $cpAddress->postcode : '';
        $billingAddress->phone = (isset($cpAddress->phoneNumber)) ? $cpAddress->phoneNumber : '';
        $billingAddress->alias = 'ClearpayExpress:'.$this->merchantCartId;
        $billingAddress->save();
        $this->merchantCart->updateAddressId(0, $billingAddress->id);
        $this->merchantCart->updateDeliveryAddressId(0, $billingAddress->id);
        $this->merchantCart->update();
        $this->merchantCart->save();

        // Creating the customer and guest objects
        $cpConsumer = $this->clearpayOrder->consumer;
        $customer = new Customer();
        $customer->firstname = $cpConsumer->givenNames;
        $customer->lastname = $cpConsumer->surname;
        $customer->email = $cpConsumer->email;
        $customer->passwd = md5(uniqid(rand(), true));
        $customer->save();

        $guest = new Guest();
        $guest->userAgent();
        $guest->id_customer = $customer->id;
        $guest->save();

        //set the customer and the guest ids into the cart
        $this->merchantCart->id_customer = $customer->id;
        $this->merchantCart->id_guest = $guest->id;
        //set the delivery method id into the cart
        $this->merchantCart->id_carrier = $this->clearpayOrder->shippingOptionIdentifier;
        $delivery_option = $this->merchantCart->getDeliveryOption();
        if (isset($delivery_option[0])) {
            unset($delivery_option[0]);
        }
        $delivery_option[$this->merchantCart->id_address_delivery] =
            (string)$this->clearpayOrder->shippingOptionIdentifier.',';
        $this->merchantCart->setDeliveryOption($delivery_option);
        $this->merchantCart->save();

        $validateOrder = $this->module->validateOrder(
            $this->merchantCartId,
            Configuration::get('PS_OS_PAYMENT'),
            $cpAmount,
            self::PRODUCT_NAME,
            'clearpayOrderId: ' .  $this->clearpayCapturedPaymentId,
            array('transaction_id' => $this->clearpayCapturedPaymentId,
                'ClearpayAmount' => $cpAmount,
                "ShippingMethod" => $this->clearpayOrder->shippingOptionIdentifier),
            null,
            false,
            $this->config['secureKey']
        );

        $orderId = Order::getIdByCartId($this->merchantCartId);
        $order = new Order($orderId);
        $taxRate = (100 * $order->total_paid_tax_excl) / $cpAmount;
        $total_paid_tax_excl = round((($cpAmount * $taxRate) / 100), 2);
        $order->total_paid = $cpAmount;
        $order->total_paid_tax_incl = $cpAmount;
        $order->total_paid_tax_excl = $total_paid_tax_excl;

        //Update cart carrier amounts
        $shippingTotalAmount = 0;
        $shippingMethod = $this->getShippingMethods($this->clearpayOrder->shippingOptionIdentifier);
        if (!empty($shippingMethod)) {
            $shippingTotalAmount = $shippingMethod['shippingAmount']['amount'];
        }
        $order->total_shipping = $shippingTotalAmount;
        $order->total_shipping_tax_incl = $shippingTotalAmount;
        $order->total_shipping_tax_excl = round((($shippingTotalAmount * $taxRate) / 100), 2);

        $this->updateClearpayOrder();

        try {
            $token = pSQL($this->token);
            $clearpayOrderId = pSQL($this->clearpayOrderId);
            Db::getInstance()->update(
                self::ORDERS_TABLE,
                array('ps_order_id' => $this->module->currentOrder),
                'id = '. (int)$this->merchantCartId
                . ' and order_id = \'' . $clearpayOrderId . '\''
                . ' and token = \'' . $token . '\''
            );
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 2);
        }

        $message = self::PRODUCT_NAME . ' Order CONFIRMED' .
            '. ' . self::PRODUCT_NAME . ' OrderId=' .  $this->clearpayCapturedPaymentId .
            '. Prestashop OrderId=' . $this->module->currentOrder;
        $this->saveLog($message, 1);
    }

    /**
     * @throws Exception
     */
    private function updateClearpayOrder()
    {
        try {
            if ($this->config['region'] === 'ES') { //ONLY AVAILABLE FOR EUROPE
                $getOrderRequest = new ClearpayRequest();
                $getOrderRequest
                    ->setMerchantAccount($this->clearpayMerchantAccount)
                    ->setUri("/v1/payments/".$this->clearpayCapturedPaymentId)
                    ->setHttpMethod('PUT')
                    ->setRequestBody(json_encode(array("merchantReference" => $this->module->currentOrder)));
                $getOrderRequest->send();
                if ($getOrderRequest->getResponse()->getHttpStatusCode() >= 400) {
                    throw new \Exception('Unable to retrieve order from ' . self::PRODUCT_NAME .
                        ' = ' . $this->clearpayOrderId);
                }

                $this->clearpayOrder = $getOrderRequest->getResponse()->getParsedBody();
            }
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 2);
        }
    }

    /**
     * Leave the merchant order as it was previously
     *
     * @throws Exception
     */
    public function rollbackMerchantOrder()
    {
        try {
            $message = self::PRODUCT_NAME . ' Roolback method called: ' .
                '. ' . self::PRODUCT_NAME . ' OrderId=' . $this->clearpayOrderId .
                '. Prestashop CartId=' . $this->merchantCartId .
                '. Prestashop OrderId=' . $this->merchantOrderId;
            $this->saveLog($message, 2);
            if ($this->module->currentOrder) {
                $objOrder = new Order($this->module->currentOrder);
                $history = new OrderHistory();
                $history->id_order = (int)$objOrder->id;
                $history->changeIdOrderState(8, (int)($objOrder->id));
            }
        } catch (\Exception $exception) {
            $this->saveLog('Error on ' . self::PRODUCT_NAME . ' rollback Transaction: ' .
                '. ' . self::PRODUCT_NAME . ' OrderId=' . $this->clearpayOrderId .
                '. Prestashop CartId=' . $this->merchantCartId .
                '. Prestashop OrderId=' . $this->merchantOrderId .
                $exception->getMessage(), 2);
        }
    }

    /**
     * Lock the concurrency to prevent duplicated inputs
     * @param $cartId
     *
     * @throws Exception
     */
    protected function blockConcurrency($cartId)
    {
        try {
            $table = self::CART_TABLE;
            Db::getInstance()->insert($table, array('id' =>(int)$cartId, 'timestamp' =>(time())));
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @param null $orderId
     *
     * @throws Exception
     */
    private function unblockConcurrency($orderId = null)
    {
        try {
            if (is_null($orderId)) {
                Db::getInstance()->delete(self::CART_TABLE, 'timestamp < ' . (time() - self::CONCURRENCY_TIMEOUT));
                return;
            }
            Db::getInstance()->delete(self::CART_TABLE, 'id = ' . (int)$orderId);
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * Redirect the request to the e-commerce or show the output in json
     *
     * @param bool $error
     */
    public function getReturnUrl($error = true)
    {
        if($error) {
            $context = Context::getContext();
            $url = $context->link->getPageLink(
                'cart',
                null,
                null,
                array('action' => 'show')
            );
        } else {
            $url = $this->config['urlOK'];
        }
        $parameters = array(
            'id_cart' => $this->merchantCartId,
            'key' => $this->config['secureKey'],
            'id_module' => $this->module->id,
            'id_order' => $this->module->currentOrder
        );
        if ($this->mismatchError) {
            $parameters["clearpay_mismatch"] = "true";
        }
        if ($this->paymentDeclined) {
            $parameters["clearpay_declined"] = "true";
            $parameters["clearpay_reference_id"] = $this->clearpayCapturedPaymentId;
        }

        $parsedUrl = parse_url($url);
        $separator = '&';
        if (!isset($parsedUrl['query']) || $parsedUrl['query'] == null) {
            $separator = '?';
        }
        return $url. $separator . http_build_query($parameters);
    }
}
