<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPayOneUser;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Country;

class FcPayOneUserTest extends FcBaseUnitTestCase
{
    public function testFcpoLogMeIn()
    {
        $oFcPayOneUser = $this->getMockBuilder(FcPayOneUser::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneUser->method('getId')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPayOneUser, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneUser, '_fcpoLogMeIn'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoAddDeliveryAddress_NotExists()
    {
        $oFcPayOneUser = $this->getMockBuilder(FcPayOneUser::class)
            ->setMethods(['_fcpoSplitStreetAndStreetNr', '_fcpoGetCountryIdByIso2', '_fcpoCheckAddressExists',])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneUser->method('_fcpoSplitStreetAndStreetNr')->willReturn(['somestreet', '1']);
        $oFcPayOneUser->method('_fcpoGetCountryIdByIso2')->willReturn('DE');
        $oFcPayOneUser->method('_fcpoCheckAddressExists')->willReturn(false);

        $oMockAddress = $this->getMockBuilder(Address::class)
            ->setMethods(['load', 'setId', 'save', 'getEncodedDeliveryAddress',])
            ->disableOriginalConstructor()->getMock();
        $oMockAddress->method('load')->willReturn(null);
        $oMockAddress->method('setId')->willReturn(null);
        $oMockAddress->method('save')->willReturn(null);
        $oMockAddress->method('getEncodedDeliveryAddress')->willReturn('someEncodedAddress');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockAddress);
        $this->invokeSetAttribute($oFcPayOneUser, '_oFcPoHelper', $oFcPoHelper);

        $aMockResponse = [
            'add_paydata[email]' => '',
            'add_paydata[billing_firstname]' => '',
            'add_paydata[billing_lastname]' => '',
            'add_paydata[billing_street]' => '',
            'add_paydata[billing_zip]' => '',
            'add_paydata[billing_city]' => '',
            'add_paydata[billing_country]' => '',
            'add_paydata[billing_telephonenumber]' => '',
            'add_paydata[shipping_firstname]' => '',
            'add_paydata[shipping_lastname]' => '',
            'add_paydata[shipping_street]' => '',
            'add_paydata[shipping_zip]' => '',
            'add_paydata[shipping_city]' => '',
            'add_paydata[shipping_country]' => '',
            'add_paydata[shipping_telephonenumber]' => '',
        ];

        $oFcPayOneUser->_fcpoAddDeliveryAddress($aMockResponse, 'someUserId');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoAddDeliveryAddress()
    {
        $oFcPayOneUser = $this->getMockBuilder(FcPayOneUser::class)
            ->setMethods(['_fcpoSplitStreetAndStreetNr', '_fcpoGetCountryIdByIso2', '_fcpoCheckAddressExists',])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneUser->method('_fcpoSplitStreetAndStreetNr')->willReturn(['somestreet', '1']);
        $oFcPayOneUser->method('_fcpoGetCountryIdByIso2')->willReturn('DE');
        $oFcPayOneUser->method('_fcpoCheckAddressExists')->willReturn(true);

        $oMockAddress = $this->getMockBuilder(Address::class)
            ->setMethods(['load', 'setId', 'save', 'getEncodedDeliveryAddress',])
            ->disableOriginalConstructor()->getMock();
        $oMockAddress->method('load')->willReturn(null);
        $oMockAddress->method('setId')->willReturn(null);
        $oMockAddress->method('save')->willReturn(null);
        $oMockAddress->method('getEncodedDeliveryAddress')->willReturn('someEncodedAddress');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockAddress);
        $this->invokeSetAttribute($oFcPayOneUser, '_oFcPoHelper', $oFcPoHelper);

        $aMockResponse = [
            'add_paydata[email]' => '',
            'add_paydata[billing_firstname]' => '',
            'add_paydata[billing_lastname]' => '',
            'add_paydata[billing_street]' => '',
            'add_paydata[billing_zip]' => '',
            'add_paydata[billing_city]' => '',
            'add_paydata[billing_country]' => '',
            'add_paydata[billing_telephonenumber]' => '',
            'add_paydata[shipping_firstname]' => '',
            'add_paydata[shipping_lastname]' => '',
            'add_paydata[shipping_street]' => '',
            'add_paydata[shipping_zip]' => '',
            'add_paydata[shipping_city]' => '',
            'add_paydata[shipping_country]' => '',
            'add_paydata[shipping_telephonenumber]' => '',
        ];

        $oFcPayOneUser->_fcpoAddDeliveryAddress($aMockResponse, 'someUserId');
    }

    public function testFcpoCheckAddressExists()
    {
        $oFcPayOneUser = new FcPayOneUser();

        $oMockAddress = $this->getMockBuilder(Address::class)
            ->setMethods(['load', 'setId', 'save', 'getEncodedDeliveryAddress',])
            ->disableOriginalConstructor()->getMock();
        $oMockAddress->method('load')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockAddress);
        $this->invokeSetAttribute($oFcPayOneUser, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $this->invokeMethod($oFcPayOneUser, '_fcpoCheckAddressExists', ['someEncodedAddress']));
    }

    public function testFcpoSplitStreetAndStreetNr()
    {
        $oFcPayOneUser = new FcPayOneUser();
        $sMockStreetAndStreetNr = "Some Street 123";
        $aExpect = ['street' => 'Some Street', 'streetnr' => '123'];
        $this->assertEquals($aExpect, $this->invokeMethod($oFcPayOneUser, '_fcpoSplitStreetAndStreetNr', [$sMockStreetAndStreetNr]));
    }

    public function testFcpoGetCountryIdByIso2()
    {
        $oFcPayOneUser = new FcPayOneUser();

        $oMockCountry = $this->getMockBuilder(Country::class)
            ->setMethods(['getIdByCode'])
            ->disableOriginalConstructor()->getMock();
        $oMockCountry->method('getIdByCode')->willReturn('someCountryId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockCountry);
        $this->invokeSetAttribute($oFcPayOneUser, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someCountryId', $this->invokeMethod($oFcPayOneUser, '_fcpoGetCountryIdByIso2', ['someISOCode']));
    }

    public function testFcpoUnsetGroups()
    {
        $oFcPayOneUser = new FcPayOneUser();
        $oFcPayOneUser->fcpoUnsetGroups();
        $this->assertEquals(0, $oFcPayOneUser->getUserGroups()->count());
    }
}