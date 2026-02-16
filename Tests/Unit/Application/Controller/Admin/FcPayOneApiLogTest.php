<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLog;
use Fatchip\PayOne\Application\Model\FcPoRequestLog;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;

class FcPayOneApiLogTest extends ConfigUnitTestCase
{
    public function testRender()
    {
        $oFcPayOneApiLog = new FcPayOneApiLog();

        $oFactoryObject = $this->getMockBuilder(FcPoRequestLog::class)
            ->disableOriginalConstructor()
            ->getMock();
        $oFactoryObject->method('load')
            ->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oFactoryObject);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($oFactoryObject);
        $this->invokeSetAttribute($oFcPayOneApiLog, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('@fcpayone/admin/fcpayone_apilog', $this->invokeMethod($oFcPayOneApiLog, 'render'));
    }
}
