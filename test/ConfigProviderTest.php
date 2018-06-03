<?php
declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Zend\Router\ConfigProvider;

/**
 * @covers \Zend\Router\ConfigProvider
 */
class ConfigProviderTest extends TestCase
{

    public function testInvokeReturnArray()
    {
        $sUT = new ConfigProvider();

        $this->assertInternalType('array', $sUT->__invoke());
    }

    public function testGetRouteManagerConfigReturnArray()
    {
        $sUT = new ConfigProvider();

        $this->assertInternalType('array', $sUT->getRouteManagerConfig());
    }

    public function testGetDependencyConfigReturnArray()
    {
        $sUT = new ConfigProvider();

        $this->assertInternalType('array', $sUT->getDependencyConfig());
    }
}
