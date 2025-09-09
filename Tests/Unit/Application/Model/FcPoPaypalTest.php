<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPoPaypal;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\UtilsFile;

class FcPoPaypalTest extends FcBaseUnitTestCase
{
    public function testFcpoGetMessages()
    {
        $oFcPoPaypal = new FcPoPaypal();
        $this->invokeSetAttribute($oFcPoPaypal, '_aAdminMessages', ['someValue']);

        $this->assertEquals(['someValue'], $oFcPoPaypal->fcpoGetMessages());
    }

    public function testFcpoGetPayPalLogos()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $aMockResult = [['someValue', 'someValue', 'someValue', 'someValue', 'someValue']];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getAll'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getAll')->willReturn($aMockResult);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $oFcPoPaypal->fcpoGetPayPalLogos();
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoAddLogoPath()
    {
        $oFcPoPaypal = $this->getMockBuilder(FcPoPaypal::class)
            ->setMethods(['_fcpoGetLogoEnteredAndExisting'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoPaypal->method('_fcpoGetLogoEnteredAndExisting')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoHelper', $oFcPoHelper);


        $aResponse = $this->invokeMethod($oFcPoPaypal, '_fcpoAddLogoPath', ['someLogo', ['existingLogos']]);

        $this->assertIsArray($aResponse);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdatePayPalLogos()
    {
        $oFcPoPaypal = $this->getMockBuilder(FcPoPaypal::class)
            ->setMethods(['_handleUploadPaypalExpressLogo', '_fcpoTriggerUpdateLogos'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoPaypal->method('_handleUploadPaypalExpressLogo')->willReturn('someValue');

        $aMockLogos = [1 => ['active' => 'existingLogo', 'langid' => 'someId']];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(true);
        $oMockDatabase->method('quote')->willReturn('');
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoPaypal->fcpoUpdatePayPalLogos($aMockLogos);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoTriggerUpdateLogos()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute', 'quote'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(true);
        $oMockDatabase->method('quote')->willReturn('');
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoDb', $oMockDatabase);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(1);
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeMethod($oFcPoPaypal, '_fcpoTriggerUpdateLogos');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoAddPaypalExpressLogo()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(true);
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoDb', $oMockDatabase);

        $oFcPoPaypal->fcpoAddPaypalExpressLogo();
    }

    public function testFcpoGetLogoEnteredAndExisting()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoFileExists')->willReturn(true);
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(true, $this->invokeMethod($oFcPoPaypal, '_fcpoGetLogoEnteredAndExisting', ['someValue']));
    }

    public function testHandleUploadPaypalExpressLogo_NewerShopVersion()
    {
        $oFcPoPaypal = $this->getMockBuilder(FcPoPaypal::class)
            ->setMethods(['_fcpoValidateFile', '_fcpoHandleFile'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoPaypal->method('_fcpoValidateFile')->willReturn(true);
        $oFcPoPaypal->method('_fcpoHandleFile')->willReturn('someQueryAddition');

        $aFiles = [
            'logo_1' => [
                'error' => 0
            ],
        ];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetFiles')->willReturn($aFiles);
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals("someQueryAddition", $this->invokeMethod($oFcPoPaypal, '_handleUploadPaypalExpressLogo', [1]));
    }

    public function testFcpoHandleFile()
    {
        $oFcPoPaypal = $this->getMockBuilder(FcPoPaypal::class)
            ->setMethods(['_fcpoFetchMediaUrl'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoPaypal->method('_fcpoFetchMediaUrl')->willReturn('someValue');

        $aFiles = [
            'logo_1' => [
                'error' => 0
            ],
        ];

        $this->assertEquals(", FCPO_LOGO = 'someValue'", $this->invokeMethod($oFcPoPaypal, '_fcpoHandleFile', [1, $aFiles]));
    }

    public function testFcpoFetchMediaUrl_NewerShopVersion()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $oMockUtilsFile = $this->getMockBuilder(UtilsFile::class)
            ->setMethods(['handleUploadedFile', 'processFile'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsFile->method('handleUploadedFile')->willReturn('someValue');
        $oMockUtilsFile->method('processFile')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsFile')->willReturn($oMockUtilsFile);
        $this->invokeSetAttribute($oFcPoPaypal, '_oFcPoHelper', $oFcPoHelper);

        $aFiles = [
            'logo_1' => [
                'error' => 0
            ],
        ];

        $this->assertEquals('someValue', $this->invokeMethod($oFcPoPaypal, '_fcpoFetchMediaUrl', [1, $aFiles]));
    }

    public function testFcpoValidateFile()
    {
        $oFcPoPaypal = new FcPoPaypal();

        $aFiles = [
            'logo_1' => [
                'error' => 0
            ],
        ];

        $this->assertEquals(true, $this->invokeMethod($oFcPoPaypal, '_fcpoValidateFile', [1, $aFiles]));
    }
}