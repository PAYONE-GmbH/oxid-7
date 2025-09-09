<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Model;

use Fatchip\PayOne\Application\Helper\Redirect as FcPayoneRedirectHelper;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Session;
use OxidEsales\Eshop\Core\UtilsObject;

class FcpoRedirectHelperTest extends FcBaseUnitTestCase
{
    public function testGetErrorUrl()
    {
        $sExpected = 'abortTest';

        $oFcPayoneRedirectHelper = new FcPayoneRedirectHelper();
        $sResult = $oFcPayoneRedirectHelper->getErrorUrl($sExpected);

        $this->assertStringContainsString($sExpected, $sResult);
        $this->assertStringContainsString('type=error', $sResult);
    }

    public function testGetSuccessUrl()
    {
        $oMockSession = $this->getMockBuilder(Session::class)
            ->setMethods(['sid'])
            ->disableOriginalConstructor()->getMock();
        $oMockSession->method('sid')->willReturn('sid12345');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getCurrentShopUrl'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getCurrentShopUrl')->willReturn('shopURL');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('testValue');
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetSession')->willReturn($oMockSession);

        UtilsObject::setClassInstance(fcpohelper::class, $oFcPoHelper);

        $oFcPayoneRedirectHelper = new FcPayoneRedirectHelper();
        $sResult = $oFcPayoneRedirectHelper->getSuccessUrl('testRefNr');

        $this->assertStringContainsString('sid12345', $sResult);
        $this->assertStringContainsString('testValue', $sResult);
        $this->assertStringContainsString('testRefNr', $sResult);
        $this->assertStringContainsString('shopURL', $sResult);

        UtilsObject::resetClassInstances();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDestroyInstance()
    {
        FcPayoneRedirectHelper::destroyInstance();
    }
}
