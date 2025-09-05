<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminView;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;

class FcPayOneAdminViewTest extends ConfigUnitTestCase
{
    public function testFcGetAdminSeparator() {
        $oFcPayOneAdminView = new FcPayOneAdminView();

        $sExpect = '&';
        $this->assertEquals($sExpect, $oFcPayOneAdminView->fcGetAdminSeparator());
    }

    public function testGetViewId() {
        $oFcPayOneAdminView = new FcPayOneAdminView();
        $this->assertEquals('dyn_fcpayone', $oFcPayOneAdminView->getViewId());
    }

    public function testFcpoGetVersion() {
        $oFcPayOneAdminView = new FcPayOneAdminView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetModuleVersion')->willReturn('1.0.0');
        $this->invokeSetAttribute($oFcPayOneAdminView, '_oFcPoHelper', $oFcPoHelper);

        $sExpect = '1.0.0';
        $this->assertEquals($sExpect, $oFcPayOneAdminView->fcpoGetVersion());
    }

    public function testFcpoGetMerchantId() {
        $oFcPayOneAdminView = new FcPayOneAdminView();

        $oMockConfig = $this->getMockBuilder('oxConfig')->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('12345');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneAdminView, '_oFcPoHelper', $oFcPoHelper);

        $sExpect = '12345';
        $this->assertEquals($sExpect, $oFcPayOneAdminView->fcpoGetMerchantId());
    }

    public function testFcpoGetIntegratorId()
    {
        $oFcPayOneAdminView = new FcPayOneAdminView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetIntegratorId')->willReturn('someValue');
        $this->invokeSetAttribute($oFcPayOneAdminView, '_oFcPoHelper', $oFcPoHelper);

        $sExpected = 'someValue';
        $this->assertEquals($sExpected, $oFcPayOneAdminView->fcpoGetIntegratorId());
    }
}
