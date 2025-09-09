<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller;

use Fatchip\PayOne\Application\Controller\FcPayOneUserView;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;

class FcPayOneUserViewTest extends FcBaseUnitTestCase
{
    public function test_fcpoGetUserErrorMessage()
    {
        $oFcPayOneUserView = new FcPayOneUserView();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someMessage');
        $this->invokeSetAttribute($oFcPayOneUserView, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someMessage', $oFcPayOneUserView->fcpoGetUserErrorMessage());
    }
}
