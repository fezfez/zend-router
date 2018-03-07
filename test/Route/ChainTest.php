<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route;

use PHPUnit\Framework\TestCase;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteResult;
use Zend\Router\Route\Chain;
use Zend\Router\Route\Literal;
use Zend\Router\Route\Method;
use Zend\Router\Route\Segment;
use Zend\Router\RouteInterface;
use Zend\Router\RoutePluginManager;
use Zend\Router\RouteResult;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayObject;
use ZendTest\Router\FactoryTester;
use ZendTest\Router\Route\TestAsset\RouteTestDefinition;

/**
 * @covers \Zend\Router\Route\Chain
 */
class ChainTest extends TestCase
{
    use PartialRouteTestTrait;
    use RouteTestTrait;

    public function getTestRoute() : Chain
    {
        return new Chain([
            'foo' => new Segment('/:controller', [], ['controller' => 'foo']),
            'bar' => new Segment('/:bar', [], ['bar' => 'bar']),
        ]);
    }

    public function getRouteWithOptionalParam() : Chain
    {
        return new Chain([
            'foo' => new Segment('/:controller', [], ['controller' => 'foo']),
            'bar' => new Segment('[/:bar]', [], ['bar' => 'bar']),
        ]);
    }

    public function getRouteTestDefinitions() : iterable
    {
        $params = ['controller' => 'foo', 'bar' => 'bar'];
        yield 'simple match' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 8)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['controller' => 'foo', 'bar' => 'bar'];
        yield 'offset skips beginning' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/baz/foo/bar')
        ))
            ->usePathOffset(4)
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 4, 8)
            )
            ->shouldAssembleAndExpectResult(new Uri('/foo/bar'))
            ->useParamsForAssemble($params);

        $params = ['controller' => 'foo', 'bar' => 'baz'];
        yield 'parameters are used only once' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 8)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['controller' => 'foo', 'bar' => 'baz'];
        yield 'optional parameter' => (new RouteTestDefinition(
            $this->getRouteWithOptionalParam(),
            new Uri('/foo/baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 8)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['controller' => 'foo', 'bar' => 'bar'];
        yield 'optional parameter empty' => (new RouteTestDefinition(
            $this->getRouteWithOptionalParam(),
            new Uri('/foo')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['controller' => 'foo', 'bar' => 'bar'];
        yield 'partial match' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/bar/baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 8)
            )
            ->shouldAssembleAndExpectResult(new Uri('/foo/bar'))
            ->useParamsForAssemble($params);

        $params = ['controller' => 'foo', 'bar' => 'bar'];
        yield 'assemble appends path' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 8)
            )
            ->shouldAssembleAndExpectResult(new Uri('/prefixed/foo/bar'))
            ->useUriForAssemble(new Uri('/prefixed'))
            ->useParamsForAssemble($params);
    }

    public function testOnlyPartialRoutesAreAllowed()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chain route can only chain partial routes');
        new Chain([
            'foo' => $this->prophesize(RouteInterface::class)->reveal(),
        ]);
    }

    public function testAddRouteAllowsOnlyPartialRoute()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chain route can only chain partial routes');
        (new Chain([]))->addRoute('foo', $this->prophesize(RouteInterface::class)->reveal());
    }

    public function testMethodFailureReturnsMethodFailureResult()
    {
        $request = new ServerRequest([], [], new Uri('/foo'), 'PUT');
        $route = new Chain([
            'method' => new Method('GET,POST'),
            'literal' => new Literal('/foo'),
        ]);
        $result = $route->match($request);
        $this->assertTrue($result->isMethodFailure());
        $this->assertArraySubset(['GET', 'POST'], $result->getAllowedMethods());
        $this->assertCount(2, $result->getAllowedMethods());
    }

    public function testMethodFailureReturnsMethodIntersection()
    {
        $request = new ServerRequest([], [], new Uri('/foo'), 'PUT');
        $route = new Chain([
            'method1' => new Method('GET,POST'),
            'method2' => new Method('POST,DELETE'),
            'literal' => new Literal('/foo'),
        ]);
        $result = $route->match($request);
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['POST'], $result->getAllowedMethods());
    }

    public function testMethodFailureWithMethodsNotIntersectingIsAFailure()
    {
        $request = new ServerRequest([], [], new Uri('/foo'), 'PUT');
        $route = new Chain([
            'method1' => new Method('GET,POST'),
            'method2' => new Method('PUT,DELETE'),
            'literal' => new Literal('/foo'),
        ]);
        $result = $route->match($request);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testMethodFailureReturnsFailureIfOtherRoutesFail()
    {
        $request = new ServerRequest([], [], new Uri('/foo'), 'PUT');
        $route = new Chain([
            'method1' => new Method('GET,POST'),
            'literal' => new Literal('/bar'),
        ]);
        $result = $route->match($request);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testGetAssembledParams()
    {
        $uri = new Uri();

        /** @var Chain $route */
        $route = $this->getTestRoute();
        $route->assemble($uri, ['controller' => 'foo', 'bar' => 'baz', 'bat' => 'bat']);

        $this->assertEquals(['controller', 'bar'], $route->getLastAssembledParams());
        $this->assertEquals($route->getLastAssembledParams(), $route->getAssembledParams());
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Chain::class,
            [
                'routes' => 'Missing "routes" in options array',
            ],
            [
                'routes' => [],
            ]
        );
        $tester->testFactory(
            Chain::class,
            [
                'routes' => 'Missing "routes" in options array',
            ],
            [
                'routes' => new ArrayObject(),
            ]
        );
    }
}
