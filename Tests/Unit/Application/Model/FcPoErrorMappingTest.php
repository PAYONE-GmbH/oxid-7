<?php

namespace Fatchip\PayOne\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Exception;
use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\UtilsObject;

class FcPoErrorMappingTest extends FcBaseUnitTestCase
{
    public function testFcpoGetExistingMappings()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $aMockResult = [
            [
                'oxid' => 'someOxid',
                'fcpo_error_code' => 'someErrorCode',
                'fcpo_lang_id' => 'someLangId',
                'fcpo_mapped_message' => 'someMappedMessage'
            ]
        ];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getAll'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getAll')->willReturn($aMockResult);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $oFcPoErrorMapping->fcpoGetExistingMappings();
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetAvailableErrorCodes_General()
    {
        $aMockData = ['some' => 'Data'];

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoParseXml'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoParseXml')->willReturn($aMockData);

        $this->assertEquals($aMockData, $oFcPoErrorMapping->fcpoGetAvailableErrorCodes());
    }

    public function testFcpoGetAvailableErrorCodes_Iframe()
    {
        $aMockData = ['some' => 'Data'];

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoParseXml'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoParseXml')->willReturn($aMockData);

        $this->assertEquals($aMockData, $oFcPoErrorMapping->fcpoGetAvailableErrorCodes('iframe'));
    }

    public function testFcpoGetAvailableErrorCodes_Exception()
    {
        $this->wrapExpectException(Exception::class);

        $oMockException = new Exception('someErrorMessage');

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoParseXml'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoParseXml')->willThrowException($oMockException);

        $this->assertEquals(Exception::class, $oFcPoErrorMapping->fcpoGetAvailableErrorCodes());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdateMappings()
    {
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoGetQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoGetQuery')->willReturn($oMockQueryBuilder);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeStatement'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('executeStatement')->willReturn(1);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoDb', $oFcPoDb);

        $aMockMappings = ['someIndex' => ['someValue']];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoErrorMapping->fcpoUpdateMappings($aMockMappings, 'someType');
    }

    public function testFcpoFetchMappedErrorMessage()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $oMockQueryResult = $this->createMock(Result::class);
        $oMockQueryResult->method('fetchOne')->willReturn('MappedMessage');
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->setMethods(['execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockQueryBuilder->method('execute')->willReturn($oMockQueryResult);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['createQueryBuilder'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('createQueryBuilder')->willReturn($oMockQueryBuilder);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoDb', $oFcPoDb);

        $oMockUBase = $this->getMockBuilder(FrontendController::class)
            ->setMethods(['getActiveLangAbbr'])
            ->disableOriginalConstructor()->getMock();
        $oMockUBase->method('getActiveLangAbbr')->willReturn('de');

        $aMockLangData = [
            (object)[
                'abbr' => 'de',
                'id' => '1'
            ]
        ];

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['getLanguageArray'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('getLanguageArray')->willReturn($aMockLangData);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockUBase);
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('MappedMessage', $oFcPoErrorMapping->fcpoFetchMappedErrorMessage('someMessage'));
    }

    public function testFcpoParseXml()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $oMockUBase = $this->getMockBuilder(FrontendController::class)
            ->setMethods(['getActiveLangAbbr'])
            ->disableOriginalConstructor()->getMock();
        $oMockUBase->method('getActiveLangAbbr')->willReturn('de');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockUBase);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $oMockXml = (object)[
            'entry' => [
                (object)[
                    'error_code' => 'someErrorCode',
                    'error_message_de' => 'someMessage',
                    'error_message_' => 'someMessage'
                ]
            ]
        ];

        $oMockEntry = (object) [
            'sErrorCode' => 'someErrorCode',
            'sErrorMessage' => 'someMessage'
        ];

        $aExpect = [$oMockEntry];

        $this->assertEquals($aExpect, $this->invokeMethod($oFcPoErrorMapping, '_fcpoParseXml', [$oMockXml]));
    }

    public function testFcpoXml2Array()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $sMockXml = '<root><mocknode><mockvar>mockvalue</mockvar></mocknode></root>';
        $oMockXml = simplexml_load_string($sMockXml);
        $aExpect = ['mocknode' => ['mockvar' => 'mockvalue']];

        $this->assertEquals($aExpect, $this->invokeMethod($oFcPoErrorMapping, '_fcpoXml2Array', [$oMockXml]));
    }

    public function testFcpoGetQuery_Delete()
    {
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoGetUpdateQuery')->willReturn($oMockQueryBuilder);

        $aMockData = ['delete' => true];
        $sMockOxid = 'someId';
        $sQuotedOxid = DatabaseProvider::getDb()->quote($sMockOxid);
        $sMockType = 'someErrorType';

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['quote'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('quote')->willReturn($sQuotedOxid);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoDb', $oFcPoDb);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPoErrorMapping, '_fcpoGetQuery', [$sMockOxid, $aMockData, $sMockType]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetQuery_Update()
    {
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoGetUpdateQuery')->willReturn($oMockQueryBuilder);

        $aMockData = ['donotdelete' => true];
        $sMockOxid = 'someId';
        $sExpect = 'someValue';
        $sMockType = 'someErrorType';

        $sResponse = $sExpect = $this->invokeMethod($oFcPoErrorMapping, '_fcpoGetQuery', [$sMockOxid, $aMockData, $sMockType]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetUpdateQuery_Insert()
    {
        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoIsValidNewEntry')->willReturn(true);

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
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoDb', $oFcPoDb);

        $oMockUtilsObject = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUID'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsObject->method('generateUID')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockUtilsObject);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $sMockMappingId = 'someMapId';
        $aMockData = [
            'sPaymentType' => 'somePaymentId',
            'sPayoneStatus' => 'someStatus',
            'sShopStatus' => 'someFolder',
            'sErrorCode' => 'someErrorCode',
            'sLangId' => 'someLangId',
            'sMappedMessage' => 'someMessage'
        ];
        $sMockType = 'someErrorType';

        $sResponse = $sExpect = $this->invokeMethod($oFcPoErrorMapping, '_fcpoGetUpdateQuery', [$sMockMappingId, $aMockData, $sMockType]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetUpdateQuery_Update()
    {
        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoIsValidNewEntry')->willReturn(false);

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
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoDb', $oFcPoDb);

        $oMockUtilsObject = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUID'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsObject->method('generateUID')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockUtilsObject);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $sMockMappingId = 'someMapId';
        $aMockData = [
            'sPaymentType' => 'somePaymentId',
            'sPayoneStatus' => 'someStatus',
            'sShopStatus' => 'someFolder',
            'sErrorCode' => 'someErrorCode',
            'sLangId' => 'someLangId',
            'sMappedMessage' => 'someMessage'
        ];
        $sMockType = 'someErrorType';

        $sResponse = $sExpect = $this->invokeMethod($oFcPoErrorMapping, '_fcpoGetUpdateQuery', [$sMockMappingId, $aMockData, $sMockType]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetSearchQuery()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $sResponse = $sExpect = $this->invokeMethod($oFcPoErrorMapping, '_fcpoGetSearchQuery', ['someErrorCode', 'someId']);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoIsValidNewEntry()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $sMockMappingId = 'new';
        $sMockPaymentId = 'somePaymentId';
        $sMockPayoneStatus = 'someStatus';
        $sMockFolder = 'someFolder';

        $sResponse = $sExpect = $this->assertEquals(true, $this->invokeMethod($oFcPoErrorMapping, '_fcpoIsValidNewEntry', [$sMockMappingId, $sMockPaymentId, $sMockPayoneStatus, $sMockFolder]));

        $this->assertEquals($sExpect, $sResponse);
    }
}