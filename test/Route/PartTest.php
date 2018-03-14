<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\Route\Literal;
use Zend\Router\Route\Method;
use Zend\Router\Route\Part;
use Zend\Router\Route\Segment;
use Zend\Router\RouteResult;
use Zend\Router\TreeRouteStack;
use ZendTest\Router\FactoryTester;
use ZendTest\Router\Route\TestAsset\RouteTestDefinition;

/**
 * @covers \Zend\Router\Route\Part
 */
class PartTest extends TestCase
{
    use RouteTestTrait;

    public function getTestRoute() : Part
    {
        return Part::factory([
            'route' => new Literal('/foo', ['controller' => 'foo']),
            'child_routes' => [
                'bar' => new Literal('/bar', ['controller' => 'bar']),
                'baz' => Part::factory([
                    'route' => new Literal('/baz'),
                    'child_routes' => [
                        'bat' => new Segment('/:controller'),
                    ],
                ]),
                'bat' => Part::factory([
                    'route' => new Segment('/bat[/:foo]', [], ['foo' => 'bar']),
                    'may_terminate' => true,
                    'child_routes' => [
                        'literal' => new Literal('/bar'),
                        'optional' => new Segment('/bat[/:bar]'),
                    ],
                ]),
            ],
            'may_terminate' => true,
        ]);
    }

    public function getRouteAlternative() : Part
    {
        return new Part(
            new Segment('/[:controller[/:action]]', [], [
                'controller' => 'fo-fo',
                'action' => 'index',
            ]),
            new TreeRouteStack(),
            true
        );
    }

    public function getRouteTestDefinitions() : iterable
    {
        $params = ['controller' => 'foo'];
        yield 'simple match' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['controller' => 'foo'];
        yield 'offset-skips-beginning' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/bar/foo')
        ))
            ->usePathOffset(4)
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->shouldAssembleAndExpectResult(new Uri('/foo'))
            ->useParamsForAssemble($params);

        $params = ['controller' => 'bar'];
        yield 'simple child match' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params, 'bar')
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params)
            ->useOptionsForAssemble(['name' => 'bar']);

        yield 'non terminating part does not match' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            );

        $params = ['controller' => 'bat'];
        yield 'child of non terminating part does match' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/baz/bat')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params, 'baz/bat')
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params)
            ->useOptionsForAssemble(['name' => 'baz/bat']);

        $params = ['controller' => 'foo', 'foo' => 'bar'];
        yield 'optional parameters are dropped without child' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/bat')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params, 'bat')
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params)
            ->useOptionsForAssemble(['name' => 'bat']);

        $params = ['controller' => 'foo', 'foo' => 'bar'];
        yield 'optional parameters are not dropped with child' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/bat/bar/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params, 'bat/literal')
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params)
            ->useOptionsForAssemble(['name' => 'bat/literal']);

        $params = ['controller' => 'foo', 'foo' => 'bar'];
        yield 'optional parameters not required in last part' => (new RouteTestDefinition(
            $this->getTestRoute(),
            new Uri('/foo/bat/bar/bat')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params, 'bat/optional')
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params)
            ->useOptionsForAssemble(['name' => 'bat/optional']);

        $params = ['controller' => 'fo-fo', 'action' => 'index'];
        yield 'simple match 2' => (new RouteTestDefinition(
            $this->getRouteAlternative(),
            new Uri('/')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);
    }

    public function testAssembleNonTerminatedRoute()
    {
        $uri = new Uri();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Part route may not terminate');
        $this->getTestRoute()->assemble($uri, [], ['name' => 'baz']);
    }

    public function testMethodFailureReturnsMethodFailureOnTerminatedMatch()
    {
        $options = [
            'route' => new Method('GET,POST'),
            'may_terminate' => true,
        ];

        $route = Part::factory($options);

        $request = new ServerRequest([], [], new Uri('/foo'), 'PUT');
        $result = $route->match($request, 4);
        $this->assertTrue($result->isMethodFailure());
        $this->assertArraySubset(['GET', 'POST'], $result->getAllowedMethods());
        $this->assertCount(2, $result->getAllowedMethods());
    }

    public function testMethodFailureReturnsMethodFailureOnFullPathMatch()
    {
        $options = [
            'route' => new Method('GET,POST'),
            'may_terminate' => true,
            'child_routes' => [
                'foo' => new Literal('/foo'),
            ],
        ];

        $route = Part::factory($options);

        $request = new ServerRequest([], [], new Uri('/foo'), 'PUT');
        $result = $route->match($request, 0);
        $this->assertTrue($result->isMethodFailure());
        $this->assertArraySubset(['GET', 'POST'], $result->getAllowedMethods());
        $this->assertCount(2, $result->getAllowedMethods());
    }

    public function testMethodFailureReturnsFailureIfChildRoutesFail()
    {
        $options = [
            'route' => new Method('GET,POST'),
            'may_terminate' => true,
            'child_routes' => [
                'foo' => new Literal('/foo'),
            ],
        ];
        $route = Part::factory($options);

        $request = new ServerRequest([], [], new Uri('/bar'), 'PUT');
        $result = $route->match($request, 0);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testMethodFailureReturnsMethodIntersectionBetweenPartialAndChildRoutes()
    {
        $options = [
            'route' => new Method('GET,POST'),
            'may_terminate' => true,
            'child_routes' => [
                'foo' => Part::factory([
                    'route' => new Literal('/foo'),
                    'child_routes' => [
                        'verb' => new Method('POST,DELETE'),
                    ],
                ]),
            ],
        ];

        $route = Part::factory($options);

        $request = new ServerRequest([], [], new Uri('/foo'), 'PUT');
        $result = $route->match($request, 0);
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['POST'], $result->getAllowedMethods());
    }

    public function testMethodFailureWithChildMethodsNotIntersectingIsAFailure()
    {
        $options = [
            'route' => new Method('GET,POST'),
            'may_terminate' => true,
            'child_routes' => [
                'foo' => Part::factory([
                    'route' => new Literal('/foo'),
                    'child_routes' => [
                        'verb' => new Method('DELETE,OPTIONS'),
                    ],
                ]),
            ],
        ];

        $route = Part::factory($options);

        $request = new ServerRequest([], [], new Uri('/foo'), 'PUT');
        $result = $route->match($request, 0);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testChildMethodFailureWithParentPartSuccessReturnsFullListOfMethods()
    {
        $options = [
            'route' => new Method('GET,POST,DELETE'),
            'may_terminate' => true,
            'child_routes' => [
                'foo' => Part::factory([
                    'route' => new Literal('/foo'),
                    'child_routes' => [
                        'verb' => new Method('POST,DELETE,OPTIONS'),
                    ],
                ]),
            ],
        ];

        $route = Part::factory($options);

        $request = new ServerRequest([], [], new Uri('/foo'), 'GET');
        $result = $route->match($request, 0);
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['POST', 'DELETE'], $result->getAllowedMethods());
    }

    public function testParentMethodFailureWithChildSuccessReturnsFullListOfMethods()
    {
        $options = [
            'route' => new Method('GET,POST,DELETE'),
            'may_terminate' => true,
            'child_routes' => [
                'foo' => Part::factory([
                    'route' => new Literal('/foo'),
                    'child_routes' => [
                        'verb' => new Method('DELETE,OPTIONS'),
                    ],
                ]),
            ],
        ];

        $route = Part::factory($options);

        $request = new ServerRequest([], [], new Uri('/foo'), 'OPTIONS');
        $result = $route->match($request, 0);
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['DELETE'], $result->getAllowedMethods());
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Part::class,
            [
                'route' => 'Missing "route" in options array',
            ],
            [
                'route' => new Literal('/foo'),
            ]
        );
    }

    /**
     * @group 3711
     */
    public function testPartRouteMarkedAsMayTerminateCanMatchWhenQueryStringPresent()
    {
        $options = [
            'route' => new Literal('/resource', ['controller' => 'ResourceController', 'action' => 'resource']),
            'may_terminate' => true,
            'child_routes' => [
                'child' => new Literal('/child'),
            ],
        ];

        $route = Part::factory($options);
        $request = new ServerRequest([], [], new Uri('http://example.com/resource?foo=bar'));
        $request = $request->withQueryParams(['foo' => 'bar']);

        $result = $route->match($request);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('resource', $result->getMatchedParams()['action']);
    }

    public function testRejectsNegativePathOffset()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        $partial = $this->prophesize(PartialRouteInterface::class);
        $request = $this->prophesize(ServerRequestInterface::class);
        $route = new Part($partial->reveal(), new TreeRouteStack(), false);
        $route->match($request->reveal(), -1);
    }
}
