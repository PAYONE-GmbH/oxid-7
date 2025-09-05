<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneStatusMapping;
use Fatchip\PayOne\Application\Model\FcPoMapping;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;

class FcPayOneStatusMappingTest extends ConfigUnitTestCase
{
    public function testGetMappings()
    {
        $aMockMapping = ['someValue'];

        $oFcPayOneStatusMapping = $this->getMockBuilder(FcPayOneStatusMapping::class)
            ->setMethods(['_fcpoGetExistingMappings', '_fcpoAddNewMapping'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneStatusMapping->method('_fcpoGetExistingMappings')->willReturn($aMockMapping);
        $oFcPayOneStatusMapping->method('_fcpoAddNewMapping')->willReturn($aMockMapping);

        $this->assertEquals($aMockMapping, $oFcPayOneStatusMapping->getMappings());
    }

    public function testFcpoAddNewMapping()
    {
        $aMockExistingMappings = [
            (object) [
                'sOxid' => 'someOxid',
                'sPaymentType' => 'somePaymentId',
                'sPayoneStatusId' => 'someStatus',
                'sShopStatusId' => 'someFolder'
            ],
        ];

        $oFcPayOneStatusMapping = new FcPayOneStatusMapping();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $this->invokeSetAttribute($oFcPayOneStatusMapping, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $this->invokeMethod($oFcPayOneStatusMapping, '_fcpoAddNewMapping', [$aMockExistingMappings]);

        $aExpect = [
            (object) [
                'sOxid' => 'someOxid',
                'sPaymentType' => 'somePaymentId',
                'sPayoneStatusId' => 'someStatus',
                'sShopStatusId' => 'someFolder'
            ],
            (object) [
                'sOxid' => 'new',
                'sPaymentType' => '',
                'sPayoneStatusId' => '',
                'sShopStatusId' => ''
            ],
        ];

        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetExistingMappings()
    {
        $aResult = ['someIndex' => 'someValue'];

        $oFcPayOneStatusMapping = new FcPayOneStatusMapping();

        $oMockStatusMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['fcpoGetExistingMappings'])
            ->disableOriginalConstructor()->getMock();
        $oMockStatusMapping->method('fcpoGetExistingMappings')->willReturn($aResult);
        $this->invokeSetAttribute($oFcPayOneStatusMapping, '_oFcPoMapping', $oMockStatusMapping);

        $this->assertEquals($aResult, $this->invokeMethod($oFcPayOneStatusMapping, '_fcpoGetExistingMappings'));
    }

    public function testGetPaymentTypeList()
    {
        $oFcPayOneStatusMapping = new FcPayOneStatusMapping();
        $aResponse = $aExpect = $oFcPayOneStatusMapping->getPaymentTypeList();
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testGetPayoneStatusList()
    {
        $oFcPayOneStatusMapping = new FcPayOneStatusMapping();
        $aResponse = $aExpect = $oFcPayOneStatusMapping->getPayoneStatusList();
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testGetShopStatusList()
    {
        $oFcPayOneStatusMapping = new FcPayOneStatusMapping();
        $aResponse = $aExpect = $oFcPayOneStatusMapping->getShopStatusList();
        $this->assertEquals($aExpect, $aResponse);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSave()
    {
        $oMockStatusMapping = $this->getMockBuilder(FcPoMapping::class)
            ->setMethods(['fcpoUpdateMappings'])
            ->disableOriginalConstructor()->getMock();

        $oFcPayOneStatusMapping = $this->getMockBuilder(FcPayOneStatusMapping::class)
            ->setMethods(['fcpoGetInstance'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneStatusMapping->method('fcpoGetInstance')->willReturn($oMockStatusMapping);

        $aForwardings = [
            'new' => [
                'sPaymentType' => 'somePaymentType',
                'sPayoneStatus' => 'somePayoneStatus',
                'sShopStatus' => 'someStatus',
            ]
        ];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aForwardings);
        $this->invokeSetAttribute($oFcPayOneStatusMapping, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneStatusMapping->save();
    }
}
