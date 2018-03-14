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
use Zend\Router\Route\Method;

use function array_merge;
use function explode;
use function sprintf;

/**
 * Tree search implementation.
 */
class TreeRouteStack extends SimpleRouteStack
{
    public function match(Request $request, int $pathOffset = 0, array  $options = []) : RouteResult
    {
        $allowedMethods = [];
        foreach ($this->routes as $name => $route) {
            /** @var RouteInterface $route */
            $result = $route->match($request, $pathOffset, $options);
            if ($result->isSuccess()) {
                $result = $result->withMatchedRouteName($name, RouteResult::NAME_PREPEND);
                $result = $result->withMatchedParams(
                    array_merge($this->defaultParams, $result->getMatchedParams())
                );
                return $result;
            }
            if ($result->isMethodFailure()) {
                $options[Method::OPTION_FORCE_METHOD_FAILURE] = true;
                $allowedMethods = array_merge($allowedMethods, $result->getAllowedMethods());
            }
        }

        if (! empty($allowedMethods)) {
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

        $names = explode('/', $options['name'], 2);
        $route = $this->routes->get($names[0]);

        if (! $route) {
            throw new RuntimeException(sprintf('Route with name "%s" not found', $names[0]));
        }

        if (isset($names[1])) {
            if (! $route instanceof RouteStackInterface) {
                throw new RuntimeException(sprintf(
                    'Route with name "%s" does not have child routes',
                    $names[0]
                ));
            }
            $options['name'] = $names[1];
        } else {
            unset($options['name']);
        }

        return $route->assemble($uri, array_merge($this->defaultParams, $params), $options);
    }
}
