<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace Zend\Router\Route;

use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\RouteResult;

trait PartialRouteTrait
{
    /**
     * Attempts to match a request by delegating to {@see PartialRouteInterface::partialMatch()}.
     *
     * Returns successful route result with matched parameters and route name
     * only if partial route result is a success and a full uri path match.
     *
     * Returns http method failure result only if partial route result is a
     * method failure and a full path match.
     *
     * @throws InvalidArgumentException
     */
    public function match(Request $request, int $pathOffset = 0, array $options = []) : RouteResult
    {
        if ($pathOffset < 0) {
            throw new InvalidArgumentException('Path offset cannot be negative');
        }

        /** @var PartialRouteInterface $this */
        $result = $this->partialMatch($request, $pathOffset, $options);
        if (! $result->isFullPathMatch($request->getUri())) {
            return RouteResult::fromRouteFailure();
        }
        if ($result->isSuccess()) {
            return RouteResult::fromRouteMatch($result->getMatchedParams(), $result->getMatchedRouteName());
        }
        if ($result->isMethodFailure()) {
            return RouteResult::fromMethodFailure($result->getAllowedMethods());
        }
        // unreachable due to full path match check. It is kept here intentionally
        return RouteResult::fromRouteFailure();
    }
}
