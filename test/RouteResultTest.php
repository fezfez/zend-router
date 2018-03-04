<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Zend\Router\Exception\DomainException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\RouteResult;

/**
 * @covers \Zend\Router\RouteResult
 */
class RouteResultTest extends TestCase
{
    public function testFromRouteFailure()
    {
        $result = RouteResult::fromRouteFailure();
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
        $this->assertFalse($result->isSuccess());
    }

    public function testFromMethodFailure()
    {
        $methods = ['GET', 'POST'];
        $result = RouteResult::fromMethodFailure($methods);
        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->isMethodFailure());
        $this->assertFalse($result->isSuccess());
        $this->assertEquals($methods, $result->getAllowedMethods());
    }

    public function testFromMethodFailureDeduplicatesAndNormalizesHttpMethods()
    {
        $methods = ['GeT', 'get', 'POST', 'POST'];
        $result = RouteResult::fromMethodFailure($methods);
        $this->assertEquals(['GET', 'POST'], $result->getAllowedMethods());
    }

    /**
     * Empty list can occur on allowed methods intersect in Part route. Eg when
     * parent route allows only GET and child only POST. Route must handle
     * such occurrence.
     */
    public function testFromMethodFailureThrowsOnEmptyAllowedMethodsList()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Method failure requires list of allowed methods');
        RouteResult::fromMethodFailure([]);
    }

    public function testFromRouteMatchIsSuccessful()
    {
        $result = RouteResult::fromRouteMatch([], null);
        $this->assertFalse($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
        $this->assertTrue($result->isSuccess());
    }

    public function testFromRouteMatchWithNoRouteNameProvided()
    {
        $result = RouteResult::fromRouteMatch([]);
        $this->assertNull($result->getMatchedRouteName());
    }

    public function testFromRouteMatchSetsMatchedRouteNameWhenProvided()
    {
        $result = RouteResult::fromRouteMatch([], 'bar');
        $this->assertEquals('bar', $result->getMatchedRouteName());
    }

    public function testFromRouteMatchSetsMatchedParameters()
    {
        $params = ['foo' => 'bar'];
        $result = RouteResult::fromRouteMatch($params);
        $this->assertEquals($params, $result->getMatchedParams());
    }

    public function testWithRouteNameReplacesNameInNewInstance()
    {
        $result1 = RouteResult::fromRouteMatch([], 'foo');
        $result2 = $result1->withMatchedRouteName('bar');
        $this->assertNotSame($result1, $result2);
        $this->assertSame('foo', $result1->getMatchedRouteName());
        $this->assertSame('bar', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameWithPrependFlagPrependsNameToExisting()
    {
        $result1 = RouteResult::fromRouteMatch([], 'foo');
        $result2 = $result1->withMatchedRouteName('bar', RouteResult::NAME_PREPEND);
        $this->assertNotSame($result1, $result2);
        $this->assertSame('foo', $result1->getMatchedRouteName());
        $this->assertSame('bar/foo', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameWithPrependFlagSetsNameWhenRouteNameIsNotSet()
    {
        $result1 = RouteResult::fromRouteMatch([], null);
        $result2 = $result1->withMatchedRouteName('bar', RouteResult::NAME_PREPEND);
        $this->assertSame('bar', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameWithAppendFlagAppendsNameToExisting()
    {
        $result1 = RouteResult::fromRouteMatch([], 'foo');
        $result2 = $result1->withMatchedRouteName('bar', RouteResult::NAME_APPEND);
        $this->assertNotSame($result1, $result2);
        $this->assertSame('foo', $result1->getMatchedRouteName());
        $this->assertSame('foo/bar', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameWithAppendFlagSetsNameWhenRouteNameIsNotSet()
    {
        $result1 = RouteResult::fromRouteMatch([], null);
        $result2 = $result1->withMatchedRouteName('bar', RouteResult::NAME_APPEND);
        $this->assertSame('bar', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameThrowsForUnsuccessfulResult()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only successful routing can have matched route name');
        $result = RouteResult::fromRouteFailure();
        $result->withMatchedRouteName('foo');
    }

    public function testWithRouteNameRejectsEmptyName()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Route name cannot be empty');
        $result = RouteResult::fromRouteMatch([], 'foo');
        $result->withMatchedRouteName('');
    }

    public function testWithRouteNameThrowsOnUnknownFlag()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown flag');
        $result = RouteResult::fromRouteMatch([], 'foo');
        $result->withMatchedRouteName('bar', 'unknown');
    }

    public function testWithMatchedParamsReplacesInNewInstance()
    {
        $params1 = ['foo' => 'bar'];
        $params2 = ['baz' => 'qux'];
        $result1 = RouteResult::fromRouteMatch($params1, null);
        $result2 = $result1->withMatchedParams($params2);
        $this->assertNotSame($result1, $result2);
        $this->assertSame($params1, $result1->getMatchedParams());
        $this->assertSame($params2, $result2->getMatchedParams());
    }

    public function testWithMatchedParamsThrowsForUnsuccessfulResult()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only successful routing can have matched params');
        $result = RouteResult::fromRouteFailure();
        $result->withMatchedParams(['foo' => 'bar']);
    }
}
