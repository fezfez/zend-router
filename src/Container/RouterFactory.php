<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router\Container;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\UriInterface;
use Zend\Router\RouteConfigFactory;
use Zend\Router\Router;
use Zend\Router\RouteStackInterface;
use Zend\Router\TreeRouteStack;

/**
 * Default factory for Router.
 * It is not registered in ConfigProvider and acts as a base factory to
 * be used and extended by consumers
 */
class RouterFactory
{
    public function __invoke(ContainerInterface $container) : Router
    {
        $router = new Router(
            $container->get(RouteConfigFactory::class),
            $this->getRouteStack($container),
            $container->get(UriInterface::class)
        );
        $this->configureRouter($container, $router);

        return $router;
    }

    public function getRouteStack(ContainerInterface $container) : RouteStackInterface
    {
        return new TreeRouteStack();
    }

    public function configureRouter(ContainerInterface $container, Router $router) : void
    {
        $config = $this->getRouterConfig($container);

        foreach ($config['prototypes'] as $name => $prototype) {
            $router->addPrototype($name, $prototype);
        }

        foreach ($config['routes'] as $name => $route) {
            $router->addRoute($name, $route);
        }
    }

    public function getRouterConfig(ContainerInterface $container) : array
    {
        return [
            'routes' => [],
            'prototypes' => [],
        ];
    }
}
