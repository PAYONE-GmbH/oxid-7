<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneProtocol;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;

class FcPayOneProtocolTest extends FcBaseUnitTestCase
{
    public function testGetViewId()
    {
        $oFcPayOneProtocol = new FcPayOneProtocol();
        $this->assertEquals('dyn_fcpayone', $oFcPayOneProtocol->getViewId());
    }

    public function testFcGetAdminSeparator() {
        $oFcPayOneProtocol = new FcPayOneProtocol();

        $sExpect = '&';
        $this->assertEquals($sExpect, $oFcPayOneProtocol->fcGetAdminSeparator());
    }
}
