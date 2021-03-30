{*
 * This file is part of the official Clearpay module for PrestaShop.
 *
 * @author    Clearpay <integrations@clearpay.com>
 * @copyright 2020 Clearpay
 * @license   proprietary
 *}
<style>
    p.payment_module.Clearpay.ps_version_1-7 {
        margin-left: -5px;
        margin-top: -15px;
        margin-bottom: 0px;
    }
    p.payment_module a.clearpay-checkout {
        background: url('{$ICON|escape:'htmlall':'UTF-8'}') 5px 5px no-repeat #fbfbfb;
        background-size: 79px;
    }
    p.payment_module a.clearpay-checkout.ps_version_1-7 {
        background: none;
    }
    p.payment_module a.clearpay-checkout.ps_version_1-6 {
        background-color: #fbfbfb;
        max-height: 90px;
    }
    p.payment_module a.clearpay-checkout.ps_version_1-6:after {
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

    #clearpay-method-content {
        color: #7a7a7a;
        border: 1px solid #000;
        margin-bottom: 10px;
    }
    .clearpay-header {
        color: #7a7a7a;
        position: relative;
        text-align: center;
        background-color: #b2fce4;
        padding: 5px 10px 10px 0px;
        overflow: visible;
    }
    .clearpay-header img {
        height: 28px;
    }

    .clearpay-header-img {
        display: inline;
    }

    .clearpay-header-text1 {
        display: inline;
        text-align: center;
        color: black;
        font-weight: bold;
    }
    .clearpay-header-text2 {
        display: inline-block;
        text-align: center;
    }
    .clearpay-checkout-ps1-6-logo {
        height: 45px;
        margin-left: 10px;
        top: 25%;
        position: absolute;
    }
    .clearpay-more-info-text {
        padding: 1em 1em;
        text-align: center;
    }
    .clearpay-more-info {
        text-align: center !important;
    }

    .ps-clearpay-container {
        display: grid;
        max-width: 750px;
        height: auto;
        grid-template-columns: repeat(4, 1fr);
        grid-template-rows: min-content  min-content  min-content;
        grid-template-areas:
      "cp-ps-checkout-header  cp-ps-checkout-header  cp-ps-checkout-header  cp-ps-checkout-header"
      "cp-placement-wrapper cp-placement-wrapper cp-placement-wrapper cp-placement-wrapper"
      "cp-placement-wrapper cp-placement-wrapper cp-placement-wrapper cp-placement-wrapper"
    }

    .cp-ps-checkout-header {
        grid-area: cp-ps-checkout-header ;
        display: -webkit-box;
        display: -ms-flexbox;
        display: flex;
        align-content: center;
        background-color: #b2fce4;
        color: #7a7a7a;
        flex-direction: row;
        float: left;
        font-size: .875rem;
        justify-content: center;
        min-height: 35px;
        position: relative;
        text-align: center;
        width: 100%;
        padding: 7px 0 !important;
    }

    .cp-ps-header-row .cp-ps-header-img {
        align-self: center;
        width: 170px;
        max-height: 50px;
        max-width: 170px;
    }

    .cp-row-text {
        display: -webkit-box;
        display: -ms-flexbox;
        display: flex;
        flex-direction: column;
    }

    .cp-ps-header-row {
        display: -webkit-box;
        display: -ms-flexbox;
        display: flex;
        flex-wrap: nowrap;
        max-height: fit-content;
        justify-content: center;
        align-items: center;
    }

    .cp-row-text .cp-header-text {
        max-height: fit-content;
        align-self: center;
        -webkit-flex-wrap: wrap;
        flex-wrap: wrap;
        margin-bottom: 0 !important;
    }

    .ps-cp-placement-container {
        padding: 7px;
    }


    .cp-ps-placement-wrapper {
        grid-area: cp-placement-wrapper;
        display: inline-flex;
        justify-content: center;
        flex-direction: column;
    }

    .copy-container .cp-ps-checkout-more-info-1 {
        margin: 10px;
        text-align: center;
        font-size: .850rem;
    }

    .clearpay-terms {
        display: inline-block;
        text-align: center;
        padding-bottom: 7px;
    }

    .cp-ps-header-img img {

    }

    @media (max-width: 575px) {

        .cp-ps-header-img {
            align-self: center;
        }

        .cp-ps-header-row {
            flex-direction: column;
        }

    }
</style>
{if $PS_VERSION !== '1-7'}
    <div class="row">
        <div class="col-xs-12">
            <p class="payment_module">
                <a class="clearpay-checkout clearpay-checkout ps_version_{$PS_VERSION|escape:'htmlall':'UTF-8'}" href="{$PAYMENT_URL|escape:'htmlall':'UTF-8'}">
                    {$TITLE|escape:'htmlall':'UTF-8'}
                    <img class="clearpay-checkout-ps{$PS_VERSION|escape:'htmlall':'UTF-8'}-logo" src="{$LOGO|escape:'htmlall':'UTF-8'}">
                </a>
            </p>
        </div>
    </div>
{/if}
{if $PS_VERSION === '1-7'}
    <section>
        <div class="payment-method ps_version_{$PS_VERSION|escape:'htmlall':'UTF-8'}" id="clearpay-method">
            <div class="payment-method-content clearpay ps_version_{$PS_VERSION|escape:'htmlall':'UTF-8'}" id="clearpay-method-content">
                <div class="ps-clearpay-container">
                    <div class="cp-ps-checkout-header">
                        <div class="cp-row-text">
                            <div class="cp-ps-header-row">
                                <img class="cp-ps-header-img" src="{$LOGO_BADGE|escape:'htmlall':'UTF-8'}">
                                <p class="cp-header-text">{$MORE_HEADER1|escape:'htmlall':'UTF-8'}</p>
                            </div>
                            <p class="cp-header-text">{$MORE_HEADER2|escape:'htmlall':'UTF-8'} </p>
                        </div>
                    </div>
                    <div class="cp-ps-placement-wrapper">
                        <div class="copy-container">
                            <p class="cp-ps-checkout-more-info-1">{$MOREINFO_ONE|escape:'htmlall':'UTF-8'}</p>
                        </div>
                        <div class="ps-cp-placement-container">
                            <afterpay-placement
                                    data-type="price-table"
                                    data-amount="{$TOTAL_AMOUNT|escape:'htmlall':'UTF-8'}"
                                    data-price-table-theme="white"
                                    data-mobile-view-layout="{$AP_MOBILE_LAYOUT|escape:'htmlall':'UTF-8'}"
                                    data-locale="{$ISO_COUNTRY_CODE|escape:'htmlall':'UTF-8'}"
                                    data-currency="{$CURRENCY|escape:'htmlall':'UTF-8'}">
                            </afterpay-placement>
                        </div>
                        <a class="clearpay-terms" href="{$TERMS_AND_CONDITIONS_LINK|escape:'htmlall':'UTF-8'}" TARGET="_blank">
                            {$TERMS_AND_CONDITIONS|escape:'htmlall':'UTF-8'}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
{/if}
