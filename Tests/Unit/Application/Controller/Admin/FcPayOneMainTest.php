<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneMain;
use Fatchip\PayOne\Application\Model\FcPoRatePay;
use Fatchip\PayOne\FcCheckChecksum;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\UtilsView;

class FcPayOneMainTest extends FcBaseUnitTestCase
{
    public function testRender()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('@fcpayone/admin/fcpayone_main', $oFcPayOneMain->render());
    }

    public function testRender_popup()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('aoc');
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('@fcpayone/admin/popups/fcpayone_popup_main', $oFcPayOneMain->render());
    }

    public function testGetCurrencyIso()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $aMockCurrency = [(object) ['name' => 'someCurrency']];
        $aExpected = ['someCurrency'];

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getCurrencyArray'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getCurrencyArray')->willReturn($aMockCurrency);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aExpected, $oFcPayOneMain->fcpoGetCurrencyIso());
    }

    public function testFcpoGetModuleVersion()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetModuleVersion')->willReturn('someValue');
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someValue', $oFcPayOneMain->fcpoGetModuleVersion());
    }

    public function testFcpoGetConfBools()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $this->invokeSetAttribute($oFcPayOneMain, '_aConfBools', ['someValue']);

        $this->assertEquals(['someValue'], $oFcPayOneMain->fcpoGetConfBools());
    }

    public function testFcpoGetConfStrs()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $this->invokeSetAttribute($oFcPayOneMain, '_aConfStrs', ['someValue']);

        $this->assertEquals(['someValue'], $oFcPayOneMain->fcpoGetConfStrs());
    }

    public function testFcpoGetConfArrs()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $this->invokeSetAttribute($oFcPayOneMain, '_aConfArrs', ['someValue']);

        $this->assertEquals(['someValue'], $oFcPayOneMain->fcpoGetConfArrs());
    }

    public function testFcpoGetCountryList()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $this->invokeSetAttribute($oFcPayOneMain, '_aCountryList', ['someValue']);

        $this->assertEquals(['someValue'], $oFcPayOneMain->fcpoGetCountryList());
    }

    public function testFcpoGetAplCreditCards()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $this->invokeSetAttribute($oFcPayOneMain, '_aAplCreditCardsList', ['someValue']);

        $this->assertEquals(['someValue'], $oFcPayOneMain->fcpoGetAplCreditCards());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSave()
    {
        $oFcPayOneMain = $this->getMockBuilder(FcPayOneMain::class)
            ->setMethods([
                '_fcpoCheckAndAddLogos',
                '_fcpoCheckRequestAmazonPayConfiguration',
                '_handlePayPalExpressLogos',
                'handleApplePayCredentials',
                '_fcpoInsertProfiles',
                '_fcpoCheckAndAddRatePayProfile',
                '_fcpoLoadConfigs',
                '_fcpoValidateData'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneMain->method('_fcpoCheckRequestAmazonPayConfiguration')->willReturn(null);
        $oFcPayOneMain->method('_fcpoValidateData')->willReturn(true);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['saveShopConfVar', 'getShopId'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('saveShopConfVar')->willReturn(true);
        $oMockConfig->method('getShopId')->willReturn('someShopId');

        $aConfVars = [];
        $aConfVars['sFCPOApprovalText'] = 'VarValue';
        $aConfVars['sFCPOAplCertificate'] = 'VarValue';
        $aConfVars['sFCPOAplKey'] = 'VarValue';

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aConfVars);
        $oFcPoHelper->method('fcpoGetFiles')->willReturn([]);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneMain->save();
    }

    public function testFcpoLoadCountryList()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['getTplLanguage'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('getTplLanguage')->willReturn('0');

        $oMockCountryList = new CountryList();
        $oMockCountry = new Country();
        $oMockCountry->oxcountries__oxid = new Field('someId');
        $oMockCountryList->add($oMockCountry);

        $aConfArrs['aFCPODebitCountries'] = ['someId'];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockCountryList);
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneMain, '_aConfArrs', $aConfArrs);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneMain, '_fcpoLoadCountryList'));
    }

    public function testFcpoLoadConfigs()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $this->assertEquals(null, $this->invokeMethod($oFcPayOneMain, '_fcpoLoadConfigs', ['oxbaseshop']));
    }

    public function testFcpoInsertProfiles()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $aMockData = ['some'=>['Data']];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockData);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $oMockRatePay = $this->getMockBuilder(FcPoRatePay::class)
            ->setMethods(['fcpoInsertProfile'])
            ->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoRatePay', $oMockRatePay);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneMain, '_fcpoInsertProfiles'));
    }

    public function testFcpoCheckAndAddRatePayProfile()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $oMockRatePay = $this->getMockBuilder(FcPoRatePay::class)
            ->setMethods(['fcpoAddRatePayProfile'])
            ->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoRatePay', $oMockRatePay);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneMain, '_fcpoCheckAndAddRatePayProfile'));
    }

    public function testFcpoCheckAndAddLogos()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneMain, '_fcpoCheckAndAddLogos'));
    }

    public function testHandlePayPalExpressLogos()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $aLogo_1['active'] = 'eenie';
        $aLogo_1['langid'] = 'meenie';

        $aLogos = [
            '1' => $aLogo_1,
        ];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls($aLogos, 1);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneMain, '_aAdminMessages', ['someMessage']);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneMain, '_handlePayPalExpressLogos'));
    }

    public function testFcpoIsLogoAdded()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $this->assertFalse($oFcPayOneMain->fcpoIsLogoAdded());
    }

    public function testFcpoGetRatePayProfiles()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $aMockData = $aExpect = ['some'=>'Data'];

        $oMockRatePay = $this->getMockBuilder(FcPoRatePay::class)->disableOriginalConstructor()->getMock();
        $oMockRatePay->method('fcpoGetRatePayProfiles')->willReturn($aMockData);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoRatePay', $oMockRatePay);

        $this->assertEquals($aExpect, $oFcPayOneMain->fcpoGetRatepayProfiles());
    }

    public function testFcGetAdminSeparator() {
        $oFcPayOneMain = new FcPayOneMain();

        $sExpect = '&';
        $this->assertEquals($sExpect, $oFcPayOneMain->fcGetAdminSeparator());
    }

    public function testFcpoGetCheckSumResult()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockScript = $this->getMockBuilder(FcCheckChecksum::class)
            ->setMethods(['checkChecksumXml'])
            ->disableOriginalConstructor()->getMock();
        $oMockScript->method('checkChecksumXml')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetInstance')->willReturn($oMockScript);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someValue', $this->invokeMethod($oFcPayOneMain, '_fcpoGetCheckSumResult'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testExport()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockConfigExport = $this->getMockBuilder('fcpoconfigexport')
            ->setMethods(['fcpoExportConfig'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfigExport->method('fcpoExportConfig')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockConfigExport);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneMain->export();
    }

    public function testFcGetLanguages()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oLang = (object) [
            'active' => 1,
            'oxid' => 'someOxid',
            'name' => 'someName'
        ];
        $aLanguages[] = $oLang;

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['getLanguageArray'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('getLanguageArray')->willReturn($aLanguages);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $aExpect[$oLang->oxid] = $oLang->name;

        $this->assertEquals($aExpect, $this->invokeMethod($oFcPayOneMain, 'fcGetLanguages'));
    }

    public function testFcGetCurrencies()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oCurrency = (object)  ['name' => 'someName'];
        $aCurrencies[] = $oCurrency;

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getCurrencyArray'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getCurrencyArray')->willReturn($aCurrencies);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $aExpect[$oCurrency->name] = $oCurrency->name;

        $this->assertEquals($aExpect, $this->invokeMethod($oFcPayOneMain, 'fcGetCurrencies'));
    }

    public function testFcpoGetPayPalLogos()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $this->_fcpoPreparePaypalExpressLogos();
        $aResponse = $aExpect = $this->invokeMethod($oFcPayOneMain, 'fcpoGetPayPalLogos');
        $this->assertEquals($aExpect, $aResponse);
        $this->_fcpoTruncateTable('fcpopayoneexpresslogos');
    }

    public function testGetCCFields()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $aExpect = [
            'Number',
            'CVC',
            'Month',
            'Year',
        ];

        $this->assertEquals($aExpect, $oFcPayOneMain->getCCFields());
    }

    public function testGetCCTypes()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $aResponse = $aExpect = $this->invokeMethod($oFcPayOneMain, 'getCCTypes', ['Month']);
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testGetCCStyles()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $aResponse = $aExpect = $this->invokeMethod($oFcPayOneMain, 'getCCStyles');
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testGetConfigParam()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $sExpect = 'someValue';

        $sResponse = $this->invokeMethod($oFcPayOneMain, 'getConfigParam', ['initValue']);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetJsCardPreviewCode()
    {
        $oFcPayOneMain = new FcPayOneMain();
        $aResponse = $aExpect = $this->invokeMethod($oFcPayOneMain, 'fcpoGetJsCardPreviewCode');
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetJsPreviewCodeErrorBlock()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $this->invokeMethod($oFcPayOneMain, '_fcpoGetJsPreviewCodeErrorBlock');
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetJsPreviewCodeDefaultStyle()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $this->invokeMethod($oFcPayOneMain, '_fcpoGetJsPreviewCodeDefaultStyle');
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetJsPreviewCodeFields()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $this->invokeMethod($oFcPayOneMain, '_fcpoGetJsPreviewCodeFields');
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcGetJsPreviewCodeValue()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $aTestSetups = [
            ['params' => ['selector', 'someConfVal', true, true], 'expect' => 'someConfVal'],
            ['params' => ['style', 'someConfVal', true, true], 'expect' => 'someValue'],
            ['params' => ['width', 'someConfVal', true, true], 'expect' => 'someValue'],
            ['params' => ['someValue', 'someConfVal', false, false], 'expect' => 'someValue']
        ];

        foreach ($aTestSetups as $aCurrentTestSetup) {
            $aResponse = $this->invokeMethod($oFcPayOneMain, '_fcGetJsPreviewCodeValue', $aCurrentTestSetup['params']);
            $this->assertEquals($aCurrentTestSetup['expect'], $aResponse);
        }
    }

    public function testFcpoSetDefault()
    {
        $oFcPayOneMain = new FcPayOneMain();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['saveShopConfVar', 'getShopConfVar'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('saveShopConfVar')->willReturn(null);
        $oMockConfig->method('getShopConfVar')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneMain, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someValue', $this->invokeMethod($oFcPayOneMain, '_fcpoSetDefault', [['some'=>'Data'], 'any', 'value']));
    }

    protected function _fcpoPreparePaypalExpressLogos()
    {
        $this->_fcpoTruncateTable('fcpopayoneexpresslogos');
        $sQuery = "
            INSERT INTO `fcpopayoneexpresslogos` (`OXID`, `FCPO_ACTIVE`, `FCPO_LANGID`, `FCPO_LOGO`, `FCPO_DEFAULT`) VALUES
            (1, 1, 0, 'fc_andre_sw_02_250px.1.png', 1),
            (2, 1, 1, 'btn_xpressCheckout_en.gif', 0)
        ";

        DatabaseProvider::getDb()->execute($sQuery);
    }

    protected function _fcpoAddSamplePayment()
    {
        $this->_fcpoRemoveSamplePayment();
        $sQuery = "
            INSERT INTO `oxpayments` (`OXID`, `OXACTIVE`, `OXDESC`, `OXADDSUM`, `OXADDSUMTYPE`, `OXADDSUMRULES`, `OXFROMAMOUNT`, `OXTOAMOUNT`, `OXVALDESC`, `OXCHECKED`, `OXDESC_1`, `OXVALDESC_1`, `OXDESC_2`, `OXVALDESC_2`, `OXDESC_3`, `OXVALDESC_3`, `OXLONGDESC`, `OXLONGDESC_1`, `OXLONGDESC_2`, `OXLONGDESC_3`, `OXSORT`, `OXTSPAYMENTID`, `OXTIMESTAMP`, `FCPOISPAYONE`, `FCPOAUTHMODE`, `FCPOLIVEMODE`) VALUES
            ('fcpounittest', 1, 'Testzahlart', 0, 'abs', 0, 0, 1000000, '', 0, 'Kreditkarte Channel Frontend', '', '', '', '', '', '', '', '', '', 0, '', '2016-04-27 15:37:25', 1, 'preauthorization', 0);
        ";

        DatabaseProvider::getDb()->execute($sQuery);
    }

    protected function _fcpoAddSampleForwarding()
    {
        $this->_fcpoTruncateTable('fcpostatusforwarding');
        $sQuery = "
            INSERT INTO `fcpostatusforwarding` (`OXID`, `FCPO_PAYONESTATUS`, `FCPO_URL`, `FCPO_TIMEOUT`) VALUES
            (6, 'paid', 'http://paid.sample', 10);
        ";

        DatabaseProvider::getDb()->execute($sQuery);
    }

    protected function _fcpoAddSampleStatusmapping()
    {
        $this->_fcpoTruncateTable('fcpostatusmapping');
        $sQuery = "
            INSERT INTO `fcpostatusmapping` (`OXID`, `FCPO_PAYMENTID`, `FCPO_PAYONESTATUS`, `FCPO_FOLDER`) VALUES
            (1, 'fcpopaypal', 'capture', 'ORDERFOLDER_FINISHED');
        ";

        DatabaseProvider::getDb()->execute($sQuery);
    }

    protected function _fcpoRemoveSamplePayment()
    {
        $sQuery = "
            DELETE FROM oxpayments WHERE OXID = 'fcpounittest'
        ";

        DatabaseProvider::getDb()->execute($sQuery);
    }

    protected function _fcpoTruncateTable($sTableName)
    {
        $sQuery = "DELETE FROM `{$sTableName}` ";

        DatabaseProvider::getDb()->execute($sQuery);
    }
}
