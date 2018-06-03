<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Zend\Router\Route;
use Zend\Router\RoutePluginManager;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\ServiceManager;

/**
 * @covers \Zend\Router\RoutePluginManager
 */
class RoutePluginManagerTest extends TestCase
{
    public function testLoadNonExistentRoute()
    {
        $routes = new RoutePluginManager(new ServiceManager());
        $this->expectException(ServiceNotFoundException::class);
        $routes->get('foo');
    }

    public function testCanLoadAnyRoute()
    {
        $routes = new RoutePluginManager(new ServiceManager(), [
            'aliases' => [
                'DummyRoute' => TestAsset\DummyRoute::class,
            ],
        ]);
        $route = $routes->get('DummyRoute');

        $this->assertInstanceOf(TestAsset\DummyRoute::class, $route);
    }

    public function partialRouteList() : iterable
    {
        yield 'Chain' => [Route\Chain::class, ['routes' => []]];
        yield 'Hostname' => [Route\Hostname::class, ['route' => 'test']];
        yield 'Literal' => [Route\Literal::class, ['route' => 'test']];
        yield 'Method' => [Route\Method::class, ['verb' => 'GET']];
        yield 'Part' => [Route\Part::class, ['route' => new Route\Hostname('test')]];
        yield 'Regex' => [Route\Regex::class, ['regex' => '', 'spec' => '']];
        yield 'Scheme' => [Route\Scheme::class, ['scheme' => '']];
        yield 'Segment' => [Route\Segment::class, ['route' => 'test']];
    }

    /**
     * @dataProvider partialRouteList
     */
    public function testCanGetRouteViaFactory($partialRoute, $args)
    {
        $routes = new RoutePluginManager(new ServiceManager());
        $this->assertInstanceOf($partialRoute, $routes->get($partialRoute, $args));
    }
}
