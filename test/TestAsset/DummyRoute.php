<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\TestAsset;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Zend\Router\RouteInterface;
use Zend\Router\RouteResult;

/**
 * Dummy route.
 */
class DummyRoute implements RouteInterface
{
    public static function factory(array  $options) : self
    {
        return new static();
    }

    public function match(Request $request, int $pathOffset = 0, array $options = []) : RouteResult
    {
        return RouteResult::fromRouteMatch([]);
    }

    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        return $uri;
    }
}
