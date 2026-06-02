<?php

namespace Fatchip\PayOne\Tests\Unit;

use Doctrine\DBAL\Connection;
use Fatchip\PayOne\Application\Model\FcPayOnePayment;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;

class FcPayOnePaymentTest extends FcBaseUnitTestCase
{
    public function testFcIsPayOnePaymentType_IsPayone()
    {
        $oFcPayOnePayment = new FcPayOnePayment();
        $this->assertEquals(true, $oFcPayOnePayment->fcIsPayOnePaymentType('fcpoinvoice'));
    }

    public function testFcpoGetOperationMode()
    {
        $oFcPayOnePayment = new FcPayOnePayment();
        $oFcPayOnePayment->oxpayments__fcpolivemode = new Field(true);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePayment, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('live', $oFcPayOnePayment->fcpoGetOperationMode('fcpo_sofort'));
    }

    public function testGetDynValues()
    {
        $aMockDynValues = [
            (object)['name' => 'fcpo_elv_blz', 'value' => '']
        ];

        $oFcPayOnePayment = $this->getMockBuilder(FcPayOnePayment::class)
            ->setMethods(['_fcGetDynValues'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePayment->method('_fcGetDynValues')->willReturn($aMockDynValues);

        $this->assertEquals($aMockDynValues, $oFcPayOnePayment->getDynValues());
    }

    public function testFcpoGetCountryIsoAlphaById()
    {
        $oFcPayOnePayment = new FcPayOnePayment();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['fetchOne'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('fetchOne')->willReturn('someValue');
        $this->invokeSetAttribute($oFcPayOnePayment, '_oFcPoDb', $oFcPoDb);

        $this->assertEquals('someValue', $this->invokeMethod($oFcPayOnePayment, 'fcpoGetCountryIsoAlphaById', ['someCountryId']));
    }

    public function testFcpoGetCountryNameById()
    {
        $oFcPayOnePayment = new FcPayOnePayment();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['fetchOne'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('fetchOne')->willReturn('someName');
        $this->invokeSetAttribute($oFcPayOnePayment, '_oFcPoDb', $oFcPoDb);

        $this->assertEquals('someName', $this->invokeMethod($oFcPayOnePayment, 'fcpoGetCountryNameById', ['someCountryId']));
    }

    public function testFcpoAddMandateToDb()
    {
        $oFcPayOnePayment = new FcPayOnePayment();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['fetchOne'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('fetchOne')->willReturn('someValue');
        $this->invokeSetAttribute($oFcPayOnePayment, '_oFcPoDb', $oFcPoDb);

        $this->assertEquals('someValue', $this->invokeMethod($oFcPayOnePayment, 'fcpoGetCountryNameById', ['someCountryId']));
    }

    public function testFcpoGetUserPaymentId()
    {
        $oFcPayOnePayment = new FcPayOnePayment();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['fetchOne'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('fetchOne')->willReturn('someValue');
        $this->invokeSetAttribute($oFcPayOnePayment, '_oFcPoDb', $oFcPoDb);

        $this->assertEquals('someValue', $this->invokeMethod($oFcPayOnePayment, 'fcpoGetUserPaymentId', ['someUserId', 'somePaymentType']));
    }

    public function testFcGetDynValues()
    {
        $aExpectDynValues = [
            (object)['name' => 'fcpo_elv_blz', 'value' => ''],
            (object)['name' => 'fcpo_elv_ktonr', 'value' => ''],
            (object)['name' => 'fcpo_elv_iban', 'value' => ''],
            (object)['name' => 'fcpo_elv_bic', 'value' => '']
        ];

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPayOnePayment = $this->getMockBuilder(FcPayOnePayment::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePayment->method('getId')->willReturn('fcpodebitnote');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOnePayment, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aExpectDynValues, $this->invokeMethod($oFcPayOnePayment, '_fcGetDynValues', [null]));
    }

    public function testFcpoGetMandateText()
    {
        $oFcPayOnePayment = new FcPayOnePayment();

        $aMockMandate = ['mandate_status' => 'pending', 'mandate_text' => 'someText'];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetSessionVariable')->willReturn($aMockMandate);
        $this->invokeSetAttribute($oFcPayOnePayment, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aMockMandate['mandate_text'], $oFcPayOnePayment->fcpoGetMandateText());
    }

    public function testFcGetCountries()
    {
        $oFcPayOnePayment = new FcPayOnePayment();

        $aMockResult = [['someValue', 'someValue', 'someValue', 'someValue', 'someValue']];

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['fetchAllNumeric'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('fetchAllNumeric')->willReturn($aMockResult);
        $this->invokeSetAttribute($oFcPayOnePayment, '_oFcPoDb', $oFcPoDb);

        $aExpect = ['someValue'];

        $this->assertEquals($aExpect, $this->invokeMethod($oFcPayOnePayment, '_fcGetCountries', ['someId']));
    }

    public function testFcpoGetMode()
    {
        $oFcPayOnePayment = $this->getMockBuilder(FcPayOnePayment::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOnePayment->method('getId')->willReturn('fcpocreditcard');

        $aMockDynValues = ['fcpo_ccmode' => 'someValue', 'fcpo_sotype' => 'someValue'];

        $this->assertEquals('someValue', $oFcPayOnePayment->fcpoGetMode($aMockDynValues));
    }
}