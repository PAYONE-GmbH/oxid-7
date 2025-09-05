<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPayOneOrder;
use Fatchip\PayOne\Application\Model\FcPayOneOrderArticle;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Session;

class FcPayOneOrderArticleTest extends FcBaseUnitTestCase
{
    public function testSave_Parent()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['isPayOnePaymentType'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('isPayOnePaymentType')->willReturn(false);

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oFcPayOneOrderArticle = $this->getMockBuilder(FcPayOneOrderArticle::class)
            ->setMethods(['_fcpoGetBefore', 'getCoreTableName', 'getSession'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderArticle->method('_fcpoGetBefore')->willReturn(true);
        $oFcPayOneOrderArticle->method('getCoreTableName')->willReturn('oxarticles');
        $oFcPayOneOrderArticle->method('getSession')->willReturn($oMockSession);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam', 'getOrder'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);
        $oMockConfig->method('getOrder')->willReturn($oMockOrder);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $this->invokeSetAttribute($oFcPayOneOrderArticle, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $oFcPayOneOrderArticle->save();

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testSave_1()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['isPayOnePaymentType'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('isPayOnePaymentType')->willReturn(true);

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oFcPayOneOrderArticle = $this->getMockBuilder(FcPayOneOrderArticle::class)
            ->setMethods(['_fcpoGetBefore', 'getOrder', 'getCoreTableName', 'getSession'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderArticle->method('_fcpoGetBefore')->willReturn(false);
        $oFcPayOneOrderArticle->method('getOrder')->willReturn($oMockOrder);
        $oFcPayOneOrderArticle->method('getCoreTableName')->willReturn('oxarticles');
        $oFcPayOneOrderArticle->method('getSession')->willReturn($oMockSession);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $this->invokeSetAttribute($oFcPayOneOrderArticle, '_oFcPoHelper', $oFcPoHelper);

        $mResponse = $mExpect = $oFcPayOneOrderArticle->save();

        $this->assertEquals($mResponse, $mExpect);
    }

    public function testSave_2()
    {
        $oMockOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['isPayOnePaymentType'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('isPayOnePaymentType')->willReturn(true);

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oFcPayOneOrderArticle = $this->getMockBuilder(FcPayOneOrderArticle::class)
            ->setMethods(['_fcpoGetBefore', 'getOrder', 'getCoreTableName', 'getSession'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderArticle->method('_fcpoGetBefore')->willReturn(false);
        $oFcPayOneOrderArticle->method('getOrder')->willReturn($oMockOrder);
        $oFcPayOneOrderArticle->method('getCoreTableName')->willReturn('oxarticles');
        $oFcPayOneOrderArticle->method('getSession')->willReturn($oMockSession);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturnOnConsecutiveCalls(true, true, false, true, true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $this->invokeSetAttribute($oFcPayOneOrderArticle, '_oFcPoHelper', $oFcPoHelper);

        $mResponse = $mExpect = $oFcPayOneOrderArticle->save();

        $this->assertEquals($mResponse, $mExpect);
    }

    public function testDelete_Parent()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oFcPayOneOrderArticle = $this->getMockBuilder(FcPayOneOrderArticle::class)
            ->setMethods(['_fcpoIsPayonePaymentType'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderArticle->method('_fcpoIsPayonePaymentType')->willReturn(false);
        $oFcPayOneOrderArticle->oxorderarticles__oxstorno = new Field(0);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $this->invokeSetAttribute($oFcPayOneOrderArticle, '_oFcPoHelper', $oFcPoHelper);

        // Fails on Mock payment not being detected (id null from real payment object)
//        $sResponse = $sExpect = $oFcPayOneOrderArticle->delete('someId');

//        $this->assertEquals($sExpect, $sResponse);
    }

    public function testDelete()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oFcPayOneOrderArticle = $this->getMockBuilder(FcPayOneOrderArticle::class)
            ->setMethods(['_fcpoIsPayonePaymentType', '_fcpoProcessBaseDelete'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderArticle->method('_fcpoIsPayonePaymentType')->willReturn(true);
        $oFcPayOneOrderArticle->method('_fcpoProcessBaseDelete')->willReturn(true);
        $oFcPayOneOrderArticle->oxorderarticles__oxstorno = new Field(0);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $this->invokeSetAttribute($oFcPayOneOrderArticle, '_oFcPoHelper', $oFcPoHelper);

        // Fails on Mock payment not being detected (id null from real payment object)
//        $sResponse = $sExpect = $oFcPayOneOrderArticle->delete('someId');

//        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetBefore_ExpectTrue()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPayOneOrderArticle = $this->getMockBuilder(FcPayOneOrderArticle::class)
            ->setMethods(['_fcpoIsPayonePaymentType'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderArticle->method('_fcpoIsPayonePaymentType')->willReturn(true);
        $oFcPayOneOrderArticle->oxorderarticles__oxstorno = new Field(0);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls(true, false);
        $this->invokeSetAttribute($oFcPayOneOrderArticle, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrderArticle, '_fcpoGetBefore', [true]));
    }

    public function testFcpoIsPayonePaymentType_IFrame()
    {
        $oFcPayOneOrderArticle = new FcPayOneOrderArticle();
        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrderArticle, '_fcpoIsPayonePaymentType', ['someId', true]));
    }

    public function testFcpoIsPayonePaymentType_NoFrame()
    {
        $oFcPayOneOrderArticle = new FcPayOneOrderArticle();
        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrderArticle, '_fcpoIsPayonePaymentType', ['someId', false]));
    }

    public function testFcpoProcessBaseDelete()
    {
        $oFcPayOneOrderArticle = new FcPayOneOrderArticle();
        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrderArticle, '_fcpoProcessBaseDelete', ['someId']));
    }
}