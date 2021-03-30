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

	.payment-option img[src*='static.afterpay.com'] {
		height: 25px;
		padding-left: 5px;
		content: url('{$LOGO_BADGE|escape:'htmlall':'UTF-8'}');
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
		padding-left: 120px;
		background-color: #b2fce4;
		text-align: center;
		float: left;
		width: 100%;
		min-height: 35px;
		padding-top: 7px;
		margin-bottom: 10px;
		padding-bottom: 5px;
	}

	.clearpay-header img {
		height: 25px;
		margin-top: 5px;
	}

	.clearpay-header-img {
		height: 25px;
		margin-top: 5px;
		position: absolute;
		top: 0;
		left: 0;
	}

	.clearpay-header-text1 {
		display: inline;
		color: black;
		font-weight: bold;
	}

	.clearpay-header-text2 {
		display: inline;
	}

	.clearpay-checkout-ps1-6-logo {
		height: 35px;
		margin-left: 10px;
		top: 30%;
		position: absolute;
		display: inline !important;
	}

	.clearpay-checkout-ps1-6-logo-text {
		display: none;
	}

	.clearpay-more-info-text {
		padding: 1em 3em;
		text-align: center;
	}

	.clearpay-terms {
		margin-top: 10px;
		display: inline-block;
	}

	@media only screen and (max-width: 1200px) {
		.clearpay-header {
			text-align: center;
			display: block;
			height: 65px !important;
		}
	}

	@media only screen and (max-width: 1200px) and (min-width: 990px) {
		.clearpay-header img {
			padding: 0;
		}
	}

	@media only screen and (max-width: 989px) and (min-width: 768px) {
		.clearpay-header img {
			padding: 0;
		}

		.clearpay-header {
			height: 70px !important;
		}
	}

	@media only screen and (max-width: 767px) and (min-width: 575px) {
		.clearpay-header img {
			padding: 0;
		}

		.clearpay-header {
			height: 65px !important;
		}
	}

	@media only screen and (max-width: 575px) {

		/*.ps-header



	{*/
						/*    display: flex;*/
						/*    align-content: center;*/
						/*    background-color: #b2fce4;*/
						/*    color: #7a7a7a;*/
						/*    float: left;*/
						/*    font-size: .875rem;*/
						/*    flex-direction: column;*/
						/*    grid-area: header;*/
						/*    justify-content: center;*/
						/*    margin-bottom: 10px;*/
						/*    height:auto;*/
						/*    min-height: 35px;*/
						/*    !*padding-left: 120px;*!*/
						/*    padding: 7px;*/
						/*    position: relative;*/
						/*    text-align: center;*/
						/*    width: 100%;*/
						/*}*/
		.clearpay-header img {
			padding: 0;
		}

		.clearpay-header {
			height: 80px !important;
		}

		.clearpay-checkout-ps1-6-logo {
			display: none;
		}

		.clearpay-checkout-ps1-6-logo-text {
			display: inline;
		}
	}

	.ps-clearpay-container {
		display: grid;
		max-width: 750px;
		height: auto;
		grid-template-columns: 1fr 1fr 1fr 1fr;
		grid-template-rows: min-content  min-content  min-content min-content;
		/*grid-gap: 1rem;*/
		grid-template-areas:
      "header header header header"
      "content-1 content-1 content-1 content-1"
      "content-1 content-1 content-1 content-1"
      ". footer footer .";
	}

	.ps-header {
		grid-area: header;
		display: flex;
		-ms-box-orient: horizontal;
		display: -webkit-box;
		display: -moz-box;
		display: -ms-flexbox;
		display: -moz-flex;
		display: -webkit-flex;
		align-content: center;
		background-color: #b2fce4;
		color: #7a7a7a;
		flex-direction: row;
		float: left;
		font-size: .875rem;

		justify-content: center;
		/*margin-bottom: 10px;*/
		/*height:auto;*/
		min-height: 35px;
		/*padding: 7px;*/
		position: relative;
		text-align: center;
		width: 100%;
		padding: 7px 0 !important;
	}

	.header-row .header-img {
		align-self: flex-start;
		width: 120px
	}

	.row-1 {
		display: flex;
		flex-direction: column;
	}

	.header-row {
		display: flex;
		flex-wrap: nowrap;
		max-height: fit-content;
		justify-content: center;
		align-items: center;
	}

	.row-1 .h-text {
		max-height: fit-content;
		align-self: center;
		-webkit-flex-wrap: wrap;
		flex-wrap: wrap;

		margin-bottom: 0 !important;

	}

	.ps-placement-container {
		display: flex;
		padding: 7px;
	}


	.content-1 {
		grid-area: content-1;
		display: inline-flex;
		justify-content: center;
		flex-direction: column;

	}

	.copy-container > p {
		margin: 10px;
		/*display: inline-block;*/
		text-align: center;
		font-size: .850rem;
	}

	.clearpay-terms {
		text-align: center;
		padding-bottom: 7px;
	}

	.header-img img {
		max-height: 25px;
		max-width: 120px;
	}

	@media (max-width: 400px) {


		.header-img {
			align-self: center;
		}

		.header-row {
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
					<img class="clearpay-checkout-ps{$PS_VERSION|escape:'htmlall':'UTF-8'}-logo" src="{$LOGO_BADGE|escape:'htmlall':'UTF-8'}">
					<span class="clearpay-checkout-ps{$PS_VERSION|escape:'htmlall':'UTF-8'}-logo-text">{$LOGO_TEXT|escape:'htmlall':'UTF-8'}</span>
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
					<div class="ps-header">
						<div class="row-1">

							<div class="header-row">
								<img class="header-img" src="{$LOGO_BADGE|escape:'htmlall':'UTF-8'}">
								<p class="h-text">{$MORE_HEADER1|escape:'htmlall':'UTF-8'}</p>
							</div>

							<p class="h-text">{$MORE_HEADER2|escape:'htmlall':'UTF-8'} </p>
						</div>

					</div>
					<div class="content-1">
						<div class="copy-container">
							<p class="long-copy-1">{$MOREINFO_ONE|escape:'htmlall':'UTF-8'}</p>
						</div>
						<div class="ps-placement-container">
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
{if $PS_VERSION === '1-9'}
	<div class="clearpay-header">
		<div class="clearpay-header-img">
			<img src="{$LOGO_BADGE|escape:'htmlall':'UTF-8'}">
		</div>
		<div class="clearpay-header-text1">
            {$MORE_HEADER1|escape:'htmlall':'UTF-8'}
		</div>
		<div class="clearpay-header-text2">
            {$MORE_HEADER2|escape:'htmlall':'UTF-8'}
		</div>
	</div>
	<div class="clearpay-more-info-text">
		<div class="clearpay-more-info">
            {$MOREINFO_ONE|escape:'htmlall':'UTF-8'}
		</div>
		<afterpay-placement
				data-type="price-table"
				data-amount="{$TOTAL_AMOUNT|escape:'htmlall':'UTF-8'}"
				data-price-table-theme="white"
				data-locale="{$ISO_COUNTRY_CODE|escape:'htmlall':'UTF-8'}"
				data-currency="{$CURRENCY|escape:'htmlall':'UTF-8'}">
		</afterpay-placement>
		<a class="clearpay-terms" href="{$TERMS_AND_CONDITIONS_LINK|escape:'htmlall':'UTF-8'}" TARGET="_blank">
            {$TERMS_AND_CONDITIONS|escape:'htmlall':'UTF-8'}
		</a>
	</div>
{/if}