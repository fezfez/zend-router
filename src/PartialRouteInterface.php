<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Psr\Http\Message\ServerRequestInterface as Request;

interface PartialRouteInterface extends RouteInterface
{
    /**
     * Match a given request.
     * Match is considered successful even if there is more path left to match.
     */
    public function partialMatch(Request $request, int $pathOffset = 0, array $options = []) : PartialRouteResult;

    /**
     * Get parameters used to assemble uri on the last assemble invocation.
     * Used during uri assembling by Part and Chain routes
     *
     * @internal
     */
    public function getLastAssembledParams() : array;
}
