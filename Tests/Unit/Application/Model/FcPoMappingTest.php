<?php

namespace Fatchip\PayOne\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
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

        $oMockQueryResult = $this->createMock(Result::class);
        $oMockQueryResult->method('fetchAllAssociative')->willReturn($aMockResult);
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->setMethods(['execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockQueryBuilder->method('execute')->willReturn($oMockQueryResult);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['createQueryBuilder'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('createQueryBuilder')->willReturn($oMockQueryBuilder);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oFcPoDb);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $oFcPoMapping->fcpoGetExistingMappings();
        $this->assertEquals($aExpect, $aResponse);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdateMappings()
    {
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoGetQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoGetQuery')->willReturn($oMockQueryBuilder);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeStatement'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('executeStatement')->willReturn(1);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oFcPoDb);

        $aMockMappings = ['someIndex' => ['someValue']];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoMapping->fcpoUpdateMappings($aMockMappings);
    }

    public function testFcpoGetQuery_Delete()
    {
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoGetUpdateQuery')->willReturn($oMockQueryBuilder);

        $aMockData = ['delete' => true];
        $sMockOxid = 'someId';
        $sQuotedOxid = DatabaseProvider::getDb()->quote($sMockOxid);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['quote'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('quote')->willReturn($sQuotedOxid);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oFcPoDb);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPoMapping, '_fcpoGetQuery', [$sMockOxid, $aMockData, 'someType']);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetQuery_Update()
    {
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoGetUpdateQuery')->willReturn($oMockQueryBuilder);

        $aMockData = ['donotdelete' => true];
        $sMockOxid = 'someId';
        $sExpect = "someValue";

        $sResponse = $sExpect = $this->invokeMethod($oFcPoMapping, '_fcpoGetQuery', [$sMockOxid, $aMockData, 'someType']);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetUpdateQuery_Insert()
    {
        $oFcPoMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMapping->method('_fcpoIsValidNewEntry')->willReturn(true);

        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();
        $oMockQueryBuilder->method('insert')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('values')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('update')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('set')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('where')->willReturn($oMockQueryBuilder);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['createQueryBuilder'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('createQueryBuilder')->willReturn($oMockQueryBuilder);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oFcPoDb);

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

        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();
        $oMockQueryBuilder->method('insert')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('values')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('update')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('set')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('where')->willReturn($oMockQueryBuilder);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['createQueryBuilder'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('createQueryBuilder')->willReturn($oMockQueryBuilder);
        $this->invokeSetAttribute($oFcPoMapping, '_oFcPoDb', $oFcPoDb);

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