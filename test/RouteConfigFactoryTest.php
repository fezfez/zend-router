<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\Route\Chain;
use Zend\Router\Route\Literal;
use Zend\Router\Route\Part;
use Zend\Router\RouteConfigFactory;
use Zend\Router\RouteInterface;
use Zend\Router\RoutePluginManager;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\ServiceManager\ServiceManager;

use function sprintf;

/**
 * @covers \Zend\Router\RouteConfigFactory
 */
class RouteConfigFactoryTest extends TestCase
{
    /**
     * @var RoutePluginManager
     */
    private $routes;

    /**
     * @var RouteConfigFactory
     */
    private $factory;

    public function setUp()
    {
        $this->routes = new RoutePluginManager(new ServiceManager());
        $this->factory = new RouteConfigFactory($this->routes);
    }

    public function testCreateFromArray()
    {
        $spec = [
            'type' => 'TestRoute',
            'options' => [
                'foo' => 'bar',
            ],
        ];
        $route = $this->prophesize(RouteInterface::class);

        $routeFactory = $this->prophesize(FactoryInterface::class);
        $routeFactory->__invoke(Argument::any(), 'TestRoute', $spec['options'])
                     ->shouldBeCalled()
                     ->willReturn($route->reveal());

        $this->routes->setFactory('TestRoute', $routeFactory->reveal());

        $returnedRoute = $this->factory->routeFromSpec($spec);
        $this->assertSame($route->reveal(), $returnedRoute);
    }

    public function testCreateFromRouteInstanceReturnsSameInstance()
    {
        $route = $this->prophesize(RouteInterface::class);
        $returnedRoute = $this->factory->routeFromSpec($route->reveal());
        $this->assertSame($route->reveal(), $returnedRoute);
    }

    public function testCreateFromNonArraySpecShouldThrow()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route definition must be an array');
        $this->factory->routeFromSpec(123);
    }

    public function testCreateRouteWithChained()
    {
        $spec = [
            'type' => Literal::class,
            'options' => [
                'route' => '/foo',
            ],
            'chain_routes' => [
                'chained' => [
                    'type' => Literal::class,
                    'options' => [
                        'route' => '/bar',
                    ],
                ],
            ],
        ];

        $chainRoute = $this->factory->routeFromSpec($spec);
        $this->assertInstanceOf(Chain::class, $chainRoute);

        $request = new ServerRequest([], [], new Uri('/foo/bar'));
        $this->assertTrue($chainRoute->match($request)->isSuccess());
    }

    public function testCreateRouteWithChainedWithNoRouteName()
    {
        $spec = [
            'type' => Literal::class,
            'options' => [
                'route' => '/foo',
            ],
            'chain_routes' => [
                [
                    'type' => Literal::class,
                    'options' => [
                        'route' => '/bar',
                    ],
                ],
                [
                    'type' => Literal::class,
                    'options' => [
                        'route' => '/baz',
                    ],
                ],
            ],
        ];

        $chainRoute = $this->factory->routeFromSpec($spec);
        $this->assertInstanceOf(Chain::class, $chainRoute);

        $request = new ServerRequest([], [], new Uri('/foo/bar/baz'));
        $this->assertTrue($chainRoute->match($request)->isSuccess());
    }

    public function testCreateRouteWithChildRoutes()
    {
        $spec = [
            'type' => Literal::class,
            'options' => [
                'route' => '/foo',
            ],
            'child_routes' => [
                'child' => [
                    'type' => Literal::class,
                    'options' => [
                        'route' => '/bar',
                    ],
                ],
            ],
        ];

        $partRoute = $this->factory->routeFromSpec($spec);
        $this->assertInstanceOf(Part::class, $partRoute);

        $request = new ServerRequest([], [], new Uri('/foo/bar'));
        $this->assertTrue($partRoute->match($request)->isSuccess());
    }

    public function testCreateRouteWithChainedAndChildRoutes()
    {
        $spec = [
            'type' => Literal::class,
            'options' => [
                'route' => '/foo',
            ],
            'chain_routes' => [
                'chained' => [
                    'type' => Literal::class,
                    'options' => [
                        'route' => '/bar',
                    ],
                ],
            ],
            'child_routes' => [
                'child' => [
                    'type' => Literal::class,
                    'options' => [
                        'route' => '/baz',
                    ],
                ],
            ],
        ];

        $partRoute = $this->factory->routeFromSpec($spec);
        $this->assertInstanceOf(Part::class, $partRoute);

        $request = new ServerRequest([], [], new Uri('/foo/bar/baz'));
        $this->assertTrue($partRoute->match($request)->isSuccess());
    }

    public function testCreateRouteFromPrototype()
    {
        $prototypeRoute = new Literal('/');
        $prototypes = ['test' => $prototypeRoute];

        $route = $this->factory->routeFromSpec('test', $prototypes);
        $this->assertSame($prototypeRoute, $route);
    }

    public function testCreateChildRouteFromPrototype()
    {
        $prototypeRoute = new Literal('/');
        $prototypes = ['test-prototype' => $prototypeRoute];

        $spec = [
            'type' => Literal::class,
            'options' => [
                'route' => '/',
            ],
            'child_routes' => [
                'test' => 'test-prototype',
            ],
        ];

        $route = $this->factory->routeFromSpec($spec, $prototypes);
        $this->assertInstanceOf(Part::class, $route);
        $this->assertSame($prototypeRoute, $route->getRoute('test'));
    }

    public function testCreateRouteFromNonExistantPrototypeShouldThrow()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not find prototype with name test');
        $this->factory->routeFromSpec('test', []);
    }

    public function testCreateRouteFromInvalidPrototypeShouldThrow()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Invalid prototype provided. Expected %s got %s',
            RouteInterface::class,
            'string'
        ));
        $this->factory->routeFromSpec('test', ['test' => Literal::class]);
    }
}
