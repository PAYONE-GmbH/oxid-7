<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPoPaypal;

class FcPoPaypalTest extends FcBaseUnitTestCase
{
    public function testMethod()
    {
        $this->assertEquals(true, true);
    }

    public function testFcpoGetMessages()
    {
        $oFcPoPaypal = new FcPoPaypal();
        $this->invokeSetAttribute($oFcPoPaypal, '_aAdminMessages', 'someValue');

        $this->assertEquals('someValue', $oFcPoPaypal->fcpoGetMessages());
    }

    public function testFcpoGetPayPalLogos()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $aMockResult = array(array('someValue','someValue','someValue','someValue','someValue'));
        $oMockDatabase = $this->getMock('oxDb', array('getAll'));
        $oMockDatabase->expects($this->atLeastOnce())->method('getAll')->will($this->returnValue($aMockResult));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetDb')->will($this->returnValue($oMockDatabase));
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcpoHelper', $oHelper);

        $aResponse = $aExpect = $oFcPoPaypal->fcpoGetPayPalLogos();
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoAddLogoPath()
    {
        $oFcPoPaypal = $this->getMock('fcpopaypal', array('_fcpoGetLogoEnteredAndExisting'));
        $oFcPoPaypal->expects($this->any())->method('_fcpoGetLogoEnteredAndExisting')->will($this->returnValue(true));
        $aResponse = $oFcPoPaypal->_fcpoAddLogoPath('someLogo', array('existingLogos'));
        $this->assertEquals(true, is_array($aResponse));
    }

    public function testFcpoUpdatePayPalLogos()
    {
        $oFcPoPaypal = $this->getMock('fcpopaypal', array('_handleUploadPaypalExpressLogo', '_fcpoTriggerUpdateLogos'));
        $oFcPoPaypal->expects($this->any())->method('_handleUploadPaypalExpressLogo')->will($this->returnValue(true));
        $oFcPoPaypal->expects($this->any())->method('_fcpoTriggerUpdateLogos')->will($this->returnValue(true));

        $aMockLogos = array(1=>array('active'=>'existingLogo', 'langid'=>'someId'));

        $oMockDatabase = $this->getMock('oxDb', array('Execute', 'quote'));
        $oMockDatabase->expects($this->any())->method('Execute')->will($this->returnValue(true));
        $oMockDatabase->expects($this->any())->method('quote')->will($this->returnValue(''));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetDb')->will($this->returnValue($oMockDatabase));
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcpoHelper', $oHelper);

        $this->assertEquals(null, $oFcPoPaypal->fcpoUpdatePayPalLogos($aMockLogos));
    }

    public function testFcpoTriggerUpdateLogos()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $oMockDatabase = $this->getMock('oxDb', array('Execute', 'quote'));
        $oMockDatabase->expects($this->any())->method('Execute')->will($this->returnValue(true));
        $oMockDatabase->expects($this->any())->method('quote')->will($this->returnValue(''));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetRequestParameter')->will($this->returnValue(1));
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcpoHelper', $oHelper);
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcpoDb', $oMockDatabase);

        $this->assertEquals(null, $oFcPoPaypal->_fcpoTriggerUpdateLogos());
    }

    public function testFcpoAddPaypalExpressLogo()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $oMockDatabase = $this->getMock('oxDb', array('Execute'));
        $oMockDatabase->expects($this->any())->method('Execute')->will($this->returnValue(true));
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcpoDb', $oMockDatabase);

        $this->assertEquals(null, $oFcPoPaypal->fcpoAddPaypalExpressLogo());
    }

    public function testFcpoGetLogoEnteredAndExisting()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoFileExists')->will($this->returnValue(true));
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcpoHelper', $oHelper);

        $this->assertEquals(true, $oFcPoPaypal->_fcpoGetLogoEnteredAndExisting('someValue'));
    }

    public function testHandleUploadPaypalExpressLogo_NewerShopVersion()
    {
        $oFcPoPaypal = $this->getMock('fcpopaypal', array('_fcpoValidateFile','_fcpoHandleFile'));
        $oFcPoPaypal->expects($this->any())->method('_fcpoValidateFile')->will($this->returnValue(true));
        $oFcPoPaypal->expects($this->any())->method('_fcpoHandleFile')->will($this->returnValue('someQueryAddition'));

        $aFiles = array(
            'logo_1' => array('error'=>0),
        );

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetFiles')->will($this->returnValue($aFiles));

        $this->invokeSetAttribute($oFcPoPaypal, '_oFcpoHelper', $oHelper);

        $this->assertEquals("someQueryAddition", $this->invokeMethod($oFcPoPaypal, '_handleUploadPaypalExpressLogo', array(1)));
    }

    public function testFcpoHandleFile()
    {
        $oFcPoPaypal = $this->getMock('fcpopaypal', array('_fcpoFetchMediaUrl'));
        $oFcPoPaypal->expects($this->any())->method('_fcpoFetchMediaUrl')->will($this->returnValue('someValue'));

        $aFiles = array(
            'logo_1' => array('error'=>0),
        );

        $this->assertEquals(", FCPO_LOGO = 'someValue'", $oFcPoPaypal->_fcpoHandleFile(1, $aFiles));
    }

    public function testFcpoFetchMediaUrl_NewerShopVersion()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $oMockUtilsFile = $this->getMock('oxUtilsFile', array('handleUploadedFile', 'processFile'));
        $oMockUtilsFile->expects($this->any())->method('handleUploadedFile')->will($this->returnValue('someValue'));
        $oMockUtilsFile->expects($this->any())->method('processFile')->will($this->returnValue('someValue'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetIntShopVersion')->will($this->returnValue(4700));
        $oHelper->expects($this->any())->method('fcpoGetUtilsFile')->will($this->returnValue($oMockUtilsFile));
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcpoHelper', $oHelper);

        $aFiles = array(
            'logo_1' => array('error'=>0),
        );

        $this->assertEquals('someValue', $oFcPoPaypal->_fcpoFetchMediaUrl(1, $aFiles));
    }

    public function testFcpoValidateFile()
    {
        $oFcPoPaypal = new FcPoPaypal();
        $aFiles = array(
            'logo_1' => array('error'=>0),
        );

        $this->assertEquals(true, $oFcPoPaypal->_fcpoValidateFile(1, $aFiles));
    }
}