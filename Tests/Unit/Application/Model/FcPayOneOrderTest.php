<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPayOneOrder;
use Fatchip\PayOne\Application\Model\FcPoTransactionStatus;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\BasketItem;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\PaymentGateway;
use OxidEsales\Eshop\Application\Model\Shop;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Application\Model\UserPayment;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Counter;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\ArticleInputException;
use OxidEsales\Eshop\Core\Exception\NoArticleException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Model\ListModel;
use OxidEsales\Eshop\Core\Session;
use OxidEsales\Eshop\Core\Utils;
use OxidEsales\Eshop\Core\UtilsDate;
use OxidEsales\Eshop\Core\UtilsServer;

class FcPayOneOrderTest extends FcBaseUnitTestCase
{
    public function testIsPayOnePaymentType()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('fcpodebitnote');

        $this->assertEquals(true, $oFcPayOneOrder->isPayOnePaymentType());
    }

    public function testIsPayOneIframePayment()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('fcpodebitnote');

        $this->assertEquals(false, $oFcPayOneOrder->isPayOneIframePayment());
    }

    public function testFcpoGetIdByUserName()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someUserId');
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals('someUserId', $oFcPayOneOrder->fcpoGetIdByUserName('someUserName'));
    }

    public function testFcpoGetIdByCode()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someValue');
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals('someValue', $oFcPayOneOrder->fcpoGetIdByCode('someCode'));
    }

    public function testFcpoGetSalByFirstName()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someSalutation');
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals('someSalutation', $oFcPayOneOrder->fcpoGetSalByFirstName('someFirstname'));
    }

    public function testFcpoGetAddressIdByResponse()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoGetIdByCode'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoGetIdByCode')->willReturn('someId');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someAddressId');
        $oMockDatabase->method('quote')->willReturn('someValue');
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $aMockResponse = [
            'add_paydata[shipping_firstname]' => 'someFirstname',
            'add_paydata[shipping_lastname]' => 'someLastname',
            'add_paydata[shipping_city]' => 'someCity',
            'add_paydata[shipping_zip]' => 'someZip',
            'add_paydata[shipping_country]' => 'someCountry'
        ];

        $this->assertEquals('someAddressId', $oFcPayOneOrder->fcpoGetAddressIdByResponse($aMockResponse, 'someStreet', 'someStreetNr'));
    }

    public function testFcProcessUserAgentInfo()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $this->assertEquals('someStupidAgent', $this->invokeMethod($oFcPayOneOrder, '_fcProcessUserAgentInfo', ['someStupidAgent']));
    }

    public function testFcpoCheckUserAgent()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcProcessUserAgentInfo', '_fcpoValidateToken'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcProcessUserAgentInfo')->willReturnOnConsecutiveCalls('someVar', 'someOtherVar');
        $oFcPayOneOrder->method('_fcpoValidateToken')->willReturn(true);

        $oMockUtilsServer = $this->getMockBuilder(UtilsServer::class)
            ->setMethods(['getServerVar'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsServer->method('getServerVar')->willReturn('someVar');

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getRemoteAccessToken'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getRemoteAccessToken')->willReturn('someVar');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsServer')->willReturn($oMockUtilsServer);
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someValue');
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someOtherVar');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoCheckUserAgent'));
    }

    public function testFcpoValidateToken()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrder, '_fcpoValidateToken', ['someVar', 'someOtherVar']));
    }

    public function testIsRedirectAfterSave()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeSetAttribute($oFcPayOneOrder, '_blIsRedirectAfterSave', null);

        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrder, '_isRedirectAfterSave'));
    }

    public function testFinalizeOrder_NoPayone()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('save')->willReturn(true);

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['logger'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('logger')->willReturn(true);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods([
                'isPayOnePaymentType',
                'checkOrderExist',
                'validateOrder',
                'loadFromBasket',
                'setUser',
                'setPayment',
                '_fcpoCheckRefNr',
                'executePayment',
                'save',
                'updateOrderDate',
                'setOrderStatus',
                'updateWishlist',
                'updateNoticeList',
                'markVouchers',
                'sendOrderByEmail',
                'setId',
                'setFolder',
                '_fcpoProcessOrder',
                '_isRedirectAfterSave',
                '_fcpoEarlyValidation',
                '_fcpoHandleBasket',
                '_fcpoExecutePayment',
                '_fcpoSaveAfterRedirect',
                '_fcpoSetOrderStatus',
                '_fcpoMarkVouchers',
                '_fcpoFinishOrder',
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOneOrder->method('isPayOnePaymentType')->willReturn(false);
        $oFcPayOneOrder->method('checkOrderExist')->willReturn(true);
        $oFcPayOneOrder->method('validateOrder')->willReturn(true);
        $oFcPayOneOrder->method('loadFromBasket')->willReturn(true);
        $oFcPayOneOrder->method('setUser')->willReturn(true);
        $oFcPayOneOrder->method('setPayment')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('');
        $oFcPayOneOrder->method('executePayment')->willReturn(true);
        $oFcPayOneOrder->method('save')->willReturn(true);
        $oFcPayOneOrder->method('updateOrderDate')->willReturn(true);
        $oFcPayOneOrder->method('setOrderStatus')->willReturn(true);
        $oFcPayOneOrder->method('updateWishlist')->willReturn(true);
        $oFcPayOneOrder->method('updateNoticeList')->willReturn(true);
        $oFcPayOneOrder->method('markVouchers')->willReturn(true);
        $oFcPayOneOrder->method('sendOrderByEmail')->willReturn(true);
        $oFcPayOneOrder->method('setId')->willReturn(true);
        $oFcPayOneOrder->method('setFolder')->willReturn(true);
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoEarlyValidation')->willReturn(null);
        $oFcPayOneOrder->method('_fcpoExecutePayment')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoFinishOrder')->willReturn(1);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(3, $oFcPayOneOrder->finalizeOrder($oMockBasket, $oMockUser, false));
    }

    public function testFinalizeOrder_IsPayone()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('save')->willReturn(true);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods([
                'isPayOnePaymentType',
                'checkOrderExist',
                'validateOrder',
                'loadFromBasket',
                'setUser',
                'setPayment',
                '_fcpoCheckRefNr',
                'executePayment',
                'save',
                'updateOrderDate',
                'setOrderStatus',
                'updateWishlist',
                'updateNoticeList',
                'markVouchers',
                'sendOrderByEmail',
                'setId',
                'setFolder',
                '_fcpoProcessOrder',
                '_isRedirectAfterSave',
                '_fcpoEarlyValidation',
                '_fcpoHandleBasket',
                '_fcpoExecutePayment',
                '_fcpoSaveAfterRedirect',
                '_fcpoSetOrderStatus',
                '_fcpoMarkVouchers',
                '_fcpoFinishOrder',
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOneOrder->method('isPayOnePaymentType')->willReturn(true);
        $oFcPayOneOrder->method('checkOrderExist')->willReturn(true);
        $oFcPayOneOrder->method('validateOrder')->willReturn(true);
        $oFcPayOneOrder->method('loadFromBasket')->willReturn(true);
        $oFcPayOneOrder->method('setUser')->willReturn(true);
        $oFcPayOneOrder->method('setPayment')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('');
        $oFcPayOneOrder->method('executePayment')->willReturn(true);
        $oFcPayOneOrder->method('save')->willReturn(true);
        $oFcPayOneOrder->method('updateOrderDate')->willReturn(true);
        $oFcPayOneOrder->method('setOrderStatus')->willReturn(true);
        $oFcPayOneOrder->method('updateWishlist')->willReturn(true);
        $oFcPayOneOrder->method('updateNoticeList')->willReturn(true);
        $oFcPayOneOrder->method('markVouchers')->willReturn(true);
        $oFcPayOneOrder->method('sendOrderByEmail')->willReturn(true);
        $oFcPayOneOrder->method('setId')->willReturn(true);
        $oFcPayOneOrder->method('setFolder')->willReturn(true);
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoEarlyValidation')->willReturn(1);
        $oFcPayOneOrder->method('_fcpoExecutePayment')->willReturn(null);
        $oFcPayOneOrder->method('_fcpoFinishOrder')->willReturn(1);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(1, $oFcPayOneOrder->finalizeOrder($oMockBasket, $oMockUser, false));
    }

    public function testFinalizeOrder_IsPayone_OrderExists()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('save')->willReturn(true);
        $oMockUser->oxuser__oxcompany = new Field('someCompany');
        $oMockUser->oxuser__oxusername = new Field('someEmail');
        $oMockUser->oxuser__oxfname = new Field('someFirstName');
        $oMockUser->oxuser__oxlname = new Field('someLastName');
        $oMockUser->oxuser__oxstreet = new Field('someStreet');
        $oMockUser->oxuser__oxstreetnr = new Field('someStreetNr');
        $oMockUser->oxuser__oxaddinfo = new Field('someAddInfo');
        $oMockUser->oxuser__oxustid = new Field('someUstId');;
        $oMockUser->oxuser__oxcity = new Field('someCity');
        $oMockUser->oxuser__oxcountryid = new Field('someCountryId');
        $oMockUser->oxuser__oxstateid = new Field('someStateId');
        $oMockUser->oxuser__oxzip = new Field('someZip');
        $oMockUser->oxuser__oxfon = new Field('somePhone');
        $oMockUser->oxuser__oxfax = new Field('someFax');
        $oMockUser->oxuser__oxsal = new Field('someSalutation');

        $oMockUserPayment = $this->getMockBuilder(UserPayment::class)
            ->disableOriginalConstructor()->getMock();

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['logger'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('logger')->willReturn(true);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne', 'Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $oMockDatabase->method('Execute')->willReturn(true);
        $oMockDatabase->method('quote')->willReturn('someValue');

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods([
                'isPayOnePaymentType',
                'checkOrderExist',
                'validateOrder',
                'loadFromBasket',
                'setUser',
                'setPayment',
                '_fcpoCheckRefNr',
                'executePayment',
                'save',
                'updateOrderDate',
                'setOrderStatus',
                'updateWishlist',
                'updateNoticeList',
                'markVouchers',
                'sendOrderByEmail',
                'setId',
                'setFolder',
                '_fcpoProcessOrder',
                '_isRedirectAfterSave',
                '_fcpoEarlyValidation',
                '_fcpoHandleBasket',
                '_fcpoExecutePayment',
                '_fcpoSaveAfterRedirect',
                '_fcpoSetOrderStatus',
                '_fcpoMarkVouchers',
                '_fcpoFinishOrder',
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOneOrder->method('isPayOnePaymentType')->willReturn(true);
        $oFcPayOneOrder->method('checkOrderExist')->willReturn(true);
        $oFcPayOneOrder->method('validateOrder')->willReturn(true);
        $oFcPayOneOrder->method('loadFromBasket')->willReturn(true);
        $oFcPayOneOrder->method('setUser')->willReturn(true);
        $oFcPayOneOrder->method('setPayment')->willReturn($oMockUserPayment);
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('');
        $oFcPayOneOrder->method('executePayment')->willReturn(true);
        $oFcPayOneOrder->method('save')->willReturn(true);
        $oFcPayOneOrder->method('updateOrderDate')->willReturn(true);
        $oFcPayOneOrder->method('setOrderStatus')->willReturn(true);
        $oFcPayOneOrder->method('updateWishlist')->willReturn(true);
        $oFcPayOneOrder->method('updateNoticeList')->willReturn(true);
        $oFcPayOneOrder->method('markVouchers')->willReturn(true);
        $oFcPayOneOrder->method('sendOrderByEmail')->willReturn(true);
        $oFcPayOneOrder->method('setId')->willReturn(true);
        $oFcPayOneOrder->method('setFolder')->willReturn(true);
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(false);
        $oFcPayOneOrder->method('_fcpoEarlyValidation')->willReturn(null);
        $oFcPayOneOrder->method('_fcpoExecutePayment')->willReturn(null);
        $oFcPayOneOrder->method('_fcpoFinishOrder')->willReturn(1);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(1, $oFcPayOneOrder->finalizeOrder($oMockBasket, $oMockUser, false));
    }

    public function testFinalizeOrder_IsPayone_RecalcOrderHasOrderState()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('save')->willReturn(true);
        $oMockUser->method('save')->willReturn(true);
        $oMockUser->oxuser__oxcompany = new Field('someCompany');
        $oMockUser->oxuser__oxusername = new Field('someEmail');
        $oMockUser->oxuser__oxfname = new Field('someFirstName');
        $oMockUser->oxuser__oxlname = new Field('someLastName');
        $oMockUser->oxuser__oxstreet = new Field('someStreet');
        $oMockUser->oxuser__oxstreetnr = new Field('someStreetNr');
        $oMockUser->oxuser__oxaddinfo = new Field('someAddInfo');
        $oMockUser->oxuser__oxustid = new Field('someUstId');;
        $oMockUser->oxuser__oxcity = new Field('someCity');
        $oMockUser->oxuser__oxcountryid = new Field('someCountryId');
        $oMockUser->oxuser__oxstateid = new Field('someStateId');
        $oMockUser->oxuser__oxzip = new Field('someZip');
        $oMockUser->oxuser__oxfon = new Field('somePhone');
        $oMockUser->oxuser__oxfax = new Field('someFax');
        $oMockUser->oxuser__oxsal = new Field('someSalutation');

        $oMockUserPayment = $this->getMockBuilder(UserPayment::class)
            ->disableOriginalConstructor()->getMock();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne', 'Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $oMockDatabase->method('Execute')->willReturn(true);
        $oMockDatabase->method('quote')->willReturn('someValue');

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods([
                'isPayOnePaymentType',
                'checkOrderExist',
                'validateOrder',
                'loadFromBasket',
                'setUser',
                'setPayment',
                '_fcpoCheckRefNr',
                'executePayment',
                'save',
                'updateOrderDate',
                'setOrderStatus',
                'updateWishlist',
                'updateNoticeList',
                'markVouchers',
                'sendOrderByEmail',
                'setId',
                'setFolder',
                '_fcpoProcessOrder',
                '_isRedirectAfterSave',
                '_fcpoEarlyValidation',
                '_fcpoHandleBasket',
                '_fcpoExecutePayment',
                '_fcpoSaveAfterRedirect',
                '_fcpoSetOrderStatus',
                '_fcpoMarkVouchers',
                '_fcpoFinishOrder',
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOneOrder->method('isPayOnePaymentType')->willReturn(true);
        $oFcPayOneOrder->method('checkOrderExist')->willReturn(true);
        $oFcPayOneOrder->method('validateOrder')->willReturn(1);
        $oFcPayOneOrder->method('loadFromBasket')->willReturn(true);
        $oFcPayOneOrder->method('setUser')->willReturn(true);
        $oFcPayOneOrder->method('setPayment')->willReturn($oMockUserPayment);
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('');
        $oFcPayOneOrder->method('executePayment')->willReturn(true);
        $oFcPayOneOrder->method('save')->willReturn(true);
        $oFcPayOneOrder->method('updateOrderDate')->willReturn(true);
        $oFcPayOneOrder->method('setOrderStatus')->willReturn(true);
        $oFcPayOneOrder->method('updateWishlist')->willReturn(true);
        $oFcPayOneOrder->method('updateNoticeList')->willReturn(true);
        $oFcPayOneOrder->method('markVouchers')->willReturn(true);
        $oFcPayOneOrder->method('sendOrderByEmail')->willReturn(true);
        $oFcPayOneOrder->method('setId')->willReturn(true);
        $oFcPayOneOrder->method('setFolder')->willReturn(true);
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoEarlyValidation')->willReturn(null);
        $oFcPayOneOrder->method('_fcpoExecutePayment')->willReturn(null);
        $oFcPayOneOrder->method('_fcpoFinishOrder')->willReturn(2);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(2, $oFcPayOneOrder->finalizeOrder($oMockBasket, $oMockUser, false));
    }

    public function testFinalizeOrder_IsPayone_RecalcOrderNoOrderState()
    {
        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPaymentId')->willReturn('somePaymentId');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('save')->willReturn(true);
        $oMockUser->method('save')->willReturn(true);
        $oMockUser->oxuser__oxcompany = new Field('someCompany');
        $oMockUser->oxuser__oxusername = new Field('someEmail');
        $oMockUser->oxuser__oxfname = new Field('someFirstName');
        $oMockUser->oxuser__oxlname = new Field('someLastName');
        $oMockUser->oxuser__oxstreet = new Field('someStreet');
        $oMockUser->oxuser__oxstreetnr = new Field('someStreetNr');
        $oMockUser->oxuser__oxaddinfo = new Field('someAddInfo');
        $oMockUser->oxuser__oxustid = new Field('someUstId');;
        $oMockUser->oxuser__oxcity = new Field('someCity');
        $oMockUser->oxuser__oxcountryid = new Field('someCountryId');
        $oMockUser->oxuser__oxstateid = new Field('someStateId');
        $oMockUser->oxuser__oxzip = new Field('someZip');
        $oMockUser->oxuser__oxfon = new Field('somePhone');
        $oMockUser->oxuser__oxfax = new Field('someFax');
        $oMockUser->oxuser__oxsal = new Field('someSalutation');

        $oMockUserPayment = $this->getMockBuilder(UserPayment::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods([
                'isPayOnePaymentType',
                'checkOrderExist',
                'validateOrder',
                'loadFromBasket',
                'setUser',
                'setPayment',
                '_fcpoCheckRefNr',
                'executePayment',
                'save',
                'updateOrderDate',
                'setOrderStatus',
                'updateWishlist',
                'updateNoticeList',
                'markVouchers',
                'sendOrderByEmail',
                'setId',
                'setFolder',
                '_fcpoProcessOrder',
                '_isRedirectAfterSave',
                '_fcpoEarlyValidation',
                '_fcpoHandleBasket',
                '_fcpoExecutePayment',
                '_fcpoSaveAfterRedirect',
                '_fcpoSetOrderStatus',
                '_fcpoMarkVouchers',
                '_fcpoFinishOrder',
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOneOrder->method('isPayOnePaymentType')->willReturn(true);
        $oFcPayOneOrder->method('checkOrderExist')->willReturn(true);
        $oFcPayOneOrder->method('validateOrder')->willReturn(false);
        $oFcPayOneOrder->method('loadFromBasket')->willReturn(true);
        $oFcPayOneOrder->method('setUser')->willReturn(true);
        $oFcPayOneOrder->method('setPayment')->willReturn($oMockUserPayment);
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('');
        $oFcPayOneOrder->method('executePayment')->willReturn(true);
        $oFcPayOneOrder->method('save')->willReturn(true);
        $oFcPayOneOrder->method('updateOrderDate')->willReturn(true);
        $oFcPayOneOrder->method('setOrderStatus')->willReturn(true);
        $oFcPayOneOrder->method('updateWishlist')->willReturn(true);
        $oFcPayOneOrder->method('updateNoticeList')->willReturn(true);
        $oFcPayOneOrder->method('markVouchers')->willReturn(true);
        $oFcPayOneOrder->method('sendOrderByEmail')->willReturn(true);
        $oFcPayOneOrder->method('setId')->willReturn(true);
        $oFcPayOneOrder->method('setFolder')->willReturn(true);
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoEarlyValidation')->willReturn(null);
        $oFcPayOneOrder->method('_fcpoExecutePayment')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoFinishOrder')->willReturn(1);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(1, $oFcPayOneOrder->finalizeOrder($oMockBasket, $oMockUser, false));
    }

    public function testFcpoProcessOrder()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoCheckTxid', '_fcpoSaveOrderValues', '_fcpoCheckUserAgent'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoCheckTxid')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $blResponse = $this->invokeMethod($oFcPayOneOrder, '_fcpoProcessOrder', ['someTxid']);

        $this->assertEquals(null, $blResponse);
    }

    public function testFcpoExecutePayment_SaveAfterRedirectEarlyReturn()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoCheckRefNr', '_fcpoProcessOrder', 'executePayment'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('someValue');
        $oFcPayOneOrder->method('executePayment')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockBasket = new Basket();
        $oMockUserPayment = new UserPayment();

        $this->assertEquals('someValue', $this->invokeMethod($oFcPayOneOrder, '_fcpoExecutePayment', [true, $oMockBasket, $oMockUserPayment, true]));
    }

    public function testFcpoExecutePayment_SaveAfterRedirectLaterReturn()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoCheckRefNr', '_fcpoProcessOrder', 'executePayment'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('');
        $oFcPayOneOrder->method('executePayment')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockBasket = new Basket();
        $oMockUserPayment = new UserPayment();

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoExecutePayment', [true, $oMockBasket, $oMockUserPayment, true]));
    }

    public function testFcpoExecutePayment_NoSaveAfterRedirectEarlyReturn()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoCheckRefNr', '_fcpoProcessOrder', 'executePayment'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('');
        $oFcPayOneOrder->method('executePayment')->willReturn(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockBasket = new Basket();
        $oMockUserPayment = new UserPayment();

        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrder, '_fcpoExecutePayment', [false, $oMockBasket, $oMockUserPayment, false]));
    }

    public function testFcpoExecutePayment_NoSaveAfterRedirectLateReturn()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoCheckRefNr', '_fcpoProcessOrder', 'executePayment'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoCheckRefNr')->willReturn('');
        $oFcPayOneOrder->method('executePayment')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockBasket = new Basket();
        $oMockUserPayment = new UserPayment();

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoExecutePayment', [false, $oMockBasket, $oMockUserPayment, true]));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoHandleBasket_SaveAfterRedirect()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoCheckRefNr', '_fcpoProcessOrder', 'executePayment', 'getViewName'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getViewName')->willReturn('oxorder');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockBasket = new Basket();

        $oFcPayOneOrder->_fcpoHandleBasket(true, $oMockBasket);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoHandleBasket_NoSaveAfterRedirect()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoCheckRefNr', '_fcpoProcessOrder', 'executePayment'])
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockBasket = new Basket();

        $oFcPayOneOrder->_fcpoHandleBasket(false, $oMockBasket);
    }

    public function testFcpoEarlyValidation_NoSaveAfterRedirect()
    {
        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['logger'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('logger')->willReturn(true);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['checkOrderExist', 'setId', 'validateOrder'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('checkOrderExist')->willReturn(true);
        $oFcPayOneOrder->method('setId')->willReturn(true);
        $oFcPayOneOrder->method('validateOrder')->willReturn(1);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);
        
        $oMockBasket = new Basket();
        $oMockUser = new User();

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrder, '_fcpoEarlyValidation', [false, $oMockBasket, $oMockUser, false]));
    }

    public function testFcpoEarlyValidation_SaveAfterRedirect()
    {
        $oMockBasket = new Basket();
        $oMockUser = new User();

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['logger'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('logger')->willReturn(true);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne', 'Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $oMockDatabase->method('Execute')->willReturn(true);
        $oMockDatabase->method('quote')->willReturn('someValue');

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['checkOrderExist', 'setId', 'validateOrder', 'fcpoGetShadowBasket'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('checkOrderExist')->willReturn(true);
        $oFcPayOneOrder->method('setId')->willReturn(true);
        $oFcPayOneOrder->method('validateOrder')->willReturn(1);
        $oFcPayOneOrder->method('fcpoGetShadowBasket')->willReturn($oMockBasket);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrder, '_fcpoEarlyValidation', [true, $oMockBasket, $oMockUser, false]));
    }

    public function testFcpoEarlyValidation_Null()
    {
        $oMockBasket = new Basket();
        $oMockUser = new User();

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['logger'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('logger')->willReturn(true);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne', 'Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $oMockDatabase->method('Execute')->willReturn(true);
        $oMockDatabase->method('quote')->willReturn('someValue');

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['checkOrderExist', 'setId', 'validateOrder', 'fcpoGetShadowBasket'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('checkOrderExist')->willReturn(true);
        $oFcPayOneOrder->method('setId')->willReturn(true);
        $oFcPayOneOrder->method('validateOrder')->willReturn(1);
        $oFcPayOneOrder->method('fcpoGetShadowBasket')->willReturn($oMockBasket);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoEarlyValidation', [true, $oMockBasket, $oMockUser, true]));
    }

    public function testFcpoFinishOrder_SendMail()
    {
        $oMockBasket = new Basket();
        $oMockUser = new User();
        $oMockUserPayment = new UserPayment();

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['sendOrderByEmail'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('sendOrderByEmail')->willReturn(1);

        $this->assertEquals(1, $this->invokeMethod($oFcPayOneOrder, '_fcpoFinishOrder', [false, $oMockUser, $oMockBasket, $oMockUserPayment]));
    }

    public function testFcpoFinishOrder_StateOK()
    {
        $oMockBasket = new Basket();
        $oMockUser = new User();
        $oMockUserPayment = new UserPayment();

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['sendOrderByEmail'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('sendOrderByEmail')->willReturn(1);

        $this->assertEquals(1, $this->invokeMethod($oFcPayOneOrder, '_fcpoFinishOrder', [true, $oMockUser, $oMockBasket, $oMockUserPayment]));
    }

    public function testFcpoSaveAfterRedirect()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(true);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoSaveAfterRedirect', [true]));
    }

    public function testFcpoSetOrderStatus_Ok()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['setOrderStatus', '_fcpoGetAppointedError'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('setOrderStatus')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoGetAppointedError')->willReturn(false);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoSetOrderStatus'));
    }

    public function testFcpoSetOrderStatus_Error()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['setOrderStatus', '_fcpoGetAppointedError'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('setOrderStatus')->willReturn(true);
        $oFcPayOneOrder->method('_fcpoGetAppointedError')->willReturn(true);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoSetOrderStatus'));
    }

    public function testFcpoMarkVouchers()
    {
        $oMockBasket = new Basket();
        $oMockUser = new User();

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['markVouchers'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('markVouchers')->willReturn(true);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoMarkVouchers', [false, $oMockUser, $oMockBasket]));
    }

    public function testFcpoGetMandateFilename()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getId')->willReturn('someId');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someFile');
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals('someFile', $oFcPayOneOrder->fcpoGetMandateFilename());
    }

    public function testFcpoGetStatus()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getId')->willReturn('someId');

        $oMockTransactionStatus = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockTransactionStatus->method('load')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockTransactionStatus);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $aMockResult = [['someValue']];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getAll'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getAll')->willReturn($aMockResult);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $aResponse = $aExpect = $oFcPayOneOrder->fcpoGetStatus();

        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoSaveOrderValues()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someParameter');
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(true);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoSaveOrderValues', ['someTxid', '1']));
    }

    public function testFcpoCheckTxid_TxidInSession()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxremark = new Field('');

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someTranslatedString');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('');
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getOne')->willReturn(false);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $blResponse = $this->invokeMethod($oFcPayOneOrder, '_fcpoCheckTxid');

        $this->assertEquals(true, $blResponse);
    }

    public function testFcpoCheckTxid_TxidNotInSession()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxremark = new Field('');

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someTranslatedString');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn(false);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('');
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getOne')->willReturn(false);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $blResponse = $this->invokeMethod($oFcPayOneOrder, '_fcpoCheckTxid');

        $this->assertEquals(true, $blResponse);
    }

    public function testFcpoCheckRefNr()
    {
        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['getRefNr', 'sendRequestAuthorization'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('getRefNr')->willReturn('someRefValue');
        $oMockRequest->method('sendRequestAuthorization')->willReturn([]);

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load', 'fcpoGetMode'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('load')->willReturn(true);
        $oMockPayment->method('fcpoGetMode')->willReturn('test');
        $oMockPayment->oxpayments__fcpoauthmode = new Field('someAuthMode');

        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someTranslatedString');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someSessionValue');
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someRequestValue');
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockRequest, $oMockPayment);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someTranslatedString', $this->invokeMethod($oFcPayOneOrder, '_fcpoCheckRefNr'));
    }

    public function testSave_Presave()
    {
        $oMockShop = $this->getMockBuilder(Shop::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockShop->method('getId')->willReturn('oxbaseshop');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam', 'getActiveShop', 'getShopId'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);
        $oMockConfig->method('getActiveShop')->willReturn($oMockShop);
        $oMockConfig->method('getShopId')->willReturn('oxbaseshop');

        $oMockOrderArticle = $this->getMockBuilder(OrderArticle::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrderArticle->method('save')->willReturn(true);

        $oMockOrderArticles = new ListModel();
        $oMockOrderArticles->add($oMockOrderArticle);

        $oMockUtilsDate = $this->getMockBuilder(UtilsDate::class)
            ->setMethods(['getTime', 'formatDBDate'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsDate->method('getTime')->willReturn(time());
        $oMockUtilsDate->method('formatDBDate')->willReturn('someFormattedTime');

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrderArticles', 'isPayOnePaymentType', '_isRedirectAfterSave', 'getShopId', 'getCoreTableName'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getOrderArticles')->willReturn($oMockOrderArticles);
        $oFcPayOneOrder->method('isPayOnePaymentType')->willReturn(false);
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(true);
        $oFcPayOneOrder->method('getShopId')->willReturn('oxbaseshop');
        $oFcPayOneOrder->method('getCoreTableName')->willReturn('oxorder');
        $oFcPayOneOrder->oxorder__oxshopid = new Field(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetUtilsDate')->willReturn($oMockUtilsDate);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $oFcPayOneOrder->save();

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testSave_NoPresave()
    {
        $oMockShop = $this->getMockBuilder(Shop::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockShop->method('getId')->willReturn('oxbaseshop');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam', 'getActiveShop', 'getShopId'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);
        $oMockConfig->method('getActiveShop')->willReturn($oMockShop);
        $oMockConfig->method('getShopId')->willReturn('oxbaseshop');

        $oMockOrderArticle = $this->getMockBuilder(OrderArticle::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrderArticle->method('save')->willReturn(true);

        $oMockOrderArticles = new ListModel();
        $oMockOrderArticles->add($oMockOrderArticle);

        $oMockUtilsDate = $this->getMockBuilder(UtilsDate::class)
            ->setMethods(['getTime', 'formatDBDate'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsDate->method('getTime')->willReturn(time());
        $oMockUtilsDate->method('formatDBDate')->willReturn('someFormattedTime');

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrderArticles', 'isPayOnePaymentType', '_isRedirectAfterSave', 'getShopId', 'getCoreTableName'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getOrderArticles')->willReturn($oMockOrderArticles);
        $oFcPayOneOrder->method('isPayOnePaymentType')->willReturn(true);
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(true);
        $oFcPayOneOrder->method('getShopId')->willReturn('oxbaseshop');
        $oFcPayOneOrder->method('getCoreTableName')->willReturn('oxorder');
        $oFcPayOneOrder->oxorder__oxshopid = new Field(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetUtilsDate')->willReturn($oMockUtilsDate);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $oFcPayOneOrder->save();

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testAllowCapture_Authorization()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__fcpoauthmode = new Field('authorization');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(0);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals(false, $oFcPayOneOrder->allowCapture());
    }

    public function testAllowCapture_Preauthorization()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__fcpoauthmode = new Field('preauthorization');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(0);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals(false, $oFcPayOneOrder->allowCapture());
    }

    public function testAllowDebit_Authorization()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__fcpoauthmode = new Field('authorization');
        $oFcPayOneOrder->oxorder__fcpotxid = new Field('123456789');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(0);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals(true, $oFcPayOneOrder->allowDebit());
    }

    public function testAllowDebit_Preauthorization()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__fcpoauthmode = new Field('preauthorization');
        $oFcPayOneOrder->oxorder__fcpotxid = new Field('123456789');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(0);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals(false, $oFcPayOneOrder->allowDebit());
    }

    public function testAllowAccountSettlement()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('somePaymentType');

        $this->assertEquals(false, $oFcPayOneOrder->allowAccountSettlement());
    }

    public function testDebitNeedsBankData()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('somePaymentType');

        $this->assertEquals(false, $oFcPayOneOrder->debitNeedsBankData());
    }

    public function testIsDetailedProductInfoNeeded()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('somePaymentNotOnExceptionList');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $oFcPayOneOrder->isDetailedProductInfoNeeded());
    }

    public function testIsDetailedProductInfoNeededFalse()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('somePaymentNotOnExceptionList');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $oFcPayOneOrder->isDetailedProductInfoNeeded());
    }

    public function testGetSequenceNumber()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__fcpotxid = new Field('someTxid');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals(2, $oFcPayOneOrder->getSequenceNumber());
    }

    public function testGetLastStatus()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockTransaction = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockTransaction->method('load')->willReturn(true);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockTransaction);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($oMockTransaction, $oFcPayOneOrder->getLastStatus());
    }

    public function testGetResponse()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $this->invokeSetAttribute($oFcPayOneOrder, '_aResponse', null);

        $aResponse = ['someResponse'];

        $oMockRequestLog = $this->getMockBuilder(FcPoHelper::class)
            ->setMethods(['load', 'getResponseArray'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequestLog->method('load')->willReturn(true);
        $oMockRequestLog->method('getResponseArray')->willReturn($aResponse);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockRequestLog);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aResponse, $this->invokeMethod($oFcPayOneOrder, 'getResponse'));
    }

    public function testGetResponseParameter()
    {
        $aMockResponse = ['someIndex' => 'someParameter'];

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getResponse'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getResponse')->willReturn($aMockResponse);

        $this->assertEquals('someParameter', $this->invokeMethod($oFcPayOneOrder, 'getResponseParameter', ['someIndex']));
    }

    public function testGetFcpoBankaccountholder()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getResponseParameter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getResponseParameter')->willReturn('someParameter');

        $this->assertEquals('someParameter', $oFcPayOneOrder->getFcpoBankaccountholder());
    }

    public function testGetFcpoBankname()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getResponseParameter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getResponseParameter')->willReturn('someParameter');

        $this->assertEquals('someParameter', $oFcPayOneOrder->getFcpoBankname());
    }

    public function testGetFcpoBankcode()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getResponseParameter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getResponseParameter')->willReturn('someParameter');

        $this->assertEquals('someParameter', $oFcPayOneOrder->getFcpoBankcode());
    }

    public function testGetFcpoBanknumber()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getResponseParameter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getResponseParameter')->willReturn('someParameter');

        $this->assertEquals('someParameter', $oFcPayOneOrder->getFcpoBanknumber());
    }

    public function testGetFcpoBiccode()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getResponseParameter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getResponseParameter')->willReturn('someParameter');

        $this->assertEquals('someParameter', $oFcPayOneOrder->getFcpoBiccode());
    }

    public function testGetFcpoIbannumber()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getResponseParameter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getResponseParameter')->willReturn('someParameter');

        $this->assertEquals('someParameter', $oFcPayOneOrder->getFcpoIbannumber());
    }

    public function testGetFcpoCapturableAmount()
    {
        $oMockTransaction = new FcPoTransactionStatus();
        $oMockTransaction->fcpotransactionstatus__fcpo_receivable = new Field(50);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getLastStatus'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->oxorder__oxtotalordersum = new Field(100);

        $this->assertEquals(100, $oFcPayOneOrder->getFcpoCapturableAmount());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateStock()
    {
        $this->wrapExpectException('oxOutOfStockException');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oMockProduct = $this->getMockBuilder(Article::class)
            ->setMethods(['getId', 'checkForStock'])
            ->disableOriginalConstructor()->getMock();
        $oMockProduct->method('getId')->willReturn('someId');
        $oMockProduct->method('checkForStock')->willReturn(false);
        $oMockProduct->oxarticles__oxartnum = new Field('someArtNum');

        $oMockBasketItem = $this->getMockBuilder(BasketItem::class)
            ->setMethods(['getArticle'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasketItem->method('getArticle')->willReturn($oMockProduct);

        $aContents = [$oMockBasketItem];

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getContents', 'removeItem', 'getArtStockInBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getContents')->willReturn($aContents);
        $oMockBasket->method('removeItem')->willReturn(true);
        $oMockBasket->method('getArtStockInBasket')->willReturn(2);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_isRedirectAfterSave', 'fcGetArtStockInBasket'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(false);
        $oFcPayOneOrder->method('fcGetArtStockInBasket')->willReturn(2);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->validateStock($oMockBasket);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateStock_ExceptionNoArticle()
    {
        $this->wrapExpectException('oxNoArticleException');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oMockProduct = $this->getMockBuilder(Article::class)
            ->setMethods(['getId', 'checkForStock'])
            ->disableOriginalConstructor()->getMock();
        $oMockProduct->method('getId')->willReturn('someId');
        $oMockProduct->method('checkForStock')->willReturn(false);
        $oMockProduct->oxarticles__oxartnum = new Field('someArtNum');

        $oMockException = new NoArticleException();

        $oMockBasketItem = $this->getMockBuilder(BasketItem::class)
            ->setMethods(['getArticle'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasketItem->method('getArticle')->willThrowException($oMockException);

        $aContents = [$oMockBasketItem];

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getContents', 'removeItem', 'getArtStockInBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getContents')->willReturn($aContents);
        $oMockBasket->method('removeItem')->willReturn(true);
        $oMockBasket->method('getArtStockInBasket')->willReturn(2);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_isRedirectAfterSave', 'fcGetArtStockInBasket'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(false);
        $oFcPayOneOrder->method('fcGetArtStockInBasket')->willReturn(2);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->validateStock($oMockBasket);
    }

    public function testValidateStock_ExceptionInput()
    {
        $this->wrapExpectException('oxArticleInputException');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oMockProduct = $this->getMockBuilder(Article::class)
            ->setMethods(['getId', 'checkForStock'])
            ->disableOriginalConstructor()->getMock();
        $oMockProduct->method('getId')->willReturn('someId');
        $oMockProduct->method('checkForStock')->willReturn(false);
        $oMockProduct->oxarticles__oxartnum = new Field('someArtNum');

        $oMockException = new ArticleInputException();

        $oMockBasketItem = $this->getMockBuilder(BasketItem::class)
            ->setMethods(['getArticle'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasketItem->method('getArticle')->willThrowException($oMockException);

        $aContents = [$oMockBasketItem];

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getContents', 'removeItem', 'getArtStockInBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getContents')->willReturn($aContents);
        $oMockBasket->method('removeItem')->willReturn(true);
        $oMockBasket->method('getArtStockInBasket')->willReturn(2);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_isRedirectAfterSave', 'fcGetArtStockInBasket'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_isRedirectAfterSave')->willReturn(false);
        $oFcPayOneOrder->method('fcGetArtStockInBasket')->willReturn(2);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->validateStock($oMockBasket);
    }

    public function testFcGetArtStockInBasket()
    {
        $oMockProduct = $this->getMockBuilder(Article::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockProduct->method('getId')->willReturn('someId');

        $oMockOrderArticle = $this->getMockBuilder(OrderArticle::class)
            ->setMethods(['getArticle', 'getAmount'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrderArticle->method('getArticle')->willReturn($oMockProduct);
        $oMockOrderArticle->method('getAmount')->willReturn(2);

        $aContents = [$oMockOrderArticle];

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getContents'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getContents')->willReturn($aContents);

        $oFcPayOneOrder = new FcPayOneOrder();

        $this->assertEquals(2, $oFcPayOneOrder->fcGetArtStockInBasket($oMockBasket, 'someId'));
    }

    public function testFcIsPayPalOrder()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('fcpopaypal_express');

        $this->assertEquals(true, $oFcPayOneOrder->fcIsPayPalOrder());
    }

    public function testFcHandleAuthorization()
    {
        $aMockResponse = [];

        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['getRefNr', 'sendRequestAuthorization'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('getRefNr')->willReturn('someRefValue');
        $oMockRequest->method('sendRequestAuthorization')->willReturn($aMockResponse);

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load', 'fcpoGetMode'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('load')->willReturn(true);
        $oMockPayment->method('fcpoGetMode')->willReturn('test');
        $oMockPayment->oxpayments__fcpoauthmode = new Field('someAuthMode');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oMockUser = new User();
        $oMockPaymentGateway = new PaymentGateway();

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoHandleAuthorizationResponse', 'getOrderUser', '_fcpoGetNextOrderNr'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoHandleAuthorizationResponse')->willReturn(true);
        $oFcPayOneOrder->method('getOrderUser')->willReturn($oMockUser);
        $oFcPayOneOrder->method('_fcpoGetNextOrderNr')->willReturn('someOrderId');
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('somePaymentType');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockRequest, $oMockPayment);
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn([]);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $oFcPayOneOrder->fcHandleAuthorization(false, $oMockPaymentGateway));
    }

    public function testFcpoGetNextOrderNr_NewerShopVersion()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_getCounterIdent'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_getCounterIdent')->willReturn('someCounterIdent');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someOrderNr');

        $oMockCounter = $this->getMockBuilder(Counter::class)
            ->setMethods(['getNext'])
            ->disableOriginalConstructor()->getMock();
        $oMockCounter->method('getNext')->willReturn('someOrderNr');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockCounter);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals('someOrderNr', $this->invokeMethod($oFcPayOneOrder, '_fcpoGetNextOrderNr'));
    }

    public function testFcpoGetOrderNotChecked()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn(500);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(0, $this->invokeMethod($oFcPayOneOrder, '_fcpoGetOrderNotChecked'));
    }

    public function testFcpoHandleAuthorizationResponse_Error()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoHandleAuthorizationError', '_fcpoHandleAuthorizationApproved', '_fcpoHandleAuthorizationRedirect'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoHandleAuthorizationRedirect')->willReturn(true);

        $aMockResponse = ['status' => 'ERROR'];
        $oMockPaymentGateway = new PaymentGateway();
        $sMockRefNr = $sMockMode = $sMockAuthorizationType = 'someValue';
        $blMockReturnRedirectUrl = false;

        $this->assertEquals(false, $this->invokeMethod($oFcPayOneOrder, '_fcpoHandleAuthorizationResponse', [$aMockResponse, $oMockPaymentGateway, $sMockRefNr, $sMockMode, $sMockAuthorizationType, $blMockReturnRedirectUrl]));
    }

    public function testFcpoHandleAuthorizationResponse_Approved()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoHandleAuthorizationError', '_fcpoHandleAuthorizationApproved', '_fcpoHandleAuthorizationRedirect'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoHandleAuthorizationRedirect')->willReturn(true);

        $aMockResponse = ['status' => 'APPROVED'];
        $oMockPaymentGateway = new PaymentGateway();
        $sMockRefNr = $sMockMode = $sMockAuthorizationType = 'someValue';
        $blMockReturnRedirectUrl = false;

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrder, '_fcpoHandleAuthorizationResponse', [$aMockResponse, $oMockPaymentGateway, $sMockRefNr, $sMockMode, $sMockAuthorizationType, $blMockReturnRedirectUrl]));
    }

    public function testFcpoHandleAuthorizationRedirect_ReturnRedirect()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_fcpoHandleAuthorizationError', '_fcpoHandleAuthorizationApproved', '_fcpoHandleAuthorizationRedirect'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('_fcpoHandleAuthorizationRedirect')->willReturn(true);

        $aMockResponse = ['status' => 'REDIRECT'];
        $oMockPaymentGateway = new PaymentGateway();
        $sMockRefNr = $sMockMode = $sMockAuthorizationType = 'someValue';
        $blMockReturnRedirectUrl = true;

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoHandleAuthorizationResponse', [$aMockResponse, $oMockPaymentGateway, $sMockRefNr, $sMockMode, $sMockAuthorizationType, $blMockReturnRedirectUrl]));
    }

    public function testFcpoHandleAuthorizationRedirect_NoReturnNoIframe()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam', 'getCurrentShopUrl'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);
        $oMockConfig->method('getCurrentShopUrl')->willReturn('someShopUrl');

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['redirect'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('redirect')->willReturn(true);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['save', 'isPayOneIframePayment'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('save')->willReturn(true);
        $oFcPayOneOrder->method('isPayOneIframePayment')->willReturn(false);
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('fcpocreditcard');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne', 'Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $oMockDatabase->method('Execute')->willReturn(true);
        $oMockDatabase->method('quote')->willReturn('someValue');
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $sMockRedirectUrl = 'https://www.someRedirect.org';
        $aMockResponse = ['txid' => 'someTxid', 'redirecturl' => $sMockRedirectUrl];
        $sMockRefNr = $sMockMode = $sMockAuthorizationType = 'someValue';
        $blMockReturnRedirectUrl = false;

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrder, '_fcpoHandleAuthorizationRedirect', [$aMockResponse, $sMockRefNr, $sMockAuthorizationType, $sMockMode, $blMockReturnRedirectUrl]));
    }

    public function testFcpoHandleAuthorizationRedirect_NoReturnIframe()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam', 'getCurrentShopUrl'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);
        $oMockConfig->method('getCurrentShopUrl')->willReturn('someShopUrl');

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['redirect'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('redirect')->willReturn(true);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['save', 'isPayOneIframePayment'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('save')->willReturn(true);
        $oFcPayOneOrder->method('isPayOneIframePayment')->willReturn(true);
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('fcpocreditcard');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne', 'Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn(1);
        $oMockDatabase->method('Execute')->willReturn(true);
        $oMockDatabase->method('quote')->willReturn('someValue');
        $this->invokeSetAttribute( $oFcPayOneOrder, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $sMockRedirectUrl = 'https://www.someRedirect.org';
        $aMockResponse = ['txid' => 'someTxid', 'redirecturl' => $sMockRedirectUrl];
        $sMockRefNr = $sMockMode = $sMockAuthorizationType = 'someValue';
        $blMockReturnRedirectUrl = false;

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrder, '_fcpoHandleAuthorizationRedirect', [$aMockResponse, $sMockRefNr, $sMockAuthorizationType, $sMockMode, $blMockReturnRedirectUrl]));
    }

    public function testFcpoCheckReduceBefore()
    {
        $oMockOrderArticle = $this->getMockBuilder(OrderArticle::class)
            ->setMethods(['updateArticleStock'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrderArticle->method('updateArticleStock')->willReturn(null);

        $oMockOrderArticles = new ListModel();
        $oMockOrderArticles->add($oMockOrderArticle);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrderArticles'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('getOrderArticles')->willReturn($oMockOrderArticles);
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field('fcpopaypal');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_fcpoCheckReduceBefore'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoSaveWorkorderId()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $sMockPaymentId = 'fcpopo_bill';
        $aMockResponse = ['add_paydata[workorderid]' => 'someWorkorderId',];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeMethod($oFcPayOneOrder, '_fcpoSaveWorkorderId', [$sMockPaymentId, $aMockResponse]);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoSaveClearingReference()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $sMockPaymentId = 'fcpopo_bill';
        $aMockResponse = ['add_paydata[clearing_reference]' => 'someClearingReference',];

        $this->invokeMethod($oFcPayOneOrder, '_fcpoSaveClearingReference', [$sMockPaymentId, $aMockResponse]);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoSaveProfileIdent()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn('someUserProfileId');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $sMockPaymentId = 'fcporp_bill';
        $aMockResponse = ['userid' => 'someUserId'];

        $this->invokeMethod($oFcPayOneOrder, '_fcpoSaveProfileIdent', [$sMockPaymentId, $aMockResponse]);
    }

    public function testFcpoIsPayonePaymentType_Standard()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrder, '_fcpoIsPayonePaymentType', ['fcpoinvoice']));
    }

    public function testFcpoGetAppointedError()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $this->invokeSetAttribute($oFcPayOneOrder, '_blFcPayoneAppointedError', true);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneOrder, '_fcpoGetAppointedError'));
    }
}