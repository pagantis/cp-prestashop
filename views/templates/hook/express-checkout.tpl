{*
 * This file is part of the official Clearpay module for PrestaShop.
 *
 * @author    Clearpay <integrations@clearpay.com>
 * @copyright 2020 Clearpay
 * @license   proprietary
*}

<style>
    /* ------------------------------------------------------------------------------------
                                        Express Checkout
    ------------------------------------------------------------------------------------*/
    .btn-afterpay_express {
        padding: 0;
        background: transparent !important;
        vertical-align: bottom;
        -webkit-appearance: none;
        -moz-appearance: none;
        width: 300px;
        max-width: 100%;
        transition: filter 0.4s, opacity .4s;
        border-radius: 10px;
    }

    .btn-afterpay_express[disabled] {
        filter: saturate(0);
    }

    .btn-afterpay_express:hover {
        background: transparent !important;
    }

    .btn-afterpay_express img {
        width: 100%;
    }
    #afterpay_express_button {
        margin-bottom: 10px;
    }
    #HOOK_SHOPPING_CART {
        text-align: right;
    }
</style>
<!-- AfterPay.js  -->
<script>
    // ensure this function is defined before loading afterpay.js
    var initAfterPayExpress = function () {
        var spinner = null;
        var button = document.getElementById('afterpay_express_button');
        if (button.disabled == true) {
            button.disabled = false;
        }
        AfterPay.initializeForPopup({
            countryCode: '{$COUNTRY|escape:'htmlall':'UTF-8'}',
            shippingOptionRequired: true,
            buyNow: false,
            pickup: false,
            target: '#afterpay_express_button',
            onCommenceCheckout: function (actions) {
                jQuery.ajax({
                    url: '{$EXPRESS_CONTROLLER|escape:'quotes':'UTF-8'}',
                    data: {
                        action: 'start_express_process',
                    },
                    success: function (data) {
                        console.log(data);
                        if (!data.success) {
                            actions.reject(data.message);
                        } else {
                            actions.resolve(data.token);
                            AfterPay.currentExpressUrlToken = data.urlToken
                        }
                    },
                    error: function(request, statusText, errorThrown) {
                        actions.reject(AfterPay.CONSTANTS.BAD_RESPONSE);
                        console.log('Something went wrong. Please try again later.');
                    }
                });
            },
            onShippingAddressChange: function (data, actions) {
                if (data.countryCode !== '{$COUNTRY|escape:'htmlall':'UTF-8'}') {
                    // Reject any unsupported shipping addresses
                    actions.reject(AfterPay.CONSTANTS.SHIPPING_UNSUPPORTED)
                } else {
                    // Calc shipping inline
                    jQuery.ajax({
                        url: '{$EXPRESS_CONTROLLER|escape:'quotes':'UTF-8'}',
                        data: {
                            action: 'get_shipping_methods',
                        },
                        success: function(data){
                            actions.resolve(data);
                        },
                        error: function(request, statusText, errorThrown) {
                            jQuery('.btn-afterpay_express').prop('disabled', false);
                            console.log('Something went wrong. Please try again later.');
                            if (spinner) {
                                spinner.overlay.remove();
                                spinner.css.remove();
                            }
                        }
                    });
                }
            },
            onComplete: function (event) {
                // add overlay loading
                console.log(event.data);
                if (event.data) {
                    if (event.data.status && event.data.status == 'SUCCESS') {
                        if (spinner) {
                            spinner.overlay.appendTo('body');
                            spinner.css.appendTo('head');
                        }

                        jQuery.ajax({
                            url: '{$EXPRESS_CONTROLLER|escape:'quotes':'UTF-8'}',
                            method: 'POST',
                            data: {
                                action: 'complete_order',
                                token: event.data.orderToken,
                                urlToken: AfterPay.currentExpressUrlToken,
                                key: "{$SECURE_KEY|escape:'htmlall':'UTF-8'}"
                            },
                            success: function(ajax_data){
                                console.log("success", ajax_data);
                                if (ajax_data.success === true) {
                                    jQuery('.btn-afterpay_express').prop('disabled', false);
                                    window.location.href = ajax_data.url;
                                    if (spinner) {
                                        spinner.overlay.remove();
                                        spinner.css.remove();
                                    }
                                } else {
                                    alert("error: " + ajax_data.message);
                                }

                            },
                            error: function(request, statusText, errorThrown) {
                                jQuery('.btn-afterpay_express').prop('disabled', false);
                                console.log('Something went wrong. Please try again later.');
                                //window.location.href = data.url;
                                if (spinner) {
                                    spinner.overlay.remove();
                                    spinner.css.remove();
                                }
                            }
                        });
                    } else {
                        jQuery('.btn-afterpay_express').prop('disabled', false);
                    }
                }
            },
        });
    }
    document.addEventListener('readystatechange', event => {
        if (event.target.readyState === "interactive" &&
            typeof window.AfterPay !== 'undefined' &&
            typeof AfterPay.initializeForPopup != 'undefined'
        ) {
            initAfterPayExpress();
        }

        // Double check
        if (event.target.readyState === "complete" &&
            typeof window.AfterPay !== 'undefined' &&
            typeof AfterPay.initializeForPopup != 'undefined' &&
            document.getElementById('afterpay_express_button').disabled == true
        ) {
            initAfterPayExpress();
        }
    });
    document.body.addEventListener("updated_cart_totals", function(event) {
        // needs to be called here again as when the Presta cart updates via ajax the button needs to have the event re-bound
        initAfterPayExpress();
    });
</script>
<script src="https://portal.sandbox.clearpay.co.uk/afterpay.js?merchant_key=demo" async></script>
<!-- AfterPay.js -->
<button id="afterpay_express_button" class="btn-afterpay_express btn-afterpay_express_cart" type="button" disabled>
    <img src="https://static.afterpay.com/button/checkout-with-clearpay/black-on-mint.svg" alt="Checkout with AfterPay" />
</button>
