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
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\I18n\Translator\TranslatorInterface as Translator;

class TranslatorAwareRouteStackDecorator implements RouteStackInterface, TranslatorAwareInterface
{
    /**
     * Translator used for translatable segments.
     *
     * @var Translator
     */
    private $translator;

    /**
     * Whether the translator is enabled.
     *
     * @var bool
     */
    private $translatorEnabled = true;

    /**
     * Translator text domain to use.
     *
     * @var string
     */
    private $translatorTextDomain = 'default';

    /**
     * @var RouteStackInterface
     */
    private $decoratedRouteStack;

    public function __construct(RouteStackInterface $decoratedRouteStack, TranslatorInterface $translator)
    {
        $this->decoratedRouteStack = $decoratedRouteStack;
        $this->setTranslator($translator);
    }

    public function getDecoratedRouteStack() : RouteStackInterface
    {
        return $this->decoratedRouteStack;
    }

    /**
     * @param null|string $textDomain
     */
    public function setTranslator(Translator $translator = null, $textDomain = null) : TranslatorAwareInterface
    {
        $this->translator = $translator;

        if ($textDomain !== null) {
            $this->setTranslatorTextDomain($textDomain);
        }

        return $this;
    }

    public function getTranslator() : ?TranslatorInterface
    {
        return $this->translator;
    }

    public function hasTranslator() : bool
    {
        return $this->translator !== null;
    }

    /**
     * @param bool $enabled
     */
    public function setTranslatorEnabled($enabled = true) : TranslatorAwareInterface
    {
        $this->translatorEnabled = $enabled;
        return $this;
    }

    public function isTranslatorEnabled() : bool
    {
        return $this->translatorEnabled;
    }

    /**
     * @param string $textDomain
     */
    public function setTranslatorTextDomain($textDomain = 'default') : TranslatorAwareInterface
    {
        $this->translatorTextDomain = $textDomain;

        return $this;
    }

    public function getTranslatorTextDomain() : string
    {
        return $this->translatorTextDomain;
    }

    /**
     * Match a given request.
     */
    public function match(Request $request, int $pathOffset = 0, array $options = []) : RouteResult
    {
        // translator always present
        if ($this->isTranslatorEnabled() && ! isset($options['translator'])) {
            $options['translator'] = $this->getTranslator();
        }

        if ($this->isTranslatorEnabled() && ! isset($options['text_domain'])) {
            $options['text_domain'] = $this->getTranslatorTextDomain();
        }
        return $this->decoratedRouteStack->match($request, $pathOffset, $options);
    }

    /**
     * Assemble the route.
     */
    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        if ($this->isTranslatorEnabled() && ! isset($options['translator'])) {
            $options['translator'] = $this->getTranslator();
        }

        if ($this->isTranslatorEnabled() && ! isset($options['text_domain'])) {
            $options['text_domain'] = $this->getTranslatorTextDomain();
        }
        return $this->decoratedRouteStack->assemble($uri, $params, $options);
    }

    /**
     * Add a route to the stack.
     */
    public function addRoute(string $name, RouteInterface $route, int $priority = null) : void
    {
        $this->decoratedRouteStack->addRoute($name, $route, $priority);
    }

    /**
     * Add multiple routes to the stack.
     */
    public function addRoutes(iterable $routes) : void
    {
        $this->decoratedRouteStack->addRoutes($routes);
    }

    /**
     * Remove a route from the stack.
     */
    public function removeRoute(string $name) : void
    {
        $this->decoratedRouteStack->removeRoute($name);
    }

    /**
     * Remove all routes from the stack and set new ones.
     */
    public function setRoutes(iterable $routes) : void
    {
        $this->decoratedRouteStack->setRoutes($routes);
    }

    /**
     * Get the added routes
     */
    public function getRoutes() : array
    {
        return $this->decoratedRouteStack->getRoutes();
    }

    /**
     * Check if a route with a specific name exists
     */
    public function hasRoute(string $name) : bool
    {
        return $this->decoratedRouteStack->hasRoute($name);
    }

    /**
     * Get a route by name
     */
    public function getRoute(string $name) : ?RouteInterface
    {
        return $this->decoratedRouteStack->getRoute($name);
    }
}
