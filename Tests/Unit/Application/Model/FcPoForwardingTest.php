<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPoForwarding;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\UtilsObject;

class FcPoForwardingTest extends FcBaseUnitTestCase
{
    public function testFcpoGetExistingForwardings()
    {
        $oFcPoForwarding = new FcPoForwarding();

        $aMockResult = [
            [
                'oxid' => 'someOxid',
                'fcpo_payonestatus' => 'someStatus',
                'fcpo_url' => 'someUrl',
                'fcpo_timeout' => 'someTimeout'
            ]
        ];

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['getAll'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('getAll')->willReturn($aMockResult);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $oFcPoForwarding->fcpoGetExistingForwardings();
        $this->assertEquals($aExpect, $aResponse);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testFcpoUpdateForwardings()
    {
        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoGetQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoGetQuery')->willReturn('someQuery');

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['Execute'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('Execute')->willReturn(true);

        $aMockForwardings = ['someIndex' => ['someValue']];

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetDb')->willReturn($oMockDatabase);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoHelper', $oFcPoHelper);

        $oFcPoForwarding->fcpoUpdateForwardings($aMockForwardings);
    }

    public function testFcpoGetQuery_Delete()
    {
        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoGetUpdateQuery')->willReturn('someUpdateQuery');

        $aMockData = ['delete' => true];
        $sMockOxid = 'someId';
        $sQuotedOxid = DatabaseProvider::getDb()->quote($sMockOxid);

        $sExpect = "DELETE FROM fcpostatusforwarding WHERE oxid = $sQuotedOxid";

        $this->assertEquals($sExpect, $this->invokeMethod($oFcPoForwarding, '_fcpoGetQuery', [$sMockOxid, $aMockData]));
    }

    public function testFcpoGetQuery_Update()
    {
        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoGetUpdateQuery'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoGetUpdateQuery')->willReturn('someValue');

        $aMockData = ['donotdelete' => true];
        $sMockOxid = 'someId';
        $sExpect = "someValue";

        $this->assertEquals($sExpect, $this->invokeMethod($oFcPoForwarding, '_fcpoGetQuery', [$sMockOxid, $aMockData]));

    }

    public function testFcpoGetUpdateQuery_Insert()
    {
        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoIsValidNewEntry')->willReturn(true);

        $oMockUtilsObject = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUID'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsObject->method('generateUID')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockUtilsObject);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoHelper', $oFcPoHelper);

        $sMockForwardingId = 'someForwardingId';
        $sMockPayoneStatus = 'someStatus';
        $sMockUrl = 'someUrl';
        $iMockTimeout = 90;
        $sMockOxid = 'someOxid';

        $sResponse = $sExpect = $this->invokeMethod($oFcPoForwarding, '_fcpoGetUpdateQuery', [$sMockForwardingId, $sMockPayoneStatus, $sMockUrl, $iMockTimeout, $sMockOxid]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetUpdateQuery_Update()
    {
        $oFcPoForwarding = $this->getMockBuilder(FcPoForwarding::class)
            ->setMethods(['_fcpoIsValidNewEntry'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoForwarding->method('_fcpoIsValidNewEntry')->willReturn(false);

        $oMockUtilsObject = $this->getMockBuilder(UtilsObject::class)
            ->setMethods(['generateUID'])
            ->disableOriginalConstructor()->getMock();
        $oMockUtilsObject->method('generateUID')->willReturn('someId');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetUtilsObject')->willReturn($oMockUtilsObject);
        $this->invokeSetAttribute($oFcPoForwarding, '_oFcPoHelper', $oFcPoHelper);

        $sMockForwardingId = 'someForwardingId';
        $sMockPayoneStatus = 'someStatus';
        $sMockUrl = 'someUrl';
        $iMockTimeout = 90;
        $sMockOxid = 'someOxid';

        $sResponse = $sExpect = $this->invokeMethod($oFcPoForwarding, '_fcpoGetUpdateQuery', [$sMockForwardingId, $sMockPayoneStatus, $sMockUrl, $iMockTimeout, $sMockOxid]);

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoIsValidNewEntry()
    {
        $oFcPoForwarding = new FcPoForwarding();

        $sMockForwardingId = 'new';
        $sMockPayoneStatus = 'someStatus';
        $sMockUrl = 'someUrl';
        $iMockTimeout = 90;

        $this->assertEquals(true, $this->invokeMethod($oFcPoForwarding, '_fcpoIsValidNewEntry', [$sMockForwardingId, $sMockPayoneStatus, $sMockUrl, $iMockTimeout]));
    }
}