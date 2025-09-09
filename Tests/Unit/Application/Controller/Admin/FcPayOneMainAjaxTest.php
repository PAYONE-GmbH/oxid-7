<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneMainAjax;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;

class FcPayOneMainAjaxTest extends FcBaseUnitTestCase
{
    public function testGetQuery_case_1()
    {
        $oFcPayOneMainAjax = new FcPayOneMainAjax();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls('1', '2', '3');
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPayOneMainAjax, 'getQuery');

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testGetQuery_case_2()
    {
        $oFcPayOneMainAjax = new FcPayOneMainAjax();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls(false, '2', '3');
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPayOneMainAjax, 'getQuery');

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testAddpaycountry()
    {
        $oFcPayOneMainAjax = $this->getMockBuilder(FcPayOneMainAjax::class)
            ->setMethods(['_getActionIds'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneMainAjax->method('_getActionIds')->willReturn(['1', '2']);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls('1', '2', '3');
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPayOneMainAjax, 'addpaycountry');

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testRemovepaycountry_case_1()
    {
        $oFcPayOneMainAjax = $this->getMockBuilder(FcPayOneMainAjax::class)
            ->setMethods(['_getActionIds'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneMainAjax->method('_getActionIds')->willReturn(['1', '2']);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someValue');
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPayOneMainAjax, 'removepaycountry');

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testRemovepaycountry_case_2()
    {
        $oFcPayOneMainAjax = $this->getMockBuilder(FcPayOneMainAjax::class)
            ->setMethods(['_getActionIds'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneMainAjax->method('_getActionIds')->willReturn(['1', '2']);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPayOneMainAjax, 'removepaycountry');

        $this->assertEquals($sExpect, $sResponse);
    }
}
