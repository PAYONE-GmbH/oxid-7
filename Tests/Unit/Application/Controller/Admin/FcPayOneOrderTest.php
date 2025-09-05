<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneOrder;
use Fatchip\PayOne\Application\Model\FcPoTransactionStatus;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Database\Adapter\Doctrine\Database;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;

class FcPayOneOrderTest extends FcBaseUnitTestCase
{
    public function testRender()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(1);
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oMockOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('@fcpayone/admin/fcpayone_order', $oFcPayOneOrder->render());
    }

    public function testFcpoGetStatusOxid_HasFalseStatus()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeSetAttribute($oFcPayOneOrder, '_sStatusOxid', false);

        $this->assertEquals('-1', $oFcPayOneOrder->fcpoGetStatusOxid());
    }

    public function testFcpoGetStatusOxid_HasStatus()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('someValue');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeSetAttribute($oFcPayOneOrder, '_sStatusOxid', false);

        $this->assertEquals('someValue', $oFcPayOneOrder->fcpoGetStatusOxid());
    }

    public function testFcpoGetCurrentStatus_HasTransaction()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->oxorder__fcpotxid = new Field('1234');

        $oMockTransactionStatus = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockTransactionStatus->method('load')->willReturn(true);
        $oMockTransactionStatus->fcpotransactionstatus__fcpo_txid = new Field('1234');

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['fcpoGetStatusOxid', 'fcpoGetInstance'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetStatusOxid')->willReturn('someId');
        $oFcPayOneOrder->method('fcpoGetInstance')->willReturnOnConsecutiveCalls($oMockOrder, $oMockTransactionStatus);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($oMockTransactionStatus, $oFcPayOneOrder->fcpoGetCurrentStatus());
    }

    public function testFcpoGetCurrentStatus_NoTransaction()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->oxorder__fcpotxid = new Field('1234');

        $oMockTransactionStatus = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockTransactionStatus->method('load')->willReturn(true);
        $oMockTransactionStatus->fcpotransactionstatus__fcpo_txid = new Field('4321');

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['fcpoGetStatusOxid', 'fcpoGetInstance'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetStatusOxid')->willReturn('someId');
        $oFcPayOneOrder->method('fcpoGetInstance')->willReturnOnConsecutiveCalls($oMockOrder, $oMockTransactionStatus);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(false, $oFcPayOneOrder->fcpoGetCurrentStatus());
    }

    public function testGetStatus()
    {
        $this->_fcpoPrepareTransactionstatusTable();

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrder', 'fcpoGetStatusOxid'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetStatusOxid')->willReturn('someId');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load', 'fcpoGetStatusfcpoGetStatus'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->method('fcpoGetStatusfcpoGetStatus')->willReturn(true);
        $oMockOrder->oxorder__fcpotxid = new Field('156452317');
        $this->invokeSetAttribute($oMockOrder, '_oFcPoDb', DatabaseProvider::getDb());


        $oMockTransactionStatus = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockTransactionStatus->method('load')->willReturn(true);
        $oMockTransactionStatus->fcpotransactionstatus__fcpo_txid = new Field('4321');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockOrder, $oMockTransactionStatus);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneOrder, '_aStatus', null);

        $this->invokeSetAttribute($oMockOrder, '_oFcPoHelper', $oFcPoHelper);

        $aResponse = $aExpect = $oFcPayOneOrder->getStatus();

        $this->assertEquals($aExpect, $aResponse);

        $this->_fcpoTruncateTable('fcpotransactionstatus');
    }

    public function testGetStatus_ReturnValue()
    {
        $this->_fcpoPrepareTransactionstatusTable();

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrder', 'fcpoGetStatusOxid'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetStatusOxid')->willReturn('someId');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load', 'fcpoGetStatusfcpoGetStatus'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->method('fcpoGetStatusfcpoGetStatus')->willReturn(true);
        $oMockOrder->oxorder__fcpotxid = new Field('156452317');

        $oMockTransactionStatus = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockTransactionStatus->method('load')->willReturn(true);
        $oMockTransactionStatus->fcpotransactionstatus__fcpo_txid = new Field('4321');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockOrder, $oMockTransactionStatus);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->invokeSetAttribute($oFcPayOneOrder, '_aStatus', ['someValue','someOtherValue']);

        $aResponse = $aExpect = $oFcPayOneOrder->getStatus();

        $this->assertEquals($aExpect, $aResponse);

        $this->_fcpoTruncateTable('fcpotransactionstatus');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCapture_AmountAvailable()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrder', 'fcpoGetStatusOxid'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetStatusOxid')->willReturn('someId');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);

        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['load', 'sendRequestCapture'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('load')->willReturn(true);
        $oMockRequest->method('sendRequestCapture')->willReturn(['returnValue']);
        $aMockPositions = array('1' => array('capture' => '0'));

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls('1', '1', '1,99', $aMockPositions);
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockOrder, $oMockRequest);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->capture();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCapture_PositionsAvailable()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrder', 'fcpoGetStatusOxid'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetStatusOxid')->willReturn('someId');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);

        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['load', 'sendRequestCapture'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('load')->willReturn(true);
        $oMockRequest->method('sendRequestCapture')->willReturn(['returnValue']);
        $aMockPositions = array('1' => array('capture' => '0'));

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls('1', '1', null, $aMockPositions);
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockOrder, $oMockRequest);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->capture();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDebit_AmountAvailable()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrder', 'fcpoGetStatusOxid'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetStatusOxid')->willReturn('someId');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);

        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['load', 'sendRequestDebit'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('load')->willReturn(true);
        $oMockRequest->method('sendRequestDebit')->willReturn(['returnValue']);
        $aMockPositions = array('1' => array('debit' => '0'));

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls('1', 'someCountry', 'someAccount', 'someBankCode', 'someHolder', '1,99', 'someCancelReason', $aMockPositions);
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockOrder, $oMockRequest);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->debit();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDebit_PositionsAvailable()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['getOrder', 'fcpoGetStatusOxid'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetStatusOxid')->willReturn('someId');

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);

        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['load', 'sendRequestDebit'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('load')->willReturn(true);
        $oMockRequest->method('sendRequestDebit')->willReturn(['returnValue']);
        $aMockPositions = array('1' => array('debit' => '0'));

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls('1', 'someCountry', 'someAccount', 'someBankCode', 'someHolder', null, 'someCancelReason', $aMockPositions);
        $oFcPoHelper->method('getFactoryObject')->willReturnOnConsecutiveCalls($oMockOrder, $oMockRequest);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->debit();
    }

    public function testFcpoGetMandatePdfUrl_ParamExists()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load', 'fcpoGetMandateFilename'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->method('fcpoGetMandateFilename')->willReturn('someFilename');
        $oMockOrder->oxorder__oxpaymenttype = new Field('fcpodebitnote');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockDb = $this->getMockBuilder(Database::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDb->method('GetOne')->willReturn('1');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoDb', $oMockDb);

        $sResponse = $sExpect = $oFcPayOneOrder->fcpoGetMandatePdfUrl();

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetMandatePdfUrl_ParamNotExists()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load', 'fcpoGetMandateFilename'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->method('fcpoGetMandateFilename')->willReturn('someFilename');
        $oMockOrder->oxorder__oxpaymenttype = new Field('fcpodebitnote');

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(false);
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oMockDb = $this->getMockBuilder(Database::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDb->method('GetOne')->willReturn('1');
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoDb', $oMockDb);

        $sResponse = $sExpect = $oFcPayOneOrder->fcpoGetMandatePdfUrl();

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testRedownloadMandate()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load', 'getId', 'sendRequestGetFile'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->method('getId')->willReturn('1');
        $oMockOrder->method('sendRequestGetFile')->willReturn(true);
        $oMockOrder->oxorder__fcpomode = new Field('test');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_redownloadMandate', ['someFilename.pdf']));
    }

    public function testRedownloadMandate_Skip()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load', 'getId', 'sendRequestGetFile'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);
        $oMockOrder->method('getId')->willReturn('1');
        $oMockOrder->method('sendRequestGetFile')->willReturn(true);
        $oMockOrder->oxorder__fcpomode = new Field('test');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('-1');
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals(null, $this->invokeMethod($oFcPayOneOrder, '_redownloadMandate', ['someFilename.pdf']));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDownload()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['fcpoGetMandateFilename', 'load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('fcpoGetMandateFilename')->willReturn('someFilename');
        $oMockOrder->method('load')->willReturn(true);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_redownloadMandate', 'fcpoGetInstance'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetInstance')->willReturn($oMockOrder);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoFileExists')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->download(true);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDownload_FileExists()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['fcpoGetMandateFilename', 'load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('fcpoGetMandateFilename')->willReturn('someFilename');
        $oMockOrder->method('load')->willReturn(true);

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_redownloadMandate', 'fcpoGetInstance'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->method('fcpoGetInstance')->willReturn($oMockOrder);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoFileExists')->willReturn(true);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->download(true);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDownload_NoFile()
    {
        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)
            ->setMethods(['_redownloadMandate'])
            ->disableOriginalConstructor()->getMock();

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('1');
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoFileExists')->willReturn(true);
        $this->invokeSetAttribute($oFcPayOneOrder, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneOrder->download(true);
    }

    public function testFcpoGetRequestMessage_Approved()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $aResponse['status'] = 'APPROVED';
        $this->invokeSetAttribute($oFcPayOneOrder, '_aResponse', $aResponse);
        $this->invokeSetAttribute($oFcPayOneOrder, '_sResponsePrefix', 'somePrefix');

        $sResponse = $sExpect = $oFcPayOneOrder->fcpoGetRequestMessage();

        $this->assertEquals($sExpect, $sResponse);
    }

    public function testFcpoGetRequestMessage_Error()
    {
        $oFcPayOneOrder = new FcPayOneOrder();
        $aResponse['status'] = 'ERROR';
        $this->invokeSetAttribute($oFcPayOneOrder, '_aResponse', $aResponse);
        $this->invokeSetAttribute($oFcPayOneOrder, '_sResponsePrefix', 'somePrefix');

        $sResponse = $sExpect = $oFcPayOneOrder->fcpoGetRequestMessage();

        $this->assertEquals($sExpect, $sResponse);
    }

    protected function _fcpoPrepareTransactionstatusTable()
    {
        $this->_fcpoTruncateTable('fcpotransactionstatus');
        $sQuery = "
            INSERT INTO `fcpotransactionstatus` (`OXID`, `OXTIMESTAMP`, `FCPO_ORDERNR`, `FCPO_KEY`, `FCPO_TXACTION`, `FCPO_PORTALID`, `FCPO_AID`, `FCPO_CLEARINGTYPE`, `FCPO_TXTIME`, `FCPO_CURRENCY`, `FCPO_USERID`, `FCPO_ACCESSNAME`, `FCPO_ACCESSCODE`, `FCPO_PARAM`, `FCPO_MODE`, `FCPO_PRICE`, `FCPO_TXID`, `FCPO_REFERENCE`, `FCPO_SEQUENCENUMBER`, `FCPO_COMPANY`, `FCPO_FIRSTNAME`, `FCPO_LASTNAME`, `FCPO_STREET`, `FCPO_ZIP`, `FCPO_CITY`, `FCPO_EMAIL`, `FCPO_COUNTRY`, `FCPO_SHIPPING_COMPANY`, `FCPO_SHIPPING_FIRSTNAME`, `FCPO_SHIPPING_LASTNAME`, `FCPO_SHIPPING_STREET`, `FCPO_SHIPPING_ZIP`, `FCPO_SHIPPING_CITY`, `FCPO_SHIPPING_COUNTRY`, `FCPO_BANKCOUNTRY`, `FCPO_BANKACCOUNT`, `FCPO_BANKCODE`, `FCPO_BANKACCOUNTHOLDER`, `FCPO_CARDEXPIREDATE`, `FCPO_CARDTYPE`, `FCPO_CARDPAN`, `FCPO_CUSTOMERID`, `FCPO_BALANCE`, `FCPO_RECEIVABLE`, `FCPO_CLEARING_BANKACCOUNTHOLDER`, `FCPO_CLEARING_BANKACCOUNT`, `FCPO_CLEARING_BANKCODE`, `FCPO_CLEARING_BANKNAME`, `FCPO_CLEARING_BANKBIC`, `FCPO_CLEARING_BANKIBAN`, `FCPO_CLEARING_LEGALNOTE`, `FCPO_CLEARING_DUEDATE`, `FCPO_CLEARING_REFERENCE`, `FCPO_CLEARING_INSTRUCTIONNOTE`) VALUES
            (1,	'2015-02-26 11:06:31',	23005,	'f053795653c9c136ae16c400104705fc',	'appointed',	2017762,	17102,	'cc',	'2015-02-26 11:04:01',	'EUR',	'63074422',	'',	'',	'',	'test',	33.8,	'156452317',	0,	0,	'Fatchip GmbH',	'Markus',	'Riedl',	'Helmholtzstr. 2-9',	'10587',	'Berlin',	'markus.riedl@fatchip.de',	'DE',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'1701',	'V',	'411111xxxxxx1111',	35645,	0,	0,	'',	'',	'',	'',	'',	'',	'',	'',	'',	''),
            (2,	'2015-03-05 14:06:05',	0,	'f053795653c9c136ae16c400104705fc',	'appointed',	2017762,	17102,	'wlt',	'2015-03-05 14:04:27',	'EUR',	'10262077',	'',	'',	'',	'test',	53.8,	'157116888',	0,	0,	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	13,	0,	0,	'',	'',	'',	'',	'',	'',	'',	'',	'',	''),
            (3,	'2015-03-05 14:12:11',	0,	'f053795653c9c136ae16c400104705fc',	'paid',	2017762,	17102,	'wlt',	'2015-03-05 14:04:27',	'EUR',	'10262077',	'',	'',	'',	'test',	53.8,	'157116888',	0,	0,	'PAYONE',	'Robert',	'Müller',	'Helmholtzstr. 2-9',	'10587',	'Berlin',	'robert.mueller@fatchip.de',	'DE',	'',	'Test',	'Buyer',	'ESpachstr. 1',	'79111',	'Freiburg',	'DE',	'',	'',	'',	'',	'',	'',	'',	13,	-53.8,	0,	'',	'',	'',	'',	'',	'',	'',	'',	'',	'');            
        ";
        DatabaseProvider::getDb()->execute($sQuery);
    }

    protected function _fcpoTruncateTable($sTableName)
    {
        $sQuery = "DELETE FROM `{$sTableName}` ";

        DatabaseProvider::getDb()->execute($sQuery);
    }
}
