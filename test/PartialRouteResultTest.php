<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\DomainException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\PartialRouteResult;
use Zend\Router\RouteResult;

/**
 * @covers \Zend\Router\PartialRouteResult
 */
class PartialRouteResultTest extends TestCase
{
    public function testFromRouteFailure()
    {
        $result = PartialRouteResult::fromRouteFailure();
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
        $this->assertFalse($result->isSuccess());
    }

    public function testFromMethodFailure()
    {
        $methods = ['GET', 'POST'];
        $result = PartialRouteResult::fromMethodFailure($methods, 10, 20);
        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->isMethodFailure());
        $this->assertFalse($result->isSuccess());
        $this->assertEquals($methods, $result->getAllowedMethods());
        $this->assertEquals(10, $result->getUsedPathOffset());
        $this->assertEquals(20, $result->getMatchedPathLength());
    }

    public function testFromMethodFailureDeduplicatesAndNormalizesHttpMethods()
    {
        $methods = ['GeT', 'get', 'POST', 'POST'];
        $result = PartialRouteResult::fromMethodFailure($methods, 0, 0);
        $this->assertEquals(['GET', 'POST'], $result->getAllowedMethods());
    }

    public function testFromMethodFailureRejectsNegativeOffset()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        $result = PartialRouteResult::fromMethodFailure(['GET'], -1, 0);
    }

    public function testFromMethodFailureRejectsNegativeMatchedLength()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Matched path length cannot be negative');
        PartialRouteResult::fromMethodFailure(['GET'], 0, -1);
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
        PartialRouteResult::fromMethodFailure([], 10, 20);
    }

    public function testFromRouteMatchIsSuccessful()
    {
        $result = PartialRouteResult::fromRouteMatch([], 0, 0);
        $this->assertFalse($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
        $this->assertTrue($result->isSuccess());
    }

    public function testFromRouteMatchSetsPathOffsetAndMatchedLength()
    {
        $result = PartialRouteResult::fromRouteMatch([], 10, 5);
        $this->assertEquals(10, $result->getUsedPathOffset());
        $this->assertEquals(5, $result->getMatchedPathLength());
    }

    public function testFromRouteMatchWithNoRouteNameProvided()
    {
        $result = PartialRouteResult::fromRouteMatch([], 0, 0);
        $this->assertNull($result->getMatchedRouteName());
    }

    public function testFromRouteMatchSetsMatchedRouteNameWhenProvided()
    {
        $result = PartialRouteResult::fromRouteMatch([], 0, 0, 'bar');
        $this->assertEquals('bar', $result->getMatchedRouteName());
    }

    public function testFromRouteMatchSetsMatchedParameters()
    {
        $params = ['foo' => 'bar'];
        $result = PartialRouteResult::fromRouteMatch($params, 0, 0);
        $this->assertEquals($params, $result->getMatchedParams());
    }

    public function testFromRouteMatchRejectsNegativeOffset()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        PartialRouteResult::fromRouteMatch([], -1, 0);
    }

    public function testFromRouteMatchRejectsNegativeMatchedLength()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Matched path length cannot be negative');
        PartialRouteResult::fromRouteMatch([], 0, -1);
    }

    public function testWithRouteNameReplacesNameInNewInstance()
    {
        $result1 = PartialRouteResult::fromRouteMatch([], 0, 0, 'foo');
        $result2 = $result1->withMatchedRouteName('bar');
        $this->assertNotSame($result1, $result2);
        $this->assertSame('foo', $result1->getMatchedRouteName());
        $this->assertSame('bar', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameRetainsPathOffsetAndMatchedLength()
    {
        $result1 = PartialRouteResult::fromRouteMatch([], 10, 5, 'foo');
        $result2 = $result1->withMatchedRouteName('bar');
        $this->assertEquals(10, $result2->getUsedPathOffset());
        $this->assertEquals(5, $result2->getMatchedPathLength());
    }

    public function testWithRouteNameWithPrependFlagPrependsNameToExisting()
    {
        $result1 = PartialRouteResult::fromRouteMatch([], 0, 0, 'foo');
        $result2 = $result1->withMatchedRouteName('bar', RouteResult::NAME_PREPEND);
        $this->assertNotSame($result1, $result2);
        $this->assertSame('foo', $result1->getMatchedRouteName());
        $this->assertSame('bar/foo', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameWithPrependFlagSetsNameWhenRouteNameIsNotSet()
    {
        $result1 = PartialRouteResult::fromRouteMatch([], 0, 0, null);
        $result2 = $result1->withMatchedRouteName('bar', RouteResult::NAME_PREPEND);
        $this->assertSame('bar', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameWithAppendFlagAppendsNameToExisting()
    {
        $result1 = PartialRouteResult::fromRouteMatch([], 0, 0, 'foo');
        $result2 = $result1->withMatchedRouteName('bar', RouteResult::NAME_APPEND);
        $this->assertNotSame($result1, $result2);
        $this->assertSame('foo', $result1->getMatchedRouteName());
        $this->assertSame('foo/bar', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameWithAppendFlagSetsNameWhenRouteNameIsNotSet()
    {
        $result1 = PartialRouteResult::fromRouteMatch([], 0, 0, null);
        $result2 = $result1->withMatchedRouteName('bar', RouteResult::NAME_APPEND);
        $this->assertSame('bar', $result2->getMatchedRouteName());
    }

    public function testWithRouteNameThrowsForUnsuccessfulResult()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only successful routing can have matched route name');
        $result = PartialRouteResult::fromRouteFailure();
        $result->withMatchedRouteName('foo');
    }

    public function testWithRouteNameRejectsEmptyName()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Route name cannot be empty');
        $result = PartialRouteResult::fromRouteMatch([], 0, 0, 'foo');
        $result->withMatchedRouteName('');
    }

    public function testWithRouteNameThrowsOnUnknownFlag()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown flag');
        $result = PartialRouteResult::fromRouteMatch([], 0, 0, 'foo');
        $result->withMatchedRouteName('bar', 'unknown');
    }

    public function testWithMatchedParamsReplacesInNewInstance()
    {
        $params1 = ['foo' => 'bar'];
        $params2 = ['baz' => 'qux'];
        $result1 = PartialRouteResult::fromRouteMatch($params1, 0, 0, null);
        $result2 = $result1->withMatchedParams($params2);
        $this->assertNotSame($result1, $result2);
        $this->assertSame($params1, $result1->getMatchedParams());
        $this->assertSame($params2, $result2->getMatchedParams());
    }

    public function testWithMatchedParamsThrowsForUnsuccessfulResult()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only successful routing can have matched params');
        $result = PartialRouteResult::fromRouteFailure();
        $result->withMatchedParams(['foo' => 'bar']);
    }

    public function provideFullPathMatchData()
    {
        return [
            'full match' => [
                new Uri('/foo'),
                0,
                4,
                true
            ],
            'partial match' => [
                new Uri('/foo'),
                0,
                3,
                false
            ],
            'offset full match' => [
                new Uri('/foo'),
                1,
                3,
                true
            ],
            'offset partial match' => [
                new Uri('/foo/bar'),
                1,
                3,
                false
            ],
            'empty uri path' => [
                new Uri(''),
                0,
                0,
                true
            ],
        ];
    }

    /**
     * @dataProvider provideFullPathMatchData
     */
    public function testIsFullPathMatch(Uri $uri, int $offset, int $length, bool $fullMatch)
    {
        $result = PartialRouteResult::fromRouteMatch([], $offset, $length);
        $this->assertEquals($fullMatch, $result->isFullPathMatch($uri));
    }

    public function testIsNeverAFullPathMatchOnRouteFailure()
    {
        $uri = new Uri('');
        $result = PartialRouteResult::fromRouteFailure();
        $this->assertFalse($result->isFullPathMatch($uri));
    }
}
