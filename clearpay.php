<?php
/**
 * This file is part of the official Clearpay module for PrestaShop.
 *
 * @author    Clearpay <integrations@clearpay.com>
 * @copyright 2020 Clearpay
 * @license   proprietary
 */

define('_PS_CLEARPAY_DIR', _PS_MODULE_DIR_. 'clearpay');

require _PS_CLEARPAY_DIR.'/vendor/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class Clearpay
 */
class Clearpay extends PaymentModule
{
    /**
     * Default Region for assets and translations
     */
    const DEFAULT_BRAND = "CP";

    /**
     * Available currency
     */
    const SIMULATOR_IS_ENABLED = true;

    /**
     * JS CDN URL
     */
    const CLEARPAY_JS_CDN_URL = 'https://js.afterpay.com/afterpay-1.x.js';

    /**
     * @var string
     */
    public $url = 'https://clearpay.com';

    /**
     * @var bool
     */
    public $bootstrap = true;

    /** @var string $language */
    public $language;

    /**
     * Default available countries for the different operational regions
     *
     * @var array
     */
    public $defaultCountriesPerRegion = array(
        'AU' => '["AU"]',
        'CA' => '["CA"]',
        'ES' => '["ES", "IT", "FR"]',
        'GB' => '["GB"]',
        'NZ' => '["NZ"]',
        'US' => '["US"]',
    );

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
     * Assets for AfterPay/clearpay brand
     *
     * @var array
     */
    public $brandAssets = array(
        'AP' => array(
            'LOGO_APP' => 'https://static.afterpay.com/app/icon-128x128.png',
            'LOGO_BADGE'=> 'https://static.afterpay.com/email/logo-afterpay-colour.png'
        ),
        'CP' => array(
            'LOGO_APP' => 'https://static.afterpay.com/app/icon-128x128.png',
            'LOGO_BADGE'=> 'https://static.afterpay.com/email/logo-clearpay-colour.png'
        ),
    );
    /**
     * @var null $shippingAddress
     */
    protected $shippingAddress = null;
    /**
     * @var null $billingAddress
     */
    protected $billingAddress = null;

    /**
     * @var array $allowedCountries
     */
    protected $allowedCountries = array();

    /**
     * Clearpay constructor.
     *
     * Define the module main properties so that prestashop understands what are the module requirements
     * and how to manage the module.
     *
     */
    public function __construct()
    {
        $this->name = 'clearpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->author = $this->ll('Clearpay');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->module_key = '1da91d21c9c3427efd7530c2be29182d';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->displayName = $this->ll('Clearpay Payment Gateway');
        $this->description = $this->ll('Buy now, pay later. Always interest-free. Reach new customers, ') .
            $this->ll('increase your conversion rate, recurrency and average order value ofering ') .
            $this->ll('interest-free installments in your eCommerce.');
        $this->currency = 'EUR';
        $this->currencySymbol = '€';
        $context = Context::getContext();
        if (isset($context->currency)) {
            $this->currency = $context->currency->iso_code;
            $this->currencySymbol = $context->currency->sign;
        }

        parent::__construct();
    }

    /**
     * Configure the variables for Clearpay payment method.
     *
     * @return bool
     */
    public function install()
    {
        if (!extension_loaded('curl')) {
            $this->_errors[] =
                $this->ll('You have to enable the cURL extension on your server to install this module');
            return false;
        }
        if (!version_compare(phpversion(), '5.6.0', '>=')) {
            $this->_errors[] = $this->ll('The PHP version bellow 5.3.0 is not supported');
            return false;
        }

        $sql_file = dirname(__FILE__) . '/sql/install.sql';
        $this->loadSQLFile($sql_file);

        Configuration::updateValue('CLEARPAY_IS_ENABLED', 0);
        Configuration::updateValue('CLEARPAY_REGION', 'ES');
        Configuration::updateValue('CLEARPAY_PUBLIC_KEY', '');
        Configuration::updateValue('CLEARPAY_SECRET_KEY', '');
        Configuration::updateValue('CLEARPAY_PRODUCTION_SECRET_KEY', '');
        Configuration::updateValue('CLEARPAY_ENVIRONMENT', 1);
        Configuration::updateValue('CLEARPAY_MIN_AMOUNT', null);
        Configuration::updateValue('CLEARPAY_MAX_AMOUNT', null);
        Configuration::updateValue('CLEARPAY_RESTRICTED_CATEGORIES', '');
        Configuration::updateValue('CLEARPAY_ALLOWED_COUNTRIES', '["ES","FR","IT","GB"]');
        Configuration::updateValue('CLEARPAY_CSS_SELECTOR', 'default');
        Configuration::updateValue('CLEARPAY_URL_OK', '');
        Configuration::updateValue('CLEARPAY_URL_KO', '');
        Configuration::updateValue('CLEARPAY_LOGS', '');

        $return =  (parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayProductPriceBlock')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('displayWrapperTop')
            && $this->registerHook('displayExpressCheckout')
            && $this->registerHook('actionOrderStatusUpdate')
            && $this->registerHook('actionOrderSlipAdd')
            && $this->registerHook('actionProductCancel')
            && $this->registerHook('header')
        );

        if ($return && _PS_VERSION_ < "1.7") {
            $this->registerHook('payment');
        }
        if ($return && version_compare(_PS_VERSION_, '1.6.1', 'lt')) {
            $this->registerHook('displayPaymentTop');
        }

        return $return;
    }

    /**
     * Remove the production private api key and remove the files
     *
     * @return bool
     */
    public function uninstall()
    {
        Configuration::deleteByName('CLEARPAY_IS_ENABLED');
        Configuration::deleteByName('CLEARPAY_PUBLIC_KEY');
        Configuration::deleteByName('CLEARPAY_SECRET_KEY');
        Configuration::deleteByName('CLEARPAY_PRODUCTION_SECRET_KEY');
        Configuration::deleteByName('CLEARPAY_ENVIRONMENT');
        Configuration::deleteByName('CLEARPAY_REGION');
        Configuration::deleteByName('CLEARPAY_MIN_AMOUNT');
        Configuration::deleteByName('CLEARPAY_MAX_AMOUNT');
        Configuration::deleteByName('CLEARPAY_RESTRICTED_CATEGORIES');
        Configuration::deleteByName('CLEARPAY_ALLOWED_COUNTRIES');
        Configuration::deleteByName('CLEARPAY_CSS_SELECTOR');
        Configuration::deleteByName('CLEARPAY_URL_OK');
        Configuration::deleteByName('CLEARPAY_URL_KO');
        Configuration::deleteByName('CLEARPAY_LOGS');

        $sql_file = dirname(__FILE__).'/sql/uninstall.sql';
        $this->loadSQLFile($sql_file);

        return parent::uninstall();
    }

    /**
     * @param $sql_file
     * @return bool
     */
    public function loadSQLFile($sql_file)
    {
        $sql_content = Tools::file_get_contents($sql_file);
        $sql_content = str_replace('PREFIX_', _DB_PREFIX_, $sql_content);
        $sql_requests = preg_split("/;\s*[\r\n]+/", $sql_content);

        $result = true;
        foreach ($sql_requests as $request) {
            if (!empty($request)) {
                $result &= Db::getInstance()->execute(trim($request));
            }
        }

        return $result;
    }

    /**
     * Check amount of order > minAmount
     * Check valid currency
     * Check API variables are set
     *
     * @param string $product
     * @return bool
     */
    public function isPaymentMethodAvailable()
    {
        $cart = $this->context->cart;
        $totalAmount = $cart->getOrderTotal(true, Cart::BOTH);
        $isEnabled = Configuration::get('CLEARPAY_IS_ENABLED');
        $displayMinAmount = Configuration::get('CLEARPAY_MIN_AMOUNT');
        $displayMaxAmount = Configuration::get('CLEARPAY_MAX_AMOUNT');
        $publicKey = Configuration::get('CLEARPAY_PUBLIC_KEY');
        $secretKey = Configuration::get('CLEARPAY_SECRET_KEY');

        $allowedCountries = json_decode(Configuration::get('CLEARPAY_ALLOWED_COUNTRIES'));
        $language = $this->getCurrentLanguage();
        $categoryRestriction = $this->isCartRestricted($this->context->cart);
        return (
            $isEnabled &&
            $totalAmount >= $displayMinAmount &&
            $totalAmount <= $displayMaxAmount &&
            in_array(Tools::strtoupper($language), $allowedCountries) &&
            !$categoryRestriction &&
            $publicKey &&
            $secretKey
        );
    }

    /**
     * @param Cart $cart
     *
     * @return array
     * @throws Exception
     */
    private function getButtonTemplateVars(Cart $cart)
    {
        $currency = new Currency($cart->id_currency);

        return array(
            'button' => '#payment_button',
            'currency_iso' => $currency->iso_code,
            'cart_total' => $cart->getOrderTotal(),
        );
    }

    /**
     * Header hook
     */
    public function hookHeader()
    {
        if (_PS_VERSION_ >= "1.7") {
            $this->context->controller->registerJavascript(
                sha1(mt_rand(1, 90000)),
                self::CLEARPAY_JS_CDN_URL,
                array('server' => 'remote')
            );
        } else {
            $this->context->controller->addJS(self::CLEARPAY_JS_CDN_URL);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function hookPaymentOptions()
    {
        /** @var Cart $cart */
        $cart = $this->context->cart;
        $this->shippingAddress = new Address($cart->id_address_delivery);
        $this->billingAddress = new Address($cart->id_address_invoice);
        $totalAmount = Clearpay::parseAmount($cart->getOrderTotal(true, Cart::BOTH));

        $link = $this->context->link;

        $return = array();
        $this->context->smarty->assign($this->getButtonTemplateVars($cart));
        $templateConfigs = array();
        if ($this->isPaymentMethodAvailable()) {
            $amountWithCurrency = Clearpay::parseAmount($totalAmount/4) . $this->currencySymbol;
            if ($this->currency === 'GBP') {
                $amountWithCurrency = $this->currencySymbol. Clearpay::parseAmount($totalAmount/4);
            }
            $checkoutText = $this->ll('Or 4 interest-free payments of') . ' ' . $amountWithCurrency . ' ';
            $checkoutText .= $this->ll('with');
            if ($this->isOPC()) {
                $checkoutText = $this->ll('4 interest-free payments of') . ' ' . $amountWithCurrency;
            }
            $templateConfigs['TITLE'] = (string) $checkoutText;
            $language = Language::getLanguage($this->context->language->id);
            if (isset($language['locale'])) {
                $language = $language['locale'];
            } else {
                $language = $language['language_code'];
            }
            $templateConfigs['ISO_COUNTRY_CODE'] = str_replace('-', '_', $language);
            // Preserve Uppercase in locale
            if (Tools::strlen($templateConfigs['ISO_COUNTRY_CODE']) == 5) {
                $templateConfigs['ISO_COUNTRY_CODE'] = Tools::substr($templateConfigs['ISO_COUNTRY_CODE'], 0, 2) .
                    Tools::strtoupper(Tools::substr($templateConfigs['ISO_COUNTRY_CODE'], 2, 4));
            }
            $templateConfigs['CURRENCY'] = $this->currency;
            $templateConfigs['MORE_HEADER1'] = $this->ll('Always interest-free.');
            $templateConfigs['MORE_HEADER2'] = $this->ll('No extra documentation. Instant aproval.');
            $templateConfigs['TOTAL_AMOUNT'] = $totalAmount;
            $moreInfo = $this->ll('You will be redirected to Clearpay website to fill out your payment information.');
            $moreInfo .= ' ' .$this->ll('You will be redirected to our site to complete your order. Please note: ');
            $moreInfo .= ' ' . $this->ll('Clearpay can only be used as a payment method for orders with a shipping');
            $moreInfo .= ' ' . $this->ll('and billing address within the UK.');
            $templateConfigs['MOREINFO_ONE'] = $moreInfo;
            $templateConfigs['TERMS_AND_CONDITIONS'] = $this->ll('Terms and conditions');
            $termsLink = $this->ll('https://www.clearpay.co.uk/en-GB/terms-of-service');
            $templateConfigs['TERMS_AND_CONDITIONS_LINK'] = $termsLink;
            $templateConfigs['TERMS_AND_CONDITIONS_LINK'] = $this->ll(
                'https://www.clearpay.co.uk/en-GB/terms-of-service'
            );
            $templateConfigs['LOGO_TEXT'] = $this->ll("Clearpay");
            $icon = $this->getAssetsPerBrand('LOGO_APP');
            $templateConfigs['ICON'] = $icon;
            $badge = $this->getAssetsPerBrand('LOGO_BADGE');
            $templateConfigs['LOGO_BADGE'] = $badge;
            $templateConfigs['LOGO_OPC'] = Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/logo_opc.png');
            $templateConfigs['PAYMENT_URL'] = $link->getModuleLink('clearpay', 'payment');
            $templateConfigs['PS_VERSION'] = str_replace('.', '-', Tools::substr(_PS_VERSION_, 0, 3));

            $this->context->smarty->assign($templateConfigs);

            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $uri = $link->getModuleLink('clearpay', 'payment');
            $paymentOption
                ->setCallToActionText($templateConfigs['TITLE'])
                ->setAction($uri)
                ->setModuleName(__CLASS__);
            if ($this->isOPC()) {
                $paymentOption
                    ->setAdditionalInformation($this->fetch('module:clearpay/views/templates/hook/onepagecheckout.tpl'))
                    ->setLogo($templateConfigs['LOGO_OPC']);
            } else {
                $paymentOption
                    ->setAdditionalInformation($this->fetch('module:clearpay/views/templates/hook/checkout.tpl'))
                    ->setLogo($templateConfigs['LOGO_BADGE']);
            }
            $return[] = $paymentOption;
        }

        return $return;
    }

    /**
     * Get the form for editing the BackOffice options of the module
     *
     * @return array
     */
    private function getConfigForm()
    {
        $inputs = array();
        $inputs[] = array(
            'name' => 'CLEARPAY_IS_ENABLED',
            'type' =>  'switch',
            'label' => $this->ll('Module is enabled'),
            'prefix' => '<i class="icon icon-key"></i>',
            'class' => 't',
            'required' => true,
            'values'=> array(
                array(
                    'id' => 'CLEARPAY_IS_ENABLED_TRUE',
                    'value' => 1,
                    'label' => $this->ll('Yes', get_class($this), null, false),
                ),
                array(
                    'id' => 'CLEARPAY_IS_ENABLED_FALSE',
                    'value' => 0,
                    'label' => $this->ll('No', get_class($this), null, false),
                ),
            )
        );
        if (self::DEFAULT_BRAND === 'CP') {
            $query = array(
                array(
                    'CLEARPAY_REGION_id' => 'ES',
                    'CLEARPAY_REGION_name' => $this->ll('Europe')
                ),
                array(
                    'CLEARPAY_REGION_id' => 'GB',
                    'CLEARPAY_REGION_name' => $this->ll('United Kingdom')
                )
            );
        } else {
            $query = array(
                array(
                    'CLEARPAY_REGION_id' => 'AU',
                    'CLEARPAY_REGION_name' => $this->ll('Australia')
                ),
                array(
                    'CLEARPAY_REGION_id' => 'CA',
                    'CLEARPAY_REGION_name' => $this->ll('Canada')
                ),
                array(
                    'CLEARPAY_REGION_id' => 'NZ',
                    'CLEARPAY_REGION_name' => $this->ll('New Zealand')
                ),
                array(
                    'CLEARPAY_REGION_id' => 'US',
                    'CLEARPAY_REGION_name' => $this->ll('United States')
                )
            );
        }
        $inputs[] = array(
            'name' => 'CLEARPAY_REGION',
            'type' => 'select',
            'label' => $this->ll('API region'),
            'prefix' => '<i class="icon icon-key"></i>',
            'class' => 't',
            'required' => true,
            'options' => array(
                'query' => $query,
                'id' => 'CLEARPAY_REGION_id',
                'name' => 'CLEARPAY_REGION_name'
            )
        );

        $inputs[] = array(
            'name' => 'CLEARPAY_PUBLIC_KEY',
            'suffix' => $this->ll('ex: 400101010'),
            'type' => 'text',
            'label' => $this->ll('Merchant Id'),
            'prefix' => '<i class="icon icon-key"></i>',
            'col' => 6,
            'required' => true,
        );
        $inputs[] = array(
            'name' => 'CLEARPAY_SECRET_KEY',
            'suffix' => $this->ll('128 alphanumeric code'),
            'type' => 'text',
            'size' => 128,
            'label' => $this->ll('Secret Key'),
            'prefix' => '<i class="icon icon-key"></i>',
            'col' => 6,
            'required' => true,
        );
        $inputs[] = array(
            'name' => 'CLEARPAY_ENVIRONMENT',
            'type' => 'select',
            'label' => $this->ll('API Environment'),
            'prefix' => '<i class="icon icon-key"></i>',
            'class' => 't',
            'required' => true,
            'options' => array(
                'query' => array(
                    array(
                        'CLEARPAY_ENVIRONMENT_id' => 'sandbox',
                        'CLEARPAY_ENVIRONMENT_name' => $this->ll('Sandbox')
                    ),
                    array(
                        'CLEARPAY_ENVIRONMENT_id' => 'production',
                        'CLEARPAY_ENVIRONMENT_name' => $this->ll('Production')
                    )
                ),
                'id' => 'CLEARPAY_ENVIRONMENT_id',
                'name' => 'CLEARPAY_ENVIRONMENT_name'
            )
        );
        $inputs[] = array(
            'name' => 'CLEARPAY_MIN_AMOUNT',
            'suffix' => $this->ll('ex: 0.5'),
            'type' => 'text',
            'label' => $this->ll('Min Payment Limit'),
            'col' => 6,
            'disabled' => true,
            'required' => false,
        );
        $inputs[] = array(
            'name' => 'CLEARPAY_MAX_AMOUNT',
            'suffix' => $this->ll('ex: 800'),
            'type' => 'text',
            'label' => $this->ll('Max Payment Limit'),
            'col' => 6,
            'disabled' => true,
            'required' => false,
        );
        $inputs[] = array(
            'type' => 'categories',
            'label' => $this->ll('Restricted Categories'),
            'name' => 'CLEARPAY_RESTRICTED_CATEGORIES',
            'col' => 7,
            'tree' => array(
                'id' => 'CLEARPAY_RESTRICTED_CATEGORIES',
                'selected_categories' => json_decode(Configuration::get('CLEARPAY_RESTRICTED_CATEGORIES')),
                'root_category' => Category::getRootCategory()->id,
                'use_search' => true,
                'use_checkbox' => true,
            ),
        );
        $inputs[] = array(
            'name' => 'CLEARPAY_CSS_SELECTOR',
            'suffix' => $this->ll('The default value is \'default\'.'),
            'desc' => $this->ll('This property set the CSS selector needed to show the assets on the product page.') .
                ' ' . $this->ll('Only change this value if it doesn\'t appear properly.'),
            'type' => 'text',
            'size' => 128,
            'label' => $this->ll('Price CSS Selector'),
            'col' => 8,
            'required' => false,
        );
        $inputs[] = array(
            'name' => 'CLEARPAY_LOGS',
            'type' => 'checkbox',
            'label' => $this->ll('Debug mode'),
            'desc' => $this->ll(
                'You can see these logs on the "Configure -> Advanced Parameters -> Logs" section'
            ),
            'class' => 't',
            'values' => array(
                'query' => array(
                    array(
                        'CLEARPAY_LOGS_id' => 'ACTIVE',
                        'CLEARPAY_LOGS_name' => $this->ll('Activate debug logs')
                    )
                ),
                'id' => 'CLEARPAY_LOGS_id',
                'name' => 'CLEARPAY_LOGS_name'
            )
        );


        $return = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->ll('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => $inputs,
                'submit' => array(
                    'title' => $this->ll('Save'),
                    'class' => 'btn btn-default pull-right'
                )
            )
        );
        return $return;
    }

    /**
     * Form configuration function
     *
     * @param array $settings
     *
     * @return string
     */
    private function renderForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->show_toolbar = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->ll('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->ll('Back to list')
            )
        );


        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->fields_value['CLEARPAY_PUBLIC_KEY'] = Configuration::get('CLEARPAY_PUBLIC_KEY');
        $helper->fields_value['CLEARPAY_SECRET_KEY'] = Configuration::get('CLEARPAY_SECRET_KEY');
        $helper->fields_value['CLEARPAY_IS_ENABLED'] = Configuration::get('CLEARPAY_IS_ENABLED');
        $helper->fields_value['CLEARPAY_ENVIRONMENT'] = Configuration::get('CLEARPAY_ENVIRONMENT');
        $helper->fields_value['CLEARPAY_REGION'] = Configuration::get('CLEARPAY_REGION');
        $helper->fields_value['CLEARPAY_MIN_AMOUNT'] = Configuration::get('CLEARPAY_MIN_AMOUNT');
        $helper->fields_value['CLEARPAY_MAX_AMOUNT'] = Configuration::get('CLEARPAY_MAX_AMOUNT');
        $helper->fields_value['CLEARPAY_CSS_SELECTOR'] = Configuration::get('CLEARPAY_CSS_SELECTOR');
        $helper->fields_value['CLEARPAY_LOGS_ACTIVE'] = Configuration::get('CLEARPAY_LOGS');

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Function to update the variables of Clearpay Module in the backoffice of prestashop
     *
     * @return string
     * @throws SmartyException
     */
    public function getContent()
    {
        $message = '';
        $settingsKeys = array();
        $settingsKeys[] = 'CLEARPAY_IS_ENABLED';
        $settingsKeys[] = 'CLEARPAY_PUBLIC_KEY';
        $settingsKeys[] = 'CLEARPAY_SECRET_KEY';
        $settingsKeys[] = 'CLEARPAY_ENVIRONMENT';
        $settingsKeys[] = 'CLEARPAY_REGION';
        $settingsKeys[] = 'CLEARPAY_RESTRICTED_CATEGORIES';
        $settingsKeys[] = 'CLEARPAY_CSS_SELECTOR';
        $settingsKeys[] = 'CLEARPAY_LOGS_ACTIVE';

        if (Tools::isSubmit('submit'.$this->name)) {
            foreach ($settingsKeys as $key) {
                $value = trim(Tools::getValue($key));
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                if ($key === 'CLEARPAY_LOGS_ACTIVE') {
                    Configuration::updateValue('CLEARPAY_LOGS', $value);
                }
                Configuration::updateValue($key, $value);
            }
        }

        $publicKey = Configuration::get('CLEARPAY_PUBLIC_KEY');
        $secretKey = Configuration::get('CLEARPAY_SECRET_KEY');
        $environment = Configuration::get('CLEARPAY_ENVIRONMENT');
        $isEnabled = Configuration::get('CLEARPAY_IS_ENABLED');

        if (empty($publicKey) || empty($secretKey)) {
            $message = $this->displayError($this->ll('Merchant Id and Secret Key are mandatory fields'));
        } else {
            $message = $this->displayConfirmation($this->ll('All changes have been saved'));
        }

        // auto update configuration price thresholds and allowed countries in background
        $language = $this->getCurrentLanguage();
        if ($isEnabled && !empty($publicKey) && !empty($secretKey) && !empty($environment) && !empty($language)) {
            try {
                if (!empty($publicKey) && !empty($secretKey)  && $isEnabled) {
                    $merchantAccount = new Afterpay\SDK\MerchantAccount();
                    $merchantAccount
                        ->setMerchantId($publicKey)
                        ->setSecretKey($secretKey)
                        ->setApiEnvironment($environment)
                        ->setCountryCode(Configuration::get('CLEARPAY_REGION'))
                    ;

                    $apiVersion = $this->getApiVersionPerRegion(Configuration::get('CLEARPAY_REGION'));
                    $getConfigurationRequest = new Afterpay\SDK\HTTP\Request\GetConfiguration();
                    $getConfigurationRequest->setMerchantAccount($merchantAccount);
                    $getConfigurationRequest->setUri("/$apiVersion/configuration?include=activeCountries");
                    $getConfigurationRequest->send();
                    $configuration = $getConfigurationRequest->getResponse()->getParsedBody();

                    if (isset($configuration->message) || is_null($configuration)) {
                        $response = isset($configuration->message) ? $configuration->message : "NULL";

                        $message = $this->displayError(
                            $this->ll('Configuration request can not be done with the region and credentials') .
                            $this->ll(' provided. Message received: ') . $response
                        );
                        Configuration::updateValue(
                            'CLEARPAY_MIN_AMOUNT',
                            0
                        );
                        Configuration::updateValue(
                            'CLEARPAY_MAX_AMOUNT',
                            0
                        );
                    } else {
                        if (is_array($configuration)) {
                            $configuration = $configuration[0];
                        }
                        $minAmount = 0;
                        if (isset($configuration->minimumAmount)) {
                            $minAmount = $configuration->minimumAmount->amount;
                        }
                        Configuration::updateValue(
                            'CLEARPAY_MIN_AMOUNT',
                            $minAmount
                        );
                        $maxAmount = 0;
                        if (isset($configuration->maximumAmount)) {
                            $maxAmount = $configuration->maximumAmount->amount;
                        }
                        Configuration::updateValue(
                            'CLEARPAY_MAX_AMOUNT',
                            $maxAmount
                        );
                        if (isset($configuration->activeCountries)) {
                            Configuration::updateValue(
                                'CLEARPAY_ALLOWED_COUNTRIES',
                                json_encode($configuration->activeCountries)
                            );
                        } else {
                            $region = Configuration::get('CLEARPAY_REGION');
                            if (!empty($region) and is_string($region)) {
                                Configuration::updateValue(
                                    'CLEARPAY_ALLOWED_COUNTRIES',
                                    $this->getCountriesPerRegion($region)
                                );
                            }
                        }
                    }
                }
            } catch (\Exception $exception) {
                $uri = 'Unable to retrieve URL';
                if (isset($getConfigurationRequest)) {
                    $uri = $getConfigurationRequest->getApiEnvironmentUrl() . $getConfigurationRequest->getUri();
                }
                $message = $this->displayError(
                    $this->ll('An error occurred when retrieving configuration from') . ' ' . $uri
                );
            }
        }

        $logo = $this->getAssetsPerBrand('LOGO_BADGE');
        $tpl = $this->local_path.'views/templates/admin/config-info.tpl';
        $header = $this->ll('Clearpay Configuration Panel');
        $button1 = $this->ll('Contact us');
        $button2 = $this->ll('Getting started');
        $centeredText = '<strong>'. $this->ll('1. Before getting started:') . '</strong>' .
            $this->ll(' Do you want to know more about Clearpay?') .
            $this->ll(' Fill in the following contact form and our sales team will reach out to you.') .
            '<br><br><strong>'. $this->ll('2. Getting started:') . '</strong>' .
            $this->ll(' Are you ready to integrate Clearpay?') .
            $this->ll(' If you have been in contact with our sales team and have signed our contract ') .
            $this->ll(' click on the following link to get started.');
        $this->context->smarty->assign(array(
            'header' => $header,
            'button1' => $button1,
            'button2' => $button2,
            'centered_text' => $centeredText,
            'logo' => $logo,
            'form' => '',
            'message' => $message,
            'version' => 'v'.$this->version,
        ));

        return $this->context->smarty->fetch($tpl) . $this->renderForm();
    }

    /**
     * Hook to show payment method, this only applies on prestashop <= 1.6
     *
     * @param $params
     * @return bool | string
     * @throws Exception
     */
    public function hookPayment($params)
    {
        /** @var Cart $cart */
        $cart = $this->context->cart;
        $this->shippingAddress = new Address($cart->id_address_delivery);
        $this->billingAddress = new Address($cart->id_address_invoice);
        $totalAmount = Clearpay::parseAmount($cart->getOrderTotal(true, Cart::BOTH));

        $link = $this->context->link;

        $return = '';
        $this->context->smarty->assign($this->getButtonTemplateVars($cart));
        $templateConfigs = array();
        if ($this->isPaymentMethodAvailable()) {
            $amountWithCurrency = Clearpay::parseAmount($totalAmount / 4) . $this->currencySymbol;
            if ($this->currency === 'GBP') {
                $amountWithCurrency = $this->currencySymbol . Clearpay::parseAmount($totalAmount / 4);
            }
            $checkoutText = $this->ll('Or 4 interest-free payments of') . ' ' . $amountWithCurrency . ' ';
            $checkoutText .= $this->ll('with');
            if ($this->isOPC()) {
                $checkoutText = $this->ll('4 interest-free payments of') . ' ' . $amountWithCurrency
                    . $this->ll(' with Clearpay');
            }
            $templateConfigs['TITLE'] = $checkoutText;
            $templateConfigs['MORE_HEADER'] = $this->ll('Instant approval decision - 4 interest-free payments of')
                . ' ' . $amountWithCurrency;
            $templateConfigs['TOTAL_AMOUNT'] = $totalAmount;
            $templateConfigs['MOREINFO_ONE'] = $this->ll(
                'You will be redirected to Clearpay website to fill out your 
                payment information. You will be redirected to our site to complete your order. Please note: Clearpay 
                can only be used as a payment method for orders with a shipping and billing address within the UK.'
            );
            $templateConfigs['TERMS_AND_CONDITIONS'] = $this->ll('Terms and conditions');
            $templateConfigs['TERMS_AND_CONDITIONS_LINK'] = $this->ll(
                'https://www.clearpay.co.uk/en-GB/terms-of-service'
            );
            $templateConfigs['LOGO_TEXT'] = $this->ll("Clearpay");
            $icon = $this->getAssetsPerBrand('LOGO_APP');
            $templateConfigs['ICON'] = $icon;
            $badge = $this->getAssetsPerBrand('LOGO_BADGE');
            $templateConfigs['LOGO_BADGE'] = $badge;
            $templateConfigs['LOGO_OPC'] = Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/logo_opc.png');
            $templateConfigs['PAYMENT_URL'] = $link->getModuleLink('clearpay', 'payment');
            $templateConfigs['PS_VERSION'] = str_replace('.', '-', Tools::substr(_PS_VERSION_, 0, 3));

            $this->context->smarty->assign($templateConfigs);
            if ($this->isOPC()) {
                $this->checkLogoExists();
                $return .= $this->display(
                    __FILE__,
                    'views/templates/hook/onepagecheckout.tpl'
                );
            } else {
                $return .= $this->display(
                    __FILE__,
                    'views/templates/hook/checkout.tpl'
                );
            }
        }
        return $return;
    }

    /**
     * @param string $templateName
     * @return false|string
     */
    public function templateDisplay($templateName = '')
    {
        $templateConfigs = array();
        if ($templateName === 'cart.tpl') {
            $amount = Clearpay::parseAmount($this->context->cart->getOrderTotal());
            $templateConfigs['AMOUNT'] =  Clearpay::parseAmount($this->context->cart->getOrderTotal()/4);
            $templateConfigs['PRICE_TEXT'] = $this->ll('4 interest-free payments of');
            $templateConfigs['MORE_INFO'] = $this->ll('FIND OUT MORE');
            $desc1 = $this->ll('Buy now. Pay later. No interest.');
            $templateConfigs['DESCRIPTION_TEXT_ONE'] = $desc1;
            $desc2 = $this->ll('With Clearpay you can enjoy your purchase now and pay in 4 installments. ');
            $desc2 .= ' ' . $this->ll(' No hidden cost. Choose Clearpay as your payment method in the check-out, ');
            $desc2 .= ' ' . $this->ll(' fill in a simple form, no documents needed, and pay later.');
            $templateConfigs['DESCRIPTION_TEXT_TWO'] = $desc2;
            $categoryRestriction = $this->isCartRestricted($this->context->cart);
            $simulatorIsEnabled = true;
        } else {
            $productId = Tools::getValue('id_product');
            if (!$productId) {
                return false;
            }
            $categoryRestriction = $this->isProductRestricted($productId);
            $amount = Product::getPriceStatic($productId);
            $templateConfigs['AMOUNT'] = $amount;
            $simulatorIsEnabled = self::SIMULATOR_IS_ENABLED;
        }
        $return = '';
        $isEnabled = Configuration::get('CLEARPAY_IS_ENABLED');

        $allowedCountries = json_decode(Configuration::get('CLEARPAY_ALLOWED_COUNTRIES'));
        $language = $this->getCurrentLanguage();
        if ($isEnabled &&
            $simulatorIsEnabled &&
            $amount > 0 &&
            ($amount >= Configuration::get('CLEARPAY_MIN_AMOUNT') || $templateName === 'product.tpl') &&
            ($amount <= Configuration::get('CLEARPAY_MAX_AMOUNT')  || $templateName === 'product.tpl') &&
            in_array(Tools::strtoupper($language), $allowedCountries) &&
            !$categoryRestriction
        ) {
            $templateConfigs['PS_VERSION'] = str_replace('.', '-', Tools::substr(_PS_VERSION_, 0, 3));
            $templateConfigs['SDK_URL'] = self::CLEARPAY_JS_CDN_URL;
            $templateConfigs['CLEARPAY_MIN_AMOUNT'] = Configuration::get('CLEARPAY_MIN_AMOUNT');
            $templateConfigs['CLEARPAY_MAX_AMOUNT'] = Configuration::get('CLEARPAY_MAX_AMOUNT');
            $templateConfigs['CURRENCY'] = $this->currency;
            $language = Language::getLanguage($this->context->language->id);
            if (isset($language['locale'])) {
                $language = $language['locale'];
            } else {
                $language = $language['language_code'];
            }
            $templateConfigs['ISO_COUNTRY_CODE'] = str_replace('-', '_', $language);
            // Preserve Uppercase in locale
            if (Tools::strlen($templateConfigs['ISO_COUNTRY_CODE']) == 5) {
                $templateConfigs['ISO_COUNTRY_CODE'] = Tools::substr($templateConfigs['ISO_COUNTRY_CODE'], 0, 2) .
                    Tools::strtoupper(Tools::substr($templateConfigs['ISO_COUNTRY_CODE'], 2, 4));
            }
            $templateConfigs['AMOUNT_WITH_CURRENCY'] = $templateConfigs['AMOUNT'] . $this->currencySymbol;
            $templateConfigs['PRICE_SELECTOR'] = Configuration::get('CLEARPAY_CSS_SELECTOR');
            if ($templateConfigs['PRICE_SELECTOR'] === 'default'|| $templateConfigs['PRICE_SELECTOR'] === '') {
                $templateConfigs['PRICE_SELECTOR'] = '.current-price :not(span.discount)';
                if (version_compare(_PS_VERSION_, '1.7', 'lt')) {
                    $templateConfigs['PRICE_SELECTOR'] = '.our_price_display';
                }
                if ($this->currency === 'GBP') {
                    $templateConfigs['AMOUNT_WITH_CURRENCY'] = $this->currencySymbol. $templateConfigs['AMOUNT'];
                }
            }

            $this->context->smarty->assign($templateConfigs);
            $return .= $this->display(
                __FILE__,
                'views/templates/hook/' . $templateName
            );
        } else {
            if ($templateName === 'product.tpl' && Configuration::get('CLEARPAY_LOGS') === 'on') {
                $logMessage = '';
                if (!$simulatorIsEnabled) {
                    $logMessage .= "Clearpay: Simulator is disabled by 'self::SIMULATOR_IS_ENABLED'. ";
                }
                if (!in_array(Tools::strtoupper($language), $allowedCountries)) {
                    $logMessage .= "Clearpay: Simulator is disabled by the allowedCountries, 
                    current:$language and allowed:" . json_encode($allowedCountries) . '. ';
                }
                if ($categoryRestriction) {
                    $productCategories = json_encode(Product::getProductCategories($productId));
                    $logMessage .= "Clearpay: Simulator is disabled by the Categories restriction, 
                    current:$productCategories."
                    . "and not allowed:". Configuration::get('CLEARPAY_RESTRICTED_CATEGORIES');
                }
                if (Configuration::get('CLEARPAY_LOGS') == 'on') {
                    PrestaShopLogger::addLog($logMessage, 2, null, "Clearpay", 1);
                }
            }
        }

        return $return;
    }

    /**
     * @return bool
     */
    protected function isOPC()
    {
        $supercheckout_enabled = Module::isEnabled('supercheckout');
        $onepagecheckoutps_enabled = Module::isEnabled('onepagecheckoutps');
        $onepagecheckout_enabled = Module::isEnabled('onepagecheckout');

        return ($supercheckout_enabled || $onepagecheckout_enabled || $onepagecheckoutps_enabled);
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayProductPriceBlock($params)
    {
        if (isset($params['type']) && $params['type'] === 'after_price' &&
            isset($params['smarty']) && isset($params['smarty']->template_resource) &&
            (
                (
                    version_compare(_PS_VERSION_, '1.7', 'ge') &&
                    strpos($params['smarty']->template_resource, 'product-prices.tpl') !== false
                )
                ||
                (
                    version_compare(_PS_VERSION_, '1.7', 'lt') &&
                    strpos($params['smarty']->template_resource, 'product.tpl') !== false
                )
            )
        ) {
            return $this->templateDisplay('product.tpl');
        }
        if (isset($params['type'])
            && $params['type'] === 'price'
            && version_compare(_PS_VERSION_, '1.6.1', 'lt')
            && strpos($params['smarty']->template_resource, 'product.tpl') !== false
        ) {
            return $this->templateDisplay('product.tpl');
        }
        return '';
    }

    /**
     * @param array $params
     * @return string
     */
    public function hookDisplayExpressCheckout($params)
    {
        return $this->templateDisplay('cart.tpl');
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayOrderConfirmation($params)
    {
        $paymentMethod = (_PS_VERSION_ < 1.7) ? ($params["objOrder"]->payment) : ($params["order"]->payment);

        if ($paymentMethod == $this->displayName) {
            return $this->display(__FILE__, 'views/templates/hook/payment-return.tpl');
        }

        return null;
    }

    /**
     * @return string
     */
    public function hookDisplayWrapperTop()
    {
        $isDeclined = Tools::getValue('clearpay_declined');
        $isMismatch = Tools::getValue('clearpay_mismatch');
        $referenceId = Tools::getValue('clearpay_reference_id');
        $errorText1 = $this->ll('Thanks for confirming your payment, however as your cart has changed we need a new ') .
            $this->ll(' confirmation. Please proceed to Clearpay and retry again in a few minutes');
        $errorText2 = $this->ll('For more information, please contact the Clearpay Customer Service Team:');
        $declinedText1 = $this->ll('We are sorry to inform you that your payment has been declined by Clearpay.');
        $this->context->smarty->assign(array(
            'REFERENCE_ID' => $referenceId,
            'PS_VERSION' => str_replace('.', '-', Tools::substr(_PS_VERSION_, 0, 3)),
            'ERROR_TEXT1' => $errorText1,
            'ERROR_TEXT2' => $errorText2,
            'DECLINED_TEXT1' => $declinedText1,

        ));
        if ($isDeclined == 'true') {
            $return = $this->displayError(
                $this->display(__FILE__, 'views/templates/hook/payment-declined.tpl')
            );
            return $return;
        }
        if ($isMismatch == 'true') {
            $return = $this->displayError(
                $this->display(__FILE__, 'views/templates/hook/payment-error.tpl')
            );
            return $return;
        }
        return null;
    }

    /**
     * @param $params
     * @return string|null
     */
    public function hookDisplayPaymentTop($params)
    {
        if (version_compare(_PS_VERSION_, '1.6.1', 'lt')) {
            return $this->hookDisplayWrapperTop();
        }
        return null;
    }

    /**
     * Hook Action for Order Status Update (handles Refunds)
     * @param array $params
     * @return bool
     * since 1.0.0
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $newOrderStatus = null;
        $order = null;
        if (!empty($params) && !empty($params['id_order'])) {
            $order = new Order((int)$params['id_order']);
        }

        if (!empty($params) && !empty($params['newOrderStatus'])) {
            $newOrderStatus = $params['newOrderStatus'];
        }
        if ($newOrderStatus->id == _PS_OS_REFUND_) {
            $clearpayRefund = $this->createRefundObject();
            // ---- needed values ----
            $payments = $order->getOrderPayments();
            $transactionId = $payments[0]->transaction_id;
            $currency = new Currency($order->id_currency);
            $currencyCode = $currency->iso_code;
            // ------------------------
            $clearpayRefund->setOrderId($transactionId);
            $clearpayRefund->setRequestId(Tools::strtoupper(md5(uniqid(rand(), true))));
            $clearpayRefund->setAmount(
                Clearpay::parseAmount($order->total_paid_real),
                $currencyCode
            );
            $clearpayRefund->setMerchantReference($order->id);


            if ($clearpayRefund->send()) {
                if ($clearpayRefund->getResponse()->isSuccessful()) {
                    PrestaShopLogger::addLog(
                        $this->ll("Clearpay Full Refund done: ") . Clearpay::parseAmount($order->total_paid_real),
                        1,
                        null,
                        "Clearpay",
                        1
                    );
                    return true;
                }
                $parsedBody = $clearpayRefund->getResponse()->getParsedBody();
                PrestaShopLogger::addLog(
                    $this->ll("Clearpay Full Refund Error: ") . $parsedBody->errorCode . '-> ' . $parsedBody->message,
                    3,
                    null,
                    "Clearpay",
                    1
                );
            }
        }
        return false;
    }

    /**
     * Hook Action for Partial Refunds
     * @param array $params
     * since 1.0.0
     */
    public function hookActionOrderSlipAdd($params)
    {
        if (!empty($params) && !empty($params["order"]->id)) {
            $order = new Order((int)$params["order"]->id);
        } else {
            return false;
        }
        // ---- needed values ----
        $payments = $order->getOrderPayments();
        $transactionId = $payments[0]->transaction_id;
        $currency = new Currency($order->id_currency);
        $currencyCode = $currency->iso_code;
        $clearpayRefund = $this->createRefundObject();

        $refundProductsList = $params["productList"];
        $refundTotalAmount = 0;
        foreach ($refundProductsList as $item) {
            $refundTotalAmount +=  $item["amount"];
        }
        $refundTotalAmount = Clearpay::parseAmount($refundTotalAmount);

        $clearpayRefund->setOrderId($transactionId);
        $clearpayRefund->setRequestId(Tools::strtoupper(md5(uniqid(rand(), true))));
        $clearpayRefund->setAmount($refundTotalAmount, $currencyCode);
        $clearpayRefund->setMerchantReference($order->id);

        if ($clearpayRefund->send()) {
            if ($clearpayRefund->getResponse()->isSuccessful()) {
                PrestaShopLogger::addLog(
                    $this->ll("Clearpay partial Refund done: ") . $refundTotalAmount,
                    1,
                    null,
                    "Clearpay",
                    1
                );
                return true;
            }
            $parsedBody = $clearpayRefund->getResponse()->getParsedBody();
            PrestaShopLogger::addLog(
                $this->ll("Clearpay Partial Refund Error: ") . $parsedBody->errorCode . '-> ' . $parsedBody->message,
                3,
                null,
                "Clearpay",
                1
            );
        }
        return false;
    }

    /**
     * Construct the Refunds Object based on the configuration and Refunds type
     * @return Afterpay\SDK\HTTP\Request\CreateRefund
     */
    private function createRefundObject()
    {

        $publicKey = Configuration::get('CLEARPAY_PUBLIC_KEY');
        $secretKey = Configuration::get('CLEARPAY_SECRET_KEY');
        $environment = Configuration::get('CLEARPAY_ENVIRONMENT');

        $merchantAccount = new Afterpay\SDK\MerchantAccount();
        $merchantAccount
            ->setMerchantId($publicKey)
            ->setSecretKey($secretKey)
            ->setApiEnvironment($environment)
            ->setCountryCode(Configuration::get('CLEARPAY_REGION'))
        ;

        $clearpayRefund = new Afterpay\SDK\HTTP\Request\CreateRefund();
        $clearpayRefund->setMerchantAccount($merchantAccount);

        return $clearpayRefund;
    }
    /**
     * Check logo exists in OPC module
     */
    public function checkLogoExists()
    {
        $logoOPC = _PS_MODULE_DIR_ . 'onepagecheckoutps/views/img/payments/clearpay.png';
        if (!file_exists($logoOPC) && is_dir(_PS_MODULE_DIR_ . 'onepagecheckoutps/views/img/payments')) {
            copy(
                _PS_CLEARPAY_DIR . '/views/img/logo_opc.png',
                $logoOPC
            );
        }
    }

    /**
     * Get user language
     */
    private function getCurrentLanguage()
    {
        $language = 'EN';
        $allowedCountries = json_decode(Configuration::get('CLEARPAY_ALLOWED_COUNTRIES'));
        if (is_null($allowedCountries)) {
            return $language;
        }
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
        if ($this->shippingAddress) {
            $language = Country::getIsoById($this->shippingAddress->id_country);
            if (in_array(Tools::strtoupper($language), $allowedCountries)) {
                return $language;
            }
        }
        if ($this->billingAddress) {
            $language = Country::getIsoById($this->billingAddress->id_country);
            if (in_array(Tools::strtoupper($language), $allowedCountries)) {
                return $language;
            }
        }
        return $language;
    }

    /**
     * @param $region
     * @return string
     */
    public function getCountriesPerRegion($region = '')
    {
        if (isset($this->defaultCountriesPerRegion[$region])) {
            return $this->defaultCountriesPerRegion[$region];
        }
        return json_encode(array($region));
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
     * @param $assetName
     * @return string
     */
    public function getAssetsPerBrand($assetName = 'LOGO_BADGE')
    {
        return $this->brandAssets[self::DEFAULT_BRAND][$assetName];
    }

    /**
     * @param null $amount
     * @return string
     */
    public static function parseAmount($amount = null)
    {
        return number_format(
            round($amount, 2, PHP_ROUND_HALF_UP),
            2,
            '.',
            ''
        );
    }

    /**
     * @param $productId
     * @return bool
     */
    private function isProductRestricted($productId)
    {
        $clearpayRestrictedCategories = json_decode(Configuration::get('CLEARPAY_RESTRICTED_CATEGORIES'));
        if (!is_array($clearpayRestrictedCategories)) {
            return false;
        }
        $productCategories = Product::getProductCategories($productId);
        return (bool) count(array_intersect($productCategories, $clearpayRestrictedCategories));
    }

    /**
     * @param $cart
     * @return bool
     */
    private function isCartRestricted($cart)
    {
        foreach ($cart->getProducts() as $product) {
            if ($this->isProductRestricted($product['id_product'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * ll function overrides $this->l function in order to switch between AfterPay and Clearpay
     * BE CAREFULLY IF YOU REGENERATE THE TRANSLATIONS USING PRESTASHOP TRANSLATIONS TOOL AND THIS FUNCTION ARE
     * POPULATED ON THE SOURCE CODE, ALL TRANSLATIONS WILL BE ERASED.
     * First replace all '$this->ll(' by '$this->l('
     * @param string $text
     * @return string
     */
    public function ll($text)
    {
        $translation = parent::l($text);
        if (self::DEFAULT_BRAND === 'CP') {
            return $translation;
        }
        $base = array('Clearpay', 'clearpay', 'CLEARPAY');
        $replacements = array('Afterpay', 'afterpay', 'AFTERPAY');
        return str_replace($base, $replacements, $translation);
    }
}
