<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Model;

use Fatchip\PayOne\Application\Helper\Payment as FcPayonePaymentHelper;
use Fatchip\PayOne\Tests\Unit\FcBaseUnitTestCase;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\UtilsObject;

class FcpoPaymentHelperTest extends FcBaseUnitTestCase
{
    public function testLoadPaymentMethodFalse()
    {
        $oPayment = $this->getMockBuilder(Payment::class)->disableOriginalConstructor()->getMock();
        $oPayment->method('load')->willReturn(false);

        UtilsObject::setClassInstance(Payment::class, $oPayment);

        $oPaymentHelper = new FcPayonePaymentHelper();
        $sResult = $oPaymentHelper->loadPaymentMethod('test');

        $this->assertFalse($sResult);

        UtilsObject::resetClassInstances();
    }

    public function testIsPaymentMethodActive()
    {
        $oPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load', '__get'])
            ->disableOriginalConstructor()->getMock();
        $oPayment->method('load')->willReturn(true);
        $oPayment->method('__get')->willReturn(new Field(true));

        UtilsObject::setClassInstance(Payment::class, $oPayment);

        $oPaymentHelper = new FcPayonePaymentHelper();
        $sResult = $oPaymentHelper->isPaymentMethodActive('test');

        $this->assertTrue($sResult);

        UtilsObject::resetClassInstances();
    }

    public function testIsLiveMode()
    {
        $oPayment = $this->getMockBuilder(Payment::class)
            ->setMethods(['load', '__get'])
            ->disableOriginalConstructor()->getMock();
        $oPayment->method('load')->willReturn(true);
        $oPayment->method('__get')->willReturn(new Field(true));

        UtilsObject::setClassInstance(Payment::class, $oPayment);

        $oPaymentHelper = new FcPayonePaymentHelper();
        $sResult = $oPaymentHelper->isLiveMode('testValue');

        $this->assertTrue($sResult);

        UtilsObject::resetClassInstances();
    }
}
