<?php
declare(strict_types=1);

namespace ZendTest\Router;

use Zend\Router\Module;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Zend\Router\Module
 */
class ModuleTest extends TestCase
{
    public function testReturnConfig()
    {
        $sUT = new Module();
        $config = $sUT->getConfig();

        $this->assertInternalType('array',  $config);
        $this->assertArrayHasKey('service_manager', $config);
        $this->assertArrayHasKey('route_manager', $config);
        $this->assertArrayHasKey('router', $config);
    }
}
