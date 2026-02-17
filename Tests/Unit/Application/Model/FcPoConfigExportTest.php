<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\DatabaseProvider;

class FcPoConfigExportTest extends FcBaseUnitTestCase
{
    public function testFcpoGetConfig()
    {
        $oFcPoConfigExport = new FcPoConfigExport();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $aMockResult = [
            [
                'oxvarname' => 'someName',
                'oxvartype' => 'someType',
                'oxvarvalue' => 'someValue',
            ]
        ];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getAll', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getAll')->willReturn($aMockResult);
        $oMockDatabase->method('quote')->willReturn('');
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $oFcPoConfigExport->fcpoGetConfig('1');

        $this->assertEquals($aExpect, $aResponse);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoExportConfig()
    {
        $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
            ->setMethods(['fcpoGetConfigXml'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoConfigExport->method('fcpoGetConfigXml')->willReturn('someXml');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoHeader')->willReturn(true);
        $oFcPoHelper->method('fcpoExit')->willReturn(true);
        $oFcPoHelper->method('fcpoProcessResultString')->willReturn('someString');
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoConfigExport->fcpoExportConfig();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoExportConfig_FalseXml()
    {
        $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
            ->setMethods(['fcpoGetConfigXml'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoConfigExport->method('fcpoGetConfigXml')->willReturn('');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoHeader')->willReturn(true);
        $oFcPoHelper->method('fcpoExit')->willReturn(true);
        $oFcPoHelper->method('fcpoProcessResultString')->willReturn('someString');
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoConfigExport->fcpoExportConfig();
    }

    public function testGetChecksumErrors_Valid()
    {
        $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
            ->setMethods(['_fcpoGetCheckSumResult'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoConfigExport->method('_fcpoGetCheckSumResult')->willReturn('correct');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoCheckClassExists')->willReturn(true);
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoHelper', $oFcPoHelper);

        $this->assertFalse($this->invokeMethod($oFcPoConfigExport, '_getChecksumErrors'));
    }

    public function testGetChecksumErrors_Invalid()
    {
        $aResponse = ['unittests are fun', 'next message with some content'];
        $sResponse = json_encode($aResponse);

        $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
            ->setMethods(['_fcpoGetCheckSumResult'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoConfigExport->method('_fcpoGetCheckSumResult')->willReturn($sResponse);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoCheckClassExists')->willReturn(true);
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aResponse, $this->invokeMethod($oFcPoConfigExport, '_getChecksumErrors'));
    }

    public function testGetChecksumErrors_ClassNotExists()
    {
        $aResponse = ['unittests are fun', 'next message with some content'];
        $sResponse = json_encode($aResponse);

        $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
            ->setMethods(['_fcpoGetCheckSumResult'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoConfigExport->method('_fcpoGetCheckSumResult')->willReturn($sResponse);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoCheckClassExists')->willReturn(false);
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPoConfigExport, '_getChecksumErrors'));
    }

    public function testFcpoGetConfigXml()
    {
        $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
            ->setMethods([
                'fcpoGetShopIds',
                '_fcpoGetShopXmlGeneric',
                '_fcpoGetShopXmlSystem',
                '_fcpoGetShopXmlGlobal',
                '_fcpoGetShopXmlClearingTypes',
                '_fcpoGetShopXmlProtect',
                '_fcpoGetShopXmlMisc',
                '_fcpoGetShopXmlChecksums',
            ])
            ->disableOriginalConstructor()->getMock();
        $oFcPoConfigExport->method('_fcpoGetShopXmlGeneric')->willReturn('');
        $oFcPoConfigExport->method('_fcpoGetShopXmlSystem')->willReturn('');
        $oFcPoConfigExport->method('_fcpoGetShopXmlGlobal')->willReturn('');
        $oFcPoConfigExport->method('_fcpoGetShopXmlClearingTypes')->willReturn('');
        $oFcPoConfigExport->method('_fcpoGetShopXmlProtect')->willReturn('');
        $oFcPoConfigExport->method('_fcpoGetShopXmlMisc')->willReturn('');
        $oFcPoConfigExport->method('_fcpoGetShopXmlChecksums')->willReturn('');

        $aShopConfigs = [['someIndex' => 'someValue']];
        $this->invokeSetAttribute($oFcPoConfigExport, '_aShopConfigs', $aShopConfigs);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $oFcPoConfigExport->fcpoGetConfigXml();

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetShopIds()
    {
        $oFcPoConfigExport = new FcPoConfigExport();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getCol'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getCol')->willReturn(['someCol' => 'someValue']);
        $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoDb', $oMockDatabase);

        $this->assertEquals(['someCol' => 'someValue'], $oFcPoConfigExport->fcpoGetShopIds());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoSetShopConfigVars()
    {
        $aShopIds = ['oxbaseshop'];
        $oFcPoConfigExport = new FcPoConfigExport();

        $this->invokeMethod($oFcPoConfigExport, '_fcpoSetShopConfigVars', [$aShopIds]);
    }

    public function testFcpoGetShopXmlGeneric()
    {
        $aMockShopConfVars['sShopName'] = 'someShopName';
        $oFcPoConfigExport = new FcPoConfigExport();

        $sResponse = $sExpect = $this->invokeMethod($oFcPoConfigExport, '_fcpoGetShopXmlGeneric', [$aMockShopConfVars]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetShopXmlSystem()
    {
        $aMockModuleInfo = ['someIndex' => 'someInfo'];

        $aMockShopConfVars = [
            'sShopEdition' => 'someEdition',
            'sShopVersion' => 'someVersion'
        ];

        $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
            ->setMethods(['_getModuleInfo'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoConfigExport->method('_getModuleInfo')->willReturn($aMockModuleInfo);

        $sResponse = $sExpect = $this->invokeMethod($oFcPoConfigExport, '_fcpoGetShopXmlSystem', [$aMockShopConfVars]);

        $this->assertEquals($sResponse, $sExpect);
    }

    public function testFcpoGetShopXmlGlobal()
    {
        $aMockMappings = [
            'someAbbr' => [
                [
                    'someSubtype' => [
                        'from' => 'someFrom',
                        'to' => 'someTo',
                        'name' => 'someName'
                    ]
                ]
            ]
        ];

        $aMockShopConfVars = [
            'sFCPOMerchantID' => 'someEdition',
            'sFCPOSubAccountID' => 'someVersion',
            'sFCPOPortalID' => 'someVersion',
            'sFCPORefPrefix' => 'someVersion'
        ];

        $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
            ->setMethods(['_getMappings'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoConfigExport->method('_getMappings')->willReturn($aMockMappings);

        $sResponse = $sExpect = $this->invokeMethod($oFcPoConfigExport, '_fcpoGetShopXmlGlobal', [$aMockShopConfVars]);

        $this->assertEquals($sResponse, $sExpect);
    }

    public function testFcpoGetShopXmlClearingTypes()
    {
        $this->_fcpoAddSampleStatusmapping();
        $oFcPoConfigExport = new FcPoConfigExport();
        $aShopConfVars = [
            'sFCPOMerchantID' => '1',
            'sFCPOSubAccountID' => '2',
            'sFCPOPortalID' => '3'
        ];

        $aResponse = $this->invokeMethod($oFcPoConfigExport, '_fcpoGetShopXmlClearingTypes', [$aShopConfVars]);
        $this->_fcpoTruncateTable('fcpostatusmapping');

        $this->wrapAssertStringContainsString('<title><![CDATA[PAYONE Kreditkarte]]></title>', $aResponse);
    }

    public function testFcpoGetShopXmlMisc()
    {
        $this->_fcpoAddSampleForwarding();
        $oFcPoConfigExport = new FcPoConfigExport();
        $aResponse = $aExpect = $this->invokeMethod($oFcPoConfigExport, '_fcpoGetShopXmlMisc');
        $this->assertEquals($aExpect, $aResponse);
        $this->_fcpoTruncateTable('fcpostatusforwarding');
    }

    public function testFcpoGetShopXmlChecksums()
    {
        $aTestSetups = [
            [
                'return_getChecksumErrors' => false,
                'returnfcpoIniGet' => '0',
                'returnfcpoFunctionExists' => false
            ],
            [
                'return_getChecksumErrors' => ['someError'],
                'returnfcpoIniGet' => '1',
                'returnfcpoFunctionExists' => false
            ],
            [
                'return_getChecksumErrors' => ['someError'],
                'returnfcpoIniGet' => '1',
                'returnfcpoFunctionExists' => true
            ],
            [
                'return_getChecksumErrors' => false,
                'returnfcpoIniGet' => '1',
                'returnfcpoFunctionExists' => true
            ],
        ];

        foreach ($aTestSetups as $aTestSetup) {
            $oFcPoConfigExport = $this->getMockBuilder(FcPoConfigExport::class)
                ->setMethods(['_getChecksumErrors'])
                ->disableOriginalConstructor()->getMock();
            $oFcPoConfigExport->method('_getChecksumErrors')->willReturn($aTestSetup['return_getChecksumErrors']);

            $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
            $oFcPoHelper->method('fcpoIniGet')->willReturn($aTestSetup['returnfcpoIniGet']);
            $oFcPoHelper->method('fcpoFunctionExists')->willReturn($aTestSetup['returnfcpoFunctionExists']);
            $this->invokeSetAttribute($oFcPoConfigExport, '_oFcPoHelper', $oFcPoHelper);

            $aResponse = $aExpect = $this->invokeMethod($oFcPoConfigExport, '_fcpoGetShopXmlChecksums');
            $this->assertEquals($aExpect, $aResponse);
        }
    }

    public function testGetPaymentTypes()
    {
        $oFcPoConfigExport = new FcPoConfigExport();
        $aResponse = $aExpect = $this->invokeMethod($oFcPoConfigExport, '_getPaymentTypes');
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testGetPaymentCountries()
    {
        $oFcPoConfigExport = new FcPoConfigExport();

        $aMockCountries = ['a7c40f631fc920687.20179984'];

        $sCountries = '';
        foreach ($aMockCountries as $sCountryId) {
            $oCountry = new Country();
            if ($oCountry->load($sCountryId)) {
                $sCountries .= $oCountry->oxcountry__oxisoalpha2->value . ',';
            }
        }
        $sExpect = rtrim($sCountries, ',');

        $oMockPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['getCountries'])
            ->disableOriginalConstructor()->getMock();
        $oMockPayment->method('getCountries')->willReturn($aMockCountries);

        $sResponse = $this->invokeMethod($oFcPoConfigExport, '_getPaymentCountries', [$oMockPayment]);
        $this->assertEquals($sExpect, $sResponse);
    }

    public function testGetForwardings()
    {
        $oFcPoConfigExport = new FcPoConfigExport();
        $this->_fcpoAddSampleForwarding();
        $aResponse = $aExpect = $this->invokeMethod($oFcPoConfigExport, '_getForwardings');
        $this->_fcpoTruncateTable('fcpostatusforwarding');
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testGetMappings()
    {
        $oFcPoConfigExport = new FcPoConfigExport();
        $this->_fcpoAddSampleStatusmapping();
        $aResponse = $aExpect = $this->invokeMethod($oFcPoConfigExport, '_getMappings');
        $this->_fcpoTruncateTable('fcpostatusmapping');
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetMultilangConfStrVarName()
    {
        $oFcPoConfigExport = new FcPoConfigExport();

        $this->assertEquals('sFCPOApprovalText', $oFcPoConfigExport->fcpoGetMultilangConfStrVarName('sFCPOApprovalText_0', false));
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

    protected function _fcpoAddSamplePayment()
    {
        $this->_fcpoRemoveSamplePayment();
        $sQuery = "
            INSERT INTO  `oxpayments` (
                `OXID` ,
                `OXACTIVE` ,
                `OXDESC` ,
                `OXADDSUM` ,
                `OXADDSUMTYPE` ,
                `OXADDSUMRULES` ,
                `OXFROMAMOUNT` ,
                `OXTOAMOUNT` ,
                `OXVALDESC` ,
                `OXCHECKED` ,
                `OXDESC_1` ,
                `OXVALDESC_1` ,
                `OXDESC_2` ,
                `OXVALDESC_2` ,
                `OXDESC_3` ,
                `OXVALDESC_3` ,
                `OXLONGDESC` ,
                `OXLONGDESC_1` ,
                `OXLONGDESC_2` ,
                `OXLONGDESC_3` ,
                `OXSORT` ,
                `OXTIMESTAMP` ,
                `FCPOISPAYONE` ,
                `FCPOAUTHMODE` ,
                `FCPOLIVEMODE`
            )
            VALUES (
                'fcpounittest',  '1',  'Testzahlart',  '0',  'abs',  '0',  '0',  '1000000',  'Kreditkarte Channel Frontend',  '0',  '',  '',  '',  '',  '',  '', 'Kreditkarte Channel Frontend',  '',  '',  '',  '0', 
                CURRENT_TIMESTAMP ,  '1',  'preauthorization',  '0'
            )
";

        DatabaseProvider::getDb()->Execute($sQuery);
    }

    protected function _fcpoAddSampleForwarding()
    {
        $this->_fcpoTruncateTable('fcpostatusforwarding');
        $sQuery = "
            INSERT INTO `fcpostatusforwarding` (`OXID`, `FCPO_PAYONESTATUS`, `FCPO_URL`, `FCPO_TIMEOUT`) VALUES
            (6, 'paid', 'https://paid.sample', 10);
        ";

        DatabaseProvider::getDb()->Execute($sQuery);
    }

    protected function _fcpoAddSampleStatusmapping()
    {
        $this->_fcpoTruncateTable('fcpostatusmapping');
        $sQuery = "
            INSERT INTO `fcpostatusmapping` (`OXID`, `FCPO_PAYMENTID`, `FCPO_PAYONESTATUS`, `FCPO_FOLDER`) VALUES
            (1, 'fcpopaypal', 'capture', 'ORDERFOLDER_FINISHED');
        ";

        DatabaseProvider::getDb()->Execute($sQuery);
    }

    protected function _fcpoRemoveSamplePayment()
    {
        $sQuery = "
            DELETE FROM oxpayments WHERE OXID = 'fcpounittest'
        ";

        DatabaseProvider::getDb()->Execute($sQuery);
    }

    protected function _fcpoTruncateTable($sTableName)
    {
        $sQuery = "DELETE FROM `$sTableName` WHERE true ";

        DatabaseProvider::getDb()->Execute($sQuery);
    }
}