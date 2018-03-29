<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Router\Container\RouteConfigFactoryFactory;
use Zend\Router\RouteConfigFactory;
use Zend\Router\RoutePluginManager;
use Zend\ServiceManager\ServiceManager;

/**
 * @covers \Zend\Router\Container\RouteConfigFactoryFactory
 */
class RouteConfigFactoryFactoryTest extends TestCase
{
    public function testCreates()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(RoutePluginManager::class)
            ->willReturn(new RoutePluginManager(new ServiceManager()))
            ->shouldBeCalled();
        $factory = new RouteConfigFactoryFactory();
        $service = $factory->__invoke($container->reveal());
        $this->assertInstanceOf(RouteConfigFactory::class, $service);
    }
}
