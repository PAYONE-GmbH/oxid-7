<?php

namespace Fatchip\PayOne\Tests\Unit;

use OxidEsales\TestingLibrary\UnitTestCase;

class FcBaseUnitTestCase extends UnitTestCase
{
    public function invokeSetAttribute(&$object, $propertyName, $value)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        $property->setValue($object, $value);
    }

    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function wrapExpectException($param) {
        if(method_exists($this, 'expectException')) {
            $this->expectException($param);
        }

        if(method_exists($this, 'setExpectedException')) {
            $this->setExpectedException($param);
        }
    }

    public function wrapAssertStringContainsString($needle, $haystack, $message = '')
    {
        if(method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString($needle, $haystack, $message);
        } else {
            $this->assertContains($needle, $haystack, $message, false);
        }
    }

    public function wrapAssertStringContainsStringIgnoringCase($needle, $haystack, $message = '')
    {
        if(method_exists($this, 'assertStringContainsStringIgnoringCase')) {
            $this->assertStringContainsStringIgnoringCase($needle, $haystack, $message);
        } else {
            $this->assertContains($needle, $haystack, $message, true);
        }
    }

    public function testNothing()
    {
        $this->assertNull(null);
    }
}