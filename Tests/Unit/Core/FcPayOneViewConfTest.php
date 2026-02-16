<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller;

use Fatchip\PayOne\Application\Helper\Payment as FcPayonePaymentHelper;
use Fatchip\PayOne\Application\Helper\PayPal as FcPayonePayPalHelper;
use Fatchip\PayOne\Application\Helper\Redirect as FcPayoneRedirectHelper;
use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use Fatchip\PayOne\Core\FcPayOneViewConf;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Session;
use OxidEsales\Eshop\Core\Theme;
use OxidEsales\Eshop\Core\UtilsObject;

class FcPayOneViewConfTest extends FcBaseUnitTestCase
{
    public function testFcpoGetModulePath()
    {
        $oFcPayOneViewConf = $this->getMockBuilder(FcPayOneViewConf::class)
            ->setMethods(['getModulePath'])
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneViewConf, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('payone-gmbh/oxid-7', $oFcPayOneViewConf->fcpoGetModulePath());
    }

    public function testFcpoGetModuleUrl()
    {
        $oFcPayOneViewConf = $this->getMockBuilder(FcPayOneViewConf::class)
            ->setMethods(['getModuleUrl'])
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneViewConf, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('payone-gmbh/oxid-7', $oFcPayOneViewConf->fcpoGetModuleUrl());
    }

    public function testFcpoGetHostedPayoneJs()
    {
        $oFcPayOneViewConf = new FcPayOneViewConf();
        $this->assertEquals('https://secure.pay1.de/client-api/js/v1/payone_hosted_min.js', $oFcPayOneViewConf->fcpoGetHostedPayoneJs());
    }

    public function testFcpoGetIframeMappings()
    {
        $aMockMappings = ['some', 'mapping'];

        $oMockErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['fcpoGetExistingMappings'])
            ->disableOriginalConstructor()->getMock();
        $oMockErrorMapping->method('fcpoGetExistingMappings')->willReturn($aMockMappings);

        $oFcPayOneViewConf = new FcPayOneViewConf();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockErrorMapping);
        $this->invokeSetAttribute($oFcPayOneViewConf, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aMockMappings, $oFcPayOneViewConf->fcpoGetIframeMappings());
    }

    public function testFcpoGetLangAbbrById()
    {
        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['getLanguageAbbr'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('getLanguageAbbr')->willReturn('someAbbr');

        $oFcPayOneViewConf = new FcPayOneViewConf();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOneViewConf, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someAbbr', $oFcPayOneViewConf->fcpoGetLangAbbrById('someId'));
    }

    public function testFcpoGetActiveThemePath()
    {
        $oFcPayOneViewConf = new FcPayOneViewConf();

        $oMockTheme = $this->getMockBuilder(Theme::class)
            ->setMethods(['getActiveThemeId', 'getInfo'])
            ->disableOriginalConstructor()->getMock();
        $oMockTheme->method('getActiveThemeId')->willReturn('someInheritedThemeId');
        $oMockTheme->method('getInfo')->willReturn('apex');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockTheme);
        $this->invokeSetAttribute($oFcPayOneViewConf, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('apex', $oFcPayOneViewConf->fcpoGetActiveThemePath());
    }

    public function testFcpoUserHasSalutation()
    {
        $oFcPayOneViewConf = new FcPayOneViewConf();

        $oMockAddress = $this->getMockBuilder(Address::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockAddress->oxaddress__oxsal = new Field('MR');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getSelectedAddress'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getSelectedAddress')->willReturn($oMockAddress);
        $oMockUser->oxuser__oxsal = new Field('MR');

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getBasketUser'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getBasketUser')->willReturn($oMockUser);

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $this->invokeSetAttribute($oFcPayOneViewConf, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $oFcPayOneViewConf->fcpoUserHasSalutation());
    }

    public function testFcpoCanDisplayPayPalExpressV2Button()
    {
        FcPayonePaymentHelper::destroyInstance();

        $oFcPayonePaymentHelper = $this->getMockBuilder(FcPayonePaymentHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPayonePaymentHelper->method('isPaymentMethodActive')->willReturn(true);

        UtilsObject::setClassInstance(FcPayonePaymentHelper::class, $oFcPayonePaymentHelper);

        $oFcPayOneViewConf = new FcPayOneViewConf();
        $result = $oFcPayOneViewConf->fcpoCanDisplayPayPalExpressV2Button();

        $this->assertTrue($result);

        UtilsObject::resetClassInstances();
        FcPayonePaymentHelper::destroyInstance();
    }

    public function testFcpoGetPayPalExpressV2GetButtonId()
    {
        FcPayonePayPalHelper::destroyInstance();

        $expected = "fcpoPayPalExpressV2Posi";

        $oPayPalHelper = $this->getMockBuilder(FcPayonePayPalHelper::class)->disableOriginalConstructor()->getMock();
        $oPayPalHelper->method('showBNPLButton')->willReturn(true);

        UtilsObject::setClassInstance(FcPayonePayPalHelper::class, $oPayPalHelper);

        $oFcPayOneViewConf = new FcPayOneViewConf();
        $result = $oFcPayOneViewConf->fcpoGetPayPalExpressV2GetButtonId("Posi");

        $this->assertEquals($expected, $result);

        UtilsObject::resetClassInstances();
        FcPayonePayPalHelper::destroyInstance();
    }

    public function testFcpoGetPayPalExpressV2Values()
    {
        FcPayonePayPalHelper::destroyInstance();

        $expected = "stringValue";

        $oFcPayonePayPalHelper = $this->getMockBuilder(FcPayonePayPalHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPayonePayPalHelper->method('getJavascriptUrl')->willReturn($expected);
        $oFcPayonePayPalHelper->method('getButtonColor')->willReturn($expected);
        $oFcPayonePayPalHelper->method('getButtonShape')->willReturn($expected);

        UtilsObject::setClassInstance(FcPayonePayPalHelper::class, $oFcPayonePayPalHelper);

        $oFcPayOneViewConf = new FcPayOneViewConf();

        $result = $oFcPayOneViewConf->fcpoGetPayPalExpressV2JavascriptUrl();
        $this->assertEquals($expected, $result);

        $result = $oFcPayOneViewConf->fcpoGetPayPalExpressButtonColor();
        $this->assertEquals($expected, $result);

        $result = $oFcPayOneViewConf->fcpoGetPayPalExpressButtonShape();
        $this->assertEquals($expected, $result);

        $result = $oFcPayOneViewConf->fcpoGetPayPalExpressV2JavascriptUrl();
        $this->assertEquals($expected, $result);

        UtilsObject::resetClassInstances();
        FcPayonePayPalHelper::destroyInstance();
    }

    public function testFcpoGetPayPalExpressSuccessUrl()
    {
        FcPayoneRedirectHelper::destroyInstance();

        $expected = "successUrl";

        $oFcPayoneRedirectHelper = $this->getMockBuilder(FcPayoneRedirectHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPayoneRedirectHelper->method('getSuccessUrl')->willReturn($expected);

        UtilsObject::setClassInstance(FcPayoneRedirectHelper::class, $oFcPayoneRedirectHelper);

        $oFcPayOneViewConf = new FcPayOneViewConf();
        $result = $oFcPayOneViewConf->fcpoGetPayPalExpressSuccessUrl();
        $this->assertEquals($expected, $result);

        UtilsObject::resetClassInstances();
        FcPayoneRedirectHelper::destroyInstance();
    }

    public function testFcpoGetPayoneSecureEnvironment()
    {
        FcPayonePaymentHelper::destroyInstance();

        $expected = 'p';

        $oPaymentHelper = $this->getMockBuilder(FcPayonePaymentHelper::class)->disableOriginalConstructor()->getMock();
        $oPaymentHelper->method('isLiveMode')->willReturn(true);

        UtilsObject::setClassInstance(FcPayonePaymentHelper::class, $oPaymentHelper);

        $oFcPayOneViewConf = new FcPayOneViewConf();
        $result = $oFcPayOneViewConf->fcpoGetPayoneSecureEnvironment('test');

        $this->assertEquals($expected, $result);

        UtilsObject::resetClassInstances();
        FcPayonePaymentHelper::destroyInstance();
    }
}
