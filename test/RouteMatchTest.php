<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\RouteMatch;
use Zend\Router\RouteResult;

/**
 * @covers \Zend\Router\RouteMatch
 */
class RouteMatchTest extends TestCase
{
    public function testParamsAreStored()
    {
        $match = new RouteMatch(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $match->getParams());
    }

    public function testMatchedRouteNameIsSet()
    {
        $match = new RouteMatch([]);
        $match->setMatchedRouteName('foo');

        $this->assertEquals('foo', $match->getMatchedRouteName());
    }

    public function testSetParam()
    {
        $match = new RouteMatch([]);
        $match->setParam('foo', 'bar');

        $this->assertEquals(['foo' => 'bar'], $match->getParams());
    }

    public function testGetParam()
    {
        $match = new RouteMatch(['foo' => 'bar']);

        $this->assertEquals('bar', $match->getParam('foo'));
    }

    public function testGetNonExistentParamWithoutDefault()
    {
        $match = new RouteMatch([]);

        $this->assertNull($match->getParam('foo'));
    }

    public function testGetNonExistentParamWithDefault()
    {
        $match = new RouteMatch([]);

        $this->assertEquals('bar', $match->getParam('foo', 'bar'));
    }

    public function testCreateFromRouteResult()
    {
        $routeResult = RouteResult::fromRouteMatch(['foo' => 'bar'], 'baz');
        $match = RouteMatch::fromRouteResult($routeResult);
        $this->assertEquals('bar', $match->getParam('foo'));
        $this->assertEquals('baz', $match->getMatchedRouteName());
    }

    public function testCantCreateFromFailureRouteResult()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Route match cannot be created from failure route result');
        $routeResult = RouteResult::fromRouteFailure();
        RouteMatch::fromRouteResult($routeResult);
    }
}
