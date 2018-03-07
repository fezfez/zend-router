<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace Zend\Router\Route;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Traversable;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\PartialRouteResult;
use Zend\Router\RouteInterface;
use Zend\Router\SimpleRouteStack;
use Zend\Stdlib\ArrayUtils;

use function array_diff_key;
use function array_flip;
use function array_intersect;
use function array_merge;
use function array_reverse;
use function end;
use function is_array;
use function key;

/**
 * Chain route.
 */
class Chain extends SimpleRouteStack implements PartialRouteInterface
{
    use PartialRouteTrait;

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    /**
     * Create a new chain route.
     */
    public function __construct(array $routes)
    {
        parent::__construct();
        $routes = array_reverse($routes, true);
        $this->addRoutes($routes);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function factory(iterable $options = []) : self
    {
        if (! is_array($options)) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (! isset($options['routes'])) {
            throw new InvalidArgumentException('Missing "routes" in options array');
        }

        if ($options['routes'] instanceof Traversable) {
            $options['routes'] = ArrayUtils::iteratorToArray($options['routes']);
        }

        return new static(
            $options['routes']
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public function addRoute(string $name, RouteInterface $route, int $priority = null) : void
    {
        if (! $route instanceof PartialRouteInterface) {
            throw new InvalidArgumentException('Chain route can only chain partial routes');
        }
        parent::addRoute($name, $route, $priority);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function partialMatch(Request $request, int $pathOffset = 0, array $options = []) : PartialRouteResult
    {
        if ($pathOffset < 0) {
            throw new InvalidArgumentException('Path offset cannot be negative');
        }

        $nextPathOffset = $pathOffset;
        $methodFailure = false;
        $allowedMethods = null;
        $matchedParams = [];

        if ($this->routes->count() === 0) {
            return PartialRouteResult::fromRouteFailure();
        }

        foreach ($this->getRoutes() as $route) {
            /** @var PartialRouteInterface $route */
            $result = $route->partialMatch($request, $nextPathOffset, $options);

            if ($result->isFailure() && ! $result->isMethodFailure()) {
                return $result;
            }

            if ($result->isMethodFailure()) {
                $methodFailure = true;
                // make all following method routes fail, needed for allowed
                // methods gathering by Part route even tho it should not
                // be normally allowed to be chained
                $options[Method::OPTION_FORCE_METHOD_FAILURE] = true;

                $allowedMethods = $allowedMethods ?? $result->getAllowedMethods();
                $allowedMethods = array_intersect(
                    $allowedMethods,
                    $result->getAllowedMethods()
                );
            }

            if ($result->isSuccess()) {
                $matchedParams = array_merge($matchedParams, $result->getMatchedParams());

                $options['parent_match_params'] = $options['parent_match_params'] ?? [];
                $options['parent_match_params'] += $matchedParams;

                $methods = $result->getMatchedAllowedMethods();
                if (! empty($methods)) {
                    $allowedMethods = $allowedMethods ?? $methods;
                    $allowedMethods = array_intersect($allowedMethods, $methods);
                }
            }

            $nextPathOffset += $result->getMatchedPathLength();
        }

        $matchedLength = $nextPathOffset - $pathOffset;
        if ($methodFailure) {
            if (empty($allowedMethods)) {
                return PartialRouteResult::fromRouteFailure();
            }
            return PartialRouteResult::fromMethodFailure($allowedMethods, $pathOffset, $matchedLength);
        }

        // explicitly discarding chained route names if any
        return PartialRouteResult::fromRouteMatch(
            $matchedParams,
            $pathOffset,
            $matchedLength,
            null,
            $allowedMethods
        );
    }

    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        $this->assembledParams = [];

        $routes = ArrayUtils::iteratorToArray($this->routes);
        end($routes);
        $lastRouteKey = key($routes);

        foreach ($routes as $key => $route) {
            /** @var PartialRouteInterface $route */
            $chainOptions = $options;
            $hasChild = isset($options['has_child']) ? $options['has_child'] : false;

            $chainOptions['has_child'] = $hasChild || $key !== $lastRouteKey;

            $uri = $route->assemble($uri, $params, $chainOptions);
            $params = array_diff_key($params, array_flip($route->getLastAssembledParams()));

            $this->assembledParams = array_merge($this->assembledParams, $route->getLastAssembledParams());
        }

        return $uri;
    }

    public function getLastAssembledParams() : array
    {
        return $this->assembledParams;
    }

    /**
     * @deprecated
     */
    public function getAssembledParams() : array
    {
        return $this->getLastAssembledParams();
    }
}
