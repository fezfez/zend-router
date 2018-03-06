<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route;

use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Router\PartialRouteInterface;
use Zend\Router\PartialRouteResult;
use ZendTest\Router\Route\TestAsset\RouteTestDefinition;

use function ksort;
use function sort;

trait PartialRouteTestTrait
{
    abstract public function getRouteTestDefinitions() : iterable;

    /**
     * @uses self::getRouteTestDefinitions() provided definitions to prepare and
     *     provide data for partial route matching test
     */
    public function partialRouteMatchingProvider() : array
    {
        $data = [];
        $definitions = $this->getRouteTestDefinitions();
        foreach ($definitions as $description => $definition) {
            /**
             * @var RouteTestDefinition $definition
             */
            $data[$description] = [
                $definition->getRoute(),
                $definition->getRequestToMatch(),
                $definition->getPathOffset(),
                $definition->getMatchOptions(),
                $definition->getExpectedPartialMatchResult(),
            ];
        }
        return $data;
    }

    /**
     * We use callback instead of route instance so that we can get coverage
     * for all route configuration combinations.
     *
     * @dataProvider partialRouteMatchingProvider
     */
    public function testPartialMatching(
        PartialRouteInterface $route,
        Request $request,
        int $pathOffset,
        array $matchOptions,
        PartialRouteResult $expectedResult
    ) {
        $result = $route->partialMatch($request, $pathOffset, $matchOptions);

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
            $this->assertEquals(
                $expectedResult->getMatchedPathLength(),
                $result->getMatchedPathLength(),
                'Expected path match length does not meet test expectation'
            );
            $this->assertEquals(
                $expectedResult->getUsedPathOffset(),
                $result->getUsedPathOffset(),
                'Expected path offset does not meet test expectation'
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

            $this->assertEquals(
                $expectedResult->getMatchedPathLength(),
                $result->getMatchedPathLength(),
                'Expected path match length does not meet test expectation'
            );
            $this->assertEquals(
                $expectedResult->getUsedPathOffset(),
                $result->getUsedPathOffset(),
                'Expected path offset does not meet test expectation'
            );
        }
    }
}
