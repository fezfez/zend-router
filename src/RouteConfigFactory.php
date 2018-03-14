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
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;

class RouteConfigFactory
{
    /**
     * @var RoutePluginManager
     */
    private $routes;

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
    public function routeFromSpec($spec, array $prototypes = []) : RouteInterface
    {
        if ($spec instanceof RouteInterface) {
            return $spec;
        }

        if (is_string($spec)) {
            $route = $prototypes[$spec] ?? null;
            if (null === $route) {
                throw new RuntimeException(sprintf('Could not find prototype with name %s', $spec));
            }
            if (! $route instanceof RouteInterface) {
                throw new RuntimeException(sprintf(
                    'Invalid prototype provided. Expected %s got %s',
                    RouteInterface::class,
                    is_object($route) ? get_class($route) : gettype($route)
                ));
            }

            return $route;
        }

        if (! is_array($spec)) {
            throw new InvalidArgumentException('Route definition must be an array');
        }

        if (isset($spec['chain_routes'])) {
            $route = $this->createChainFromSpec($spec, $prototypes);
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
            $route = $this->createPartFromSpec($spec, $route, $prototypes);
        }

        return $route;
    }

    /**
     * Wraps route in spec with Chain route, adds chain_routes to chain
     *
     * @throws InvalidArgumentException
     */
    private function createChainFromSpec(array $specs, array $prototypes) : RouteInterface
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
            $chainRoutes[$name] = $this->routeFromSpec($routeSpec, $prototypes);
        }

        $options = [
            'routes' => $chainRoutes,
        ];

        return $this->routes->build('chain', $options);
    }

    /**
     * Wraps route in spec with Part route and adds child_routes to Part
     */
    private function createPartFromSpec(array $specs, RouteInterface $route, array $prototypes) : RouteInterface
    {
        $childRoutes = [];
        foreach ($specs['child_routes'] as $name => $childSpec) {
            $childRoutes[$name] = $this->routeFromSpec($childSpec, $prototypes);
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
