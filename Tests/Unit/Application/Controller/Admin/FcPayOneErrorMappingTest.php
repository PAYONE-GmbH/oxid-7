<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneErrorMapping;
use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;
use OxidEsales\Eshop\Core\Language;

class FcPayOneErrorMappingTest extends ConfigUnitTestCase
{
    public function testGetMappings()
    {
        $aMockDataMappings = [
            'some' => 'Data'
        ];

        $oFcPayOneErrorMapping = $this->getMockBuilder(FcPayOneErrorMapping::class)
            ->setMethods(['_fcpoGetExistingMappings', '_fcpoAddNewMapping'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneErrorMapping->method('_fcpoGetExistingMappings')->willReturn($aMockDataMappings);
        $oFcPayOneErrorMapping->method('_fcpoAddNewMapping')->willReturn($aMockDataMappings);

        $this->assertEquals($aMockDataMappings, $oFcPayOneErrorMapping->getMappings());
    }

    public function testGetIframeMappings()
    {
        $aMockDataMappings = [
            'some' => 'Data'
        ];

        $oFcPayOneErrorMapping = $this->getMockBuilder(FcPayOneErrorMapping::class)
            ->setMethods(['_fcpoGetExistingIframeMappings', '_fcpoAddNewIframeMapping'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneErrorMapping->method('_fcpoGetExistingIframeMappings')->willReturn($aMockDataMappings);
        $oFcPayOneErrorMapping->method('_fcpoAddNewIframeMapping')->willReturn($aMockDataMappings);

        $this->assertEquals($aMockDataMappings, $oFcPayOneErrorMapping->getIframeMappings());
    }

    public function testFcpoGetPayoneErrorMessages()
    {
        $aMockErrorCodes = [
            'some' => 'Data'
        ];
        $oFcPayOneErrorMapping = new FcPayOneErrorMapping();

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('fcpoGetAvailableErrorCodes')->willReturn($aMockErrorCodes);
        $this->invokeSetAttribute($oFcPayOneErrorMapping, '_oFcPoErrorMapping', $oFcPoErrorMapping);

        $this->assertEquals($aMockErrorCodes, $oFcPayOneErrorMapping->fcpoGetPayoneErrorMessages());
    }

    public function testGetLanguages()
    {
        $oFcPayOneErrorMapping = new FcPayOneErrorMapping();
        $aMockLang = [
            'some' => 'Lang',
        ];

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['getLanguageArray'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('getLanguageArray')->willReturn($aMockLang);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPayOneErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($aMockLang, $oFcPayOneErrorMapping->getLanguages());
    }

    public function testFcpoAddNewMapping()
    {
        $aMockDataMappings = [];
        $oFcPayOneErrorMapping = new FcPayOneErrorMapping();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $this->invokeSetAttribute($oFcPayOneErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $aExpect = $aResponse = $this->invokeMethod($oFcPayOneErrorMapping, '_fcpoAddNewMapping', [$aMockDataMappings]);
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoAddNewIframeMapping()
    {
        $aMockDataMappings = [];
        $oFcPayOneErrorMapping = new FcPayOneErrorMapping();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $this->invokeSetAttribute($oFcPayOneErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $aExpect = $aResponse = $this->invokeMethod($oFcPayOneErrorMapping, '_fcpoAddNewIframeMapping', [$aMockDataMappings]);
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetExistingMappings()
    {
        $aMockMappings = [
            'some' => 'Data'
        ];
        $oFcPayOneErrorMapping = new FcPayOneErrorMapping();

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('fcpoGetExistingMappings')->willReturn($aMockMappings);
        $this->invokeSetAttribute($oFcPayOneErrorMapping, '_oFcPoErrorMapping', $oFcPoErrorMapping);

        $this->assertEquals($aMockMappings, $this->invokeMethod($oFcPayOneErrorMapping, '_fcpoGetExistingMappings'));
    }

    public function testFcpoGetExistingIframeMappings()
    {
        $aMockMappings = [
            'some' => 'Data'
        ];
        $oFcPayOneErrorMapping = new FcPayOneErrorMapping();

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('fcpoGetExistingMappings')->willReturn($aMockMappings);
        $this->invokeSetAttribute($oFcPayOneErrorMapping, '_oFcPoErrorMapping', $oFcPoErrorMapping);

        $this->assertEquals($aMockMappings, $this->invokeMethod($oFcPayOneErrorMapping, '_fcpoGetExistingIframeMappings'));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSave()
    {
        $aMockMappings = [
            'some' => ['Data']
        ];

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoGetQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoGetQuery')->willReturn('someQuery');

        $oFcPayOneErrorMapping = $this->getMockBuilder(FcPayOneErrorMapping::class)
            ->setMethods(['fcpoGetInstance'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneErrorMapping->method('fcpoGetInstance')->willReturn($oFcPoErrorMapping);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockMappings);
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneErrorMapping->save();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSaveIframe()
    {
        $aMockMappings = [
            'some' => ['Data']
        ];

        $oFcPoErrorMapping = $this->getMockBuilder(FcPoErrorMapping::class)
            ->setMethods(['_fcpoGetQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoErrorMapping->method('_fcpoGetQuery')->willReturn('someQuery');

        $oFcPayOneErrorMapping = $this->getMockBuilder(FcPayOneErrorMapping::class)
            ->setMethods(['fcpoGetInstance'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneErrorMapping->method('fcpoGetInstance')->willReturn($oFcPoErrorMapping);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn($aMockMappings);

        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneErrorMapping, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneErrorMapping->saveIframe();
    }
}
