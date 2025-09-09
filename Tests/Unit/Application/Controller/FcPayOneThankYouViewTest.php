<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller;

use Fatchip\PayOne\Application\Controller\FcPayOneThankYouView;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;

class FcPayOneThankYouViewTest extends FcBaseUnitTestCase
{
    public function testFcpoGetMandatePdfUrl_Active()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam', 'getShopUrl'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someConfigParam');
        $oMockConfig->method('getShopUrl')->willReturn('https://www.someshopurl.org/');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('getId')->willReturn('someId');
        $oMockOrder->oxorder__oxpaymenttype = new Field('fcpodebitnote');

        $oMockUser = $this->getMockBuilder(User::class)->disableOriginalConstructor()->getMock();
        $oMockUser->oxuser__oxpassword = new Field(false);

        $oFcPayOneThankYouView = $this->getMockBuilder(FcPayOneThankYouView::class)
            ->setMethods(['getConfig', 'getOrder', 'getUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneThankYouView->method('getConfig')->willReturn($oMockConfig);
        $oFcPayOneThankYouView->method('getOrder')->willReturn($oMockOrder);
        $oFcPayOneThankYouView->method('getUser')->willReturn($oMockUser);

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load', 'fcpoAddMandateToDb'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('load')->willReturn(true);

        $aMockMandate = [
            'mandate_identification' => 'someValue',
            'mode' => 'test',
            'mandate_status' => 'active',
        ];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturnOnConsecutiveCalls($aMockMandate, 'someUserId');
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockPayment);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneThankYouView, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oMockPayment, '_oFcPoHelper', $oFcPoHelper);

        $sExpect = 'https://www.someshopurl.org/modules/fc/fcpayone/download.php?id=someId&uid=someUserId';

        $this->assertEquals($sExpect, $oFcPayOneThankYouView->fcpoGetMandatePdfUrl());
    }

    public function testFcpoGetMandatePdfUrl_Inactive()
    {
        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(true);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam', 'getShopUrl'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someConfigParam');
        $oMockConfig->method('getShopUrl')->willReturn('https://www.someshopurl.org/');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('getId')->willReturn('someId');
        $oMockOrder->oxorder__oxpaymenttype = new Field('fcpodebitnote');

        $oMockUser = $this->getMockBuilder(User::class)->disableOriginalConstructor()->getMock();
        $oMockUser->oxuser__oxpassword = new Field(false);

        $oFcPayOneThankYouView = $this->getMockBuilder(FcPayOneThankYouView::class)
            ->setMethods(['getConfig', 'getOrder', 'getUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneThankYouView->method('getConfig')->willReturn($oMockConfig);
        $oFcPayOneThankYouView->method('getOrder')->willReturn($oMockOrder);
        $oFcPayOneThankYouView->method('getUser')->willReturn($oMockUser);

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load', 'fcpoGetOperationMode'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('load')->willReturn(true);
        $oMockPayment->method('fcpoGetOperationMode')->willReturn('test');

        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['sendRequestGetFile'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('sendRequestGetFile')->willReturn('https://www.someurl.org/somepdf.pdf');

        $aMockMandate = [
            'mandate_identification' => 'someValue',
            'mode' => 'test',
            'mandate_status' => 'lazy'
        ];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturnOnConsecutiveCalls($aMockMandate, 'someUserId');
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockPayment, $oMockRequest);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneThankYouView, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneThankYouView, '_oFcPoDb', $oMockDatabase);

        $sExpect = 'https://www.someurl.org/somepdf.pdf&uid=someUserId';

        $this->assertEquals($sExpect, $oFcPayOneThankYouView->fcpoGetMandatePdfUrl());
    }

    public function testFcpoOrderHasProblemsError()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['isPayOnePaymentType'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('isPayOnePaymentType')->willReturn(true);
        $oMockOrder->oxorder__oxfolder = new Field('ORDERFOLDER_PROBLEMS');
        $oMockOrder->oxorder__oxtransstatus = new Field('ERROR');

        $oFcPayOneThankYouView = $this->getMockBuilder(FcPayOneThankYouView::class)
            ->setMethods(['getOrder'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneThankYouView->method('getOrder')->willReturn($oMockOrder);

        $this->assertEquals(true, $oFcPayOneThankYouView->fcpoOrderHasProblems());
    }

    public function testRender()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn('someId');

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getProductsCount'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getProductsCount')->willReturn(5);

        $oFcPayOneThankYouView = $this->getMockBuilder(FcPayOneThankYouView::class)
            ->setMethods(['getUser', '_fcpoHandleAmazonThankyou', '_fcpoDeleteSessionVariablesOnOrderFinish'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneThankYouView->method('getUser')->willReturn($oMockUser);
        $oFcPayOneThankYouView->method('_fcpoHandleAmazonThankyou')->willReturn(null);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneThankYouView, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneThankYouView, '_oBasket', $oMockBasket);

        $this->assertEquals('page/checkout/thankyou', $oFcPayOneThankYouView->render());
    }

    public function testFcpoDeleteSessionVariablesOnOrderFinish()
    {
        $oFcPayOneThankYouView = new FcPayOneThankYouView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneThankYouView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneThankYouView, '_fcpoDeleteSessionVariablesOnOrderFinish'));
    }
}
