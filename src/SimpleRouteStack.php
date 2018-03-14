<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;

use function array_merge;
use function array_reduce;
use function sprintf;

/**
 * Simple route stack implementation.
 */
class SimpleRouteStack implements RouteStackInterface
{
    /**
     * Stack containing all routes.
     *
     * @var PriorityList
     */
    protected $routes;

    /**
     * Default parameters.
     *
     * @var array
     */
    protected $defaultParams = [];

    /**
     * Create a new simple route stack.
     */
    public function __construct()
    {
        $this->routes = new PriorityList();
    }

    public function addRoutes(iterable $routes) : void
    {
        foreach ($routes as $name => $route) {
            $this->addRoute($name, $route);
        }
    }

    public function addRoute(string $name, RouteInterface $route, int $priority = null) : void
    {
        if ($priority === null && isset($route->priority)) {
            $priority = $route->priority;
        }

        $this->routes->insert($name, $route, $priority);
    }

    public function removeRoute(string $name) : void
    {
        $this->routes->remove($name);
    }

    public function setRoutes(iterable $routes) : void
    {
        $this->routes->clear();
        $this->addRoutes($routes);
    }

    public function getRoutes() : array
    {
        return $this->routes->toArray($this->routes::EXTR_DATA);
    }

    public function hasRoute(string $name) : bool
    {
        return $this->routes->get($name) !== null;
    }

    public function getRoute(string $name) : ?RouteInterface
    {
        return $this->routes->get($name);
    }

    public function setDefaultParams(array $params) : void
    {
        $this->defaultParams = $params;
    }

    /**
     * Set a default parameter.
     *
     * @param mixed $value
     */
    public function setDefaultParam(string $name, $value) : void
    {
        $this->defaultParams[$name] = $value;
    }

    public function match(Request $request, int $pathOffset = 0, array $options = []) : RouteResult
    {
        $methodFailureResults = [];
        foreach ($this->routes as $name => $route) {
            /** @var RouteInterface $route */
            $result = $route->match($request, $pathOffset, $options);
            if ($result->isSuccess()) {
                $result = $result->withMatchedRouteName($name);
                $result = $result->withMatchedParams(
                    array_merge($this->defaultParams, $result->getMatchedParams())
                );
                return $result;
            }
            if ($result->isMethodFailure()) {
                $methodFailureResults[] = $result;
            }
        }

        if (! empty($methodFailureResults)) {
            $allowedMethods = array_reduce($methodFailureResults, function (array $methods, RouteResult $result) {
                return $methods + $result->getAllowedMethods();
            }, []);
            return RouteResult::fromMethodFailure($allowedMethods);
        }
        return RouteResult::fromRouteFailure();
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        if (! isset($options['name'])) {
            throw new InvalidArgumentException('Missing "name" option');
        }

        $route = $this->routes->get($options['name']);

        if (! $route) {
            throw new RuntimeException(sprintf('Route with name "%s" not found', $options['name']));
        }

        unset($options['name']);

        return $route->assemble($uri, array_merge($this->defaultParams, $params), $options);
    }
}
