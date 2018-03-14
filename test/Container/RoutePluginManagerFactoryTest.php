<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Container;

use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Zend\Router\Container\RoutePluginManagerFactory;
use Zend\Router\Route\Literal;
use Zend\Router\RouteInterface;
use Zend\Router\RoutePluginManager;

/**
 * @covers \Zend\Router\Container\RoutePluginManagerFactory
 */
class RoutePluginManagerFactoryTest extends TestCase
{
    /**
     * @var ContainerInterface|ObjectProphecy
     */
    private $container;

    /**
     * @var RoutePluginManagerFactory
     */
    private $factory;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->factory = new RoutePluginManagerFactory();
    }

    public function testInvocationReturnsAPluginManager()
    {
        $plugins = $this->factory->__invoke($this->container->reveal(), RoutePluginManager::class);
        $this->assertInstanceOf(RoutePluginManager::class, $plugins);
    }

    public function testUsesRouteManagerConfigFromContainerWhenProvided()
    {
        $route = new Literal('/');
        $factory = function () use ($route) {
            return $route;
        };
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([
            RoutePluginManager::class => [
                'factories' => [
                    'test' => $factory,
                ],
            ],
        ]);
        $routes = $this->factory->__invoke($this->container->reveal(), RoutePluginManager::class);
        $this->assertSame($route, $routes->get('test'));
    }

    public function testInvocationCanProvideOptionsToThePluginManager()
    {
        $options = [
            'factories' => [
                'TestRoute' => function ($container) {
                    return $this->prophesize(RouteInterface::class)->reveal();
                },
            ],
        ];
        $plugins = $this->factory->__invoke(
            $this->container->reveal(),
            RoutePluginManager::class,
            $options
        );
        $this->assertInstanceOf(RoutePluginManager::class, $plugins);
        $route = $plugins->get('TestRoute');
        $this->assertInstanceOf(RouteInterface::class, $route);
    }
}
