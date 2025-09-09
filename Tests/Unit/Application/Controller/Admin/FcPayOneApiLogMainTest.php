<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLogMain;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;

class FcPayOneApiLogMainTest extends ConfigUnitTestCase
{
    public function testFcGetAdminSeparator()
    {
        $oFcPayOneApiLogMain = new FcPayOneApiLogMain();

        $sExpect = '&';
        $this->assertEquals($sExpect, $oFcPayOneApiLogMain->fcGetAdminSeparator());
    }
}
