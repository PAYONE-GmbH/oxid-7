<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller;

use Exception;
use Fatchip\PayOne\Application\Controller\FcPayOneOrderView;
use Fatchip\PayOne\Application\Helper\PayPal;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\DeliverySet;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Session;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Core\UtilsView;

class FcPayOneOrderViewTest extends FcBaseUnitTestCase
{
    public function testExecute_Mandate()
    {
        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods(['_fcpoMandateAcceptanceNeeded'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('_fcpoMandateAcceptanceNeeded')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('false');
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $mResponse = $mExpect = $oFcPayOneOrderView->execute();

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testExecute_Parent()
    {
        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods(['_fcpoMandateAcceptanceNeeded'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('_fcpoMandateAcceptanceNeeded')->willReturn(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('true');
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $mResponse = $mExpect = $oFcPayOneOrderView->execute();

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testFcpoHandlePayPalExpress_PositiveCall()
    {
        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods(['_handlePayPalExpressCall'])
            ->disableOriginalConstructor()->getMock();

        $oMockUtilsView = $this->getMockBuilder(UtilsView::class)
            ->setMethods(['addErrorToDisplay'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsView->method('addErrorToDisplay')->willReturn(null);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsView')->willReturn($oMockUtilsView);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $oFcPayOneOrderView->fcpoHandlePayPalExpress());
        $this->assertEquals(null, $oFcPayOneOrderView->fcpoHandlePayPalExpressV2());
    }

    public function testFcpoHandlePayPalExpress_Exception()
    {
        $oMockException = new Exception();

        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods(['_handlePayPalExpressCall'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('_handlePayPalExpressCall')->willThrowException($oMockException);

        $oMockUtilsView = $this->getMockBuilder(UtilsView::class)
            ->setMethods(['addErrorToDisplay'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsView->method('addErrorToDisplay')->willReturn(null);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsView')->willReturn($oMockUtilsView);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $sExpected = 'basket';

        $this->assertEquals($sExpected, $oFcPayOneOrderView->fcpoHandlePayPalExpress());
        $this->assertEquals($sExpected, $oFcPayOneOrderView->fcpoHandlePayPalExpressV2());
    }

    public function testGetNextStep()
    {
        $iMockSuccess = 1;
        $sMockRedirectAction = 'thankyou';

        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->disableOriginalConstructor()->getMock();

        $this->assertEquals($sMockRedirectAction, $this->invokeMethod($oFcPayOneOrderView, 'getNextStep', [$iMockSuccess]));
    }

    public function testFcpoDoesExpressUserAlreadyExist()
    {
        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->disableOriginalConstructor()->getMock();

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['fcpoDoesUserAlreadyExist'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('fcpoDoesUserAlreadyExist')->willReturn('someUserId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockUser);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someUserId', $this->invokeMethod($oFcPayOneOrderView, '_fcpoDoesExpressUserAlreadyExist', ['someEmail']));
    }

    public function testFcpoGetIdByUserName()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['fcpoGetIdByUserName'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('fcpoGetIdByUserName')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someId', $this->invokeMethod($oFcPayOneOrderView, '_fcpoGetIdByUserName', ['someUserName']));
    }

    public function testFcpoGetIdByCode()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['fcpoGetIdByCode'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('fcpoGetIdByCode')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someId', $this->invokeMethod($oFcPayOneOrderView, '_fcpoGetIdByCode', ['someCode']));
    }

    public function testFcpoGetSal_MR()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['fcpoGetSalByFirstName'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('fcpoGetSalByFirstName')->willReturn('Herr');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('MR', $this->invokeMethod($oFcPayOneOrderView, '_fcpoGetSal', ['someFirstName']));
    }

    public function testFcpoGetSal_MRS()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['fcpoGetSalByFirstName'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('fcpoGetSalByFirstName')->willReturn('Frau');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('MRS', $this->invokeMethod($oFcPayOneOrderView, '_fcpoGetSal', ['someFirstName']));
    }

    public function testFcpoIsSamePayPalUser()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();

        $aResponseParam = [
            'add_paydata[shipping_firstname]' => 'someFirstName',
            'add_paydata[shipping_lastname]' => 'someLastName',
            'add_paydata[shipping_city]' => 'someCity',
            'add_paydata[shipping_street]' => 'someStreet',
        ];

        $oMockUserParam = $this->getMockBuilder(User::class)->disableOriginalConstructor()->getMock();
        $oMockUserParam->oxuser__oxfname = new Field('someOtherFirstName');
        $oMockUserParam->oxuser__oxlname = new Field('someOtherLastName');
        $oMockUserParam->oxuser__oxcity = new Field('someOtherCity');
        $oMockUserParam->oxuser__oxstreet = new Field('someStreet');

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrderView, '_fcpoIsSamePayPalUser', [$oMockUserParam, $aResponseParam]));
    }

    public function testFcpoHandlePaypalExpressUser_RemoveAddressFromSession()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId', 'load'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn(true);
        $oMockUser->method('load')->willReturn(true);
        $oMockUser->oxuser__oxusername = new Field('someEmail');

        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods([
                'getUser',
                '_fcpoDoesExpressUserAlreadyExist',
                '_fcpoIsSamePayPalUser',
                '_fcpoCreateExpressDelAddress',
                '_fcpoThrowException',
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('getUser')->willReturn($oMockUser);
        $oFcPayOneOrderView->method('_fcpoDoesExpressUserAlreadyExist')->willReturn('someUserId');
        $oFcPayOneOrderView->method('_fcpoIsSamePayPalUser')->willReturnOnConsecutiveCalls(true, true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockUser);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $aParams = [
            'add_paydata[shipping_street]' => 'someStreet someStreetNr',
            'add_paydata[shipping_addressaddition]' => 'someAddition',
            'add_paydata[email]' => 'someUserMail',
            'add_paydata[shipping_firstname]' => 'someFirstName',
            'add_paydata[shipping_lastname]' => 'someLastName',
            'add_paydata[shipping_city]' => 'someCity',
            'add_paydata[shipping_zip]' => 'someZip',
            'add_paydata[shipping_country]' => 'someCountry',
        ];

        $mResponse = $mExpect = $this->invokeMethod($oFcPayOneOrderView, '_fcpoHandleExpressUser', [$aParams]);

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testFcpoHandlePaypalExpressUser_CreatePaypalDelAddress()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId', 'load'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn(true);
        $oMockUser->method('load')->willReturn(true);
        $oMockUser->oxuser__oxusername = new Field('someEmail');

        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods([
                'getUser',
                '_fcpoDoesExpressUserAlreadyExist',
                '_fcpoIsSamePayPalUser',
                '_fcpoCreateExpressDelAddress',
                '_fcpoThrowException',
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('getUser')->willReturn($oMockUser);
        $oFcPayOneOrderView->method('_fcpoDoesExpressUserAlreadyExist')->willReturn('someUserId');
        $oFcPayOneOrderView->method('_fcpoIsSamePayPalUser')->willReturnOnConsecutiveCalls(false, false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockUser);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $aParams = [
            'add_paydata[shipping_street]' => 'someStreet someStreetNr',
            'add_paydata[shipping_addressaddition]' => 'someAddition',
            'add_paydata[email]' => 'someUserMail',
            'add_paydata[shipping_firstname]' => 'someFirstName',
            'add_paydata[shipping_lastname]' => 'someLastName',
            'add_paydata[shipping_city]' => 'someCity',
            'add_paydata[shipping_zip]' => 'someZip',
            'add_paydata[shipping_country]' => 'someCountry',
        ];

        $mResponse = $mExpect = $this->invokeMethod($oFcPayOneOrderView, '_fcpoHandleExpressUser', [$aParams]);

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testFcpoHandlePaypalExpressUser_ThrowException()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId', 'load'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn(true);
        $oMockUser->method('load')->willReturn(true);
        $oMockUser->oxuser__oxusername = new Field('someEmail');

        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods([
                'getUser',
                '_fcpoDoesExpressUserAlreadyExist',
                '_fcpoIsSamePayPalUser',
                '_fcpoCreateExpressDelAddress',
                '_fcpoThrowException',
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('getUser')->willReturn(false);
        $oFcPayOneOrderView->method('_fcpoDoesExpressUserAlreadyExist')->willReturn('someUserId');
        $oFcPayOneOrderView->method('_fcpoIsSamePayPalUser')->willReturnOnConsecutiveCalls(false, false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockUser);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $aParams = [
            'add_paydata[shipping_street]' => 'someStreet someStreetNr',
            'add_paydata[shipping_addressaddition]' => 'someAddition',
            'add_paydata[email]' => 'someUserMail',
            'add_paydata[shipping_firstname]' => 'someFirstName',
            'add_paydata[shipping_lastname]' => 'someLastName',
            'add_paydata[shipping_city]' => 'someCity',
            'add_paydata[shipping_zip]' => 'someZip',
            'add_paydata[shipping_country]' => 'someCountry',
        ];

        $mResponse = $mExpect = $this->invokeMethod($oFcPayOneOrderView, '_fcpoHandleExpressUser', [$aParams]);

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testFcpoHandlePaypalExpressUser_CreatePaypalAddress()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId', 'load', 'exists', 'getCoreTableName'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn(true);
        $oMockUser->method('load')->willReturn(true);
        $oMockUser->method('exists')->willReturn(false);
        $oMockUser->method('getCoreTableName')->willReturn('oxuser');
        $oMockUser->oxuser__oxusername = new Field('someEmail');

        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods([
                'getUser',
                '_fcpoDoesExpressUserAlreadyExist',
                '_fcpoIsSamePayPalUser',
                '_fcpoCreateExpressDelAddress',
                '_fcpoThrowException',
                '_fcpoGetIdByUserName',
                '_fcpoGetSal',
                '_fcpoGetIdByCode'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('getUser')->willReturn($oMockUser);
        $oFcPayOneOrderView->method('_fcpoDoesExpressUserAlreadyExist')->willReturn(false);
        $oFcPayOneOrderView->method('_fcpoIsSamePayPalUser')->willReturnOnConsecutiveCalls(true, false);
        $oFcPayOneOrderView->method('_fcpoGetIdByUserName')->willReturn('someUserId');
        $oFcPayOneOrderView->method('_fcpoGetSal')->willReturn('MR');
        $oFcPayOneOrderView->method('_fcpoGetIdByCode')->willReturn('TEST');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockUser);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oMockUser, '_oFcPoHelper', $oFcPoHelper);

        $aParams = [
            'add_paydata[shipping_street]' => 'someStreet someStreetNr',
            'add_paydata[shipping_addressaddition]' => 'someAddition',
            'add_paydata[email]' => 'someUserMail',
            'add_paydata[shipping_firstname]' => 'someFirstName',
            'add_paydata[shipping_lastname]' => 'someLastName',
            'add_paydata[shipping_city]' => 'someCity',
            'add_paydata[shipping_zip]' => 'someZip',
            'add_paydata[shipping_country]' => 'someCountry',
        ];

        $mResponse = $mExpect = $this->invokeMethod($oFcPayOneOrderView, '_fcpoHandleExpressUser', [$aParams]);

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testFcpoThrowException()
    {
        $this->wrapExpectException('Exception');

        $oFcPayOneOrderView = new FcPayOneOrderView();

        $oMockException = $this->getMockBuilder(Exception::class)
            ->setMethods(['setMessage'])
            ->disableOriginalConstructor()->getMock();

        UtilsObject::setClassInstance(Exception::class, $oMockException);

        $this->invokeMethod($oFcPayOneOrderView, '_fcpoThrowException', ['someMessage']);

        UtilsObject::resetClassInstances();
    }

    public function testHandlePayPalExpressCall()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods([
                'setBasketUser',
                'setPayment',
                'setShipping',
                'onUpdate',
                'calculateBasket'
            ])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('setBasketUser')->willReturn(true);
        $oMockBasket->method('setPayment')->willReturn(true);
        $oMockBasket->method('setShipping')->willReturn(true);
        $oMockBasket->method('onUpdate')->willReturn(true);
        $oMockBasket->method('calculateBasket')->willReturn(true);

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $aDeliverySetData = ['1', '1', []];
        $oMockOxDeliverySet = $this->getMockBuilder(DeliverySet::class)
            ->setMethods(['getDeliverySetData'])
            ->disableOriginalConstructor()->getMock();
        $oMockOxDeliverySet->method('getDeliverySetData')->willReturn($aDeliverySetData);

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('load')->willReturn(true);
        $oMockUser->oxuser__oxusername = new Field('someEmail');

        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods(['_fcpoHandleUser', '_fcpoDoesExpressUserAlreadyExist', '_fcpoIsSamePayPalUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('_fcpoHandleUser')->willReturn($oMockUser);
        $oFcPayOneOrderView->method('_fcpoDoesExpressUserAlreadyExist')->willReturn('someEmail');
        $oFcPayOneOrderView->method('_fcpoIsSamePayPalUser')->willReturn(true);

        $aMockOutput = [
            'add_paydata[email]' => 'someEmail'
        ];
        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['sendRequestGenericPayment', 'load'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('sendRequestGenericPayment')->willReturn($aMockOutput);
        $oMockRequest->method('load')->willReturn(true);

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load', 'fcpoGetIdByUserName'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->method('fcpoGetIdByUserName')->willReturn('someUserId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someValue');
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockRequest, $oMockUser, $oMockOxDeliverySet);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $mResponse = $mExpect = $this->invokeMethod($oFcPayOneOrderView, '_handlePayPalExpressCall', [PayPal::PPE_EXPRESS]);

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testFcpoMandateAcceptanceNeeded_Yes()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();
        $aMockMandate = [
            'mandate_status' => 'pending',
            'mandate_text' => 'someText',
        ];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn($aMockMandate);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrderView, '_fcpoMandateAcceptanceNeeded'));
    }

    public function testFcpoMandateAcceptanceNeeded_No()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();
        $aMockMandate = [
            'someblabla' => 'falseValue',
            'mandate_next' => 'moreCrap',
        ];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn($aMockMandate);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrderView, '_fcpoMandateAcceptanceNeeded'));
    }

    public function testFcpoIsMandateError()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();
        $this->invokeSetAttribute($oFcPayOneOrderView, '_blFcpoConfirmMandateError', false);

        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrderView, 'fcpoIsMandateError'));
    }

    public function testValidateTermsAndConditions()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['hasArticlesWithDownloadableAgreement'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('hasArticlesWithDownloadableAgreement')->willReturn(true);

        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('getBasket')->willReturn($oMockBasket);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam', 'getRequestParameter'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturnOnConsecutiveCalls(true, true);
        $oMockConfig->method('getRequestParameter')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls(true, true);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $blResponse = $this->invokeMethod($oFcPayOneOrderView, 'validateTermsAndConditions');

        $this->assertEquals(true, $blResponse);
    }

    public function testFcpoSplitAddress()
    {
        $oFcPayOneOrderView = new FcPayOneOrderView();

        $sInput = "MyStreet 123";

        $aExpect = ['MyStreet', '123'];

        $aResult = $this->invokeMethod($oFcPayOneOrderView, '_fcpoSplitAddress', [$sInput]);

        $this->assertEquals($aExpect, $aResult);
    }

    public function testFcpoCreatePayPalDelAddress_HasAddressId()
    {
        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods(['_fcpoGetExistingPayPalAddressId', '_fcpoSplitAddress'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('_fcpoGetExistingPayPalAddressId')->willReturn('someAddressId');
        $oFcPayOneOrderView->method('_fcpoSplitAddress')->willReturn(['MyStreet', '123']);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $aMockResponse = ['add_paydata[shipping_addressaddition]' => 'someAddition'];

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrderView, '_fcpoCreateExpressDelAddress', [$aMockResponse, 'someUserId']));
    }

    public function testFcpoCreatePayPalDelAddress_NoAddressId()
    {
        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods([
                '_fcpoGetExistingPayPalAddressId',
                '_fcpoSplitAddress',
                '_fcpoGetIdByCode',
                '_fcpoGetSal'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrderView->method('_fcpoGetExistingPayPalAddressId')->willReturn('someAddressId');
        $oFcPayOneOrderView->method('_fcpoSplitAddress')->willReturn(['MyStreet', '123']);
        $oFcPayOneOrderView->method('_fcpoGetIdByCode')->willReturn('someCountryId');
        $oFcPayOneOrderView->method('_fcpoGetSal')->willReturn('someSalutation');

        $oMockAddress = $this->getMockBuilder(Address::class)
            ->setMethods(['save',])
            ->disableOriginalConstructor()->getMock();
        $oMockAddress->method('save')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockAddress);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $aMockResponse = ['add_paydata[shipping_addressaddition]' => 'someAddition'];

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrderView, '_fcpoCreateExpressDelAddress', [$aMockResponse, 'someUserId']));
    }

    public function testFcpoGetExistingPayPalAddressId_Success()
    {
        $oFcPayOneOrderView = $this->getMockBuilder(FcPayOneOrderView::class)
            ->setMethods(['_fcpoGetIdByCode'])
            ->disableOriginalConstructor()->getMock();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['fcpoGetAddressIdByResponse'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('fcpoGetAddressIdByResponse')->willReturn('someAddressId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOneOrderView, '_oFcPoHelper', $oFcPoHelper);

        $aMockResponse = ['add_paydata[shipping_street]' => 'MyStreet 123'];

        $this->assertEquals('someAddressId', $this->invokeMethod($oFcPayOneOrderView, '_fcpoGetExistingPayPalAddressId', [$aMockResponse]));
    }
}
