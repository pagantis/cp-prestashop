{*
 * This file is part of the official Afterpay module for PrestaShop.
 *
 * @author    Afterpay <integrations@afterpay.com>
 * @copyright 2020 Afterpay
 * @license   proprietary
 *}
<style>
    p.payment_module.Afterpay.ps_version_1-7 {
        margin-left: -5px;
        margin-top: -15px;
        margin-bottom: 0px;
    }
    p.payment_module a.afterpay-checkout {
        background: url('{$ICON|escape:'htmlall':'UTF-8'}') 5px 5px no-repeat #fbfbfb;
        background-size: 79px;
    }
    p.payment_module a.afterpay-checkout.ps_version_1-7 {
        background: none;
    }
    .payment-option img[src*='static.afterpay.com'] {
        height: 25px;
        padding-left: 5px;
        content:url('{$LOGO_BADGE|escape:'htmlall':'UTF-8'}');
    }
    p.payment_module a.afterpay-checkout.ps_version_1-6 {
        background-color: #fbfbfb;
        max-height: 90px;
    }
    p.payment_module a.afterpay-checkout.ps_version_1-6:after {
        display: block;
        content: "\f054";
        position: absolute;
        right: 15px;
        margin-top: -11px;
        top: 50%;
        font-family: "FontAwesome";
        font-size: 25px;
        height: 22px;
        width: 14px;
        color: #777;
    }
    p.payment_module a:hover {
        background-color: #f6f6f6;
    }

    #afterpay-method-content {
        color: #7a7a7a;
        border: 1px solid #000;
        margin-bottom: 10px;
    }
    .afterpay-header {
        color: #7a7a7a;
        position: relative;
        background-color: #b2fce4;
        text-align: center;
        float: left;
        width: 100%;
        min-height: 35px;
        padding-top: 7px;
        margin-bottom: 10px;
        padding-bottom: 5px;
    }
    .afterpay-header img {
        height: 25px;
    }
    .afterpay-header-img {
        display: inline;
        text-align: center;
    }

    .afterpay-header-text1 {
        display: inline;
        color: black;
        font-weight: bold;
    }
    .afterpay-header-text2 {
        display: inline;
    }
    .afterpay-checkout-ps1-6-logo {
        height: 35px;
        margin-left: 10px;
        top: 30%;
        position: absolute;
        display: inline !important;
    }
    .afterpay-checkout-ps1-6-logo-text {
        display: none;
    }
    .afterpay-more-info-text {
        padding: 1em 3em;
        text-align: center;
    }
    .afterpay-terms {
        margin-top: 10px;
        display: inline-block;
    }
    @media only screen and (max-width: 1200px) {
        .afterpay-header {
            text-align: center;
            display: block;
            height: 65px !important;
        }
    }
    @media only screen and (max-width: 1200px) and (min-width: 990px)  {
        .afterpay-header img {
            padding: 0;
        }
    }
    @media only screen and (max-width: 989px) and (min-width: 768px)  {
        .afterpay-header img {
            padding: 0;
        }
        .afterpay-header {
            height: 70px !important;
        }
    }
    @media only screen and (max-width: 767px) and (min-width: 575px)  {
        .afterpay-header img {
            padding: 0;
        }
        .afterpay-header {
            height: 65px !important;
        }
    }
    @media only screen and (max-width: 575px) {
        .afterpay-header img {
            padding: 0;
        }
        .afterpay-header {
            height: 80px !important;
        }
        .afterpay-checkout-ps1-6-logo {
            display: none;
        }
        .afterpay-checkout-ps1-6-logo-text {
            display: inline;
        }
    }
</style>
{if $PS_VERSION !== '1-7'}
    <div class="row">
        <div class="col-xs-12">
            <p class="payment_module">
                <a class="afterpay-checkout afterpay-checkout ps_version_{$PS_VERSION|escape:'htmlall':'UTF-8'}" href="{$PAYMENT_URL|escape:'htmlall':'UTF-8'}">
                    {$TITLE|escape:'htmlall':'UTF-8'}
                    <img class="afterpay-checkout-ps{$PS_VERSION|escape:'htmlall':'UTF-8'}-logo" src="{$LOGO_BADGE|escape:'htmlall':'UTF-8'}">
                    <span class="afterpay-checkout-ps{$PS_VERSION|escape:'htmlall':'UTF-8'}-logo-text">{$LOGO_TEXT|escape:'htmlall':'UTF-8'}</span>
                </a>
            </p>
        </div>
    </div>
{/if}
{if $PS_VERSION === '1-7'}
    <section>
        <div class="payment-method ps_version_{$PS_VERSION|escape:'htmlall':'UTF-8'}" id="afterpay-method" >
            <div class="payment-method-content afterpay ps_version_{$PS_VERSION|escape:'htmlall':'UTF-8'}" id="afterpay-method-content">
                <div class="afterpay-header">
                    <div class="afterpay-header-img">
                        <img src="{$LOGO_BADGE|escape:'htmlall':'UTF-8'}">
                    </div>
                </div>
                <div class="afterpay-more-info-text">
                    <div class="afterpay-more-info">
                        {$DESCRIPTION|escape:'htmlall':'UTF-8'}
                    </div>
                    <afterpay-placement
                            data-type="price-table"
                            data-amount="{$TOTAL_AMOUNT|escape:'htmlall':'UTF-8'}"
                            data-price-table-theme="white"
                            data-locale="{$ISO_COUNTRY_CODE|escape:'htmlall':'UTF-8'}"
                            data-currency="{$CURRENCY|escape:'htmlall':'UTF-8'}">
                    </afterpay-placement>
                    <a class="afterpay-terms" href="{$TERMS_AND_CONDITIONS_LINK|escape:'htmlall':'UTF-8'}" TARGET="_blank">
                        {$TERMS_AND_CONDITIONS|escape:'htmlall':'UTF-8'}
                    </a>
                    {if $ISO_COUNTRY_CODE == 'es_ES' }
                        &nbsp;|&nbsp;
                        <a href="javascript:void(0)" onclick="Afterpay.launchModal('{$ISO_COUNTRY_CODE|escape:'javascript':'UTF-8'}');">
                            {$MORE_INFO_TEXT|escape:'htmlall':'UTF-8'}
                        </a>
                    {/if}
                </div>
            </div>
        </div>
    </section>
{/if}