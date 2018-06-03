<?php
declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Zend\Router\PartialRouteInterface;
use Zend\Router\Route\Hostname;
use Zend\Router\RouteInvokableFactory;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use ZendTest\Router\TestAsset\EmptyClass;

/**
 * @covers \Zend\Router\RouteInvokableFactory
 */
class RouteInvokableFactoryTest extends TestCase
{
    public function provideFailCaseOnCanCreate() : iterable
    {
        yield 'array' => [[]];
        yield 'bool' => [true];
        yield 'tnt' => [10];
        yield 'classDoesNotExist' => ['ClassThatDoesNotExist'];
        yield 'classNotSubClassOfRouteInterface' => [EmptyClass::class];

        $partialRoute = $this->prophesize(PartialRouteInterface::class);

        yield 'partialRouteWithoutFactory' => [get_class($partialRoute->reveal())];
    }

    /**
     * @dataProvider provideFailCaseOnCanCreate
     */
    public function testFailCaseOnCanCreate($routeName) : void
    {
        $container = $this->prophesize(ContainerInterface::class);
        $sUT = new RouteInvokableFactory();

        $this->assertFalse($sUT->canCreate($container->reveal(), $routeName));
    }

    public function testCanCreateOnCorrectClass()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $sUT = new RouteInvokableFactory();

        $this->assertTrue($sUT->canCreate($container->reveal(),Hostname::class));
    }

    public function provideFailCaseOnInvoke() : iterable
    {
        yield 'classDoesNotExist' => [
            'ClassThatDoesNotExist',
            'Zend\Router\RouteInvokableFactory: failed retrieving invokable class "ClassThatDoesNotExist"; class does not exist'
        ];
        yield 'classNotSubClassOfRouteInterface' => [
            EmptyClass::class,
            'Zend\Router\RouteInvokableFactory: failed retrieving invokable class "ZendTest\Router\TestAsset\EmptyClass"; class does not implement Zend\Router\RouteInterface'
        ];

        $partialRoute = get_class($this->prophesize(PartialRouteInterface::class)->reveal());

        yield 'partialRouteWithoutFactory' => [
            $partialRoute,
            sprintf('Zend\Router\RouteInvokableFactory: failed retrieving invokable class "%s"; class does not provide factory method', $partialRoute)
        ];
    }

    /**
     * @dataProvider provideFailCaseOnInvoke
     */
    public function testFailCaseOnInvoke($routeName, string $message) : void
    {
        $container = $this->prophesize(ContainerInterface::class);
        $sUT = new RouteInvokableFactory();

        $this->expectException(ServiceNotCreatedException::class);
        $this->expectExceptionMessage($message);
        $this->assertFalse($sUT->__invoke($container->reveal(), $routeName));
    }

    public function testInvokeOnCorrectClass()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $sUT = new RouteInvokableFactory();

        $this->assertInstanceOf(Hostname::class, $sUT->__invoke($container->reveal(),Hostname::class, ['route' => 'test']));
    }
}
