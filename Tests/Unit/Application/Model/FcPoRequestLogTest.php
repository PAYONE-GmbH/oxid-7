<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPoRequestLog;
use OxidEsales\Eshop\Core\Field;

class FcPoRequestLogTest extends FcBaseUnitTestCase
{
    public function testGetRequestArray()
    {
        $oFcPoRequestLog = new FcPoRequestLog();

        $aMockData = ['someRequestKey' => 'someRequestValue'];
        $sMockData = serialize($aMockData);
        $oFcPoRequestLog->fcporequestlog__fcpo_request = new Field($sMockData, Field::T_RAW);

        $this->assertEquals($aMockData, $oFcPoRequestLog->getRequestArray());
    }

    public function testGetResponseArray()
    {
        $oFcPoRequestLog = new FcPoRequestLog();

        $aMockData = ['someResponseKey' => 'someResponseValue'];
        $sMockData = serialize($aMockData);
        $oFcPoRequestLog->fcporequestlog__fcpo_response = new Field($sMockData, Field::T_RAW);

        $this->assertEquals($aMockData, $oFcPoRequestLog->getResponseArray());
    }

    public function testGetArray()
    {
        $oFcPoRequestLog = new FcPoRequestLog();

        $aMockData = ['someVar' => 'someValue'];
        $sMockData = serialize($aMockData);

        $this->assertEquals($aMockData, $this->invokeMethod($oFcPoRequestLog, 'getArray', [$sMockData]));
    }
}