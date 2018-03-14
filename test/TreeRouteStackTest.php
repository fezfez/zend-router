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
use ReflectionClass;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\PriorityList;
use Zend\Router\Route\Hostname;
use Zend\Router\Route\Literal;
use Zend\Router\Route\Method;
use Zend\Router\Route\Part;
use Zend\Router\Route\Scheme;
use Zend\Router\RouteInterface;
use Zend\Router\RouteResult;
use Zend\Router\TreeRouteStack;

/**
 * @covers \Zend\Router\TreeRouteStack
 */
class TreeRouteStackTest extends TestCase
{
    public function testAssemble()
    {
        $uri = new Uri();
        $stack = new TreeRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRoute());
        $this->assertEquals('', $stack->assemble($uri, [], ['name' => 'foo'])->getPath());
    }

    public function testAssembleCanonicalUriWithHostnameRoute()
    {
        $stack = new TreeRouteStack();
        $stack->addRoute('foo', new Hostname('example.com'));
        $uri = new Uri();
        $uri = $uri->withScheme('http');

        $this->assertEquals(
            'http://example.com',
            $stack->assemble($uri, [], ['name' => 'foo'])->__toString()
        );
    }

    public function testAssembleCanonicalUriWithHostnameRouteWithoutScheme()
    {
        $stack = new TreeRouteStack();
        $stack->addRoute('foo', new Hostname('example.com'));
        $uri = new Uri();

        $this->assertEquals(
            '//example.com',
            $stack->assemble($uri, [], ['name' => 'foo'])->__toString()
        );
    }

    public function testAssembleWithEncodedPath()
    {
        $uri = new Uri();
        $stack = new TreeRouteStack();
        $stack->addRoute('index', new Literal('/this%2Fthat'));

        $this->assertEquals('/this%2Fthat', $stack->assemble($uri, [], ['name' => 'index'])->getPath());
    }

    public function testAssembleWithScheme()
    {
        $uri = new Uri();
        $uri = $uri->withScheme('http');
        $uri = $uri->withHost('example.com');
        $stack = new TreeRouteStack();
        $stack->addRoute(
            'secure',
            Part::factory([
                'route' => new Scheme('https'),
                'child_routes' => [
                    'index' => new Literal('/'),
                ],
            ])
        );
        $this->assertEquals(
            'https://example.com/',
            $stack->assemble($uri, [], ['name' => 'secure/index'])->__toString()
        );
    }

    public function testAssembleWithoutNameOption()
    {
        $uri = new Uri();
        $stack = new TreeRouteStack();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "name" option');
        $stack->assemble($uri);
    }

    public function testAssembleNonExistentRoute()
    {
        $uri = new Uri();
        $stack = new TreeRouteStack();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Route with name "foo" not found');
        $stack->assemble($uri, [], ['name' => 'foo']);
    }

    public function testAssembleNonExistentChildRoute()
    {
        $uri = new Uri();
        $stack = new TreeRouteStack();
        $stack->addRoute('index', new Literal('/'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Route with name "index" does not have child routes');
        $stack->assemble($uri, [], ['name' => 'index/foo']);
    }

    public function testDefaultParamIsAddedToMatch()
    {
        $stack = new TreeRouteStack();
        $request = new ServerRequest();
        $route = $this->prophesize(RouteInterface::class);
        $route->match($request, Argument::any(), Argument::any())
              ->willReturn(RouteResult::fromRouteMatch([]));
        $stack->addRoute('foo', $route->reveal());
        $stack->setDefaultParam('foo', 'bar');

        $result = $stack->match($request);
        $this->assertTrue($result->isSuccess());
        $this->assertArraySubset(['foo' => 'bar'], $result->getMatchedParams());
    }

    public function testMethodFailureVerbsAreCombined()
    {
        $stack = new TreeRouteStack();
        $stack->addRoute('foo', new Method('POST,DELETE'));
        $stack->addRoute('bar', new Method('GET,POST'));

        $request = new ServerRequest([], [], new Uri('/'), 'PUT');
        $result = $stack->match($request, 1);
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['GET', 'POST', 'DELETE'], $result->getAllowedMethods());
    }

    public function testRoutingFailure()
    {
        $stack = new TreeRouteStack();
        $stack->addRoute('foo', new Literal('/foo'));

        $request = new ServerRequest([], [], new Uri('/bar'));
        $result = $stack->match($request);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testDefaultParamDoesNotOverrideMatchParam()
    {
        $stack = new TreeRouteStack();
        $route = $this->prophesize(RouteInterface::class);
        $route->match(Argument::any(), Argument::any(), Argument::any())
              ->willReturn(RouteResult::fromRouteMatch(['foo' => 'bar']));
        $stack->addRoute('foo', $route->reveal());
        $stack->setDefaultParam('foo', 'baz');

        $result = $stack->match(new ServerRequest());
        $this->assertTrue($result->isSuccess());
        $this->assertArraySubset(['foo' => 'bar'], $result->getMatchedParams());
    }

    public function testDefaultParamIsUsedForAssembling()
    {
        $uri = new Uri();
        $stack = new TreeRouteStack();
        $route = $this->prophesize(RouteInterface::class);
        $route->assemble($uri, ['foo' => 'bar'], [])
              ->shouldBeCalled();
        $stack->addRoute('foo', $route->reveal());
        $stack->setDefaultParam('foo', 'bar');

        $stack->assemble($uri, [], ['name' => 'foo']);
    }

    public function testDefaultParamDoesNotOverrideParamForAssembling()
    {
        $uri = new Uri();
        $stack = new TreeRouteStack();
        $route = $this->prophesize(RouteInterface::class);
        $route->assemble($uri, ['foo' => 'bar'], [])
              ->shouldBeCalled();
        $stack->addRoute('foo', $route->reveal());
        $stack->setDefaultParam('foo', 'baz');

        $stack->assemble($uri, ['foo' => 'bar'], ['name' => 'foo']);
    }

    public function testPriorityIsPassedToPartRoute()
    {
        $stack = new TreeRouteStack();
        $stack->addRoute('foo', Part::factory([
            'route' => new Literal('/foo', ['controller' => 'foo']),
            'may_terminate' => true,
            'child_routes' => [
                'bar' => new Literal('/bar', ['controller' => 'foo', 'action' => 'bar']),
            ],
        ]), 1000);

        $reflectedClass = new ReflectionClass($stack);
        $reflectedProperty = $reflectedClass->getProperty('routes');
        $reflectedProperty->setAccessible(true);
        $routes = $reflectedProperty->getValue($stack);

        $this->assertEquals(1000, $routes->toArray(PriorityList::EXTR_PRIORITY)['foo']);
    }
}
