<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller;

use Fatchip\PayOne\Application\Controller\FcPayOnePaymentView;
use Fatchip\PayOne\Application\Model\FcPoRatePay;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Application\Model\UserPayment;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\Session;
use OxidEsales\Eshop\Core\Utils;
use OxidEsales\Eshop\Core\ViewConfig;

class FcPayOnePaymentViewTest extends FcBaseUnitTestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testInit()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->disableOriginalConstructor()->getMock();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturnOnConsecutiveCalls(true, true);

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn(true);
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('cancel');
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOnePaymentView->init();
    }

    public function testFcpoGetRatePayMatchedProfile()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $aMockProfileIds = ['somePaymentId' => 'someProfile'];

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_aRatePayProfileIds', $aMockProfileIds);
        $this->assertEquals('someProfile', $oFcPayOnePaymentView->fcpoGetRatePayMatchedProfile('somePaymentId'));
    }

    public function testFcpoGetSofoShowIban()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoGetSofoShowIban());
    }

    public function testFcpoForceDeprecatedBankData()
    {
        $oMockCurrency = (object) ['sign' => 'CHF'];

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getActCurrency', 'fcGetBillCountry', 'fcpoGetSofoShowIban'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getActCurrency')->willReturn($oMockCurrency);
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('CH');
        $oFcPayOnePaymentView->method('fcpoGetSofoShowIban')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoForceDeprecatedBankData());
    }

    public function testGetConfigParam()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someConfigValue');

        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someConfigValue', $oFcPayOnePaymentView->getConfigParam('someParamName'));
    }

    public function testGetMerchantId()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn('someMerchantId');

        $this->assertEquals('someMerchantId', $oFcPayOnePaymentView->getMerchantId());
    }

    public function testGetSubAccountId()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn('someValue');

        $this->assertEquals('someValue', $oFcPayOnePaymentView->getSubAccountId());
    }

    public function testGetPortalId()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn('someValue');

        $this->assertEquals('someValue', $oFcPayOnePaymentView->getPortalId());
    }

    public function testGetPortalKey()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn('someValue');

        $this->assertEquals('someValue', $oFcPayOnePaymentView->getPortalKey());
    }

    public function testGetChecktype()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn('someValue');

        $this->assertEquals('someValue', $oFcPayOnePaymentView->getChecktype());
    }

    public function testFcpoRatePayAllowed()
    {
        $aMockProfile = array('OXID'=>'someOxid');
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoGetMatchingProfile'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoGetMatchingProfile')->willReturn($aMockProfile);

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoRatePayAllowed('somePaymentId'));
    }

    public function testGetUserBillCountryId()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()->getMock();
        $oMockUser->oxuser__oxcountryid = new Field('someCountryId');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_sUserBillCountryId', null);

        $this->assertEquals('someCountryId', $oFcPayOnePaymentView->getUserBillCountryId());
    }

    public function testGetUserDelCountryId()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockAddress = $this->getMockBuilder(Address::class)->disableOriginalConstructor()->getMock();
        $oMockAddress->oxaddress__oxcountryid = new Field('someCountryId');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['getDelAddressInfo'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('getDelAddressInfo')->willReturn($oMockAddress);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_sUserDelCountryId', null);

        $this->assertEquals('someCountryId', $oFcPayOnePaymentView->getUserDelCountryId());
    }

    public function testIsPaymentMethodAvailableToUser_DelAddress()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUserBillCountryId', 'getUserDelCountryId'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUserBillCountryId')->willReturn(false);
        $oFcPayOnePaymentView->method('getUserDelCountryId')->willReturn(true);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someValue');
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals('1', $this->invokeMethod( $oFcPayOnePaymentView, 'isPaymentMethodAvailableToUser', ['paymentid', 'type']));
    }

    public function testIsPaymentMethodAvailableToUser_BillAddress()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUserBillCountryId', 'getUserDelCountryId'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUserBillCountryId')->willReturn(true);
        $oFcPayOnePaymentView->method('getUserDelCountryId')->willReturn(false);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someValue');
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals('1', $this->invokeMethod( $oFcPayOnePaymentView, 'isPaymentMethodAvailableToUser', ['paymentid', 'type']));
    }

    public function testHasPaymentMethodAvailableSubTypes_CC()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                'getVisa',
                'getMastercard',
                'getAmex',
                'getDiners',
                'getJCB',
                'getCarteBleue',
                'getSofortUeberweisung',
                'getEPS',
                'getPostFinanceEFinance',
                'getPostFinanceCard',
                'getIdeal',
                'getP24'
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOnePaymentView->method('getVisa')->willReturn(false);
        $oFcPayOnePaymentView->method('getMastercard')->willReturn(false);
        $oFcPayOnePaymentView->method('getAmex')->willReturn(false);
        $oFcPayOnePaymentView->method('getDiners')->willReturn(false);
        $oFcPayOnePaymentView->method('getJCB')->willReturn(false);
        $oFcPayOnePaymentView->method('getCarteBleue')->willReturn(false);
        $oFcPayOnePaymentView->method('getSofortUeberweisung')->willReturn(false);
        $oFcPayOnePaymentView->method('getEPS')->willReturn(false);
        $oFcPayOnePaymentView->method('getPostFinanceCard')->willReturn(false);
        $oFcPayOnePaymentView->method('getIdeal')->willReturn(false);
        $oFcPayOnePaymentView->method('getP24')->willReturn(false);

        $this->assertEquals(false, $oFcPayOnePaymentView->hasPaymentMethodAvailableSubTypes('cc'));
    }

    public function testHasPaymentMethodAvailableSubTypes_SB()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                'getVisa',
                'getMastercard',
                'getAmex',
                'getDiners',
                'getJCB',
                'getCarteBleue',
                'getSofortUeberweisung',
                'getEPS',
                'getPostFinanceEFinance',
                'getPostFinanceCard',
                'getIdeal',
                'getP24'
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOnePaymentView->method('getVisa')->willReturn(false);
        $oFcPayOnePaymentView->method('getMastercard')->willReturn(false);
        $oFcPayOnePaymentView->method('getAmex')->willReturn(false);
        $oFcPayOnePaymentView->method('getDiners')->willReturn(false);
        $oFcPayOnePaymentView->method('getJCB')->willReturn(false);
        $oFcPayOnePaymentView->method('getCarteBleue')->willReturn(false);
        $oFcPayOnePaymentView->method('getSofortUeberweisung')->willReturn(false);
        $oFcPayOnePaymentView->method('getEPS')->willReturn(false);
        $oFcPayOnePaymentView->method('getPostFinanceCard')->willReturn(false);
        $oFcPayOnePaymentView->method('getIdeal')->willReturn(false);
        $oFcPayOnePaymentView->method('getP24')->willReturn(false);

        $this->assertEquals(false, $oFcPayOnePaymentView->hasPaymentMethodAvailableSubTypes('sb'));
    }

    public function testGetVisa()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getVisa());
    }

    public function testGetMastercard()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getMastercard());
    }

    public function testGetAmex()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getAmex());
    }

    public function testGetDiners()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getDiners());
    }

    public function testGetJCB()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getJCB());
    }

    public function testGetCarteBleue()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getCarteBleue());
    }

    public function testGetSofortUeberweisung()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getSofortUeberweisung());
    }

    public function testGetEPS()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getEPS());
    }

    public function testGetPostFinanceEFinance()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getPostFinanceEFinance());
    }

    public function testGetPostFinanceCard()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getPostFinanceCard());
    }

    public function testGetIdeal()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getIdeal());
    }

    public function testGetP24()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'isPaymentMethodAvailableToUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn(true);
        $oFcPayOnePaymentView->method('isPaymentMethodAvailableToUser')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->getP24());
    }

    public function testGetEncoding_Utf8()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['isUtf'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('isUtf')->willReturn(true);

        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('UTF-8', $oFcPayOnePaymentView->getEncoding());
    }

    public function testGetAmount()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockPrice = $this->getMockBuilder(Price::class)
            ->setMethods(['getBruttoPrice'])
            ->disableOriginalConstructor()->getMock();
        $oMockPrice->method('getBruttoPrice')->willReturn(1.99);

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getPrice'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getPrice')->willReturn($oMockPrice);

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(199, $oFcPayOnePaymentView->getAmount());
    }

    public function testGetTplLang()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['getLanguageAbbr'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('getLanguageAbbr')->willReturn('DE');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn('false');
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oMockLang);

        $this->assertEquals('DE', $oFcPayOnePaymentView->getTplLang());
    }

    public function testFcGetLangId()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['getBaseLanguage'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('getBaseLanguage')->willReturn(0);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn('false');
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oMockLang);

        $this->assertEquals(0, $oFcPayOnePaymentView->fcGetLangId());
    }

    public function testGetHashCC()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $sResponse = $sExpect =  $oFcPayOnePaymentView->getHashCC('test');

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetCCPaymentMetaData()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                'getVisa',
                'getMastercard',
                'getAmex',
                'getDiners',
                'getJCB',
                'getCarteBleue',
                '_fcpoGetCCPaymentMetaData'
            ])
            ->disableOriginalConstructor()->getMock();

        $oMockPaymentData = (object) [
            'sHashName' => 'someHashName',
            'sHashValue' => 'someHashValue',
            'sOperationModeName' => 'someOperationModeName',
            'sOperationModeValue' => 'someOperationModeValue',
            'sPaymentTag' => 'somePaymentTag',
            'sPaymentName' => 'somePaymentName',
            'blSelected' => true,
        ];

        $oFcPayOnePaymentView->method('getVisa')->willReturn(true);
        $oFcPayOnePaymentView->method('getMastercard')->willReturn(true);
        $oFcPayOnePaymentView->method('getAmex')->willReturn(true);
        $oFcPayOnePaymentView->method('getDiners')->willReturn(true);
        $oFcPayOnePaymentView->method('getJCB')->willReturn(true);
        $oFcPayOnePaymentView->method('getCarteBleue')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoGetCCPaymentMetaData')->willReturn($oMockPaymentData);

        $aExpect = [
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData,
        ];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $oFcPayOnePaymentView->fcpoGetCCPaymentMetaData();

        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetOnlinePaymentMetaData()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                'getSofortUeberweisung',
                'getEPS',
                'getPostFinanceEFinance',
                'getPostFinanceCard',
                'getIdeal',
                'getP24',
                'getBancontact',
                '_fcpoGetOnlinePaymentData'
            ])
            ->disableOriginalConstructor()->getMock();

        $oMockPaymentData = (object) [
            'sShortcut' => 'someShortcut',
            'sCaption' => 'someCaption',
            'blSelected' => true,
        ];

        $oFcPayOnePaymentView->method('getSofortUeberweisung')->willReturn(true);
        $oFcPayOnePaymentView->method('getEPS')->willReturn(true);
        $oFcPayOnePaymentView->method('getPostFinanceEFinance')->willReturn(true);
        $oFcPayOnePaymentView->method('getPostFinanceCard')->willReturn(true);
        $oFcPayOnePaymentView->method('getIdeal')->willReturn(true);
        $oFcPayOnePaymentView->method('getP24')->willReturn(true);
        $oFcPayOnePaymentView->method('getBancontact')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoGetOnlinePaymentData')->willReturn($oMockPaymentData);

        $aExpect = [
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData,
            $oMockPaymentData
        ];
        $aResponse = $oFcPayOnePaymentView->fcpoGetOnlinePaymentMetaData();

        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetActiveThemePath()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockViewConfig = $this->getMockBuilder(ViewConfig::class)
            ->setMethods(['fcpoGetActiveThemePath'])
            ->disableOriginalConstructor()->getMock();
        $oMockViewConfig->method('fcpoGetActiveThemePath')->willReturn('apex');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockViewConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('apex', $oFcPayOnePaymentView->fcpoGetActiveThemePath());
    }

    public function testFcpoValidatePayolutionBillHasTelephone()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoValidatePayolutionBillHasTelephone', 'fcpoGetUserValue'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoValidatePayolutionBillHasTelephone')->willReturn(true);
        $oFcPayOnePaymentView->method('fcpoGetUserValue')->willReturn('');

        $oMockViewConfig = $this->getMockBuilder(ViewConfig::class)
            ->setMethods(['fcpoGetActiveThemePath'])
            ->disableOriginalConstructor()->getMock();
        $oMockViewConfig->method('fcpoGetActiveThemePath')->willReturn('apex');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockViewConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertTrue( $oFcPayOnePaymentView->_fcpoValidatePayolutionBillHasTelephone());
    }

    public function testFcpoGetOnlinePaymentData()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getDynValue'])
            ->disableOriginalConstructor()->getMock();

        $sIdent = 'P24';
        $aDynValue['fcpo_sotype'] = $sIdent;
        $oFcPayOnePaymentView->method('getDynValue')->willReturn($aDynValue);

        $oExpectPaymentMetaData = (object) [
            'sShortcut' => $sIdent,
            'sCaption' => 'P24',
            'blSelected' => true,
        ];

        $oResponse = $this->invokeMethod( $oFcPayOnePaymentView, '_fcpoGetOnlinePaymentData', [$sIdent]);
        $this->assertEquals($oExpectPaymentMetaData, $oResponse);
    }

    public function testFcpoGetCCPaymentMetaData_protected()
    {
        $sPaymentTag = 'someTag';
        $sPaymentName = 'someName';
        $aDynValue['fcpo_kktype'] = $sPaymentTag;
        $sHashCC = md5('12345');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getDynValue', 'getHashCC'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getDynValue')->willReturn($aDynValue);
        $oFcPayOnePaymentView->method('getHashCC')->willReturn($sHashCC);

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['getId', 'fcpoGetOperationMode'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('getId')->willReturn('someId');
        $oMockPayment->method('fcpoGetOperationMode')->willReturn('test');

        $oExpectPaymentMetaData = (object) [
            'sHashName' => 'fcpo_hashcc_' . $sPaymentTag,
            'sHashValue' => $sHashCC,
            'sOperationModeName' => 'fcpo_mode_someId_' . $sPaymentTag,
            'sOperationModeValue' => 'test',
            'sPaymentTag' => $sPaymentTag,
            'sPaymentName' => $sPaymentName,
            'blSelected' => true,
        ];

        $oResponse = $this->invokeMethod( $oFcPayOnePaymentView, '_fcpoGetCCPaymentMetaData', [$oMockPayment, $sPaymentTag, $sPaymentName]);
        $this->assertEquals($oExpectPaymentMetaData, $oResponse);
    }

    public function testGetOperationModeELV()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load', 'fcpoGetOperationMode'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('load')->willReturn(true);
        $oMockPayment->method('fcpoGetOperationMode')->willReturn('test');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockPayment);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('test', $this->invokeMethod( $oFcPayOnePaymentView, '_getOperationModeELV'));
    }

    public function testGetHashELVWithChecktype()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('md5');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                'getSubAccountId',
                'getChecktype',
                'getEncoding',
                'getMerchantId',
                '_getOperationModeELV',
                'getPortalId',
                'getPortalKey',
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOnePaymentView->method('getSubAccountId')->willReturn('someSubaccountId');
        $oFcPayOnePaymentView->method('getChecktype')->willReturn('someChecktype');
        $oFcPayOnePaymentView->method('getEncoding')->willReturn('someEncoding');
        $oFcPayOnePaymentView->method('getMerchantId')->willReturn('someMerchantId');
        $oFcPayOnePaymentView->method('_getOperationModeELV')->willReturn('test');
        $oFcPayOnePaymentView->method('getPortalId')->willReturn('somePortalId');
        $oFcPayOnePaymentView->method('getPortalKey')->willReturn('somePortalKey');

        $sExpectHash = hash_hmac('sha384', 'someSubaccountIdsomeChecktypesomeEncodingsomeMerchantIdtestsomePortalIdbankaccountcheckJSON', 'somePortalKey');

        $this->assertEquals($sExpectHash, $this->invokeMethod( $oFcPayOnePaymentView, 'getHashELVWithChecktype'));
    }

    public function testGetHashELVWithoutChecktype()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('md5');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                'getSubAccountId',
                'getEncoding',
                'getMerchantId',
                '_getOperationModeELV',
                'getPortalId',
                'getPortalKey',
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOnePaymentView->method('getSubAccountId')->willReturn('someSubaccountId');
        $oFcPayOnePaymentView->method('getEncoding')->willReturn('someEncoding');
        $oFcPayOnePaymentView->method('getMerchantId')->willReturn('someMerchantId');
        $oFcPayOnePaymentView->method('_getOperationModeELV')->willReturn('test');
        $oFcPayOnePaymentView->method('getPortalId')->willReturn('somePortalId');
        $oFcPayOnePaymentView->method('getPortalKey')->willReturn('somePortalKey');

        $sExpectHash = hash_hmac('sha384', 'someSubaccountIdsomeEncodingsomeMerchantIdtestsomePortalIdbankaccountcheckJSON', 'somePortalKey');

        $this->assertEquals($sExpectHash, $this->invokeMethod( $oFcPayOnePaymentView, 'getHashELVWithoutChecktype'));
    }

    public function testGetPaymentList_1()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('after');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['checkAddressAndScore'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('checkAddressAndScore')->willReturn(true);

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['redirect'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('redirect')->willReturn(null);

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oMockUser, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oPaymentList', null);

        $mResponse = $mExpect = $this->invokeMethod( $oFcPayOnePaymentView, 'getPaymentList');

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testGetPaymentList_2()
    {
        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['checkAddressAndScore'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('checkAddressAndScore')->willReturn(false);

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['redirect'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('redirect')->willReturn(null);

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oMockUser, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oPaymentList', null);

        $mResponse = $mExpect = $this->invokeMethod( $oFcPayOnePaymentView, 'getPaymentList');

        $this->assertEquals($mExpect, $mResponse);
    }

    public function testFcpoGetBICMandatory()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoGetBICMandatory());
    }

    public function testFcpoGetCreditcardType()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someValue', $this->invokeMethod( $oFcPayOnePaymentView, 'fcpoGetCreditcardType'));
    }

    public function testFcpoGetInstallments()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_aInstallmentCalculation', ['someInstallment']);

        $this->assertEquals(['someInstallment'], $oFcPayOnePaymentView->fcpoGetInstallments());
    }

    public function testFcpoGetMatchingProfile()
    {
        $aMockProfiles = [
            ['tx_limit_someString_max' => 200],
            ['tx_limit_someString_min' => 5.99],
            ['activation_status_someString']
        ];

        $aExpect = [
            'tx_limit_someString_max' => 200
        ];

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoFetchRatePayProfilesByPaymentType',
                '_fcpoGetRatePayStringAdditionByPaymentId',
                '_fcpoCheckRatePayProfileMatch',
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoFetchRatePayProfilesByPaymentType')->willReturn($aMockProfiles);
        $oFcPayOnePaymentView->method('_fcpoGetRatePayStringAdditionByPaymentId')->willReturn('someString');
        $oFcPayOnePaymentView->method('_fcpoCheckRatePayProfileMatch')->willReturn(true);

        $this->assertEquals($aExpect, $oFcPayOnePaymentView->_fcpoGetMatchingProfile('someId'));
    }

    public function testFcpoCheckRatePayProfileMatch()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                'fcpoGetBasketSum',
                'fcpoGetDBasketSum',
                'fcGetBillCountry',
                'getActCurrency'
            ])
            ->disableOriginalConstructor()->getMock();

        $oMockCurrency = (object) [
            'name' => 'EUR',
            'sign' => '€'
        ];

        $oFcPayOnePaymentView->method('fcpoGetBasketSum')->willReturn('10');
        $oFcPayOnePaymentView->method('fcpoGetDBasketSum')->willReturn(10.0);
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('DE');
        $oFcPayOnePaymentView->method('getActCurrency')->willReturn($oMockCurrency);

        $aMockData = [
            'activation_status' => '2',
            'basketvalue_max' => 15,
            'basketvalue_min' => 5,
            'country_code_billing' => 'DE',
            'currency' => 'EUR'
        ];

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoCheckRatePayProfileMatch($aMockData));
    }

    public function testFcpoGetRatePayStringAdditionByPaymentId()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals('invoice', $oFcPayOnePaymentView->_fcpoGetRatePayStringAdditionByPaymentId('fcporp_bill'));
    }

    public function testFcpoFetchRatePayProfilesByPaymentType()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $aMockProfiles = [['profile1'], ['profile1']];
        $aExpect = [];

        $oMockRatePay = $this->getMockBuilder(FcPoRatePay::class)
            ->setMethods(['fcpoGetRatePayProfiles'])
            ->disableOriginalConstructor()->getMock();
        $oMockRatePay->method('fcpoGetRatePayProfiles')->willReturn($aMockProfiles);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockRatePay);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aExpect, $oFcPayOnePaymentView->_fcpoFetchRatePayProfilesByPaymentType('somePaymentId'));
    }

    public function testFcpoCheckPaypalExpressRemoval()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oPaymentList', ['fcpopaypal_express' => 'someValue']);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod( $oFcPayOnePaymentView, '_fcpoCheckPaypalExpressRemoval'));
    }

    public function testFcpoKlarnaUpdateUser()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getSelectedAddressId', 'save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getSelectedAddressId')->willReturn('someAddressId');
        $oMockUser->method('save')->willReturn(true);

        $oMockAddress = $this->getMockBuilder(Address::class)
            ->setMethods(['load', 'save'])
            ->disableOriginalConstructor()->getMock();
        $oMockAddress->method('load')->willReturn(true);
        $oMockAddress->method('save')->willReturn(true);

        $sType = 'kls';
        $aDynValue = [
            'fcpo_' . $sType . '_fon' => '123456',
            'fcpo_' . $sType . '_birthday' => 'someBirthday',
            'fcpo_' . $sType . '_personalid' => 'someId',
            'fcpo_' . $sType . '_sal' => 'someSal',
            'fcpo_' . $sType . '_addinfo' => 'someAddinfo',
            'fcpo_' . $sType . '_del_addinfo' => 'someDelAddinfo',
        ];

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getDynValue', 'getUser', '_fcpoGetType'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getDynValue')->willReturn($aDynValue);
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('_fcpoGetType')->willReturn($sType);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(false);
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockAddress);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod( $oFcPayOnePaymentView, '_fcpoKlarnaUpdateUser'));
    }

    public function testFcpoCheckUpdateField_AlreadyChanged()
    {
        $blMockUserChanged = true;
        $sMockDbField = 'someDbField';
        $sMockType = 'kls';
        $sMockDynValueField = 'someDynValueField';
        $aMockDynValue = [
            'fcpo_' . $sMockType . '_fon' => '123456',
            'fcpo_' . $sMockType . '_birthday' => 'someBirthday',
            'fcpo_' . $sMockType . '_personalid' => 'someId',
            'fcpo_' . $sMockType . '_sal' => 'someSal',
            'fcpo_' . $sMockType . '_addinfo' => 'someAddinfo',
            'fcpo_' . $sMockType . '_del_addinfo' => 'someDelAddinfo',
        ];

        $oMockUser = new User();
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoCheckUpdateField($blMockUserChanged, $sMockType, $aMockDynValue, $sMockDbField, $sMockDynValueField, $oMockUser));
    }

    public function testFcpoCheckUpdateField_NotYetChanged()
    {
        $blMockUserChanged = false;
        $sMockDbField = 'someDbField';
        $sMockType = 'someOtherType';
        $sMockDynValueField = 'someDynValueField';
        $aMockDynValue = [
            'fcpo_' . $sMockType . '_fon' => '123456',
            'fcpo_' . $sMockType . '_birthday' => 'someBirthday',
            'fcpo_' . $sMockType . '_personalid' => 'someId',
            'fcpo_' . $sMockType . '_sal' => 'someSal',
            'fcpo_' . $sMockType . '_addinfo' => 'someAddinfo',
            'fcpo_' . $sMockType . '_del_addinfo' => 'someDelAddinfo',
        ];

        $oMockUser = new User();
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $this->assertEquals(false, $oFcPayOnePaymentView->_fcpoCheckUpdateField($blMockUserChanged, 'kls', $aMockDynValue, $sMockDbField, $sMockDynValueField, $oMockUser));
    }

    public function testFcpoGetType()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals('klv', $oFcPayOnePaymentView->_fcpoGetType());
    }

    public function testValidatePayment()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoGetPaymentId',
                '_fcpoKlarnaUpdateUser',
                '_processParentReturnValue',
                '_fcpoProcessValidation',
                'getUser'
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOnePaymentView->method('_fcpoGetPaymentId')->willReturn('somePaymentId');
        $oFcPayOnePaymentView->method('_processParentReturnValue')->willReturn('order');
        $oFcPayOnePaymentView->method('_fcpoProcessValidation')->willReturn('order');

        $this->assertEquals('order', $this->invokeMethod( $oFcPayOnePaymentView, 'validatePayment'));
    }

    public function testProcessParentReturnValue()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals('someValue', $this->invokeMethod( $oFcPayOnePaymentView, '_processParentReturnValue', ['someValue']));
    }

    public function testGetIntegratorid()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetIntegratorId')->willReturn('someIntegratorId');
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someIntegratorId', $oFcPayOnePaymentView->getIntegratorid());
    }

    public function testGetIntegratorver()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetIntegratorVersion')->willReturn('someIntegratorVersion');
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someIntegratorVersion', $oFcPayOnePaymentView->getIntegratorver());
    }

    public function testGetIntegratorextver()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetModuleVersion')->willReturn('someModuleVersion');
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someModuleVersion', $oFcPayOnePaymentView->getIntegratorextver());
    }

    public function testFcpoGetConfirmationText()
    {
        $sId = 'someKlarnaStoreId';
        $sKlarnaLang = '';
        $sConfirmText = 'someConfirmText';

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoGetKlarnaLang'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoGetKlarnaLang')->willReturn($sKlarnaLang);

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn($sConfirmText);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $sExpect = $sConfirmText;
        $this->assertEquals($sExpect, $oFcPayOnePaymentView->fcpoGetConfirmationText());
    }

    public function testFcpoKlarnaIsTelephoneNumberNeeded()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()->getMock();
        $oMockUser->oxuser__oxfon = new Field('123456789');
        $oMockUser->oxuser__oxcountryid = new Field('someCountryId');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser', 'fcGetBillCountry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('DE');

        $this->assertEquals(false, $oFcPayOnePaymentView->fcpoKlarnaIsTelephoneNumberNeeded());
    }

    public function testFcpoKlarnaIsBirthdayNeeded()
    {
        $oMockCountry = $this->getMockBuilder(Country::class)
            ->disableOriginalConstructor()->getMock();
        $oMockCountry->oxcountry__oxisoalpha2 = new Field('DE');

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getUserCountry'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getUserCountry')->willReturn($oMockCountry);
        $oMockUser->oxuser__oxbirthdate = new Field('0000-00-00');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser', 'fcGetBillCountry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('DE');

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoKlarnaIsBirthdayNeeded());
    }

    public function testFcpoKlarnaIsAddressAdditionNeeded()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()->getMock();
        $oMockUser->oxuser__oxaddinfo = new Field('');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser', 'fcGetBillCountry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('nl');

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoKlarnaIsAddressAdditionNeeded());
    }

    public function testFcpoKlarnaIsDelAddressAdditionNeeded()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getSelectedAddressId'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getSelectedAddressId')->willReturn('someAddressId');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser', 'fcGetBillCountry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('nl');

        $oMockAddress = $this->getMockBuilder(Address::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockAddress->method('load')->willReturn(true);
        $oMockAddress->oxaddress__oxaddinfo = new Field(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockAddress);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoKlarnaIsDelAddressAdditionNeeded());
    }

    public function testFcpoKlarnaIsGenderNeeded()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()->getMock();
        $oMockUser->oxuser__oxsal = new Field(false);

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser', 'fcGetBillCountry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('nl');

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoKlarnaIsGenderNeeded());
    }

    public function testFcpoKlarnaIsPersonalIdNeeded()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()->getMock();
        $oMockUser->oxuser__fcpopersonalid = new Field(false);

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUser', 'fcGetBillCountry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('dk');

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoKlarnaIsPersonalIdNeeded());
    }

    public function testFcpoKlarnaInfoNeeded()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                'fcpoKlarnaIsTelephoneNumberNeeded',
                'fcpoKlarnaIsBirthdayNeeded',
                'fcpoKlarnaIsAddressAdditionNeeded',
                'fcpoKlarnaIsDelAddressAdditionNeeded',
                'fcpoKlarnaIsGenderNeeded',
                'fcpoKlarnaIsPersonalIdNeeded'
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOnePaymentView->method('fcpoKlarnaIsTelephoneNumberNeeded')->willReturn(false);
        $oFcPayOnePaymentView->method('fcpoKlarnaIsBirthdayNeeded')->willReturn(false);
        $oFcPayOnePaymentView->method('fcpoKlarnaIsAddressAdditionNeeded')->willReturn(false);
        $oFcPayOnePaymentView->method('fcpoKlarnaIsDelAddressAdditionNeeded')->willReturn(false);
        $oFcPayOnePaymentView->method('fcpoKlarnaIsGenderNeeded')->willReturn(false);
        $oFcPayOnePaymentView->method('fcpoKlarnaIsPersonalIdNeeded')->willReturn(false);

        $this->assertEquals(false, $oFcPayOnePaymentView->fcpoKlarnaInfoNeeded());
    }

    public function testFcpoGetDebitCountries()
    {
        $aCountries = ['a7c40f631fc920687.20179984'];

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['fcpoGetCountryIsoAlphaById', 'fcpoGetCountryNameById'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('fcpoGetCountryIsoAlphaById')->willReturn('DE');
        $oMockPayment->method('fcpoGetCountryNameById')->willReturn('Deutschland');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn($aCountries);

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['fcGetBillCountry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('someCountry');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockPayment);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $aExpect = ['DE' => 'Deutschland'];

        $this->assertEquals($aExpect, $oFcPayOnePaymentView->fcpoGetDebitCountries());
    }

    public function testFcCleanupSessionFragments()
    {
        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('getId')->willReturn('someId');

        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals(null, $this->invokeMethod( $oFcPayOnePaymentView, '_fcCleanupSessionFragments', [$oMockPayment]));
    }

    public function testFcGetPaymentByPaymentType_Positive()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn('someId');

        $sMockPaymentType = 'fcpopayadvance';

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someValue');
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcPoDb', $oMockDatabase);

        $oMockUserPayment = $this->getMockBuilder(UserPayment::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockUserPayment->method('load')->willReturn(true);

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['fcpoGetUserPaymentId'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('fcpoGetUserPaymentId')->willReturn('someUserPaymentId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockPayment, $oMockUserPayment);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $oResponse = $oExpect = $this->invokeMethod( $oFcPayOnePaymentView, '_fcGetPaymentByPaymentType', [$oMockUser, $sMockPaymentType]);

        $this->assertEquals($oExpect, $oResponse);
    }

    public function testFcGetPaymentByPaymentType_Negative()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn('someId');

        $sMockPaymentType = null;

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someValue');
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcPoDb', $oMockDatabase);

        $oMockUserPayment = $this->getMockBuilder(UserPayment::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockUserPayment->method('load')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockUserPayment);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $this->invokeMethod( $oFcPayOnePaymentView, '_fcGetPaymentByPaymentType', [$oMockUser, $sMockPaymentType]));
    }

    public function testAssignDebitNoteParams()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn('someId');

        $oMockUserPayment = $this->getMockBuilder(UserPayment::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockUserPayment->method('load')->willReturn(true);

        $aMockPaymentData = [(object) ['name' => 'someName', 'value' => 'someValue',]];

        $oMockUtils = $this->getMockBuilder(Utils::class)
            ->setMethods(['assignValuesFromText'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtils->method('assignValuesFromText')->willReturn($aMockPaymentData);

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', '_fcGetPaymentByPaymentType', 'getUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn('1');
        $oFcPayOnePaymentView->method('_fcGetPaymentByPaymentType')->willReturn($oMockUserPayment);
        $oFcPayOnePaymentView->method('getUser')->willReturn($oMockUser);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtils')->willReturn($oMockUtils);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $this->invokeMethod( $oFcPayOnePaymentView, '_assignDebitNoteParams'));
    }

    public function testGetDynValue()
    {
        $aPaymentList = ['fcpodebitnote' => 'someValue'];

        $aDynValues = ['someDynValue'];

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getConfigParam', 'getPaymentList', '_assignDebitNoteParams'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getConfigParam')->willReturn('1');
        $oFcPayOnePaymentView->method('getPaymentList')->willReturn($aPaymentList);

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_aDynValue', $aDynValues);

        $this->assertEquals($aDynValues, $oFcPayOnePaymentView->getDynValue());
    }

    public function testFcGetBillCountry()
    {
        $oMockCountry = $this->getMockBuilder(Country::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockCountry->method('load')->willReturn(true);
        $oMockCountry->oxcountry__oxisoalpha2 = new Field('de');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['getUserBillCountryId'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('getUserBillCountryId')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockCountry);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('de', $oFcPayOnePaymentView->fcGetBillCountry());
    }

    public function testFcpoGetKlarnaLang()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['fcGetBillCountry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('fcGetBillCountry')->willReturn('de');

        $this->assertEquals('de_de', $this->invokeMethod( $oFcPayOnePaymentView, '_fcpoGetKlarnaLang'));
    }

    public function testFcIsPayOnePaymentType()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $this->assertEquals(true, $oFcPayOnePaymentView->fcIsPayOnePaymentType('fcpopo_bill'));
    }

    public function testFcpoProcessValidation_Error()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoSetMandateParams',
                '_fcCleanupSessionFragments',
                '_fcpoSecInvoiceSaveRequestedValues',
                '_fcpoBNPLSaveRequestedValues',
                '_fcpoKlarnaCombinedValidate',
                '_fcpoPayolutionPreCheck',
                '_fcpoCheckRatePayBillMandatoryUserData',
                '_fcpoAdultCheck'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoSecInvoiceSaveRequestedValues')->willReturn('order');
        $oFcPayOnePaymentView->method('_fcpoBNPLSaveRequestedValues')->willReturn('order');
        $oFcPayOnePaymentView->method('_fcpoKlarnaCombinedValidate')->willReturn('order');
        $oFcPayOnePaymentView->method('_fcpoPayolutionPreCheck')->willReturn('order');
        $oFcPayOnePaymentView->method('_fcpoCheckRatePayBillMandatoryUserData')->willReturn('order');
        $oFcPayOnePaymentView->method('_fcpoAdultCheck')->willReturn(null);

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('load')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockPayment);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertNotEquals('order', $oFcPayOnePaymentView->_fcpoProcessValidation('order', 'somePaymentId'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSetPayolutionAjaxParams()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $aMockParams = ['some', 'params'];
        $oFcPayOnePaymentView->setPayolutionAjaxParams($aMockParams);
    }

    public function testFcpoPayolutionPreCheck()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoPayolutionPreCheck'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoPayolutionPreCheck')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoPayolutionPreCheck('somePaymentId'));
    }

    public function testFcpoGetDBasketSum()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockBasket = $this->getMockBuilder(Basket::class)
            ->setMethods(['getBruttoSum'])
            ->disableOriginalConstructor()->getMock();
        $oMockBasket->method('getBruttoSum')->willReturn(5.99);

        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['getBasket'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('getBasket')->willReturn($oMockBasket);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(5.99, $oFcPayOnePaymentView->fcpoGetDBasketSum());
    }

    public function testFcpoGetBasketSum()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['fcpoGetDBasketSum'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('fcpoGetDBasketSum')->willReturn(5.99);

        $this->assertEquals('5,99', $oFcPayOnePaymentView->fcpoGetBasketSum());
    }

    public function testFcpoRatePayShowUstid()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals(false, $oFcPayOnePaymentView->fcpoRatePayShowUstid());
    }

    public function testFcpoRatePayShowBirthdate()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals(false, $oFcPayOnePaymentView->fcpoRatePayShowBirthdate());
    }

    public function testFcpoRatePayShowFon()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoRatePayShowFon());
    }

    public function testFcpoCheckRatePayBillMandatoryUserData_B2BMode()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoRatePaySaveRequestedValues',
                'fcpoRatePayShowUstid',
                'fcpoRatePayShowBirthdate'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('fcpoRatePayShowUstid')->willReturn(true);
        $oFcPayOnePaymentView->method('fcpoRatePayShowBirthdate')->willReturn(false);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn(null);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $oFcPayOnePaymentView->_fcpoCheckRatePayBillMandatoryUserData(true, 'somePaymentId'));
    }

    public function testFcpoCheckRatePayBillMandatoryUserData_B2CMode()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoRatePaySaveRequestedValues',
                'fcpoRatePayShowUstid',
                'fcpoRatePayShowBirthdate'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('fcpoRatePayShowUstid')->willReturn(true);
        $oFcPayOnePaymentView->method('fcpoRatePayShowBirthdate')->willReturn(true);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn(null);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $oFcPayOnePaymentView->_fcpoCheckRatePayBillMandatoryUserData(true, 'somePaymentId'));
    }

    public function testFcpoPayolutionPreCheck_protected()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoIsPayolution', '_fcpoValidatePayolutionPreCheck'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoIsPayolution')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoValidatePayolutionPreCheck')->willReturn(true);

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoPayolutionPreCheck('test', 'somePaymentId'));
    }

    public function testFcpoValidatePayolutionPreCheck_Validated()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoPayolutionSaveRequestedValues',
                '_fcpoCheckAgreedDataUsage',
                '_fcpoCheckPayolutionMandatoryUserData',
                '_fcpoValidateBankDataRelatedPayolutionPayment',
                '_fcpoFinalValidationPayolutionPreCheck',
                '_fcpoGetPayolutionErrorMessage',
                '_fcpoSetPayolutionErrorMessage'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoPayolutionSaveRequestedValues')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoCheckAgreedDataUsage')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoCheckPayolutionMandatoryUserData')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoValidateBankDataRelatedPayolutionPayment')->willReturn('order');
        $oFcPayOnePaymentView->method('_fcpoFinalValidationPayolutionPreCheck')->willReturn('order');
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionErrorMessage')->willReturn('');

        $this->assertEquals('order', $oFcPayOnePaymentView->_fcpoValidatePayolutionPreCheck('order', 'fcpopo_bill'));
    }

    public function testFcpoGetPayolutionErrorMessage_USTID()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals('FCPO_PAYOLUTION_NO_USTID', $oFcPayOnePaymentView->_fcpoGetPayolutionErrorMessage(true, false));
    }

    public function testFcpoFinalValidationPayolutionPreCheck()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoPerformPayolutionPreCheck', '_fcpoSetPayolutionErrorMessage',])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoPerformPayolutionPreCheck')->willReturn(false);

        $this->assertEquals(null, $oFcPayOnePaymentView->_fcpoFinalValidationPayolutionPreCheck(true, 'somePaymentId'));
    }

    public function testFcpoValidateBankDataRelatedPayolutionPaymentInvalidBankData()
    {
        $sMockPaymentId = 'fcpopo_installment';

        $aMockBankData = [
            'fcpo_payolution_installment_iban' => 'someIban',
            'fcpo_payolution_installment_bic' => 'someBic',
            'fcpo_payolution_installment_accountholder' => 'someAccountHolder',
        ];

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoCheckIsBankDataRelatedPayolutionPayment',
                '_fcpoGetPayolutionBankData',
                '_fcpoValidateBankData',
                '_fcpoCheckSepaAgreed',
                '_fcpoSetPayolutionErrorMessage',
                '_fcpoGetPayolutionSelectedInstallmentIndex'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoCheckIsBankDataRelatedPayolutionPayment')->will($this->returnValue(true));
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
        $oFcPayOnePaymentView->method('_fcpoValidateBankData')->will($this->returnValue(false));
        $oFcPayOnePaymentView->method('_fcpoCheckSepaAgreed')->will($this->returnValue(true));
        $oFcPayOnePaymentView->method('_fcpoSetPayolutionErrorMessage')->will($this->returnValue(null));
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionSelectedInstallmentIndex')->will($this->returnValue(false));

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_blIsPayolutionInstallmentAjax', false);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoValidateBankDataRelatedPayolutionPayment', ['order', $sMockPaymentId]));
    }

    public function testFcpoValidateBankDataRelatedPayolutionPayment_NoSepaAgree()
    {
        $sMockPaymentId = 'fcpopo_installment';

        $aMockBankData = [
            'fcpo_payolution_installment_iban' => 'someIban',
            'fcpo_payolution_installment_bic' => 'someBic',
            'fcpo_payolution_installment_accountholder' => 'someAccountHolder',
        ];

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoCheckIsBankDataRelatedPayolutionPayment',
                '_fcpoGetPayolutionBankData',
                '_fcpoValidateBankData',
                '_fcpoCheckSepaAgreed',
                '_fcpoSetPayolutionErrorMessage',
                '_fcpoGetPayolutionSelectedInstallmentIndex'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoCheckIsBankDataRelatedPayolutionPayment')->will($this->returnValue(true));
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
        $oFcPayOnePaymentView->method('_fcpoValidateBankData')->will($this->returnValue(true));
        $oFcPayOnePaymentView->method('_fcpoCheckSepaAgreed')->will($this->returnValue(false));
        $oFcPayOnePaymentView->method('_fcpoSetPayolutionErrorMessage')->will($this->returnValue(null));
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionSelectedInstallmentIndex')->will($this->returnValue(false));

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_blIsPayolutionInstallmentAjax', false);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoValidateBankDataRelatedPayolutionPayment', [$sMockPaymentId, '']));
    }

    public function testFcpoValidateBankDataRelatedPayolutionPayment_MissingBankData()
    {
        $sMockPaymentId = 'fcpopo_installment';

        $aMockBankData = [];

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoCheckIsBankDataRelatedPayolutionPayment',
                '_fcpoGetPayolutionBankData',
                '_fcpoValidateBankData',
                '_fcpoCheckSepaAgreed',
                '_fcpoSetPayolutionErrorMessage',
                '_fcpoGetPayolutionSelectedInstallmentIndex'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoCheckIsBankDataRelatedPayolutionPayment')->will($this->returnValue(true));
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
        $oFcPayOnePaymentView->method('_fcpoValidateBankData')->will($this->returnValue(true));
        $oFcPayOnePaymentView->method('_fcpoCheckSepaAgreed')->will($this->returnValue(false));
        $oFcPayOnePaymentView->method('_fcpoSetPayolutionErrorMessage')->will($this->returnValue(null));
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionSelectedInstallmentIndex')->will($this->returnValue(false));

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_blIsPayolutionInstallmentAjax', false);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoValidateBankDataRelatedPayolutionPayment', [$sMockPaymentId, '']));

    }

    public function testFcpoCheckPayolutionMandatoryUserData_Valid()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoValidateMandatoryUserDataForPayolutionBill'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoValidateMandatoryUserDataForPayolutionBill')->willReturn(true);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoCheckPayolutionMandatoryUserData', ['fcpopo_bill']));
    }

    public function tests__fcpoProcessValidation_Ok()
    {
        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoSetMandateParams',
                '_fcCleanupSessionFragments',
                '_fcpoSecInvoiceSaveRequestedValues',
                '_fcpoPayolutionPreCheck'
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoSecInvoiceSaveRequestedValues')->willReturn(null);
        $oFcPayOnePaymentView->method('_fcpoPayolutionPreCheck')->willReturn('order');

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('load')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockPayment);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('order', $this->invokeMethod($oFcPayOnePaymentView, '_fcpoProcessValidation', ['order', 'somePaymentId']));
    }

    public function testFcpoCheckIsBankDataRelatedPayolutionPayment()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoCheckIsBankDataRelatedPayolutionPayment('fcpopo_debitnote'));
    }

    public function testFcpoGetPayolutionPreCheckReturnValue()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_blIsPayolutionInstallmentAjax', true);
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_sPayolutionCurrentErrorMessage', 'someMessage');

        $this->assertEquals('someMessage', $this->invokeMethod($oFcPayOnePaymentView, '_fcpoGetPayolutionPreCheckReturnValue', [null]));
    }

    public function testFcpoPayolutionPreCheck_ValidBankData()
    {
        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someValue');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoIsPayolution',
                '_fcpoPayolutionSaveRequestedValues',
                '_fcpoCheckAgreed',
                '_fcpoGetPayolutionBankData',
                '_fcpoValidateBankData',
                '_fcpoCheckSepaAgreed',
                '_fcpoPerformPayolutionPreCheck'
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOnePaymentView->method('_fcpoIsPayolution')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoPayolutionSaveRequestedValues')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoCheckAgreed')->willReturn(false);
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionBankData')->willReturn(false);
        $oFcPayOnePaymentView->method('_fcpoValidateBankData')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoCheckSepaAgreed')->willReturn(false);
        $oFcPayOnePaymentView->method('_fcpoPerformPayolutionPreCheck')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoPayolutionPreCheck', [null, 'someId']));
    }

    public function testFcpoPayolutionPreCheck_InvalidBankData()
    {
        $aMockBankData = [
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        ];

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someValue');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoIsPayolution',
                '_fcpoPayolutionSaveRequestedValues',
                '_fcpoCheckAgreed',
                '_fcpoGetPayolutionBankData',
                '_fcpoValidateBankData',
                '_fcpoCheckSepaAgreed',
                '_fcpoPerformPayolutionPreCheck'
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOnePaymentView->method('_fcpoIsPayolution')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoPayolutionSaveRequestedValues')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoCheckAgreed')->willReturn(false);
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionBankData')->willReturn($aMockBankData);
        $oFcPayOnePaymentView->method('_fcpoValidateBankData')->willReturn(false);
        $oFcPayOnePaymentView->method('_fcpoCheckSepaAgreed')->willReturn(false);
        $oFcPayOnePaymentView->method('_fcpoPerformPayolutionPreCheck')->willReturn(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoPayolutionPreCheck', [null, 'someId']));
    }

    public function testFcpoPayolutionPreCheck_Sepa()
    {
        $aMockBankData = [
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        ];

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someValue');

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods([
                '_fcpoIsPayolution',
                '_fcpoPayolutionSaveRequestedValues',
                '_fcpoCheckAgreed',
                '_fcpoGetPayolutionBankData',
                '_fcpoValidateBankData',
                '_fcpoCheckSepaAgreed',
                '_fcpoPerformPayolutionPreCheck'
            ])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOnePaymentView->method('_fcpoIsPayolution')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoPayolutionSaveRequestedValues')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoCheckAgreed')->willReturn(false);
        $oFcPayOnePaymentView->method('_fcpoGetPayolutionBankData')->willReturn($aMockBankData);
        $oFcPayOnePaymentView->method('_fcpoValidateBankData')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoCheckSepaAgreed')->willReturn(false);
        $oFcPayOnePaymentView->method('_fcpoPerformPayolutionPreCheck')->willReturn(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoPayolutionPreCheck', [null, 'someId']));
    }

    public function testFcpoValidateBankData()
    {
        $aMockBankData = [
            'fcpo_payolution_installment_accountholder' => 'Some Person',
            'fcpo_payolution_installment_iban' => 'DE12500105170648489890',
            'fcpo_payolution_installment_bic' => 'BELADEBEXXX',
        ];

        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $this->assertEquals(true, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoValidateBankData', [$aMockBankData, 'fcpopo_installment']));
    }

    public function testFcpoGetPayolutionBankData()
    {
        $aMockBankData = [
            'fcpo_payolution_debitnote_accountholder' => 'Some Person',
            'fcpo_payolution_debitnote_iban' => 'DE12500105170648489890',
            'fcpo_payolution_debitnote_bic' => '',
        ];

        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockBankData);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $aMockExpectedResult = [
            'fcpo_payolution_debitnote_bic' => '',
            'fcpo_payolution_debitnote_iban' => 'DE12500105170648489890',
            'fcpo_payolution_debitnote_accountholder' => 'Some Person',
        ];

        $this->assertEquals($aMockExpectedResult, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoGetPayolutionBankData', ['fcpopo_debitnote']));
    }

    public function testFcpoCheckAgreed()
    {
        $aMockData = [
            'fcpo_payolution_bill_agreed' => 'agreed'
        ];

        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockData);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_blIsPayolutionInstallmentAjax', true);
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_aAjaxPayolutionParams', $aMockData);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoCheckAgreedDataUsage'));
    }

    public function testFcpoCheckSepaAgreed_Debitnote()
    {
        $aMockData = [
            'fcpo_payolution_debitnote_sepa_agreed' => 'agreed',
        ];

        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockData);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoCheckSepaAgreed('fcpopo_debitnote'));
    }

    public function testFcpoCheckSepaAgreed_Installment()
    {
        $aMockData = [
            'fcpo_payolution_installment_sepa_agreed' => 'agreed',
        ];

        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockData);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoCheckSepaAgreed('fcpopo_installment'));
    }

    public function testFcpoRatePaySaveRequestedValues()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $sMockPaymentId = 'somePaymentId';
        $aMockData = [
            $sMockPaymentId.'_birthdate_year' => '1978',
            $sMockPaymentId.'_birthdate_month' => '12',
            $sMockPaymentId.'_birthdate_day' => '08',
            $sMockPaymentId.'_fon' => '987654321',
            $sMockPaymentId.'_ustid' => 'DE987654321',
        ];

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('save')->willReturn(null);
        $oMockUser->oxuser__oxbirthdate = new Field('1978-12-07');
        $oMockUser->oxuser__oxfon = new Field('0123456789');
        $oMockUser->oxuser__oxustid = new Field('DE123456789');

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
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockData);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoRatePaySaveRequestedValues', [$sMockPaymentId]));
    }

    public function testFcpoPayolutionSaveRequestedValues_ValidBirthdate()
    {
        $aMockData = [
            'fcpo_payolution_birthdate_year' => '1978',
            'fcpo_payolution_birthdate_month' => '12',
            'fcpo_payolution_birthdate_day' => '07',
            'fcpo_payolution_ustid' => 'someUstid',
        ];

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoSaveBirthdayData', '_fcpoSaveUserData',])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoSaveBirthdayData')->willReturn(true);
        $oFcPayOnePaymentView->method('_fcpoSaveUserData')->willReturn(true);

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_aAjaxPayolutionParams', $aMockData);
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_blIsPayolutionInstallmentAjax', true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockData);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoPayolutionSaveRequestedValues', ['fcpopo_bill']));
    }

    public function testFcpoGetRequestedUstid()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $sMockPaymentId = 'somePaymentId';
        $aMockData = [
            'fcpo_payolution_'.$sMockPaymentId.'_ustid' => 'DE987654321',
        ];

        $this->assertEquals('DE987654321', $this->invokeMethod($oFcPayOnePaymentView, '_fcpoGetRequestedUstid', [$aMockData, $sMockPaymentId]));
    }

    public function testFcpoSaveUserData()
    {
        $aMockRequestedValues = 'someData';
        $sMockPaymentId = 'somePaymentId';
        $sMockDbFieldName = 'someDbField';

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['fcpoGetUserValue', '_fcpoSetUserValue', '_fcpoGetRequestedValue',])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('fcpoGetUserValue')->willReturn('userValue');
        $oFcPayOnePaymentView->method('_fcpoGetRequestedValue')->willReturn($aMockRequestedValues);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('requestedValue');
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoSaveUserData', [$sMockPaymentId, $sMockDbFieldName]));
    }

    public function testFcpoGetRequestedValue() {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $sMockPaymentId = 'fcpopo_debitnote';
        $sMockDbFieldName = 'someDBField';
        $aMockRequestedValues['fcpo_payolution_debitnote_'.$sMockDbFieldName] = 'someValue';

        $oFcPayOnePaymentView->_aFcRequestedValues = $aMockRequestedValues;

        $this->assertEquals('someValue', $this->invokeMethod($oFcPayOnePaymentView, '_fcpoGetRequestedValue', [$sMockPaymentId,$sMockDbFieldName]));
    }

    public function testFcpoSaveBirthdayData_BirthdayRequired()
    {
        $aMockRequestedValues = array('some', 'data');
        $sMockPaymentId = 'somePaymentId';

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('save')->willReturn(true);

        $aMockBirthdayValidityCheckResult = [
            'blValidBirthdateData' => true,
            'blBirthdayRequired' => true
        ];

        $aMockBirthdate = '1978-12-07';

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoGetUserFromSession', '_fcpoValidateBirthdayData', '_fcpoExtractBirthdateFromRequest'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoGetUserFromSession')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('_fcpoValidateBirthdayData')->willReturn($aMockBirthdayValidityCheckResult);
        $oFcPayOnePaymentView->method('_fcpoExtractBirthdateFromRequest')->willReturn($aMockBirthdate);

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoSaveBirthdayData', [$aMockRequestedValues, $sMockPaymentId]));
    }

    public function testFcpoSaveBirthdayData_InvalidBirthday()
    {
        $aMockRequestedValues = array('some', 'data');
        $sMockPaymentId = 'somePaymentId';

        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['save'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('save')->willReturn(true);

        $aMockBirthdayValidityCheckResult = [
            'blValidBirthdateData' => false,
            'blBirthdayRequired' => true
        ];

        $aMockBirthdate = '1978-12-07';

        $oFcPayOnePaymentView = $this->getMockBuilder(FcPayOnePaymentView::class)
            ->setMethods(['_fcpoGetUserFromSession', '_fcpoValidateBirthdayData', '_fcpoExtractBirthdateFromRequest'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePaymentView->method('_fcpoGetUserFromSession')->willReturn($oMockUser);
        $oFcPayOnePaymentView->method('_fcpoValidateBirthdayData')->willReturn($aMockBirthdayValidityCheckResult);
        $oFcPayOnePaymentView->method('_fcpoExtractBirthdateFromRequest')->willReturn($aMockBirthdate);

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOnePaymentView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $this->invokeMethod($oFcPayOnePaymentView, '_fcpoSaveBirthdayData', [$aMockRequestedValues, $sMockPaymentId]));

    }

    public function testFcpoExtractBirthdateFromRequest_Payolution() {
        $sMockPaymentId = 'fcpopo_debitnote';
         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array (
            '_fcpoGetRequestedValues'
        ));

        $aMockRequestValues = array(
            'fcpo_payolution_debitnote_birthdate_year'=>'1978',
            'fcpo_payolution_debitnote_birthdate_month'=>'12',
            'fcpo_payolution_debitnote_birthdate_day'=>'07',
        );

        $sExpect = "1978-12-07";

        $this->assertEquals($sExpect, $oFcPayOnePaymentView->_fcpoExtractBirthdateFromRequest($aMockRequestValues, $sMockPaymentId));
    }

    public function testFcpoValidateBirthdayData_Payolution() {
         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array(
            'fcpoShowB2C',
            '_fcpoValidatePayolutionBirthdayData'
        ));
         $oFcPayOnePaymentView
            ->expects($this->any())
            ->method('fcpoShowB2C')
            ->will($this->returnValue(true));
         $oFcPayOnePaymentView
            ->expects($this->any())
            ->method('_fcpoValidatePayolutionBirthdayData')
            ->will($this->returnValue(true));

        $aMockRequestValues = array(
            'fcpo_payolution_debitnote_birthdate_year'=>'1978',
            'fcpo_payolution_debitnote_birthdate_month'=>'12',
            'fcpo_payolution_debitnote_birthdate_day'=>'07',
        );

        $sMockPaymentId = 'fcpopo_installment';
        $aExpect = array(
            'blValidBirthdateData' => true,
            'blBirthdayRequired' => true
        );

        $this->assertEquals($aExpect, $oFcPayOnePaymentView->_fcpoValidateBirthdayData($sMockPaymentId, $aMockRequestValues));
    }

    public function testFcpoPerformInstallmentCalculation()
    {
         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('_fcpoPerformInstallmentCalculation'));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPerformInstallmentCalculation')->will($this->returnValue(null));

        $this->assertEquals(null, $oFcPayOnePaymentView->fcpoPerformInstallmentCalculation());
    }

    public function testFcpoPerformInstallmentCalculation_Valid()
    {
        $aMockResponse = array('status'=>'OK','workorderid'=>'someId');
        $aMockBankData = array(
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        );

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestPayolutionInstallment'));
        $oMockRequest->expects($this->any())->method('sendRequestPayolutionInstallment')->will($this->returnValue($aMockResponse));

         $oFcPayOnePaymentView = $this->getMock(
            'fcPayOnePaymentView',
            array(
                'getUser',
                '_fcpoGetPayolutionBankData',
                '_fcpoSetInstallmentOptionsByResponse',
            )
        );
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue(true));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoSetInstallmentOptionsByResponse')->will($this->returnValue(null));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoSetSessionVariable')->will($this->returnValue(true));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoPerformInstallmentCalculation('somePaymentId'));
    }

    public function testFcpoPerformInstallmentCalculation_Error()
    {
        $aMockResponse = array('status'=>'ERROR','workorderid'=>'someId');
        $aMockBankData = array(
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        );

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestPayolutionInstallment'));
        $oMockRequest->expects($this->any())->method('sendRequestPayolutionInstallment')->will($this->returnValue($aMockResponse));

         $oFcPayOnePaymentView = $this->getMock(
            'fcPayOnePaymentView',
            array(
                'getUser',
                '_fcpoGetPayolutionBankData',
                '_fcpoSetInstallmentOptionsByResponse',
            )
        );
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue(true));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoSetInstallmentOptionsByResponse')->will($this->returnValue(null));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoSetSessionVariable')->will($this->returnValue(true));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(false, $oFcPayOnePaymentView->_fcpoPerformInstallmentCalculation('somePaymentId'));
    }

    public function testFcpoSetInstallmentOptionsByResponse()
    {
        $aMockResponse = array(
            'add_paydata[PaymentDetails_1_Duration' => 'someValue',
            'add_paydata[PaymentDetails_1_Currency' => 'someValue',
            'add_paydata[PaymentDetails_1_StandardCreditInformationUrl' => 'someValue',
            'add_paydata[PaymentDetails_1_Usage' => 'someValue',
            'add_paydata[PaymentDetails_1_EffectiveInterestRate' => 'someValue',
            'add_paydata[PaymentDetails_1_InterestRate' => 'someValue',
            'add_paydata[PaymentDetails_1_OriginalAmount' => 'someValue',
            'add_paydata[PaymentDetails_1_TotalAmount' => 'someValue',
            'add_paydata[PaymentDetails_1_MinimumInstallmentFee' => 'someValue',
            'add_paydata[PaymentDetails_1_Installment_1_Amount]' => '120',
            'add_paydata[PaymentDetails_1_Installment_2_Amount]' => '120',
            'add_paydata[PaymentDetails_1_Installment_3_Amount]' => '120',
        );

        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals(null, $oFcPayOnePaymentView->_fcpoSetInstallmentOptionsByResponse($aMockResponse));
    }

    public function testFcpoPerformPayolutionPreCheck_PrecheckNeededValid()
    {
        $aMockResponse = array('status'=>'OK','workorderid'=>'someId');
        $aMockBankData = array(
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        );

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestPayolutionPreCheck'));
        $oMockRequest->expects($this->any())->method('sendRequestPayolutionPreCheck')->will($this->returnValue($aMockResponse));

        $oMockUser = $this->getMock('oxUser', array('save'));
        $oMockUser->expects($this->any())->method('save')->will($this->returnValue(true));

        $oMockBasket = $this->getMock('oxBasket', array('getBasketUser'));
        $oMockBasket->expects($this->any())->method('getBasketUser')->will($this->returnValue($oMockUser));

        $oMockSession = $this->getMock('oxSession', array('getBasket'));
        $oMockSession->expects($this->any())->method('getBasket')->will($this->returnValue($oMockBasket));

         $oFcPayOnePaymentView = $this->getMock(
            'fcPayOnePaymentView',
            array(
                '_fcpoCheckIfPrecheckNeeded',
                'getUser',
                'getSession',
                '_fcpoGetPayolutionBankData',
                '_fcpoGetPayolutionSelectedInstallmentIndex',
                '_fcpoPerformInstallmentCalculation',
                '_fcpoPayolutionFetchDuration',
            )
        );
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoCheckIfPrecheckNeeded')->will($this->returnValue(true));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue(false));
         $oFcPayOnePaymentView->expects($this->any())->method('getSession')->will($this->returnValue($oMockSession));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionSelectedInstallmentIndex')->will($this->returnValue('someIndex'));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPerformInstallmentCalculation')->will($this->returnValue(true));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPayolutionFetchDuration')->will($this->returnValue('3'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoSetSessionVariable')->will($this->returnValue(true));
        $oHelper->expects($this->any())->method('fcpoGetSessionVariable')->will($this->returnValue('someWorkorderId'));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoPerformPayolutionPreCheck('somePaymentId'));
    }

    public function testFcpoPerformPayolutionPreCheck_PrecheckNeededInvalid()
    {
        $aMockResponse = array('status'=>'ERROR','workorderid'=>'someId');
        $aMockBankData = array(
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        );

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestPayolutionPreCheck'));
        $oMockRequest->expects($this->any())->method('sendRequestPayolutionPreCheck')->will($this->returnValue($aMockResponse));

        $oMockUser = $this->getMock('oxUser', array('save'));
        $oMockUser->expects($this->any())->method('save')->will($this->returnValue(true));

        $oMockBasket = $this->getMock('oxBasket', array('getBasketUser'));
        $oMockBasket->expects($this->any())->method('getBasketUser')->will($this->returnValue($oMockUser));

        $oMockSession = $this->getMock('oxSession', array('getBasket'));
        $oMockSession->expects($this->any())->method('getBasket')->will($this->returnValue($oMockBasket));

         $oFcPayOnePaymentView = $this->getMock(
            'fcPayOnePaymentView',
            array(
                '_fcpoCheckIfPrecheckNeeded',
                'getUser',
                'getSession',
                '_fcpoGetPayolutionBankData',
                '_fcpoGetPayolutionSelectedInstallmentIndex',
                '_fcpoPerformInstallmentCalculation',
                '_fcpoPayolutionFetchDuration',
            )
        );
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoCheckIfPrecheckNeeded')->will($this->returnValue(true));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue(false));
         $oFcPayOnePaymentView->expects($this->any())->method('getSession')->will($this->returnValue($oMockSession));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionSelectedInstallmentIndex')->will($this->returnValue('someIndex'));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPerformInstallmentCalculation')->will($this->returnValue(true));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPayolutionFetchDuration')->will($this->returnValue('3'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoSetSessionVariable')->will($this->returnValue(true));
        $oHelper->expects($this->any())->method('fcpoGetSessionVariable')->will($this->returnValue('someWorkorderId'));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(false, $oFcPayOnePaymentView->_fcpoPerformPayolutionPreCheck('somePaymentId'));
    }

    public function testFcpoPerformPayolutionPreCheck_PrecheckNotNeededInvalid()
    {
        $aMockResponse = array('status'=>'ERROR','workorderid'=>'someId');
        $aMockBankData = array(
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        );

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestPayolutionPreCheck'));
        $oMockRequest->expects($this->any())->method('sendRequestPayolutionPreCheck')->will($this->returnValue($aMockResponse));

        $oMockUser = $this->getMock('oxUser', array('save'));
        $oMockUser->expects($this->any())->method('save')->will($this->returnValue(true));

        $oMockBasket = $this->getMock('oxBasket', array('getBasketUser'));
        $oMockBasket->expects($this->any())->method('getBasketUser')->will($this->returnValue($oMockUser));

        $oMockSession = $this->getMock('oxSession', array('getBasket'));
        $oMockSession->expects($this->any())->method('getBasket')->will($this->returnValue($oMockBasket));

         $oFcPayOnePaymentView = $this->getMock(
            'fcPayOnePaymentView',
            array(
                '_fcpoCheckIfPrecheckNeeded',
                'getUser',
                'getSession',
                '_fcpoGetPayolutionBankData',
                '_fcpoGetPayolutionSelectedInstallmentIndex',
                '_fcpoPerformInstallmentCalculation',
                '_fcpoPayolutionFetchDuration',
            )
        );
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoCheckIfPrecheckNeeded')->will($this->returnValue(false));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue(false));
         $oFcPayOnePaymentView->expects($this->any())->method('getSession')->will($this->returnValue($oMockSession));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionSelectedInstallmentIndex')->will($this->returnValue('someIndex'));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPerformInstallmentCalculation')->will($this->returnValue(false));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPayolutionFetchDuration')->will($this->returnValue('3'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoSetSessionVariable')->will($this->returnValue(true));
        $oHelper->expects($this->any())->method('fcpoGetSessionVariable')->will($this->returnValue('someWorkorderId'));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(false, $oFcPayOnePaymentView->_fcpoPerformPayolutionPreCheck('somePaymentId'));
    }

    public function testFcpoPerformPayolutionPreCheck_PrecheckNotNeededValid()
    {
        $aMockResponse = array('status'=>'ERROR','workorderid'=>'someId');
        $aMockBankData = array(
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        );

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestPayolutionPreCheck'));
        $oMockRequest->expects($this->any())->method('sendRequestPayolutionPreCheck')->will($this->returnValue($aMockResponse));

        $oMockUser = $this->getMock('oxUser', array('save'));
        $oMockUser->expects($this->any())->method('save')->will($this->returnValue(true));

        $oMockBasket = $this->getMock('oxBasket', array('getBasketUser'));
        $oMockBasket->expects($this->any())->method('getBasketUser')->will($this->returnValue($oMockUser));

        $oMockSession = $this->getMock('oxSession', array('getBasket'));
        $oMockSession->expects($this->any())->method('getBasket')->will($this->returnValue($oMockBasket));

         $oFcPayOnePaymentView = $this->getMock(
            'fcPayOnePaymentView',
            array(
                '_fcpoCheckIfPrecheckNeeded',
                'getUser',
                'getSession',
                '_fcpoGetPayolutionBankData',
                '_fcpoGetPayolutionSelectedInstallmentIndex',
                '_fcpoPerformInstallmentCalculation',
                '_fcpoPayolutionFetchDuration',
            )
        );
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoCheckIfPrecheckNeeded')->will($this->returnValue(false));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue(false));
         $oFcPayOnePaymentView->expects($this->any())->method('getSession')->will($this->returnValue($oMockSession));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionSelectedInstallmentIndex')->will($this->returnValue('someIndex'));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPerformInstallmentCalculation')->will($this->returnValue(true));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoPayolutionFetchDuration')->will($this->returnValue('3'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoSetSessionVariable')->will($this->returnValue(true));
        $oHelper->expects($this->any())->method('fcpoGetSessionVariable')->will($this->returnValue('someWorkorderId'));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoPerformPayolutionPreCheck('somePaymentId'));
    }

    public function testFcpoCheckIfPrecheckNeeded()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_blIsPayolutionInstallmentAjax', false);

        $this->assertEquals(false, $oFcPayOnePaymentView->_fcpoCheckIfPrecheckNeeded('fcpopo_installment'));
    }

    public function testFcpoPayolutionFetchDuration()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $aMockInstallmentCalculation = array(
            'someIndex' => array('Duration'=>'someDuration'),
        );
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_aInstallmentCalculation', $aMockInstallmentCalculation);

        $this->assertEquals('someDuration', $oFcPayOnePaymentView->_fcpoPayolutionFetchDuration('someIndex'));
    }

    public function testFcpoIsPayolution_IsPayolutionDebit()
    {
        $sMockPaymentId = 'fcpopo_debitnote';
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoIsPayolution($sMockPaymentId));
    }

    public function testFcpoPerformPayolutionPreCheck_Error()
    {
        $aMockBankData = array(
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        );

        $aMockResponse = array('status'=>'ERROR','workorderid'=>'someId');

        $oMockUser = $this->getMock('oxUser', array('save'));
        $oMockUser->expects($this->any())->method('save')->will($this->returnValue(true));
        $oMockUser->oxuser__oxbirthdate = new oxField('1977-12-08', oxField::T_RAW);
        $oMockUser->oxuser__oxustid = new oxField('someUstid', oxField::T_RAW);

         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('getUser','_fcpoGetPayolutionBankData'));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue($oMockUser));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestPayolutionPreCheck'));
        $oMockRequest->expects($this->any())->method('sendRequestPayolutionPreCheck')->will($this->returnValue($aMockResponse));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoSetSessionVariable')->will($this->returnValue(true));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(false, $oFcPayOnePaymentView->_fcpoPerformPayolutionPreCheck('someId'));
    }

    public function testFcpoPerformPayolutionPreCheck_OK()
    {
        $aMockBankData = array(
            'fcpo_payolution_accountholder' => 'Some Person',
            'fcpo_payolution_iban' => 'DE12500105170648489890',
            'fcpo_payolution_bic' => 'BELADEBEXXX',
        );

        $aMockResponse = array('status'=>'OK','workorderid'=>'someId');

        $oMockUser = $this->getMock('oxUser', array('save'));
        $oMockUser->expects($this->any())->method('save')->will($this->returnValue(true));
        $oMockUser->oxuser__oxbirthdate = new oxField('1977-12-08', oxField::T_RAW);
        $oMockUser->oxuser__oxustid = new oxField('someUstid', oxField::T_RAW);

         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('getUser','_fcpoGetPayolutionBankData'));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue($oMockUser));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoGetPayolutionBankData')->will($this->returnValue($aMockBankData));

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestPayolutionPreCheck'));
        $oMockRequest->expects($this->any())->method('sendRequestPayolutionPreCheck')->will($this->returnValue($aMockResponse));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoSetSessionVariable')->will($this->returnValue(true));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->_fcpoPerformPayolutionPreCheck('someId'));
    }

    public function testFcpoSetMandateParams()
    {
        $oMockPayment = $this->getMock('oxPayment', array('getId', 'fcpoGetOperationMode'));
        $oMockPayment->expects($this->any())->method('getId')->will($this->returnValue('fcpodebitnote'));
        $oMockPayment->expects($this->any())->method('fcpoGetOperationMode')->will($this->returnValue('test'));

        $oMockRequest = $this->getMock('fcporequest', array('sendRequestManagemandate'));
        $oMockRequest->expects($this->any())->method('sendRequestManagemandate')->will($this->returnValue(true));

        $oMockConfig = $this->getMock('oxConfig', array('getConfigParam'));
        $oMockConfig->expects($this->any())->method('getConfigParam')->will($this->returnValue('someParam'));

         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('_fcpoHandleMandateResponse'));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoHandleMandateResponse')->will($this->returnValue(true));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockRequest));
        $oHelper->expects($this->any())->method('fcpoGetConfig')->will($this->returnValue($oMockConfig));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(null, $oFcPayOnePaymentView->_fcpoSetMandateParams($oMockPayment));
    }

    public function testFcpoHandleMandateResponse_Error()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $aMockResponse['status'] = 'ERROR';

        $this->assertEquals(null, $oFcPayOnePaymentView->_fcpoHandleMandateResponse($aMockResponse));
    }

    public function testFcpoHandleMandateResponse_Ok()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $aMockResponse['status'] = 'OK';
        $aMockResponse['mandate_status'] = 'someMandateStatus';

        $this->assertEquals(null, $oFcPayOnePaymentView->_fcpoHandleMandateResponse($aMockResponse));
    }

    public function testFcpoCheckKlarnaUpdateUser()
    {
         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('getUser', '_fcpoKlarnaUpdateUser'));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue(true));
         $oFcPayOnePaymentView->expects($this->any())->method('_fcpoKlarnaUpdateUser')->will($this->returnValue(null));

        $this->assertEquals(null, $oFcPayOnePaymentView->_fcpoCheckKlarnaUpdateUser('fcpoklarna'));
    }

    public function testFcpoGetDynValues()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetRequestParameter')->will($this->returnValue(false));
        $oHelper->expects($this->any())->method('fcpoGetSessionVariable')->will($this->returnValue(array('someValue')));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(array('someValue'), $oFcPayOnePaymentView->_fcpoGetDynValues());
    }

    public function testFcpoShowB2B_B2BModeActive()
    {
        $oMockConfig = $this->getMock('oxConfig', array('getConfigParam'));
        $oMockConfig->expects($this->any())->method('getConfigParam')->will($this->returnValue(true));
        $oMockUser = oxNew('oxUser');
        $oMockUser->oxuser__oxcompany = new oxField('someCompany', oxField::T_RAW);

         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('getUser'));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue($oMockUser));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetConfig')->will($this->returnValue($oMockConfig));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(true, $oFcPayOnePaymentView->fcpoShowPayolutionB2B());
    }

    public function testFcpoShowB2B_B2BModeInActive()
    {
        $oMockConfig = $this->getMock('oxConfig', array('getConfigParam'));
        $oMockConfig->expects($this->any())->method('getConfigParam')->will($this->returnValue(false));
        $oMockUser = oxNew('oxUser');
        $oMockUser->oxuser__oxcompany = new oxField('someCompany', oxField::T_RAW);

         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('getUser'));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue($oMockUser));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetConfig')->will($this->returnValue($oMockConfig));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals(false, $oFcPayOnePaymentView->fcpoShowPayolutionB2B());
    }

    public function testFcpoShowB2C()
    {
         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('fcpoShowPayolutionB2B'));
         $oFcPayOnePaymentView->expects($this->any())->method('fcpoShowPayolutionB2B')->will($this->returnValue(true));

        $this->assertEquals(false, $oFcPayOnePaymentView->fcpoShowPayolutionB2C());
    }

    public function testFcpoPayolutionBillTelephoneRequired() {
         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array(
            'fcpoGetTargetCountry',
            'fcpoGetUserValue',
        ));
         $oFcPayOnePaymentView
            ->expects($this->any())
            ->method('fcpoGetTargetCountry')
            ->will($this->returnValue('someCountry'));
         $oFcPayOnePaymentView
            ->expects($this->any())
            ->method('fcpoGetUserValue')
            ->will($this->returnValue(''));

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_aPayolutionBillMandatoryTelephoneCountries', array('someOtherCountry'));

        $this->assertEquals(false, $oFcPayOnePaymentView->fcpoPayolutionBillTelephoneRequired());
    }

    public function testFcpoGetBirthdayField()
    {
         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('fcpoGetUserValue'));
         $oFcPayOnePaymentView->expects($this->any())->method('fcpoGetUserValue')->will($this->returnValue('1978-12-07'));

        $this->assertEquals('1978', $oFcPayOnePaymentView->fcpoGetBirthdayField('year'));
    }

    public function testFcpoGetUserValue()
    {
        $oMockUser = oxNew('oxUser');
        $oMockUser->oxuser__oxbirthdate = new oxField('1978-12-07', oxField::T_RAW);

         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('getUser'));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue($oMockUser));

        $this->assertEquals('1978-12-07', $oFcPayOnePaymentView->fcpoGetUserValue('oxbirthdate'));
    }

    public function testFcpoSetUserValue() {
        $oMockUser = $this->getMock('oxUser', array('save'));
        $oMockUser
            ->expects($this->any())
            ->method('save')
            ->will($this->returnValue(null));
        $oMockUser->oxuser__oxbirthdate = new oxField('1978-12-07', oxField::T_RAW);

         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array('getUser'));
         $oFcPayOnePaymentView->expects($this->any())->method('getUser')->will($this->returnValue($oMockUser));

        $this->assertEquals(null, $oFcPayOnePaymentView->_fcpoSetUserValue('oxbirthdate', 'someValue'));
    }

    public function testFcpoGetPayolutionAgreementLink()
    {
        $sCompanyName = 'someCompany';

        $oMockLang = $this->getMock('oxLang', array(
            'getLanguageAbbr'
        ));
        $oMockLang
            ->expects($this->any())
            ->method('getLanguageAbbr')
            ->will($this->returnValue('someAbbr'));

        $oMockConfig = $this->getMock('oxConfig', array(
            'getConfigParam'
        ));
        $oMockConfig
            ->expects($this->any())
            ->method('getConfigParam')
            ->will($this->returnValue($sCompanyName));

        $sExpect = 'https://payment.payolution.com/payolution-payment/infoport/dataprivacydeclaration?mId=' . base64_encode($sCompanyName) . '&lang=someAbbr&territory=DE';

         $oFcPayOnePaymentView = $this->getMock('fcPayOnePaymentView', array(
            'fcpoGetTargetCountry',
        ));
         $oFcPayOnePaymentView
            ->expects($this->any())
            ->method('fcpoGetTargetCountry')
            ->will($this->returnValue('de'));

        $oHelper = $this
            ->getMockBuilder('fcpohelper')
            ->disableOriginalConstructor()
            ->getMock();
        $oHelper
            ->expects($this->any())
            ->method('fcpoGetConfig')
            ->will($this->returnValue($oMockConfig));
        $oHelper
            ->expects($this->any())
            ->method('fcpoGetLang')
            ->will($this->returnValue($oMockLang));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->assertEquals($sExpect, $oFcPayOnePaymentView->fcpoGetPayolutionAgreementLink());
    }

    public function testFcpoGetPayolutionSepaAgreementLink()
    {
        $oMockConfig = $this->getMock('oxConfig', array('getShopUrl'));
        $oMockConfig->expects($this->any())->method('getShopUrl')->will($this->returnValue('http://someshop.com'));

        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetConfig')->will($this->returnValue($oMockConfig));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_sPayolutionSepaAgreement', 'http://somesepalink.com/');
        $sExpect = 'http://someshop.com/modules/fc/fcpayone/lib/fcpopopup_content.php?loadurl=http://somesepalink.com/';

        $this->assertEquals($sExpect, $oFcPayOnePaymentView->fcpoGetPayolutionSepaAgreementLink());
    }

    public function testFcpoGetNumericRange()
    {
        $aExpect = array('Bitte wählen...','01','02','03');
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $this->assertEquals($aExpect, $oFcPayOnePaymentView->_fcpoGetNumericRange(1, 3, 2));
    }

    public function testFcpoGetYearRange()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        // I will not prepare a hundred entries array ;-)
        $aExpect = $aRange =  $oFcPayOnePaymentView->fcpoGetYearRange();
        $this->assertEquals($aExpect, $aRange);
    }

    public function testFcpoGetMonthRange()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $aExpect = $aRange =  $oFcPayOnePaymentView->fcpoGetMonthRange();
        $this->assertEquals($aExpect, $aRange);
    }

    public function testFcpoGetDayRange()
    {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $aExpect = $aRange =  $oFcPayOnePaymentView->fcpoGetDayRange();
        $this->assertEquals($aExpect, $aRange);
    }

    public function testFcpoGetUserFromSession() {
        $oFcPayOnePaymentView = new FcPayOnePaymentView();

        $oMockUser = oxNew('oxUser');

        $oMockBasket = $this->getMock('oxBasket', array('getBasketUser'));
        $oMockBasket->expects($this->any())->method('getBasketUser')->will($this->returnValue($oMockUser));

        $oMockSession = $this->getMock('oxSession', array('getBasket'));
        $oMockSession->expects($this->any())->method('getBasket')->will($this->returnValue($oMockBasket));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetSession')->will($this->returnValue($oMockSession));
        $this->invokeSetAttribute( $oFcPayOnePaymentView, '_oFcpoHelper', $oHelper);

        $oExpect = $oMockUser;

        $this->assertEquals($oExpect, $oFcPayOnePaymentView->_fcpoGetUserFromSession());
    }

    public function testFcpoPaymentActive()
    {
        fcpopaymenthelper::destroyInstance();

        $oPaymentHelper = $this->getMockBuilder(fcpopaymenthelper::class)->disableOriginalConstructor()->getMock();
        $oPaymentHelper->method('isPaymentMethodActive')->willReturn(true);

        UtilsObject::setClassInstance(fcpopaymenthelper::class, $oPaymentHelper);

        $oFcPayOnePaymentView = new FcPayOnePaymentView();
        $result =  $oFcPayOnePaymentView->fcpoPaymentActive('test');

        $this->assertTrue($result);

        UtilsObject::resetClassInstances();
        fcpopaymenthelper::destroyInstance();
    }
}
