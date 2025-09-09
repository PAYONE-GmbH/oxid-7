<?php

namespace Fatchip\PayOne\Tests\Unit;

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

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(null);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oMockDatabase);

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

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(null);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oMockDatabase);

        $oFcPoRatepay->fcpoInsertProfile('someId', $aMockData);
    }

    /**
     * @doesNotPerformAssertions
     */
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

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getAll', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getAll')->willReturn($aMockResult);
        $oMockDatabase->method('quote')->willReturn(null);
        $this->invokeSetAttribute( $oFcPoRatepay, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
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

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(null);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoDb', $oMockDatabase);

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

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoRatepay, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aExpect, $oFcPoRatepay->fcpoGetProfileData('someId'));
    }

    public function testFcpoGetFields()
    {
        $oFcPoRatepay = new FcPoRatepay();

        $aMockResult = ['someValue'];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getRow'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getRow')->willReturn($aMockResult);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
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

        $oFcPoRatepay->_fcpoUpdateRatePayProfile('someId');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdateRatePayProfileByResponse()
    {
        $oFcPoRatepay = new FcPoRatepay();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(null);
        $oMockDatabase->method('quote')->willReturn(null);
        $this->invokeSetAttribute( $oFcPoRatepay, '_oFcPoDb', $oMockDatabase);

        $oFcPoRatepay->_fcpoUpdateRatePayProfileByResponse('someId', []);
    }
}