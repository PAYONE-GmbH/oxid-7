<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPoTransactionStatus;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Language;

class FcPoTransactionStatusTest extends FcBaseUnitTestCase
{
    public function testGetAction()
    {
        $oFcPoTransactionStatus = new FcPoTransactionStatus();
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_txaction = new Field('paid');
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_txreceivable = new Field(10);
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_balance = new Field(-20);

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someTranslation');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPoTransactionStatus, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $oFcPoTransactionStatus->getAction();
        $this->assertEquals('someTranslation', $sResponse);
    }

    public function testGetClearingtype()
    {
        $oMockOrder = new Order();
        $oMockOrder->oxorder__oxpaymenttype = new Field('somePaymentType');

        $oFcPoTransactionStatus = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['_fcpoGetOrderByTxid'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoTransactionStatus->method('_fcpoGetOrderByTxid')->willReturn($oMockOrder);
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_txid = new Field('someTxid');
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_clearingtype = new Field('fnc');

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someTranslation');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPoTransactionStatus, '_oFcPoHelper', $oFcPoHelper);

        $sResponse = $oFcPoTransactionStatus->getClearingtype();
        $this->assertEquals('someTranslation', $sResponse);
    }

    public function testFcpoGetOrderByTxid()
    {
        $oFcPoTransactionStatus = new FcPoTransactionStatus();

        $oMockDatabase = $this->getMockBuilder(DatabaseProvider::getDb()::class)
            ->setMethods(['GetOne'])
            ->disableOriginalConstructor()->getMock();
        $oMockDatabase->method('GetOne')->willReturn('someOxid');
        $this->invokeSetAttribute($oFcPoTransactionStatus, '_oFcPoDb', $oMockDatabase);

        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['load'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('load')->willReturn(true);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('getFactoryObject')->willReturn($oMockOrder);
        $this->invokeSetAttribute($oFcPoTransactionStatus, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($oMockOrder, $this->invokeMethod($oFcPoTransactionStatus, '_fcpoGetOrderByTxid', ['someTxid']));
    }

    public function testGetCardtype()
    {
        $oFcPoTransactionStatus = new FcPoTransactionStatus();
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_cardtype = new Field('B');

        $this->assertEquals('Carte Bleue', $oFcPoTransactionStatus->getCardtype());
    }

    public function testGetDisplayNameReceivable()
    {
        $oFcPoTransactionStatus = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['_fcpoGetLangIdent', '_fcpoGetMapAction'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoTransactionStatus->method('_fcpoGetLangIdent')->willReturn('someTranslationIdent');
        $oFcPoTransactionStatus->method('_fcpoGetMapAction')->willReturn('someLangIdent');
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_txid = new Field('someTxid');
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_clearingtype = new Field('fnc');
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_txaction = new Field('appointed');

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someTranslation');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPoTransactionStatus, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someTranslation', $oFcPoTransactionStatus->getDisplayNameReceivable(100));
    }

    public function testGetDisplayNamePayment()
    {
        $oFcPoTransactionStatus = $this->getMockBuilder(FcPoTransactionStatus::class)
            ->setMethods(['_fcpoGetLangIdent', '_fcpoGetMapAction'])
            ->disableOriginalConstructor()->getMock();
        $oFcPoTransactionStatus->method('_fcpoGetLangIdent')->willReturn('someTranslationIdent');
        $oFcPoTransactionStatus->method('_fcpoGetMapAction')->willReturn('someLangIdent');
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_txid = new Field('someTxid');
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_clearingtype = new Field('fnc');
        $oFcPoTransactionStatus->fcpotransactionstatus__fcpo_txaction = new Field('appointed');

        $oMockLang = $this->getMockBuilder(Language::class)
            ->setMethods(['translateString'])
            ->disableOriginalConstructor()->getMock();
        $oMockLang->method('translateString')->willReturn('someTranslation');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetLang')->willReturn($oMockLang);
        $this->invokeSetAttribute($oFcPoTransactionStatus, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals('someTranslation', $oFcPoTransactionStatus->getDisplayNamePayment(100));
    }

    public function testFcpoGetLangIdent()
    {
        $oFcPoTransactionStatus = new FcPoTransactionStatus();

        $sResponse = $this->invokeMethod($oFcPoTransactionStatus, '_fcpoGetLangIdent', [100, 'Option1', 'Option2']);

        $this->assertEquals('Option1', $sResponse);
    }

    public function testFcpoGetMapAction()
    {
        $sMockTxAction = 'someTxAction';
        $aMockMatchMap = [$sMockTxAction => 'someAssignment'];

        $oFcPoTransactionStatus = new FcPoTransactionStatus();

        $sResponse = $this->invokeMethod($oFcPoTransactionStatus, '_fcpoGetMapAction', [$sMockTxAction, $aMockMatchMap, 'someDefaultValue']);

        $this->assertEquals('someAssignment', $sResponse);
    }
}