<?php
namespace Test\Selenium\Basic;

use Facebook\WebDriver\WebDriverExpectedCondition;
use Test\Common\AbstractPs17Selenium;

/**
 * @group prestashop17basic
 */
class BasicPs17Test extends AbstractPs17Selenium
{
    /**
     * Const title
     */
    const PRODUCT_TITLE = 'Hummingbird printed t-shirt';

    /**
     * Const title
     */
    const ADMIN_TITLE = 'PrestaShop (PrestaShop™)';

    /**
     * @throws \Exception
     */
    public function testTitlePrestashop17()
    {
        $this->webDriver->get(self::PS17URL.'/men/1-1-hummingbird-printed-t-shirt.html#/1-size-s/8-color-white');
        $condition = WebDriverExpectedCondition::titleContains(self::PRODUCT_TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
        $this->quit();
    }

    /**
     * @throws \Exception
     */
    public function testBackOfficeTitlePrestashop17()
    {
        $this->webDriver->get(self::PS17URL.self::BACKOFFICE_FOLDER);
        $condition = WebDriverExpectedCondition::titleContains(self::ADMIN_TITLE);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
        $this->quit();
    }
}
