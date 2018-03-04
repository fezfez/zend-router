<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\SimpleRouteStack;

/**
 * @covers \Zend\Router\SimpleRouteStack
 */
class SimpleRouteStackTest extends TestCase
{
    public function testAddRoutes()
    {
        $stack = new SimpleRouteStack();
        $stack->addRoutes([
            'foo' => new TestAsset\DummyRoute(),
        ]);

        $this->assertTrue($stack->match(new ServerRequest())->isSuccess());
    }

    public function testSetRoutes()
    {
        $stack = new SimpleRouteStack();
        $stack->setRoutes([
            'foo' => new TestAsset\DummyRoute(),
        ]);

        $this->assertTrue($stack->match(new ServerRequest())->isSuccess());

        $stack->setRoutes([]);

        $this->assertFalse($stack->match(new ServerRequest())->isSuccess());
    }

    public function testRemoveRoute()
    {
        $stack = new SimpleRouteStack();
        $stack->addRoutes([
            'foo' => new TestAsset\DummyRoute(),
        ]);

        $stack->removeRoute('foo');
        $this->assertFalse($stack->match(new ServerRequest())->isSuccess());
    }

    public function testAddRouteWithPriority()
    {
        $stack = new SimpleRouteStack();

        $route = new TestAsset\DummyRouteWithParam();
        $route->priority = 2;
        $stack->addRoute('baz', $route);

        $stack->addRoute('foo', new TestAsset\DummyRoute(), 1);

        $result = $stack->match(new ServerRequest());
        $this->assertTrue($result->isSuccess());
        $this->assertArraySubset(['foo' => 'bar'], $result->getMatchedParams());
    }

    public function testAssemble()
    {
        $uri = new Uri();
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRoute());
        $this->assertEquals('', $stack->assemble($uri, [], ['name' => 'foo'])->getPath());
    }

    public function testAssembleWithoutNameOption()
    {
        $uri = new Uri();
        $stack = new SimpleRouteStack();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "name" option');
        $stack->assemble($uri);
    }

    public function testAssembleNonExistentRoute()
    {
        $uri = new Uri();
        $stack = new SimpleRouteStack();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Route with name "foo" not found');
        $stack->assemble($uri, [], ['name' => 'foo']);
    }

    public function testDefaultParamIsAddedToMatch()
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRoute());
        $stack->setDefaultParam('foo', 'bar');

        $result = $stack->match(new ServerRequest());
        $this->assertTrue($result->isSuccess());
        $this->assertArraySubset(['foo' => 'bar'], $result->getMatchedParams());
    }

    public function testDefaultParamDoesNotOverrideParam()
    {
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRouteWithParam());
        $stack->setDefaultParam('foo', 'baz');

        $result = $stack->match(new ServerRequest());
        $this->assertTrue($result->isSuccess());
        $this->assertArraySubset(['foo' => 'bar'], $result->getMatchedParams());
    }

    public function testDefaultParamIsUsedForAssembling()
    {
        $uri = new Uri();
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRouteWithParam());
        $stack->setDefaultParam('foo', 'bar');

        $this->assertEquals('bar', $stack->assemble($uri, [], ['name' => 'foo'])->getPath());
    }

    public function testDefaultParamDoesNotOverrideParamForAssembling()
    {
        $uri = new Uri();
        $stack = new SimpleRouteStack();
        $stack->addRoute('foo', new TestAsset\DummyRouteWithParam());
        $stack->setDefaultParam('foo', 'baz');

        $this->assertEquals('bar', $stack->assemble($uri, ['foo' => 'bar'], ['name' => 'foo'])->getPath());
    }

    public function testGetRoutes()
    {
        $stack = new SimpleRouteStack();

        $route = new TestAsset\DummyRoute();
        $stack->addRoute('foo', $route);
        $this->assertEquals(['foo' => $route], $stack->getRoutes());
    }

    public function testGetRouteByName()
    {
        $stack = new SimpleRouteStack();
        $route = new TestAsset\DummyRoute();
        $stack->addRoute('foo', $route);

        $this->assertEquals($route, $stack->getRoute('foo'));
    }

    public function testHasRoute()
    {
        $stack = new SimpleRouteStack();
        $this->assertEquals(false, $stack->hasRoute('foo'));

        $stack->addRoute('foo', new TestAsset\DummyRoute());
        $this->assertEquals(true, $stack->hasRoute('foo'));
    }
}
