<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;
use Zend\Router\Container\RouterFactory;
use Zend\Router\Route\Literal;
use Zend\Router\Route\Method;
use Zend\Router\RouteConfigFactory;
use Zend\Router\RoutePluginManager;
use Zend\Router\RouteStackInterface;
use Zend\Router\SimpleRouteStack;
use Zend\Router\TreeRouteStack;
use Zend\ServiceManager\ServiceManager;

/**
 * @covers \Zend\Router\Container\RouterFactory
 */
class RouterFactoryTest extends TestCase
{
    /**
     * @var ContainerInterface|ObjectProphecy
     */
    private $container;

    public function setUp()
    {
        $routeFactory = new RouteConfigFactory(new RoutePluginManager(new ServiceManager()));
        $uriFactory = function () {
            return new Uri();
        };
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container->get(RouteConfigFactory::class)
                        ->willReturn($routeFactory);
        $this->container->get(UriInterface::class)
                        ->willReturn($uriFactory);
    }

    public function testGetRouteStackInstantiatesTreeRouteStack()
    {
        $factory = new RouterFactory();
        $routeStack = $factory->getRouteStack($this->container->reveal());
        $this->assertInstanceOf(TreeRouteStack::class, $routeStack);
    }

    public function testConfigureRouterUsesConfigProvidedByGetRouterConfig()
    {
        $factory = new class() extends RouterFactory {
            public function getRouterConfig(ContainerInterface $container) : array
            {
                return [
                    'routes' => [
                        'test-route' => new Literal('/'),
                        'test-prototype' => 'prototype',
                    ],
                    'prototypes' => [
                        'prototype' => new Method('GET'),
                    ],
                ];
            }
        };
        $router = $factory->__invoke($this->container->reveal());
        $this->assertInstanceOf(
            Method::class,
            $router->getPrototype('prototype')
        );
        $this->assertInstanceOf(
            Literal::class,
            $router->getRouteStack()->getRoute('test-route')
        );
        $this->assertInstanceOf(
            Method::class,
            $router->getRouteStack()->getRoute('test-prototype')
        );
    }

    public function testCreatesRouterWithRouteStackReturnedByGetRouteStack()
    {
        $factory = new class() extends RouterFactory {
            public function getRouteStack(ContainerInterface $container) : RouteStackInterface
            {
                return new SimpleRouteStack();
            }
        };

        $router = $factory->__invoke($this->container->reveal());
        $this->assertInstanceOf(SimpleRouteStack::class, $router->getRouteStack());
    }

    public function testGetRouterConfigReturnsDefaultEmptyConfig()
    {
        $this->container->get(Argument::any())->shouldNotBeCalled();
        $factory = new RouterFactory();
        $config = $factory->getRouterConfig($this->container->reveal());
        $this->assertEquals(['routes' => [], 'prototypes' => []], $config);
    }
}
