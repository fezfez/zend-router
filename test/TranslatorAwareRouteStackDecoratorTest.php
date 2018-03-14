<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\Router\RouteInterface;
use Zend\Router\RouteResult;
use Zend\Router\RouteStackInterface;
use Zend\Router\TranslatorAwareRouteStackDecorator;

/**
 * @covers \Zend\Router\TranslatorAwareRouteStackDecorator
 */
class TranslatorAwareRouteStackDecoratorTest extends TestCase
{
    /**
     * @var ObjectProphecy|TranslatorInterface
     */
    private $translator;

    /**
     * @var ObjectProphecy|TranslatorAwareRouteStackDecorator
     */
    private $decorator;

    /**
     * @var RouteStackInterface
     */
    private $routeStack;

    public function setUp()
    {
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->routeStack = $this->prophesize(RouteStackInterface::class);
        $this->decorator = new TranslatorAwareRouteStackDecorator(
            $this->routeStack->reveal(),
            $this->translator->reveal()
        );
    }

    public function testGetDecoratedRouteStack()
    {
        $this->assertSame($this->routeStack->reveal(), $this->decorator->getDecoratedRouteStack());
    }

    public function testGetTranslator()
    {
        $this->assertSame($this->translator->reveal(), $this->decorator->getTranslator());
    }

    public function testSetTranslator()
    {
        $translator = $this->prophesize(TranslatorInterface::class)
                           ->reveal();
        $this->decorator->setTranslator($translator);
        $this->assertSame($translator, $this->decorator->getTranslator());
    }

    public function testHasTranslator()
    {
        // translator is constructor injected and always present
        $this->assertTrue($this->decorator->hasTranslator());
    }

    public function testSetTranslatorEnabled()
    {
        $this->assertTrue($this->decorator->isTranslatorEnabled());
        $this->decorator->setTranslatorEnabled(false);
        $this->assertFalse($this->decorator->isTranslatorEnabled());
    }

    public function testTranslatorEnabledByDefault()
    {
        $this->assertTrue($this->decorator->isTranslatorEnabled());
    }

    public function testSetTranslatorTextDomain()
    {
        $this->decorator->setTranslatorTextDomain('foo');
        $this->assertEquals('foo', $this->decorator->getTranslatorTextDomain());

        $this->decorator->setTranslator($this->translator->reveal(), 'bar');
        $this->assertEquals('bar', $this->decorator->getTranslatorTextDomain());
    }

    public function testGetTranslatorTextDomain()
    {
        $this->assertEquals('default', $this->decorator->getTranslatorTextDomain());
    }

    public function testMatchProxiesToDecoratedRouteStack()
    {
        $request = new ServerRequest();
        $options = ['foo' => 'bar'];
        $result = RouteResult::fromRouteFailure();
        $this->routeStack->match($request, 1, $options)
                         ->willReturn($result)
                         ->shouldBeCalled();

        $this->decorator->setTranslatorEnabled(false);
        $returnedResult = $this->decorator->match($request, 1, $options);
        $this->assertSame($result, $returnedResult);
    }

    public function testMatchAddsTextDomainAndTranslatorWhenTranslatorEnabled()
    {
        $request = new ServerRequest();
        $options = ['foo' => 'bar'];
        $result = RouteResult::fromRouteFailure();
        $expectedOptions = $options;
        $expectedOptions['text_domain'] = 'default';
        $expectedOptions['translator'] = $this->translator->reveal();
        $this->routeStack->match($request, 1, $expectedOptions)
                         ->willReturn($result)
                         ->shouldBeCalled();

        $this->decorator->setTranslatorEnabled(true);
        $returnedResult = $this->decorator->match($request, 1, $options);
        $this->assertSame($result, $returnedResult);
    }

    /**
     * Matches v3 TranslatorAwareTreeRouteStack behavior. From design
     * standpoint it should at least hard fail if 'translator' option is not
     * instance of TranslatorInterface to avoid hard to debug unexpected behavior.
     */
    public function testMatchDoesNotOverrideTranslatorOrTextDomainOptions()
    {
        $request = new ServerRequest();
        $options = ['foo' => 'bar', 'text_domain' => 'another', 'translator' => 'foo'];
        $result = RouteResult::fromRouteFailure();
        $this->routeStack->match($request, 1, $options)
                         ->willReturn($result)
                         ->shouldBeCalled();

        $this->decorator->setTranslatorEnabled(true);
        $returnedResult = $this->decorator->match($request, 1, $options);
        $this->assertSame($result, $returnedResult);
    }

    public function testAssembleProxiesToDecoratedRouteStack()
    {
        $uri = new Uri();
        $expectUri = new Uri();
        $options = ['foo' => 'bar'];
        $params = ['baz' => 'qux'];
        $this->routeStack->assemble($uri, $params, $options)
                         ->willReturn($expectUri)
                         ->shouldBeCalled();

        $this->decorator->setTranslatorEnabled(false);
        $returnedUri = $this->decorator->assemble($uri, $params, $options);
        $this->assertSame($expectUri, $returnedUri);
    }

    public function testAssembleAddsTextDomainAndTranslatorWhenTranslatorEnabled()
    {
        $uri = new Uri();
        $expectUri = new Uri();
        $options = ['foo' => 'bar'];
        $params = ['baz' => 'qux'];
        $expectedOptions = $options;
        $expectedOptions['text_domain'] = 'default';
        $expectedOptions['translator'] = $this->translator->reveal();
        $this->routeStack->assemble($uri, $params, $expectedOptions)
                         ->willReturn($expectUri)
                         ->shouldBeCalled();

        $this->decorator->setTranslatorEnabled(true);
        $returnedUri = $this->decorator->assemble($uri, $params, $options);
        $this->assertSame($expectUri, $returnedUri);
    }

    public function testAssembleDoesNotOverrideTextDomainAndTranslatorOptions()
    {
        $uri = new Uri();
        $expectUri = new Uri();
        $options = ['foo' => 'bar', 'text_domain' => 'another', 'translator' => 'foo'];
        $params = ['baz' => 'qux'];
        $this->routeStack->assemble($uri, $params, $options)
                         ->willReturn($expectUri)
                         ->shouldBeCalled();

        $this->decorator->setTranslatorEnabled(true);
        $returnedUri = $this->decorator->assemble($uri, $params, $options);
        $this->assertSame($expectUri, $returnedUri);
    }

    public function testAddRoute()
    {
        $route = $this->prophesize(RouteInterface::class)->reveal();
        $this->routeStack->addRoute('test', $route, 10)
                         ->shouldBeCalled();
        $this->decorator->addRoute('test', $route, 10);
    }

    public function testAddRoutes()
    {
        $route = $this->prophesize(RouteInterface::class)->reveal();
        $this->routeStack->addRoutes(['test' => $route])
                         ->shouldBeCalled();
        $this->decorator->addRoutes(['test' => $route]);
    }

    public function testRemoveRoute()
    {
        $this->routeStack->removeRoute('test')
                         ->shouldBeCalled();
        $this->decorator->removeRoute('test');
    }

    public function testSetRoutes()
    {
        $route = $this->prophesize(RouteInterface::class)->reveal();
        $this->routeStack->setRoutes(['test' => $route])
                         ->shouldBeCalled();
        $this->decorator->setRoutes(['test' => $route]);
    }

    public function testGetRoutes()
    {
        $route = $this->prophesize(RouteInterface::class)->reveal();
        $this->routeStack->getRoutes()
                         ->willReturn(['test' => $route])
                         ->shouldBeCalled();
        $routes = $this->decorator->getRoutes();
        $this->assertEquals(['test' => $route], $routes);
    }

    public function testHasRoute()
    {
        $this->routeStack->hasRoute('test')
                         ->shouldBeCalled();
        $this->decorator->hasRoute('test');
    }

    public function testGetRoute()
    {
        $route = $this->prophesize(RouteInterface::class)->reveal();
        $this->routeStack->getRoute('test')
                         ->willReturn($route)
                         ->shouldBeCalled();
        $returned = $this->decorator->getRoute('test');
        $this->assertSame($route, $returned);
    }
}
