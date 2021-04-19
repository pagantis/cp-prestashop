<?php
/**
 * This file is part of the official Clearpay module for PrestaShop.
 *
 * @author    Clearpay <integrations@clearpay.com>
 * @copyright 2019 Clearpay
 * @license   proprietary
 */

require_once('AbstractController.php');

use Afterpay\SDK\HTTP\Request as ClearpayRequest;
use Afterpay\SDK\HTTP\Request\ImmediatePaymentCapture as ClearpayImmediatePaymentCaptureRequest;
use Afterpay\SDK\MerchantAccount as ClearpayMerchant;

/**
 * Class ClearpayNotifyModuleFrontController
 */
class ClearpayNotifyModuleFrontController extends AbstractController
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
     * @throws Exception
     */
    public function postProcess()
    {
        // Validations
        try {
            $this->prepareVariables();
            if (!is_null($this->merchantOrderId)) {
                $this->finishProcess(false);
            }
            $this->checkConcurrency();
            $this->getMerchantCart();
            $this->getClearpayOrderId();
            $this->getClearpayOrder();
            $this->validateAmount();
            $this->checkMerchantOrderStatus();
        } catch (\Exception $exception) {
            return $this->cancelProcess($exception->getMessage());
        }
        // Process Clearpay Order
        try {
            $this->captureClearpayPayment();
        } catch (\Exception $exception) {
            return $this->cancelProcess($exception->getMessage());
        }

        // Process Merchant Order
        try {
            $this->processMerchantOrder();
        } catch (\Exception $exception) {
            $this->rollbackMerchantOrder();
            return $this->cancelProcess($exception->getMessage());
        }

        try {
            $this->unblockConcurrency($this->merchantCartId);
        } catch (\Exception $exception) {
            $this->saveLog($exception->getMessage(), 3);
        }

        return $this->finishProcess(false);
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
        $this->token = Tools::getValue('token');
        $this->merchantCartId = Tools::getValue('id_cart');

        if ($this->merchantCartId == '') {
            throw new \Exception("Merchant cart id not provided in callback url");
        }

        $callbackOkUrl = $this->context->link->getPageLink('order-confirmation', null, null);
        $callbackKoUrl = $this->context->link->getPageLink('order', null, null, array('step'=>3));

        $this->config = array(
            'urlOK' => $callbackOkUrl,
            'urlKO' => $callbackKoUrl,
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
        try {
            $this->merchantCart = new Cart($this->merchantCartId);
            if (!Validate::isLoadedObject($this->merchantCart)) {
                // This exception is only for Prestashop
                throw new \Exception('Unable to load cart');
            }
            if ($this->merchantCart->secure_key != $this->config['secureKey']) {
                throw new \Exception('Secure Key is not valid');
            }
        } catch (\Exception $exception) {
            throw new \Exception('Unable to find cart with id' . $this->merchantCartId);
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
            throw new \Exception($this->l('Unable to retrieve order from ') . self::PRODUCT_NAME .
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
        $totalAmount = (string) $cpAmount;
        $merchantAmount = (string) ($this->merchantCart->getOrderTotal(true, Cart::BOTH));
        if ($totalAmount != $merchantAmount) {
            $numberClearpayAmount = (integer) (100 * $cpAmount);
            $numberMerchantAmount = (integer) (100 * $this->merchantCart->getOrderTotal(true, Cart::BOTH));
            $amountDff =  $numberMerchantAmount - $numberClearpayAmount;
            if (abs($amountDff) > self::MISMATCH_AMOUNT_THRESHOLD) {
                $this->mismatchError = true;
                $amountMismatchError = 'Amount mismatch in PrestaShop Cart #'. $this->merchantCartId .
                    ' compared with ' . self::PRODUCT_NAME . ' Order: ' . $this->clearpayOrderId .
                    '. The Cart in PrestaShop has an amount of: ' . $merchantAmount . ' and in ' . self::PRODUCT_NAME .
                    ' of: ' . $totalAmount;

                $this->saveLog($amountMismatchError, 3);
                throw new \Exception($amountMismatchError);
            }
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
        $immediatePaymentCaptureRequest = new ClearpayImmediatePaymentCaptureRequest(array(
            'token' => $this->clearpayOrder->token
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
        try {
            $this->module->validateOrder(
                $this->merchantCartId,
                Configuration::get('PS_OS_PAYMENT'),
                $cpAmount,
                self::PRODUCT_NAME,
                'clearpayOrderId: ' .  $this->clearpayCapturedPaymentId,
                array('transaction_id' => $this->clearpayCapturedPaymentId),
                null,
                false,
                $this->config['secureKey']
            );
            $this->updateClearpayOrder();
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
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
     * Do all the necessary actions to cancel the confirmation process in case of error
     * 1. Unblock concurrency
     * 2. Save log
     *
     * @param string $message
     * @return mixed
     */
    public function cancelProcess($message = '')
    {
        $this->saveLog($message, 2);
        return $this->finishProcess(true);
    }

    /**
     * Redirect the request to the e-commerce or show the output in json
     *
     * @param bool $error
     */
    public function finishProcess($error = true)
    {
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
        $url = ($error)? $this->config['urlKO'] : $this->config['urlOK'];
        return $this->redirect($url, $parameters);
    }
}
