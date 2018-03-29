<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router\Container;

use Psr\Container\ContainerInterface;
use Zend\Router\RouteConfigFactory;
use Zend\Router\RoutePluginManager;

class RouteConfigFactoryFactory
{
    public function __invoke(ContainerInterface $container) : RouteConfigFactory
    {
        return new RouteConfigFactory($container->get(RoutePluginManager::class));
    }
}
