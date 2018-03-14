<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Zend\Router\Exception\RuntimeException;

use function array_key_exists;

/**
 * RouteInterface match.
 *
 * @deprecated
 */
class RouteMatch
{
    /**
     * Match parameters.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Matched route name.
     *
     * @var string
     */
    protected $matchedRouteName;

    /**
     * Create a RouteMatch with given parameters.
     */
    public function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     * @throws RuntimeException
     */
    public static function fromRouteResult(RouteResult $result) : self
    {
        if (! $result->isSuccess()) {
            throw new RuntimeException('Route match cannot be created from failure route result');
        }
        $match = new static($result->getMatchedParams());
        $match->setMatchedRouteName($result->getMatchedRouteName());

        return $match;
    }

    /**
     * Set name of matched route.
     *
     * @param string $name
     * @return $this
     */
    public function setMatchedRouteName($name)
    {
        $this->matchedRouteName = $name;
        return $this;
    }

    /**
     * Get name of matched route.
     *
     * @return string
     */
    public function getMatchedRouteName()
    {
        return $this->matchedRouteName;
    }

    /**
     * Set a parameter.
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setParam($name, $value)
    {
        $this->params[$name] = $value;
        return $this;
    }

    /**
     * Get all parameters.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get a specific parameter.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getParam($name, $default = null)
    {
        if (array_key_exists($name, $this->params)) {
            return $this->params[$name];
        }

        return $default;
    }
}
