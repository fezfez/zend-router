<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\AbstractPluginManager;

/**
 * Plugin manager implementation for routes
 *
 * Enforces that routes retrieved are instances of RouteInterface. It overrides
 * configure() to map invokables to the component-specific
 * RouteInvokableFactory.
 *
 * The manager is marked to not share by default, in order to allow multiple
 * route instances of the same type.
 */
class RoutePluginManager extends AbstractPluginManager
{
    /**
     * Only RouteInterface instances are valid
     *
     * @var string
     */
    protected $instanceOf = RouteInterface::class;

    /**
     * Do not share instances.
     *
     * @var bool
     */
    protected $shareByDefault = false;

    /**
     * @var array
     */
    protected $aliases = [
        'chain'    => Route\Chain::class,
        'Chain'    => Route\Chain::class,
        'hostname' => Route\Hostname::class,
        'Hostname' => Route\Hostname::class,
        'literal'  => Route\Literal::class,
        'Literal'  => Route\Literal::class,
        'method'   => Route\Method::class,
        'Method'   => Route\Method::class,
        'part'     => Route\Part::class,
        'Part'     => Route\Part::class,
        'regex'    => Route\Regex::class,
        'Regex'    => Route\Regex::class,
        'scheme'   => Route\Scheme::class,
        'Scheme'   => Route\Scheme::class,
        'segment'  => Route\Segment::class,
        'Segment'  => Route\Segment::class,
        'Zend\Router\Http\Chain' => Route\Chain::class,
        'Zend\Router\Http\Hostname' => Route\Hostname::class,
        'Zend\Router\Http\Literal' => Route\Literal::class,
        'Zend\Router\Http\Method' => Route\Method::class,
        'Zend\Router\Http\Part' => Route\Part::class,
        'Zend\Router\Http\Regex' => Route\Regex::class,
        'Zend\Router\Http\Scheme' => Route\Scheme::class,
        'Zend\Router\Http\Segment' => Route\Segment::class,
    ];

    /**
     * @var array
     */
    protected $factories = [
        Route\Chain::class    => RouteInvokableFactory::class,
        Route\Hostname::class => RouteInvokableFactory::class,
        Route\Literal::class  => RouteInvokableFactory::class,
        Route\Method::class   => RouteInvokableFactory::class,
        Route\Part::class     => RouteInvokableFactory::class,
        Route\Regex::class    => RouteInvokableFactory::class,
        Route\Scheme::class   => RouteInvokableFactory::class,
        Route\Segment::class  => RouteInvokableFactory::class,
    ];

    /**
     * Constructor
     *
     * Ensure that the instance is seeded with the RouteInvokableFactory as an
     * abstract factory.
     *
     * @param ContainerInterface|\Zend\ServiceManager\ConfigInterface $configOrContainerInstance
     */
    public function __construct($configOrContainerInstance, array $config = [])
    {
        $this->addAbstractFactory(RouteInvokableFactory::class);
        parent::__construct($configOrContainerInstance, $config);
    }
}
