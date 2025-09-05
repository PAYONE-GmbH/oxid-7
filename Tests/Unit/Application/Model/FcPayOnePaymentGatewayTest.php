<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPayOneOrder;
use Fatchip\PayOne\Application\Model\FcPayOnePaymentGateway;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\TestingLibrary\UnitTestCase;

class FcPayOnePaymentGatewayTest extends UnitTestCase
{
    public function testExecutePaymentNotPayone()
    {
        $oFcPayOnePaymentGateway = new FcPayOnePaymentGateway();

        $oFcPayOneOrder = $this->getMockBuilder(FcPayOneOrder::class)->disableOriginalConstructor()->getMock();
        $oFcPayOneOrder->oxorder__oxpaymenttype = new Field("oxidpaypal");

        $blResult = $oFcPayOnePaymentGateway->executePayment(50, $oFcPayOneOrder);

        $this->assertTrue($blResult);
    }
}