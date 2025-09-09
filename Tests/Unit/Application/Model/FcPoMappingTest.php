<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPoMapping;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\UtilsObject;

class FcPoMappingTest extends FcBaseUnitTestCase
{
    public function testFcpoGetExistingMappings()
    {
        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->disableOriginalConstructor()->getMock();

        $aMockResult = [
            [
                'oxid' => 'someOxid',
                'fcpo_error_code' => 'someErrorCode',
                'fcpo_lang_id' => 'someLangId',
                'fcpo_mapped_message' => 'someMappedMessage'
            ],
        ];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getAll'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getAll')->willReturn($aMockResult);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oMockDatabase);

        $aResponse = $aExpect = $oFcPoMapping->fcpoGetExistingMappings();
        $this->assertEquals($aExpect, $aResponse);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdateMappings()
    {
        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoGetQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoGetQuery')->willReturn('someQuery');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(true);

        $aMockMappings = ['someIndex' => ['someValue']];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoMapping->fcpoUpdateMappings($aMockMappings);
    }

    public function testFcpoGetQuery_Delete()
    {
        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoGetUpdateQuery')->willReturn('someQuery');

        $aMockData = ['delete' => true];
        $sMockOxid = 'someId';
        $sQuotedOxid = DatabaseProvider::getDb()->quote($sMockOxid);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('quote')->willReturn($sQuotedOxid);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoHelper', $oFcPoHelper);

        $sExpect = "DELETE FROM fcpostatusmapping WHERE oxid = $sQuotedOxid";

        $this->assertEquals($sExpect, $this->invokeMethod($oFcPoMapping, '_fcpoGetQuery', [$sMockOxid, $aMockData, 'someType']));
    }

    public function testFcpoGetQuery_Update()
    {
        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoGetUpdateQuery')->willReturn('someValue');

        $aMockData = ['donotdelete' => true];
        $sMockOxid = 'someId';
        $sExpect = "someValue";

        $this->assertEquals($sExpect, $this->invokeMethod($oFcPoMapping, '_fcpoGetQuery', [$sMockOxid, $aMockData, 'someType']));
    }

    public function testFcpoGetUpdateQuery_Insert()
    {
        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoIsValidNewEntry')->willReturn(true);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oMockDatabase);

        $oMockUtilsObject = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUID'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsObject->method('generateUID')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockUtilsObject);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoHelper', $oFcPoHelper);

        $sMockMappingId = 'someMapId';
        $aMockData = [
            'sPaymentType' => 'somePaymentId',
            'sPayoneStatus' => 'someStatus',
            'sShopStatus' => 'someFolder',
            'sErrorCode' => 'someErrorCode',
            'sLangId' => 'someLangId',
            'sMappedMessage' => 'someMessage'
        ];
        $sMockType = 'someType';

        $sResponse = $sExpect = $this->invokeMethod($oFcPoMapping, '_fcpoGetUpdateQuery', [$sMockMappingId, $aMockData, $sMockType]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetUpdateQuery_Update()
    {
        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoIsValidNewEntry')->willReturn(false);

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oMockDatabase);

        $oMockUtilsObject = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUID'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsObject->method('generateUID')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockUtilsObject);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoHelper', $oFcPoHelper);

        $sMockMappingId = 'someMapId';
        $aMockData = [
            'sPaymentType' => 'somePaymentId',
            'sPayoneStatus' => 'someStatus',
            'sShopStatus' => 'someFolder',
            'sErrorCode' => 'someErrorCode',
            'sLangId' => 'someLangId',
            'sMappedMessage' => 'someMessage'
        ];
        $sMockType = 'someType';

        $sResponse = $sExpect = $this->invokeMethod($oFcPoMapping, '_fcpoGetUpdateQuery', [$sMockMappingId, $aMockData, $sMockType]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoIsValidNewEntry()
    {
        $oFcPoMapping = new FcPoMapping();

        $sMockMappingId = 'new';
        $sErrorCode = 'someErrorCode';
        $sLangId = 'someLangId';
        $sMappedMessage = 'someMessage';

        $this->assertEquals(true, $this->invokeMethod($oFcPoMapping, '_fcpoIsValidNewEntry', [$sMockMappingId, $sErrorCode, $sLangId, $sMappedMessage]));
    }
}