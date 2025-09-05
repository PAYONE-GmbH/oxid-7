<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Model;

use Fatchip\PayOne\Application\Model\FcPayOneOrder;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;

class FcPayOneOrderTest extends ConfigUnitTestCase
{
    public function testIsPayOnePaymentType()
    {
        $oFcPayOneOrder = new FcPayOneOrder();

        $this->assertFalse($oFcPayOneOrder->isPayOnePaymentType('oxidinvoice'));
        $this->assertTrue($oFcPayOneOrder->isPayOnePaymentType('fcpocreditcard'));
    }
}