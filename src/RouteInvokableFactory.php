<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;

use function class_exists;
use function is_subclass_of;
use function method_exists;
use function sprintf;

/**
 * Specialized invokable/abstract factory for use with RoutePluginManager.
 *
 * Can be mapped directly to specific route plugin names, or used as an
 * abstract factory to map FQCN services to invokables.
 */
class RouteInvokableFactory implements AbstractFactoryInterface
{
    /**
     * Can we create a route instance with the given name?
     *
     * Only works for FQCN $routeName values, for classes that implement RouteInterface
     * and have factory method.
     *
     * @param string $routeName
     */
    public function canCreate(ContainerInterface $container, $routeName) : bool
    {
        if (! is_string($routeName)) {
            return false;
        }

        if (! class_exists($routeName)) {
            return false;
        }

        if (! is_subclass_of($routeName, RouteInterface::class)) {
            return false;
        }

        if (! method_exists($routeName, 'factory')) {
            return false;
        }

        return true;
    }

    /**
     * Create and return a RouteInterface instance.
     *
     * If the specified $routeName class does not exist, does not implement
     * RouteInterface or does not provide factory method, this method will raise an exception.
     *
     * Otherwise, it uses the class' `factory()` method with the provided
     * $options to produce an instance.
     *
     * @param string $routeName
     * @throws ServiceNotCreatedException
     */
    public function __invoke(ContainerInterface $container, $routeName, array $options = null) : RouteInterface
    {
        $options = $options ?: [];

        if (! class_exists($routeName)) {
            throw new ServiceNotCreatedException(sprintf(
                '%s: failed retrieving invokable class "%s"; class does not exist',
                __CLASS__,
                $routeName
            ));
        }

        if (! is_subclass_of($routeName, RouteInterface::class)) {
            throw new ServiceNotCreatedException(sprintf(
                '%s: failed retrieving invokable class "%s"; class does not implement %s',
                __CLASS__,
                $routeName,
                RouteInterface::class
            ));
        }

        if (! method_exists($routeName, 'factory')) {
            throw new ServiceNotCreatedException(sprintf(
                '%s: failed retrieving invokable class "%s"; class does not provide factory method',
                __CLASS__,
                $routeName
            ));
        }

        return $routeName::factory($options);
    }
}
