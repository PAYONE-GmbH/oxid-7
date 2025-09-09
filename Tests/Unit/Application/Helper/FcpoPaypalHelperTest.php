<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Model;

use Fatchip\PayOne\Application\Helper\Payment as FcPayonePaymentHelper;
use Fatchip\PayOne\Application\Helper\PayPal as FcPayonePaypalHelper;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Session;
use OxidEsales\Eshop\Core\UtilsObject;

class FcpoPaypalHelperTest extends FcBaseUnitTestCase
{
    public function testGetButtonColor()
    {
        $sExpected = "gold";

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn($sExpected);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);

        UtilsObject::setClassInstance(fcpohelper::class, $oFcPoHelper);

        $oPaypalHelper = new FcPayonePaypalHelper();
        $sResult = $oPaypalHelper->getButtonColor();

        $this->assertEquals($sExpected, $sResult);

        UtilsObject::resetClassInstances();
    }

    public function testGetButtonShape()
    {
        $sExpected = "rect";

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn($sExpected);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);

        UtilsObject::setClassInstance(fcpohelper::class, $oFcPoHelper);

        $oPaypalHelper = new FcPayonePaypalHelper();
        $sResult = $oPaypalHelper->getButtonShape();

        $this->assertEquals($sExpected, $sResult);

        UtilsObject::resetClassInstances();
    }

    public function testShowBNPLButton()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);

        UtilsObject::setClassInstance(fcpohelper::class, $oFcPoHelper);

        $oPaypalHelper = new FcPayonePaypalHelper();
        $sResult = $oPaypalHelper->showBNPLButton();

        $this->assertTrue($sResult);

        UtilsObject::resetClassInstances();
    }

    public function testGetJavascriptUrl()
    {
        $oPaymentHelper = $this->getMockBuilder(FcPayonePaymentHelper::class)->disableOriginalConstructor()->getMock();
        $oPaymentHelper->method('isLiveMode')->willReturn(true);

        UtilsObject::setClassInstance(FcPayonePaymentHelper::class, $oPaymentHelper);

        $oCurrency = (object)['name' => 'EURTEST'];

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getBasketCurrency'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getBasketCurrency')->willReturn($oCurrency);

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturnMap(
            [
                ['blFCPOPayPalV2MerchantID', null, 'merchantId'],
                ['blFCPOPayPalV2BNPL', null, true],
            ]
        );

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('not_found');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);

        UtilsObject::setClassInstance(fcpohelper::class, $oFcPoHelper);

        $oPaypalHelper = new FcPayonePaypalHelper();
        $sResult = $oPaypalHelper->getJavascriptUrl();

        $this->assertStringContainsString("FZ8jE7shhaY2mVydsWsSrjmHk0qJxmgJoWgHESqyoG35jL", $sResult);
        $this->assertStringContainsString("merchantId", $sResult);
        $this->assertStringContainsString("EURTEST", $sResult);
        $this->assertStringContainsString("enable-funding=paylater", $sResult);

        UtilsObject::resetClassInstances();
        FcPayonePaypalHelper::destroyInstance();
    }

    public function testGetInstance()
    {
        $oResult = FcPayonePaypalHelper::getInstance();
        $this->assertInstanceOf(FcPayonePaypalHelper::class, $oResult);

        FcPayonePaypalHelper::destroyInstance();
    }
}
