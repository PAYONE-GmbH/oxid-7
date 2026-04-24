<?php

namespace Fatchip\PayOne\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Fatchip\PayOne\Application\Model\FcPoRatePay;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\UtilsObject;

class FcPoRatepayTest extends FcBaseUnitTestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoInsertProfile_Delete()
    {
        $aMockData = ['delete' => 'someValue'];

        $oFcPoRatepay = $this->getMockBuilder(FcPoRatepay::class)
            ->setMethods(['_fcpoUpdateRatePayProfile'])
            ->disableOriginalConstructor()->getMock();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeStatement'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('executeStatement')->willReturn(1);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oFcPoDb);

        $oFcPoRatepay->fcpoInsertProfile('someId', $aMockData);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoInsertProfile_Update()
    {
        $aMockData = ['someIndex' => 'someValue'];

        $oFcPoRatepay = $this->getMockBuilder(FcPoRatepay::class)
            ->setMethods(['_fcpoUpdateRatePayProfile'])
            ->disableOriginalConstructor()->getMock();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeStatement'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('executeStatement')->willReturn(1);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oFcPoDb);

        $oFcPoRatepay->fcpoInsertProfile('someId', $aMockData);
    }

    public function testFcpoGetRatePayProfiles()
    {
        $oFcPoRatepay = new FcPoRatePay();

        $aMockResult = [
            [
                'OXID' => 'someValue',
                'Index1' => 'someValue',
                'Index2' => 'someValue',
                'Index3' => 'someValue',
                'Index4' => 'someValue'
            ]
        ];

        $oMockQueryResult = $this->createMock(Result::class);
        $oMockQueryResult->method('fetchAllAssociative')->willReturn($aMockResult);
        $oMockQueryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->setMethods(['execute', 'select', 'from', 'where', 'setParameter'])
            ->disableOriginalConstructor()->getMock();
        $oMockQueryBuilder->method('execute')->willReturn($oMockQueryResult);
        $oMockQueryBuilder->method('select')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('from')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('where')->willReturn($oMockQueryBuilder);
        $oMockQueryBuilder->method('setParameter')->willReturn($oMockQueryBuilder);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['createQueryBuilder'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('createQueryBuilder')->willReturn($oMockQueryBuilder);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oFcPoDb);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoHelper', $oFcPoHelper);

        $aExpect = [
            'someValue' => [
                'OXID' => 'someValue',
                'Index1' => 'someValue',
                'Index2' => 'someValue',
                'Index3' => 'someValue',
                'Index4' => 'someValue'
            ]
        ];

        $this->assertEquals($aExpect, $oFcPoRatepay->fcpoGetRatePayProfiles('somePaymentId'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoAddRatePayProfile()
    {
        $oFcPoRatepay = new FcPoRatepay();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['executeStatement'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('executeStatement')->willReturn(1);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oFcPoDb);

        $oMockOxUtils = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUId'])
            ->disableOriginalConstructor()->getMock();
        $oMockOxUtils->method('generateUId')->willReturn('someOxid');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockOxUtils);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoRatepay->fcpoAddRatePayProfile();
    }

    public function testFcpoGetProfileData()
    {
        $aMockResult = ['one' => 'value1', 'two' => 'value2', 'three' => 'value3'];
        $aExpect = ['one' => 'value1', 'two' => 'value2', 'three' => 'value3'];

        $oFcPoRatepay = new FcPoRatepay();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetRow', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetRow')->willReturn($aMockResult);
        $oMockDatabase->method('quote')->willReturn(null);

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['fetchAssociative'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('fetchAssociative')->willReturn($aMockResult);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oFcPoDb);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aExpect, $oFcPoRatepay->fcpoGetProfileData('someId'));
    }

    public function testFcpoGetFields()
    {
        $oFcPoRatepay = new FcPoRatepay();

        $aMockResult = ['someValue'];

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['fetchAssociative'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('fetchAssociative')->willReturn($aMockResult);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oFcPoDb);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoHelper', $oFcPoHelper);

        $aExpect = ['someValue'];

        $this->assertEquals($aExpect, $oFcPoRatepay->fcpoGetFields());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdateRatePayProfile()
    {
        $aMockResponse = ['status' => 'OK'];

        $oFcPoRatepay = $this->getMockBuilder(FcPoRatepay::class)
            ->setMethods(['fcpoGetProfileData', '_fcpoUpdateRatePayProfileByResponse'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoRatepay->method('fcpoGetProfileData')->willReturn([]);

        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['sendRequestRatePayProfile'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('sendRequestRatePayProfile')->willReturn($aMockResponse);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockRequest);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeMethod($oFcPoRatepay, '_fcpoUpdateRatePayProfile', ['someId']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdateRatePayProfileByResponse()
    {
        $oFcPoRatepay = new FcPoRatepay();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['quote', 'executeStatement'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('executeStatement')->willReturn(false);
        $oFcPoDb->method('quote')->willReturn(false);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oFcPoDb);

        $this->invokeMethod($oFcPoRatepay, '_fcpoUpdateRatePayProfileByResponse', ['someId', []]);
    }
}