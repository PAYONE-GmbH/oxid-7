<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use OxidEsales\Eshop\Core\DatabaseProvider;

class FcPoErrorMappingTest extends FcBaseUnitTestCase
{
    public function testMethod()
    {
        $this->assertEquals(true, true);
    }

    public function testFcpoGetExistingMappings()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $aMockResult = array(
            array(
                'oxid'=>'someOxid',
                'fcpo_error_code'=>'someErrorCode',
                'fcpo_lang_id'=>'someLangId',
                'fcpo_mapped_message'=>'someMappedMessage'
            ),
        );
        $oMockDatabase = $this->getMock('oxDb', array('getAll'));
        $oMockDatabase->expects($this->atLeastOnce())->method('getAll')->will($this->returnValue($aMockResult));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetDb')->will($this->returnValue($oMockDatabase));
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcpoHelper', $oHelper);

        $aResponse = $aExpect = $oFcPoErrorMapping->fcpoGetExistingMappings();
        $this->assertEquals($aExpect, $aResponse);
    }

    public function testFcpoGetAvailableErrorCodes_General()
    {
        $aMockData = array('some'=>'Data');
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoParseXml'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoParseXml')->will($this->returnValue($aMockData));

        $this->assertEquals($aMockData, $oFcPoErrorMapping->fcpoGetAvailableErrorCodes());
    }

    public function testFcpoGetAvailableErrorCodes_Iframe()
    {
        $aMockData = array('some'=>'Data');
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoParseXml'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoParseXml')->will($this->returnValue($aMockData));

        $this->assertEquals($aMockData, $oFcPoErrorMapping->fcpoGetAvailableErrorCodes('iframe'));
    }

    public function testFcpoGetAvailableErrorCodes_Exception()
    {
        $this->wrapExpectException(\Exception::class);
        $oMockException = new \Exception('someErrorMessage');
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoParseXml'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoParseXml')->will($this->throwException($oMockException));

        $this->assertEquals(\Exception::class, $oFcPoErrorMapping->fcpoGetAvailableErrorCodes());
    }

    public function testFcpoUpdateMappings()
    {
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoGetQuery'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoGetQuery')->will($this->returnValue(true));

        $oMockDatabase = $this->getMock('oxDb', array('Execute'));
        $oMockDatabase->expects($this->any())->method('Execute')->will($this->returnValue(true));

        $aMockMappings = array('someIndex' => array('someValue'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetDb')->will($this->returnValue($oMockDatabase));
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcpoHelper', $oHelper);

        $this->assertEquals(null, $oFcPoErrorMapping->fcpoUpdateMappings($aMockMappings, 'someType'));
    }

    public function testFcpoFetchMappedErrorMessage()
    {
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoGetSearchQuery'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoGetSearchQuery')->will($this->returnValue('someQuery'));

        $oMockDb = $this->getMock('oxdb', array('GetOne'));
        $oMockDb->expects($this->any())->method('GetOne')->will($this->returnValue('MappedMessage'));
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcpoDb', $oMockDb);

        $oMockUBase = $this->getMock('oxubase', array('getActiveLangAbbr'));
        $oMockUBase->expects($this->any())->method('getActiveLangAbbr')->will($this->returnValue('de'));

        $oMockLangData = new stdClass();
        $oMockLangData->abbr = 'de';
        $oMockLangData->id = '1';
        $aMockLangData = array($oMockLangData);

        $oMockLang = $this->getMock('oxlang', array('getLanguageArray'));
        $oMockLang->expects($this->any())->method('getLanguageArray')->will($this->returnValue($aMockLangData));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockUBase));
        $oHelper->expects($this->any())->method('fcpoGetLang')->will($this->returnValue($oMockLang));
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcpoHelper', $oHelper);

        $this->assertEquals('MappedMessage', $oFcPoErrorMapping->fcpoFetchMappedErrorMessage('someMessage'));
    }

    public function testFcpoGetMappingWhere()
    {
        $sExpect = "WHERE fcpo_error_type='general'";
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $this->assertEquals($sExpect, $oFcPoErrorMapping->_fcpoGetMappingWhere('general'));
    }

    public function testFcpoParseXml()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $oMockUBase = $this->getMock('oxubase', array('getActiveLangAbbr'));
        $oMockUBase->expects($this->any())->method('getActiveLangAbbr')->will($this->returnValue('de'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('getFactoryObject')->will($this->returnValue($oMockUBase));
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcpoHelper', $oHelper);

        $oMockXmlEntry = new stdClass();
        $oMockXmlEntry->error_code = 'someErrorCode';
        $oMockXmlEntry->error_message_de = 'someMessage';
        $oMockXmlEntry->error_message_ = 'someMessage';

        $oMockXml = new stdClass();
        $oMockXml->entry = array($oMockXmlEntry);

        $oMockEntry = new stdClass();
        $oMockEntry->sErrorCode = 'someErrorCode';
        $oMockEntry->sErrorMessage = 'someMessage';

        $aExpect = array($oMockEntry);

        $this->assertEquals($aExpect, $oFcPoErrorMapping->_fcpoParseXml($oMockXml));
    }

    public function testFcpoXml2Array()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $sMockXml = '<root><mocknode><mockvar>mockvalue</mockvar></mocknode></root>';
        $oMockXml = simplexml_load_string($sMockXml);
        $aExpect = array('mocknode'=>array('mockvar'=>'mockvalue'));

        $this->assertEquals($aExpect, $oFcPoErrorMapping->_fcpoXml2Array($oMockXml));
    }

    public function testFcpoGetQuery_Delete()
    {
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoGetUpdateQuery'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoGetUpdateQuery')->will($this->returnValue(true));

        $aMockData = array('delete' => true);
        $sMockOxid = 'someId';
        $sQuotedOxid = DatabaseProvider::getDb()->quote($sMockOxid);

        $sExpect = "DELETE FROM fcpoerrormapping WHERE oxid = {$sQuotedOxid}";

        $this->assertEquals($sExpect, $oFcPoErrorMapping->_fcpoGetQuery($sMockOxid, $aMockData, 'someErrorType'));
    }

    public function testFcpoGetQuery_Update()
    {
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoGetUpdateQuery'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoGetUpdateQuery')->will($this->returnValue('someValue'));

        $aMockData = array('donotdelete' => true);
        $sMockOxid = 'someId';
        $sExpect = "someValue";

        $this->assertEquals($sExpect, $oFcPoErrorMapping->_fcpoGetQuery($sMockOxid, $aMockData, 'someErrorType'));
    }

    public function testFcpoGetUpdateQuery_Insert()
    {
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoIsValidNewEntry'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoIsValidNewEntry')->will($this->returnValue(true));

        $oMockUtilsObject = $this->getMock('oxUtilsObject', array('generateUID'));
        $oMockUtilsObject->expects($this->any())->method('generateUID')->will($this->returnValue('someId'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetUtilsObject')->will($this->returnValue($oMockUtilsObject));
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcpoHelper', $oHelper);

        $sMockMappingId = 'someMapId';
        $sMockPaymentId = 'somePaymentId';
        $sMockPayoneStatus = 'someStatus';
        $sMockFolder = 'someFolder';

        $aMockData = array('sPaymentType' => $sMockPaymentId, 'sPayoneStatus' => $sMockPayoneStatus, 'sShopStatus' => $sMockFolder);

        $sResponse = $sExpect = $oFcPoErrorMapping->_fcpoGetUpdateQuery($sMockMappingId, $aMockData, 'someErrorType');

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetUpdateQuery_Update()
    {
        $oFcPoErrorMapping = $this->getMock('fcpoerrormapping', array('_fcpoIsValidNewEntry'));
        $oFcPoErrorMapping->expects($this->any())->method('_fcpoIsValidNewEntry')->will($this->returnValue(false));

        $oMockUtilsObject = $this->getMock('oxUtilsObject', array('generateUID'));
        $oMockUtilsObject->expects($this->any())->method('generateUID')->will($this->returnValue('someId'));

        $oHelper = $this->getMockBuilder('fcpohelper')->disableOriginalConstructor()->getMock();
        $oHelper->expects($this->any())->method('fcpoGetUtilsObject')->will($this->returnValue($oMockUtilsObject));
        $this->invokeSetAttribute($oFcPoErrorMapping, '_oFcpoHelper', $oHelper);

        $sMockMappingId = 'someMapId';
        $sMockPaymentId = 'somePaymentId';
        $sMockPayoneStatus = 'someStatus';
        $sMockFolder = 'someFolder';

        $aMockData = array('sPaymentType' => $sMockPaymentId, 'sPayoneStatus' => $sMockPayoneStatus, 'sShopStatus' => $sMockFolder);

        $sResponse = $sExpect = $oFcPoErrorMapping->_fcpoGetUpdateQuery($sMockMappingId, $aMockData, 'someErrorType');

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetSearchQuery()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $sExpect = "
            SELECT fcpo_mapped_message FROM fcpoerrormapping 
            WHERE 
            fcpo_error_code = 'someErrorCode' AND
            fcpo_lang_id = 'someId'
            LIMIT 1
        ";

        $this->assertEquals($sExpect, $oFcPoErrorMapping->_fcpoGetSearchQuery('someErrorCode', 'someId'));
    }

    public function testFcpoIsValidNewEntry()
    {
        $oFcPoErrorMapping = new FcPoErrorMapping();

        $sMockMappingId = 'new';
        $sMockPaymentId = 'somePaymentId';
        $sMockPayoneStatus = 'someStatus';
        $sMockFolder = 'someFolder';

        $this->assertEquals(true, $oFcPoErrorMapping->_fcpoIsValidNewEntry($sMockMappingId, $sMockPaymentId, $sMockPayoneStatus, $sMockFolder));
    }
}