<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminDetails;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;

class FcPayOneAdminDetailsTest extends FcBaseUnitTestCase
{
    public function testGetPayoneStatusList()
    {
        $oFcPayOneAdminDetails = new FcPayOneAdminDetails();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetPayoneStatusList')->willReturn(['appointed']);
        $this->invokeSetAttribute($oFcPayOneAdminDetails, '_oFcPoHelper', $oFcPoHelper);

        $aExpected = [(object) ['sId' => 'appointed', 'sTitle' => $oFcPoHelper->fcpoGetLang()->translateString('fcpo_status_appointed')]];

        $aResult = $oFcPayOneAdminDetails->getPayoneStatusList();

        $this->assertIsArray($aResult);
        $this->assertEquals($aExpected, $aResult);
    }
}
