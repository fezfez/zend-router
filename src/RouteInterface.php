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

/**
 * RouteInterface interface.
 */
interface RouteInterface
{
    /**
     * Match a given request.
     */
    public function match(Request $request, int $pathOffset = 0, array $options = []) : RouteResult;

    /**
     * Assemble the route.
     */
    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface;
}
