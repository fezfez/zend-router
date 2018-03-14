<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

interface RouteStackInterface extends RouteInterface
{
    /**
     * Add a route to the stack.
     */
    public function addRoute(string $name, RouteInterface $route, int $priority = null) : void;

    /**
     * Add multiple routes to the stack.
     */
    public function addRoutes(iterable $routes) : void;

    /**
     * Remove a route from the stack.
     */
    public function removeRoute(string $name) : void;

    /**
     * Remove all routes from the stack and set new ones.
     */
    public function setRoutes(iterable $routes) : void;

    /**
     * Get the added routes
     */
    public function getRoutes() : array;

    /**
     * Check if a route with a specific name exists
     */
    public function hasRoute(string $name) : bool;

    /**
     * Get a route by name
     */
    public function getRoute(string $name) : ?RouteInterface;
}
