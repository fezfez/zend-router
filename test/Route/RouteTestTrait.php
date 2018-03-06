<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Zend\Router\RouteInterface;
use Zend\Router\RouteResult;
use ZendTest\Router\Route\TestAsset\RouteTestDefinition;

use function ksort;
use function sort;

trait RouteTestTrait
{
    abstract public function getRouteTestDefinitions() : iterable;

    /**
     * @uses self::getRouteTestDefinitions() provided definitions to prepare and
     *     provide data for route matching test
     */
    public function routeMatchingProvider() : iterable
    {
        $definitions = $this->getRouteTestDefinitions();
        foreach ($definitions as $description => $definition) {
            /**
             * @var RouteTestDefinition $definition
             */
            yield $description => [
                $definition->getRoute(),
                $definition->getRequestToMatch(),
                $definition->getPathOffset(),
                $definition->getMatchOptions(),
                $definition->getExpectedMatchResult(),
            ];
        }
    }

    /**
     * We use callback instead of route instance so that we can get coverage
     * for all route configuration combinations.
     *
     * @dataProvider routeMatchingProvider
     */
    public function testMatching(
        RouteInterface $route,
        Request $request,
        int $pathOffset,
        array $matchOptions,
        RouteResult $expectedResult
    ) {
        $result = $route->match($request, $pathOffset, $matchOptions);

        if ($expectedResult->isSuccess()) {
            $this->assertTrue($result->isSuccess(), 'Expected successful routing');
            $expectedParams = $expectedResult->getMatchedParams();
            ksort($expectedParams);
            $actualParams = $result->getMatchedParams();
            ksort($expectedParams);
            $this->assertEquals($expectedParams, $actualParams, 'Matched parameters do not meet test expectation');

            $this->assertSame(
                $expectedResult->getMatchedRouteName(),
                $result->getMatchedRouteName(),
                'Expected matched route name do not meet test expectation'
            );
        }
        if ($expectedResult->isFailure()) {
            $this->assertTrue($result->isFailure(), 'Failed routing is expected');
        }
        if ($expectedResult->isMethodFailure()) {
            $this->assertTrue($result->isMethodFailure(), 'Http method routing failure is expected');

            $expectedMethods = $expectedResult->getAllowedMethods();
            sort($expectedMethods);
            $actualMethods = $result->getAllowedMethods();
            sort($actualMethods);

            $this->assertEquals($expectedMethods, $actualMethods, 'Allowed http methods do not match expectation');
        }
    }

    /**
     * @uses self::getRouteTestDefinitions() provided definitions to prepare and
     *     provide data for route assembling uri test
     */
    public function routeUriAssemblingProvider() : iterable
    {
        $definitions = $this->getRouteTestDefinitions();
        foreach ($definitions as $description => $definition) {
            /**
             * @var RouteTestDefinition $definition
             */
            $assembleResult = $definition->getExpectedAssembleResult();
            if (null === $assembleResult) {
                continue;
            }
            yield $description => [
                $definition->getRoute(),
                $definition->getUriForAssemble(),
                $definition->getParamsForAssemble(),
                $definition->getOptionsForAssemble(),
                $assembleResult,
            ];
        }
    }

    /**
     * @dataProvider routeUriAssemblingProvider
     */
    public function testAssembling(
        RouteInterface $route,
        UriInterface $uriForAssemble,
        array $params,
        array $options,
        UriInterface $expectedUri
    ) {
        $uri = $route->assemble($uriForAssemble, $params, $options);

        $this->assertEquals($expectedUri->__toString(), $uri->__toString());
    }
}
