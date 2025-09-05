<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneLog;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;

class FcPayOneLogTest extends ConfigUnitTestCase
{
    public function testRender()
    {
        $oFcPayOneLog = new FcPayOneLog();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn('TheOxid');
        $oFcPoHelper->method('fcpoGetHelpUrl')->willReturn('someValue');
        $this->invokeSetAttribute($oFcPayOneLog, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('@fcpayone/admin/fcpayone_log', $this->invokeMethod($oFcPayOneLog, 'render'));
    }

    public function testGetStatus()
    {
        $oFcPayOneLog = new FcPayOneLog();

        $oOrder = new Order();
        $oOrder->oxorder__fcpotxid = new Field('156452317');

        $this->_fcpoPrepareTransactionTable();
        $aReturn = $oFcPayOneLog->getStatus($oOrder);

        $this->assertIsArray($aReturn);

        $this->_fcpoTruncateTransactionTable();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCapture()
    {
        $oFcPayOneLog = new FcPayOneLog();

        $oMockRequest = $this->getMockBuilder(FcPoRequest::class)
            ->setMethods(['sendRequestCapture'])
            ->disableOriginalConstructor()->getMock();
        $oMockRequest->method('sendRequestCapture')->willReturn([]);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturnOnConsecutiveCalls('1', '20');
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockRequest);
        $this->invokeSetAttribute($oFcPayOneLog, '_oFcPoHelper', $oFcPoHelper);

        $oFcPayOneLog->capture();
    }

    public function testGetCaptureMessage_approved()
    {
        $oFcPayOneLog = new FcPayOneLog();
        $aResponse['status'] = 'APPROVED';
        $this->invokeSetAttribute($oFcPayOneLog, '_aResponse', $aResponse);

        $aPossibleReturns = [
            '<span style="color: green;">FCPO_CAPTURE_APPROVED</span>',
            '<span style="color: green;">Buchung war erfolgreich</span>',
        ];

        $this->assertTrue(in_array($oFcPayOneLog->getCaptureMessage(), $aPossibleReturns));
    }

    public function testGetCaptureMessage_error()
    {
        $oFcPayOneLog = new FcPayOneLog();
        $aResponse['status'] = 'ERROR';
        $this->invokeSetAttribute($oFcPayOneLog, '_aResponse', $aResponse);

        $aPossibleReturns = [
            '<span style="color: red;">FCPO_CAPTURE_ERROR</span>',
            '<span style="color: red;">Fehler bei Buchung: </span>',
        ];

        $this->assertTrue(in_array($oFcPayOneLog->getCaptureMessage(), $aPossibleReturns));
    }

    /**
     * Creates some entries in fcpotransactionstatus table
     *
     * @param  void
     * @return void
     */
    protected function _fcpoPrepareTransactionTable()
    {
        $sQuery = "
            INSERT INTO `fcpotransactionstatus` (`OXID`, `OXTIMESTAMP`, `FCPO_ORDERNR`, `FCPO_KEY`, `FCPO_TXACTION`, `FCPO_PORTALID`, `FCPO_AID`, `FCPO_CLEARINGTYPE`, `FCPO_TXTIME`, `FCPO_CURRENCY`, `FCPO_USERID`, `FCPO_ACCESSNAME`, `FCPO_ACCESSCODE`, `FCPO_PARAM`, `FCPO_MODE`, `FCPO_PRICE`, `FCPO_TXID`, `FCPO_REFERENCE`, `FCPO_SEQUENCENUMBER`, `FCPO_COMPANY`, `FCPO_FIRSTNAME`, `FCPO_LASTNAME`, `FCPO_STREET`, `FCPO_ZIP`, `FCPO_CITY`, `FCPO_EMAIL`, `FCPO_COUNTRY`, `FCPO_SHIPPING_COMPANY`, `FCPO_SHIPPING_FIRSTNAME`, `FCPO_SHIPPING_LASTNAME`, `FCPO_SHIPPING_STREET`, `FCPO_SHIPPING_ZIP`, `FCPO_SHIPPING_CITY`, `FCPO_SHIPPING_COUNTRY`, `FCPO_BANKCOUNTRY`, `FCPO_BANKACCOUNT`, `FCPO_BANKCODE`, `FCPO_BANKACCOUNTHOLDER`, `FCPO_CARDEXPIREDATE`, `FCPO_CARDTYPE`, `FCPO_CARDPAN`, `FCPO_CUSTOMERID`, `FCPO_BALANCE`, `FCPO_RECEIVABLE`, `FCPO_CLEARING_BANKACCOUNTHOLDER`, `FCPO_CLEARING_BANKACCOUNT`, `FCPO_CLEARING_BANKCODE`, `FCPO_CLEARING_BANKNAME`, `FCPO_CLEARING_BANKBIC`, `FCPO_CLEARING_BANKIBAN`, `FCPO_CLEARING_LEGALNOTE`, `FCPO_CLEARING_DUEDATE`, `FCPO_CLEARING_REFERENCE`, `FCPO_CLEARING_INSTRUCTIONNOTE`) VALUES
            (1,	'2015-02-26 11:06:31',	23005,	'f053795653c9c136ae16c400104705fc',	'appointed',	2017762,	17102,	'cc',	'2015-02-26 11:04:01',	'EUR',	'63074422',	'',	'',	'',	'test',	33.8,	'156452317',	0,	0,	'Fatchip GmbH',	'Markus',	'Riedl',	'Helmholtzstr. 2-9',	'10587',	'Berlin',	'markus.riedl@fatchip.de',	'DE',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'1701',	'V',	'411111xxxxxx1111',	35645,	0,	0,	'',	'',	'',	'',	'',	'',	'',	'',	'',	''),
            (2,	'2015-03-05 14:06:05',	0,	'f053795653c9c136ae16c400104705fc',	'appointed',	2017762,	17102,	'wlt',	'2015-03-05 14:04:27',	'EUR',	'10262077',	'',	'',	'',	'test',	53.8,	'157116888',	0,	0,	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	'',	13,	0,	0,	'',	'',	'',	'',	'',	'',	'',	'',	'',	''),
            (3,	'2015-03-05 14:12:11',	0,	'f053795653c9c136ae16c400104705fc',	'paid',	2017762,	17102,	'wlt',	'2015-03-05 14:04:27',	'EUR',	'10262077',	'',	'',	'',	'test',	53.8,	'157116888',	0,	0,	'PAYONE',	'Robert',	'Müller',	'Helmholtzstr. 2-9',	'10587',	'Berlin',	'robert.mueller@fatchip.de',	'DE',	'',	'Test',	'Buyer',	'ESpachstr. 1',	'79111',	'Freiburg',	'DE',	'',	'',	'',	'',	'',	'',	'',	13,	-53.8,	0,	'',	'',	'',	'',	'',	'',	'',	'',	'',	'');            
        ";

        DatabaseProvider::getDb()->execute($sQuery);
    }


    /**
     * Truncates fcpotransactionstatus table
     *
     * @param  void
     * @return void
     */
    protected function _fcpoTruncateTransactionTable()
    {
        $sQuery = "DELETE FROM `fcpotransactionstatus` ";

        DatabaseProvider::getDb()->execute($sQuery);
    }
}
