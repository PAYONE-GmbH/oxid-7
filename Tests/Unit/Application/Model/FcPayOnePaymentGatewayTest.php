<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPayOnePaymentGateway;
use OxidEsales\Eshop\Application\Model\Order;

class FcPayOnePaymentGatewayTest extends FcBaseUnitTestCase
{
    public function testExecutePayment_Parent()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['isPayOnePaymentType'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('isPayOnePaymentType')->willReturn(false);

        $oFcPayOnePaymentGateway = new FcPayOnePaymentGateway();
        $mResult = $mExpect = $oFcPayOnePaymentGateway->executePayment(1, $oMockOrder);

        $this->assertEquals($mExpect, $mResult);
    }

    public function testExecutePayment()
    {
        $oMockOrder = $this->getMockBuilder(Order::class)
            ->setMethods(['isPayOnePaymentType', 'fcHandleAuthorization'])
            ->disableOriginalConstructor()->getMock();
        $oMockOrder->method('isPayOnePaymentType')->willReturn(true);
        $oMockOrder->method('fcHandleAuthorization')->willReturn(true);

        $oFcPayOnePaymentGateway = new FcPayOnePaymentGateway();

        $this->assertEquals(true, $oFcPayOnePaymentGateway->executePayment(1, $oMockOrder));
    }

    public function testFcpoSetLastErrorNr()
    {
        $oFcPayOnePaymentGateway = new FcPayOnePaymentGateway();
        $oFcPayOnePaymentGateway->fcpoSetLastErrorNr(1);
        $this->assertEquals(1, $oFcPayOnePaymentGateway->getLastErrorNo());
    }

    public function testFcpoSetLastError()
    {
        $oFcPayOnePaymentGateway = new FcPayOnePaymentGateway();
        $oFcPayOnePaymentGateway->fcpoSetLastError('someError');
        $this->assertEquals('someError', $oFcPayOnePaymentGateway->getLastError());

    }
}