<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller;

use Fatchip\PayOne\Application\Controller\FcPayOneBasketView;
use Fatchip\PayOne\Application\Helper\Payment;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Utils;
use OxidEsales\Eshop\Core\UtilsObject;

class FcPayOneBasketViewTest extends FcBaseUnitTestCase
{
    public function testRender()
    {
        $oFcPayOneBasketView = $this->getMockBuilder(FcPayOneBasketView::class)
            ->setMethods(['_fcpoCheckForAmazonLogoff'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneBasketView->method('_fcpoCheckForAmazonLogoff')->willReturn(null);

        $sExpect = $sResult = $oFcPayOneBasketView->render();
        $this->assertEquals($sExpect, $sResult);
    }

    public function testFcpoGetBasketErrorMessage()
    {
        $oFcPayOneBasketView = new FcPayOneBasketView();

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someMessage');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someMessage');
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOneBasketView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someMessage', $oFcPayOneBasketView->fcpoGetBasketErrorMessage());
    }

    public function testFcpoGetPayPalExpressPic()
    {
        $oFcPayOneBasketView = $this->getMockBuilder(FcPayOneBasketView::class)
            ->setMethods(['_fcpoIsPayPalExpressActive', '_fcpoGetPayPalExpressPic', 'getViewName'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneBasketView->method('_fcpoIsPayPalExpressActive')->willReturnOnConsecutiveCalls(true, false);
        $oFcPayOneBasketView->method('_fcpoGetPayPalExpressPic')->willReturn('somePic');

        $oMockPaymentHelper = $this->getMockBuilder(Payment::class)
            ->setMethods(['getInstance', 'isPaymentMethodActive'])
            ->disableOriginalConstructor()->getMock();
        $oMockPaymentHelper->method('isPaymentMethodActive')->willReturnOnConsecutiveCalls(true, false);

        UtilsObject::setClassInstance(Payment::class, $oMockPaymentHelper);

        $sResponse = $this->invokeMethod($oFcPayOneBasketView, 'fcpoGetPayPalExpressPic');
        $sExpected = 'somePic';

        $this->assertEquals($sExpected, $sResponse);

        UtilsObject::resetClassInstances();
    }

    public function testFcpoGetPayPalExpressPic_protected()
    {
        DatabaseProvider::getDb()->execute('DELETE FROM fcpopayoneexpresslogos WHERE 1');

        $sExpected = 'somePic.jpg';
        DatabaseProvider::getDb()->execute('INSERT INTO fcpopayoneexpresslogos (oxid, fcpo_active, fcpo_langid, fcpo_logo, fcpo_default) VALUES ("unitTestLogo", 1, 0, "' . $sExpected . '", "1")');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getCurrentShopUrl'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getCurrentShopUrl')->willReturn('https://someurl.com/');

        $oFcPayOneBasketView = $this->getMockBuilder(FcPayOneBasketView::class)
            ->setMethods(['getConfig'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneBasketView->method('getConfig')->willReturn($oMockConfig);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getOne')->willReturn($sExpected);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoFileExists')->willReturn(true);
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneBasketView, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneBasketView, '_sPayPalExpressLogoPath', 'somePath/');

        $sResponse = $this->invokeMethod($oFcPayOneBasketView, '_fcpoGetPayPalExpressPic');
        $sExpect = 'https://someurl.com/somePath/' . $sExpected;

        $this->assertEquals($sExpect, $sResponse);

        DatabaseProvider::getDb()->execute('DELETE FROM fcpopayoneexpresslogos WHERE oxid = "unitTestLogo"');
    }

    public function testFcpoUsePayPalExpress_Error()
    {
        $oFcPayOneBasketView = $this->getMockBuilder(FcPayOneBasketView::class)
            ->disableOriginalConstructor()->getMock();

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['redirect'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('redirect')->willReturn(false);

        $aMockOutput['status'] = 'ERROR';
        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['load', 'sendRequestGenericPayment'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('sendRequestGenericPayment')->willReturn($aMockOutput);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockRequest);
        $this->invokeSetAttribute($oFcPayOneBasketView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $this->invokeMethod($oFcPayOneBasketView, 'fcpoUsePayPalExpress'));
    }

    public function testFcpoUsePayPalExpress_Redirect()
    {
        $oFcPayOneBasketView = $this->getMockBuilder(FcPayOneBasketView::class)
            ->disableOriginalConstructor()->getMock();

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['redirect'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('redirect')->willReturn(false);

        $aMockOutput['status'] = 'REDIRECT';
        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['load', 'sendRequestGenericPayment'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('sendRequestGenericPayment')->willReturn($aMockOutput);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockRequest);
        $this->invokeSetAttribute($oFcPayOneBasketView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $this->invokeMethod($oFcPayOneBasketView, 'fcpoUsePayPalExpress'));
    }

    protected function _fcpoPreparePaypalExpressLogos()
    {
        $this->_fcpoTruncateTable('fcpopayoneexpresslogos');
        $sQuery = "
            INSERT INTO `fcpopayoneexpresslogos` (`OXID`, `FCPO_ACTIVE`, `FCPO_LANGID`, `FCPO_LOGO`, `FCPO_DEFAULT`) VALUES
            (1, 1, 0, 'fc_andre_sw_02_250px.1.png', 1),
            (2, 1, 1, 'btn_xpressCheckout_en.gif', 0)
        ";

        DatabaseProvider::getDb()->Execute($sQuery);
    }

    protected function _fcpoTruncateTable($sTableName)
    {
        $sQuery = "DELETE FROM `$sTableName` ";

        DatabaseProvider::getDb()->Execute($sQuery);
    }
}
