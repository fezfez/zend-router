<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;

use function array_unshift;
use function is_array;
use function is_numeric;
use function is_string;
use function sprintf;

class RouteConfigFactory
{
    /**
     * @var RoutePluginManager
     */
    private $routes;

    /**
     * @var array
     */
    private $prototypes = [];

    /**
     * @var int
     */
    static private $chainedIndex = 0;

    public function __construct(RoutePluginManager $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Creates route or route tree from the provided spec
     *
     * @param array|string|RouteInterface $spec
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function routeFromSpec($spec) : RouteInterface
    {
        if ($spec instanceof RouteInterface) {
            return $spec;
        }

        if (is_string($spec)) {
            if (null === ($route = $this->getPrototype($spec))) {
                throw new RuntimeException(sprintf('Could not find prototype with name %s', $spec));
            }

            return $route;
        }

        if (! is_array($spec)) {
            throw new InvalidArgumentException('Route definition must be an array');
        }

        if (isset($spec['chain_routes'])) {
            $route = $this->createChainFromSpec($spec);
        } else {
            if (! isset($spec['type'])) {
                throw new InvalidArgumentException('Missing "type" option');
            }

            if (! isset($spec['options'])) {
                $spec['options'] = [];
            }

            $route = $this->routes->build($spec['type'], $spec['options']);

            if (isset($spec['priority'])) {
                $route->priority = $spec['priority'];
            }
        }

        if (isset($spec['child_routes'])) {
            $route = $this->createPartFromSpec($spec, $route);
        }

        return $route;
    }

    /**
     * Returns defined prototype route
     */
    public function getPrototype(string $name) : ?RouteInterface
    {
        return $this->prototypes[$name] ?? null;
    }

    /**
     * Defines prototype route to be re-used when creating routes from spec
     */
    public function addPrototype(string $name, RouteInterface $route) : void
    {
        $this->prototypes[$name] = $route;
    }

    /**
     * Wraps route in spec with Chain route, adds chain_routes to chain
     *
     * @throws InvalidArgumentException
     */
    private function createChainFromSpec(array $specs) : RouteInterface
    {
        if (! is_array($specs['chain_routes'])) {
            throw new InvalidArgumentException('Chain routes must be an array');
        }

        $chainRoutesSpec = $specs['chain_routes'];

        $route = $specs;
        unset($route['chain_routes']);
        unset($route['child_routes']);

        array_unshift($chainRoutesSpec, $route);

        $chainRoutes = [];
        foreach ($chainRoutesSpec as $name => $routeSpec) {
            if (is_numeric($name)) {
                $name = sprintf('__chained_route_no_name_%d', self::$chainedIndex++);
            }
            $chainRoutes[$name] = $this->routeFromSpec($routeSpec);
        }

        $options = [
            'routes' => $chainRoutes,
        ];

        return $this->routes->build('chain', $options);
    }

    /**
     * Wraps route in spec with Part route and adds child_routes to Part
     */
    private function createPartFromSpec(array $specs, RouteInterface $route) : RouteInterface
    {
        $childRoutes = [];
        foreach ($specs['child_routes'] as $name => $childSpec) {
            $childRoutes[$name] = $this->routeFromSpec($childSpec);
        }
        $options = [
            'route'         => $route,
            'may_terminate' => $specs['may_terminate'] ?? false,
            'child_routes'  => $childRoutes,
        ];

        $priority = isset($route->priority) ? $route->priority : null;

        $route = $this->routes->build('part', $options);
        if (isset($priority)) {
            $route->priority = $priority;
        }

        return $route;
    }
}
