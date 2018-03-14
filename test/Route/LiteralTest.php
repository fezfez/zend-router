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
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteResult;
use Zend\Router\Route\Literal;
use Zend\Router\RouteResult;
use ZendTest\Router\FactoryTester;
use ZendTest\Router\Route\TestAsset\RouteTestDefinition;

/**
 * @covers \Zend\Router\Route\Literal
 */
class LiteralTest extends TestCase
{
    use PartialRouteTestTrait;

    public function getRouteTestDefinitions() : iterable
    {
        yield 'simple match' => (new RouteTestDefinition(
            new Literal('/foo'),
            new Uri('/foo')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching();

        yield 'no match without leading slash' => (new RouteTestDefinition(
            new Literal('foo'),
            new Uri('/foo')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        yield 'only partial match with trailing slash' => (new RouteTestDefinition(
            new Literal('/foo'),
            new Uri('/foo/')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 4)
            );
        yield 'offset skips beginning' => (new RouteTestDefinition(
            new Literal('foo'),
            new Uri('/foo')
        ))
            ->usePathOffset(1)
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 1, 3)
            );
        yield 'offset does not prevent partial match' => (new RouteTestDefinition(
            new Literal('foo'),
            new Uri('/foo/bar')
        ))
            ->usePathOffset(1)
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 1, 3)
            );
        yield 'assemble appends to path present in provided uri' => (new RouteTestDefinition(
            new Literal('/foo'),
            new Uri('/foo')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 4)
            )
            ->useUriForAssemble(new Uri('/bar'))
            ->shouldAssembleAndExpectResult(new Uri('/bar/foo'));
    }

    public function testGetAssembledParams()
    {
        $uri = new Uri();
        $route = new Literal('/foo');
        $route->assemble($uri, ['foo' => 'bar']);

        $this->assertEquals([], $route->getLastAssembledParams());
        $this->assertEquals($route->getLastAssembledParams(), $route->getAssembledParams());
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Literal::class,
            [
                'route' => 'Missing "route" in options array',
            ],
            [
                'route' => '/foo',
            ]
        );
    }

    public function testEmptyLiteral()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Literal uri path part cannot be empty');
        new Literal('');
    }

    public function testRejectsNegativePathOffset()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        $request = $this->prophesize(ServerRequestInterface::class);
        $route = new Literal('/foo');
        $route->partialMatch($request->reveal(), -1);
    }
}
