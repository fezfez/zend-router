<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteResult;
use Zend\Router\Route\Method;
use Zend\Router\RouteResult;
use ZendTest\Router\FactoryTester;
use ZendTest\Router\Route\TestAsset\RouteTestDefinition;

/**
 * @covers \Zend\Router\Route\Method
 */
class MethodTest extends TestCase
{
    use PartialRouteTestTrait;
    use RouteTestTrait;

    public function getRouteTestDefinitions() : iterable
    {
        $request = new ServerRequest([], [], null, null, 'php://memory');

        yield 'simple match' => (new RouteTestDefinition(
            new Method('GET'),
            $request->withMethod('GET')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 0)
            );

        yield 'match comma separated verbs' => (new RouteTestDefinition(
            new Method('get,post'),
            $request->withMethod('POST')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 0)
            );

        yield 'match comma separated verbs with whitespace' => (new RouteTestDefinition(
            new Method('get ,    post , put'),
            $request->withMethod('POST')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 0)
            );

        yield 'match ignores case' => (new RouteTestDefinition(
            new Method('Get'),
            $request->withMethod('get')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 0)
            );

        yield 'no match gives list of allowed methods' => (new RouteTestDefinition(
            new Method('POST,PUT,DELETE'),
            $request->withMethod('GET')
        ))
            ->expectMatchResult(
                RouteResult::fromMethodFailure(['POST', 'PUT', 'DELETE'])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromMethodFailure(['POST', 'PUT', 'DELETE'], 0, 0)
            );
    }

    public function testAssembleSimplyReturnsPassedUri()
    {
        $uri = new Uri();
        $method = new Method('get');

        $this->assertSame($uri, $method->assemble($uri));
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Method::class,
            [
                'verb' => 'Missing "verb" in options array',
            ],
            [
                'verb' => 'get',
            ]
        );
    }

    public function testRejectsNegativePathOffset()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        $request = $this->prophesize(ServerRequestInterface::class);
        $route = new Method('GET');
        $route->partialMatch($request->reveal(), -1);
    }
}
