<?php

namespace Test\Install;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Test\Common\AbstractPs17Selenium;

/**
 * @requires prestashop17basic
 * @group prestashop17install
 */
class ClearpayPs17InstallTest extends AbstractPs17Selenium
{
    /**
     * @throws \Exception
     */
    public function testInstallAndConfigureClearpayInPrestashop17()
    {
        $this->loginToBackOffice();
        $this->uploadClearpay();
        $this->configureClearpay();
        $this->configureLanguagePack('72', 'Español (Spanish)');
        $this->quit();
    }

    /**
     * @throws \Exception
     */
    public function configureClearpay()
    {
        $this->findByCss('#CLEARPAY_IS_ENABLED_on + label')->click();
        $this->findById('CLEARPAY_PUBLIC_KEY')->clear()->sendKeys($this->configuration['publicKey']);
        $this->findById('CLEARPAY_SECRET_KEY')->clear()->sendKeys($this->configuration['secretKey']);
        $this->findById('configuration_form_submit_btn')->click();
        $confirmationSearch = WebDriverBy::className('module_confirmation');
        $condition = WebDriverExpectedCondition::textToBePresentInElement(
            $confirmationSearch,
            'All changes have been saved'
        );
        $this->webDriver->wait($condition);
        $this->assertTrue((bool) $condition);
    }
}
