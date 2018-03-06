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
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\PartialRouteResult;
use Zend\Router\Route\Hostname;
use Zend\Router\RouteResult;
use ZendTest\Router\FactoryTester;
use ZendTest\Router\Route\TestAsset\RouteTestDefinition;

/**
 * @covers \Zend\Router\Route\Hostname
 */
class HostnameTest extends TestCase
{
    use PartialRouteTestTrait;
    use RouteTestTrait;

    /**
     * Provides route test definitions. As a data provider it does not
     * generate coverage report for route instantiation and configuration logic
     * triggered on newing.
     */
    public function getRouteTestDefinitions() : iterable
    {
        $params = ['foo' => 'bar'];
        yield 'simple match' => (new RouteTestDefinition(
            new Hostname(':foo.example.com'),
            (new Uri())->withHost('bar.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'no match on different hostname' => (new RouteTestDefinition(
            new Hostname('foo.example.com'),
            (new Uri())->withHost('bar.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        yield 'no match with different number of parts' => (new RouteTestDefinition(
            new Hostname('foo.example.com'),
            (new Uri())->withHost('example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        yield 'no match with different number of parts 2' => (new RouteTestDefinition(
            new Hostname('example.com'),
            (new Uri())->withHost('foo.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        $params = ['foo' => 'bat'];
        yield 'match overrides default' => (new RouteTestDefinition(
            new Hostname(':foo.example.com', [], ['foo' => 'baz']),
            (new Uri())->withHost('bat.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'constraints prevent match' => (new RouteTestDefinition(
            new Hostname(':foo.example.com', ['foo' => '\d+']),
            (new Uri())->withHost('bar.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        $params = ['foo' => '123'];
        yield 'constraints allow match' => (new RouteTestDefinition(
            new Hostname(':foo.example.com', ['foo' => '\d+']),
            (new Uri())->withHost('123.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['domain' => 'mydomain'];
        yield 'constraints allow match 2' => (new RouteTestDefinition(
            new Hostname(
                'www.:domain.com',
                ['domain' => '(mydomain|myaltdomain1|myaltdomain2)'],
                ['domain'    => 'mydomain']
            ),
            (new Uri())->withHost('www.mydomain.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar'];
        yield 'optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.]example.com'),
            (new Uri())->withHost('bar.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'baz', 'bar' => 'bat'];
        yield 'two optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.][:bar.]example.com'),
            (new Uri())->withHost('baz.bat.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'missing optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.]example.com'),
            (new Uri())->withHost('example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble([]);

        yield 'Assemble with optional parameter equal to null' => (new RouteTestDefinition(
            new Hostname('[:foo.]example.com'),
            (new Uri())->withHost('example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble(['foo' => null]);

        /**
         * @todo investigate if this can be fixed or should be documented as a quirk
         *
         * There is a workaround, [[:foo.]:bar.] removes ambiguity. Fix could
         *     be by emulating such nesting for same level optional parts, but it
         *     might break other use cases
         */
        $params = ['bar' => 'bat'];
        yield 'optional parameters evaluated right to left' => (new RouteTestDefinition(
            new Hostname('[:foo.][:bar.]example.com'),
            (new Uri())->withHost('bat.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'two missing optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.][:bar.]example.com'),
            (new Uri())->withHost('example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching();

        $params = ['foo' => 'baz', 'bar' => 'bat'];
        yield 'two optional subdomain nested' => (new RouteTestDefinition(
            new Hostname('[[:foo.]:bar.]example.com'),
            (new Uri())->withHost('baz.bat.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['bar' => 'bat'];
        yield 'one of two missing optional subdomain nested' => (new RouteTestDefinition(
            new Hostname('[[:foo.]:bar.]example.com'),
            (new Uri())->withHost('bat.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'two missing optional subdomain nested' => (new RouteTestDefinition(
            new Hostname('[[:foo.]:bar.]example.com'),
            (new Uri())->withHost('example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching();

        yield 'no match on different hostname and optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.]example.com'),
            (new Uri())->withHost('bar.test.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        yield 'no match with different number of parts and optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.]example.com'),
            (new Uri())->withHost('bar.baz.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        $params = ['foo' => 'bat', 'bar' => 'qux'];
        yield 'match overrides default optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.]:bar.example.com', [], ['bar' => 'baz']),
            (new Uri())->withHost('bat.qux.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'constraints prevent match optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.]example.com', ['foo' => '\d+']),
            (new Uri())->withHost('bar.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        $params = ['foo' => '123'];
        yield 'constraints allow match optional subdomain' => (new RouteTestDefinition(
            new Hostname('[:foo.]example.com', ['foo' => '\d+']),
            (new Uri())->withHost('123.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'baz', 'bar' => 'bat'];
        yield 'middle subdomain optional' => (new RouteTestDefinition(
            new Hostname(':foo.[:bar.]example.com'),
            (new Uri())->withHost('baz.bat.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        // @TODO Revisit this behavior. It looks error prone and may be dangerous
        $params = ['foo' => 'baz'];
        yield 'missing middle subdomain optional' => (new RouteTestDefinition(
            new Hostname(':foo.[:bar.]example.com'),
            (new Uri())->withHost('baz.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['username' => 'jdoe'];
        yield 'non standard delimiter' => (new RouteTestDefinition(
            new Hostname('user-:username.example.com'),
            (new Uri())->withHost('user-jdoe.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['page' => 'article', 'username' => 'jdoe'];
        yield 'non standard delimiter optional' => (new RouteTestDefinition(
            new Hostname(':page{-}[-:username].example.com'),
            (new Uri())->withHost('article-jdoe.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['page' => 'article'];
        yield 'missing non standard delimiter optional' => (new RouteTestDefinition(
            new Hostname(':page{-}[-:username].example.com'),
            (new Uri())->withHost('article.example.com')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 0)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);
    }

    public function testOnAssembleLeftMostOptionalPartWithProvidedParameterMakesEverythingToTheRightRequired()
    {
        // @TODO further investigation needed. See todo for 'optional parameters evaluated right to left'
        $this->markTestIncomplete();
        $route = new Hostname('[:foo][:bar].example.com');
        $uri = new Uri();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing parameter "bar"');
        $route->assemble($uri, ['foo' => 'baz']);
    }

    public function testHostnameDefinitionWithEmptyParameterNameIsThrowing()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empty parameter name');
        new Hostname(':.example.com');
    }

    public function testHostnameDefinitionWithUnpairedBracketsIsThrowing()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Found unbalanced brackets');
        new Hostname('[:foo[:bar].example.com');
    }

    public function testHostnameDefinitionWithClosingBracketAndMissingOpening()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Found closing bracket without matching opening bracket');
        new Hostname(':foo[:bar]].example.com');
    }

    public function testAssemblingWithMissingParameter()
    {
        $route = new Hostname(':foo.example.com');
        $uri = new Uri();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing parameter "foo"');
        $route->assemble($uri, []);
    }

    public function testGetAssembledParams()
    {
        $route = new Hostname(':foo.example.com');
        $uri = new Uri();
        $route->assemble($uri, ['foo' => 'bar', 'baz' => 'bat']);

        $this->assertEquals(['foo'], $route->getLastAssembledParams());
        $this->assertEquals($route->getLastAssembledParams(), $route->getAssembledParams());
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Hostname::class,
            [
                'route' => 'Missing "route" in options array',
            ],
            [
                'route' => 'example.com',
            ]
        );
    }

    public function testFailedHostnameSegmentMatchDoesNotEmitErrors()
    {
        $this->expectException(RuntimeException::class);
        new Hostname(':subdomain.with_underscore.com');
    }

    public function testRejectsNegativePathOffset()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        $request = $this->prophesize(ServerRequestInterface::class);
        $route = new Hostname('example.com');
        $route->partialMatch($request->reveal(), -1);
    }
}
