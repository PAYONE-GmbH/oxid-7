<?php

namespace Fatchip\PayOne\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Fatchip\PayOne\Application\Model\FcPoForwarding;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\UtilsObject;

class FcPoForwardingTest extends FcBaseUnitTestCase
{
    public function testFcpoGetExistingForwardings()
    {
        $oFcPoForwarding = new FcPoForwarding();

        $aMockResult = [
            [
                'oxid' => 'someOxid',
                'fcpo_payonestatus' => 'someStatus',
                'fcpo_url' => 'someUrl',
                'fcpo_timeout' => 'someTimeout'
            ]
        ];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getAll'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getAll')->willReturn($aMockResult);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $oFcPoForwarding->fcpoGetExistingForwardings();
        $this->assertEquals($aExpect, $aResponse);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdateForwardings()
    {
        $oMockQueryResult = $this->createMock(Result::class);
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->setMethods(['execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockQueryBuilder->method('execute')->willReturn($oMockQueryResult);

        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoGetQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoGetQuery')->willReturn($oMockQueryBuilder);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('Execute')->willReturn(1);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoDb', $oFcPoDb);

        $aMockForwardings = ['someIndex' => ['someValue']];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoForwarding->fcpoUpdateForwardings($aMockForwardings);
    }

    public function testFcpoGetQuery_Delete()
    {
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();
        $oMockQueryBuilder->method('delete')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('where')->willReturn($oMockQueryBuilder);

        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoGetUpdateQuery')->willReturn($oMockQueryBuilder);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['createQueryBuilder'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('createQueryBuilder')->willReturn($oMockQueryBuilder);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoDb', $oFcPoDb);

        $aMockData = [
            'delete' => true,
            'sPayoneStatus' => 'someStatus',
            'sForwardingUrl' => 'someUrl',
            'iForwardingTimeout' => 0
        ];
        $sMockOxid = 'someId';
        $sQuotedOxid = DatabaseProvider::getDb()->quote($sMockOxid);

        $oResponse = $oExpect = $this->invokeMethod($oFcPoForwarding, '_fcpoGetQuery', [$sMockOxid, $aMockData]);

        $this->assertEquals($oExpect, $oResponse);
    }

    public function testFcpoGetQuery_Update()
    {
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoGetUpdateQuery')->willReturn($oMockQueryBuilder);

        $aMockData = [
            'donotdelete' => true,
            'sPayoneStatus' => 'someStatus',
            'sForwardingUrl' => 'someUrl',
            'iForwardingTimeout' => 0
        ];
        $sMockOxid = 'someId';

        $oResponse = $oExpect = $this->invokeMethod($oFcPoForwarding, '_fcpoGetQuery', [$sMockOxid, $aMockData]);

        $this->assertEquals($oExpect, $oResponse);
    }

    public function testFcpoGetUpdateQuery_Insert()
    {
        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoIsValidNewEntry')->willReturn(true);

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
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoDb', $oFcPoDb);

        $oMockUtilsObject = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUID'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsObject->method('generateUID')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockUtilsObject);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoHelper', $oFcPoHelper);

        $sMockForwardingId = 'someForwardingId';
        $sMockPayoneStatus = 'someStatus';
        $sMockUrl = 'someUrl';
        $iMockTimeout = 90;
        $sMockOxid = 'someOxid';

        $oResponse = $oExpect = $this->invokeMethod($oFcPoForwarding, '_fcpoGetUpdateQuery', [$sMockForwardingId, $sMockPayoneStatus, $sMockUrl, $iMockTimeout, $sMockOxid]);

        $this->assertEquals($oExpect, $oResponse);
    }

    public function testFcpoGetUpdateQuery_Update()
    {
        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoIsValidNewEntry')->willReturn(false);

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
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoDb', $oFcPoDb);

        $oMockUtilsObject = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUID'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsObject->method('generateUID')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockUtilsObject);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoHelper', $oFcPoHelper);

        $sMockForwardingId = 'someForwardingId';
        $sMockPayoneStatus = 'someStatus';
        $sMockUrl = 'someUrl';
        $iMockTimeout = 90;
        $sMockOxid = 'someOxid';

        $oResponse = $oExpect = $this->invokeMethod($oFcPoForwarding, '_fcpoGetUpdateQuery', [$sMockForwardingId, $sMockPayoneStatus, $sMockUrl, $iMockTimeout, $sMockOxid]);

        $this->assertEquals($oExpect, $oResponse);
    }

    public function testFcpoIsValidNewEntry()
    {
        $oFcPoForwarding = new FcPoForwarding();

        $sMockForwardingId = 'new';
        $sMockPayoneStatus = 'someStatus';
        $sMockUrl = 'someUrl';
        $iMockTimeout = 90;

        $this->assertEquals(true, $this->invokeMethod($oFcPoForwarding, '_fcpoIsValidNewEntry', [$sMockForwardingId, $sMockPayoneStatus, $sMockUrl, $iMockTimeout]));
    }
}