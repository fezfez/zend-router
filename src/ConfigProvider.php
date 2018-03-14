<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Zend\Router\Container\RoutePluginManagerFactory;

/**
 * Provide base configuration for using the component.
 *
 * Provides base configuration expected in order to:
 *
 * - seed and configure the default routers and route plugin manager.
 * - provide routes to the given routers.
 */
class ConfigProvider
{
    /**
     * Provide default configuration.
     *
     * @return array
     */
    public function __invoke()
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            RoutePluginManager::class => $this->getRouteManagerConfig(),
        ];
    }

    /**
     * Provide default container dependency configuration.
     *
     * @return array
     */
    public function getDependencyConfig()
    {
        return [
            'aliases' => [
                'RoutePluginManager' => RoutePluginManager::class,
            ],
            'factories' => [
                RoutePluginManager::class => RoutePluginManagerFactory::class,
            ],
        ];
    }

    /**
     * Provide default route plugin manager configuration.
     *
     * @return array
     */
    public function getRouteManagerConfig()
    {
        return [];
    }
}
