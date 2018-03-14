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
use Zend\Router\Route\Literal;
use Zend\Router\RouteConfigFactory;
use Zend\Router\RouteInterface;
use Zend\Router\RoutePluginManager;
use Zend\Router\Router;
use Zend\Router\RouteResult;
use Zend\Router\RouteStackInterface;
use Zend\Router\SimpleRouteStack;
use Zend\Router\TreeRouteStack;
use Zend\ServiceManager\ServiceManager;

/**
 * @covers \Zend\Router\Router
 */
class RouterTest extends TestCase
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @var RouteConfigFactory
     */
    private $routeFactory;

    /**
     * @var RouteStackInterface
     */
    private $routeStack;

    public function setUp()
    {
        $uriFactory = function () {
            return new Uri();
        };
        $this->routeFactory = new RouteConfigFactory(new RoutePluginManager(new ServiceManager()));
        $this->routeStack = new TreeRouteStack();
        $this->router = new Router($this->routeFactory, $this->routeStack, $uriFactory);
    }

    public function testGetRouteFactoryReturnsComposedFactory()
    {
        $factory = $this->router->getRouteFactory();
        $this->assertSame($this->routeFactory, $factory);
    }

    public function testGetRouteStackReturnsComposedRouteStack()
    {
        $routeStack = $this->router->getRouteStack();
        $this->assertSame($this->routeStack, $routeStack);
    }

    public function testSetRouteStackReplacesRouteStack()
    {
        $routeStack = new SimpleRouteStack();
        $this->router->setRouteStack($routeStack);
        $this->assertSame($routeStack, $this->router->getRouteStack());
    }

    public function testAddRouteAddsToUnderlyingRouteStack()
    {
        $route = $this->prophesize(RouteInterface::class);
        $this->router->addRoute('test', $route->reveal());
        $routeStack = $this->router->getRouteStack();
        $this->assertTrue($routeStack->hasRoute('test'));
        $this->assertSame($route->reveal(), $routeStack->getRoute('test'));
    }

    public function testAddRouteCreatesRouteFromConfig()
    {
        $spec = [
            'type' => Literal::class,
            'options' => [
                'route' => '/',
            ],
        ];
        $this->router->addRoute('test', $spec);
        $routeStack = $this->router->getRouteStack();
        $this->assertTrue($routeStack->hasRoute('test'));
        $this->assertInstanceOf(Literal::class, $routeStack->getRoute('test'));
    }

    public function testByDefaultNoPrototypesRegistered()
    {
        $prototypes = $this->router->getPrototypes();
        $this->assertEmpty($prototypes);
    }

    public function testAddPrototypeWithRouteAddsPrototype()
    {
        $route = $this->prophesize(RouteInterface::class);
        $this->router->addPrototype('test', $route->reveal());
        $this->assertSame($route->reveal(), $this->router->getPrototype('test'));
    }

    public function testAddPrototypeAsSpecCreatesRouteAndAddsAsPrototype()
    {
        $spec = [
            'type' => Literal::class,
            'options' => [
                'route' => '/',
            ],
        ];
        $this->router->addPrototype('test', $spec);
        $route = $this->router->getPrototype('test');
        $this->assertInstanceOf(Literal::class, $route);
    }

    public function testGetPrototypesReturnsRegisteredPrototypes()
    {
        $route = $this->prophesize(RouteInterface::class);
        $this->router->addPrototype('test', $route->reveal());
        $prototypes = $this->router->getPrototypes();
        $this->assertEquals(['test' => $route->reveal()], $prototypes);
    }

    public function testAddRouteAsSpecUsesRegisteredPrototypes()
    {
        $route = $this->prophesize(RouteInterface::class);
        $this->router->addPrototype('testPrototype', $route->reveal());

        $this->router->addRoute('test', 'testPrototype');
        $this->assertSame(
            $route->reveal(),
            $this->router->getRouteStack()->getRoute('test')
        );
    }

    public function testAddRoutePassesRouteConfigToRouteFactory()
    {
        $spec = [
            'type' => Literal::class,
            'options' => [
                'route' => '/',
            ],
        ];
        $routeFactory = $this->prophesize(RouteConfigFactory::class);
        $routeFactory->routeFromSpec($spec, [])
                     ->shouldBecalled()
                     ->willReturn($this->prophesize(RouteInterface::class)->reveal());
        $uriFactory = function () {
            return new Uri();
        };
        $router = new Router($routeFactory->reveal(), new TreeRouteStack(), $uriFactory);
        $router->addRoute('test', $spec);
    }

    public function testProxiesMatchToUnderlyingRouteStackAndReturnsItsResult()
    {
        $request = new ServerRequest();
        $expectedResult = RouteResult::fromRouteFailure();
        $routeStack = $this->prophesize(RouteStackInterface::class);
        $routeStack->match($request, 0)
                   ->shouldBeCalled()
                   ->willReturn($expectedResult);

        $this->router->setRouteStack($routeStack->reveal());

        $result = $this->router->match($request);

        $this->assertSame($expectedResult, $result);
    }

    public function testProxiesAssembleToUnderlyingRouteStackAndReturnsItsResult()
    {
        $uri = new Uri();
        $route = $this->prophesize(RouteInterface::class);
        $route->assemble(Argument::any(), ['foo' => 'bar'], ['baz' => 'qux'])
              ->willReturn($uri)
              ->shouldBeCalled();

        $this->router->addRoute('test', $route->reveal());

        $returnedUri = $this->router->assemble('test', ['foo' => 'bar'], ['baz' => 'qux']);
        $this->assertSame($uri, $returnedUri);
    }

    public function testAssembleUsesUriClosureFactoryToCreateUriAndPassToRouteStackAssemble()
    {
        $uri = new Uri();
        $routeStack = $this->prophesize(RouteStackInterface::class);
        $routeStack->assemble($uri, [], ['name' => 'test'])
                   ->shouldBeCalled();

        $uriFactory = function () use ($uri) {
            return $uri;
        };
        $router = new Router($this->routeFactory, $routeStack->reveal(), $uriFactory);
        $router->assemble('test', [], []);
    }
}
