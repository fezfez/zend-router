<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router\Route;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Zend\Router\Exception;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\RouteInterface;
use Zend\Router\RouteResult;
use Zend\Router\RouteStackInterface;
use Zend\Router\TreeRouteStack;
use Zend\Stdlib\ArrayUtils;

use function array_diff_key;
use function array_flip;
use function array_intersect;
use function array_merge;
use function is_array;

/**
 * Part route.
 */
class Part implements RouteStackInterface
{
    use PartialRouteTrait;

    /**
     * RouteInterface to match.
     *
     * @var PartialRouteInterface
     */
    private $route;

    /**
     * Child routes.
     *
     * @var RouteStackInterface
     */
    private $childRoutes;

    /**
     * Whether the route may terminate.
     *
     * @var bool
     */
    protected $mayTerminate;

    /**
     * Create a new part route.
     */
    public function __construct(PartialRouteInterface $route, RouteStackInterface $childRoutes, bool $mayTerminate)
    {
        $this->route = $route;
        $this->childRoutes = $childRoutes;
        $this->mayTerminate = $mayTerminate;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function factory(iterable $options = []) : self
    {
        if (! is_array($options)) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (! isset($options['route'])) {
            throw new InvalidArgumentException('Missing "route" in options array');
        }

        if (! isset($options['child_routes']) || ! $options['child_routes']) {
            $options['child_routes'] = [];
        }

        if (is_array($options['child_routes'])) {
            $childRoutes = new TreeRouteStack();
            $childRoutes->addRoutes($options['child_routes']);
            $options['child_routes'] = $childRoutes;
        }

        if (! isset($options['may_terminate'])) {
            $options['may_terminate'] = false;
        }

        return new static(
            $options['route'],
            $options['child_routes'],
            $options['may_terminate']
        );
    }

    /**
     * Match a given request.
     *
     * @throws InvalidArgumentException on negative path offset
     */
    public function match(Request $request, int $pathOffset = 0, array $options = []) : RouteResult
    {
        if ($pathOffset < 0) {
            throw new InvalidArgumentException('Path offset cannot be negative');
        }
        $partialResult = $this->route->partialMatch($request, $pathOffset, $options);

        // continue matching for method failure to allow precise method list
        if ($partialResult->isFailure() && ! $partialResult->isMethodFailure()) {
            return RouteResult::fromRouteFailure();
        }

        if ($this->mayTerminate && $partialResult->isFullPathMatch($request->getUri())) {
            // we get complete list of allowed methods on method failure. Child
            // routes cannot expand it, so no reason to try to gather allowed
            // methods for them
            if ($partialResult->isMethodFailure()) {
                return RouteResult::fromMethodFailure($partialResult->getAllowedMethods());
            }
            // We got full match, our work here is done
            return RouteResult::fromRouteMatch(
                $partialResult->getMatchedParams()
            );
        }

        // pass matched params to child routes.
        // Could be used for eg obtaining locale from matched parameters from parent routes.
        if ($partialResult->isSuccess()) {
            $options['parent_match_params'] = $options['parent_match_params'] ?? [];
            $options['parent_match_params'] += $partialResult->getMatchedParams();
        }

        // we continue matching only to gather allowed methods. Force
        // method routes to fail
        if ($partialResult->isMethodFailure()) {
            $options[Method::OPTION_FORCE_METHOD_FAILURE] = true;
        }

        $nextOffset = $pathOffset + $partialResult->getMatchedPathLength();

        $childResult = $this->childRoutes->match($request, $nextOffset, $options);

        if ($partialResult->isSuccess() && $childResult->isSuccess()) {
            return $childResult->withMatchedParams(
                array_merge($partialResult->getMatchedParams(), $childResult->getMatchedParams())
            );
        }

        if ($childResult->isMethodFailure() && $partialResult->isMethodFailure()) {
            $methods = array_intersect(
                $partialResult->getAllowedMethods(),
                $childResult->getAllowedMethods()
            );
            if (empty($methods)) {
                return RouteResult::fromRouteFailure();
            }
            return RouteResult::fromMethodFailure($methods);
        }

        if ($partialResult->isMethodFailure() && $childResult->isSuccess()) {
            return RouteResult::fromMethodFailure(
                $partialResult->getAllowedMethods()
            );
        }

        if ($childResult->isMethodFailure()) {
            $parentMethods = $partialResult->getMatchedAllowedMethods();
            $methods = $childResult->getAllowedMethods();
            if (! empty($parentMethods)) {
                $methods = array_intersect(
                    $parentMethods,
                    $childResult->getAllowedMethods()
                );
            }
            return RouteResult::fromMethodFailure($methods);
        }

        return RouteResult::fromRouteFailure();
    }

    /**
     * Assemble uri for the route.
     *
     * @throws Exception\RuntimeException when trying to assemble part route without
     *     child route name, if part route can't terminate
     */
    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        $partOptions = $options;
        $partOptions['has_child'] = isset($options['name']);
        unset($partOptions['name']);

        $uri = $this->route->assemble($uri, $params, $partOptions);
        $params = array_diff_key($params, array_flip($this->route->getLastAssembledParams()));

        if (! isset($options['name'])) {
            if (! $this->mayTerminate) {
                throw new Exception\RuntimeException('Part route may not terminate');
            } else {
                return $uri;
            }
        }

        return $this->childRoutes->assemble($uri, $params, $options);
    }

    /**
     * Add a route to the stack.
     */
    public function addRoute(string $name, RouteInterface $route, int $priority = null) : void
    {
        $this->childRoutes->addRoute($name, $route, $priority);
    }

    /**
     * Add multiple routes to the stack.
     */
    public function addRoutes(iterable $routes) : void
    {
        $this->childRoutes->addRoutes($routes);
    }

    /**
     * Remove a route from the stack.
     */
    public function removeRoute(string $name) : void
    {
        $this->childRoutes->removeRoute($name);
    }

    /**
     * Remove all routes from the stack and set new ones.
     */
    public function setRoutes(iterable $routes) : void
    {
        $this->childRoutes->setRoutes($routes);
    }

    /**
     * Get the added routes
     */
    public function getRoutes() : array
    {
        return $this->childRoutes->getRoutes();
    }

    /**
     * Check if a route with a specific name exists
     */
    public function hasRoute(string $name) : bool
    {
        return $this->childRoutes->hasRoute($name);
    }

    /**
     * Get a route by name
     */
    public function getRoute(string $name) : ?RouteInterface
    {
        return $this->childRoutes->getRoute($name);
    }
}
