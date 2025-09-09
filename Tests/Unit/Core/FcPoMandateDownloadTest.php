<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller;

use Fatchip\PayOne\Core\FcPoMandateDownload;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\User;

class FcPoMandateDownloadTest extends FcBaseUnitTestCase
{
    public function testFcpoGetUserId()
    {
        $oMockUser = $this->getMockBuilder(User::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $oMockUser->method('getId')->willReturn('someUserId');

        $oFcPoMandateDownload = $this->getMockBuilder(FcPoMandateDownload::class)
            ->setMethods(['getUser'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoMandateDownload->method('getUser')->willReturn($oMockUser);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someRequestUserId');
        $this->invokeSetAttribute($oFcPoMandateDownload, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someUserId', $this->invokeMethod($oFcPoMandateDownload, '_fcpoGetUserId'));
    }

    public function testFcpoGetUserId_UserNotFound()
    {
        $oFcPoMandateDownload = $this->getMockBuilder(FcPoMandateDownload::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someRequestUserId');
        $this->invokeSetAttribute($oFcPoMandateDownload, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someRequestUserId', $this->invokeMethod($oFcPoMandateDownload, '_fcpoGetUserId'));
    }
}
