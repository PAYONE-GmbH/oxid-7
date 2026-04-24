<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Doctrine\DBAL\Connection;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneMainAjax;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;

class FcPayOneMainAjaxTest extends FcBaseUnitTestCase
{
    public function testGetQuery_case_1()
    {
        $oFcPayOneMainAjax = $this->getMockBuilder(FcPayOneMainAjax::class)->disableOriginalConstructor()->getMock();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['quote'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('quote')->willReturn("'someQuotedString'");
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoDb', $oFcPoDb);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls('1', '2', '3');
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPayOneMainAjax, 'getQuery');

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testGetQuery_case_2()
    {
        $oFcPayOneMainAjax = $this->getMockBuilder(FcPayOneMainAjax::class)->disableOriginalConstructor()->getMock();

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['quote'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('quote')->willReturn("'someQuotedString'");
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoDb', $oFcPoDb);

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

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['quote', 'executeStatement'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('quote')->willReturn("'someQuotedString'");
        $oFcPoDb->method('executeStatement')->willReturn(1);
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoDb', $oFcPoDb);

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

        $oFcPoDb = $this->getMockBuilder(Connection::class)
            ->setMethods(['quote', 'executeStatement'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoDb->method('quote')->willReturn("'someQuotedString'");
        $oFcPoDb->method('executeStatement')->willReturn(1);
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoDb', $oFcPoDb);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneMainAjax, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $sExpect = $this->invokeMethod($oFcPayOneMainAjax, 'removepaycountry');

        $this->assertEquals($sExpect, $sResponse);
    }
}
