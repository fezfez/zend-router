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

class Router
{
    /**
     * @var RouteConfigFactory
     */
    private $routeFactory;

    /**
     * @var RouteStackInterface
     */
    private $routeStack;

    /**
     * @var callable
     */
    private $uriFactory;

    /**
     * Extra options to pass to match and assemble
     *
     * @var array
     */
    private $options = [];

    /**
     * Reusable "prototype" routes could be referenced by name and reused in
     * router configuration
     *
     * @var RouteInterface[]
     */
    private $prototypes = [];

    public function __construct(
        RouteConfigFactory $routeFactory,
        RouteStackInterface $routeStack,
        callable $uriFactory
    ) {
        $this->routeFactory = $routeFactory;
        $this->routeStack = $routeStack;
        $this->uriFactory = $uriFactory;
    }

    public function getRouteFactory() : RouteConfigFactory
    {
        return $this->routeFactory;
    }

    public function setRouteStack(RouteStackInterface $routeStack) : void
    {
        $this->routeStack = $routeStack;
    }

    public function getRouteStack() : RouteStackInterface
    {
        return $this->routeStack;
    }

    /**
     * Add route to the underlying route stack.
     *
     * @param array|string|RouteInterface $routeOrSpec Route instance, array
     *     specification or string name of prototype route to add
     */
    public function addRoute(string $name, $routeOrSpec) : void
    {
        $this->routeStack->addRoute($name, $this->routeFactory->routeFromSpec($routeOrSpec, $this->prototypes));
    }

    /**
     * Add reusable "prototype" route to be used when adding routers as
     * array or string specification
     *
     * @param array|RouteInterface $routeOrSpec
     */
    public function addPrototype(string $name, $routeOrSpec) : void
    {
        $this->prototypes[$name] = $this->routeFactory->routeFromSpec($routeOrSpec);
    }

    /**
     * Get reusable "prototype" route
     */
    public function getPrototype(string $name) : ?RouteInterface
    {
        return $this->prototypes[$name] ?? null;
    }

    /**
     * Get registered "prototype" routes
     */
    public function getPrototypes() : array
    {
        return $this->prototypes;
    }

    /**
     * Match request using configured route stack
     */
    public function match(Request $request) : RouteResult
    {
        return $this->routeStack->match($request, 0);
    }

    /**
     * Assemble uri using configured route stack
     */
    public function assemble(string $name, array $params, array $options = []) : UriInterface
    {
        $options['name'] = $name;
        $uri = ($this->uriFactory)();
        return $this->routeStack->assemble($uri, $params, $options);
    }
}
