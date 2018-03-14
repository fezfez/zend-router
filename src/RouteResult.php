<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Zend\Router\Exception\DomainException;
use Zend\Router\Exception\RuntimeException;

use function array_change_key_case;
use function array_flip;
use function array_keys;
use function sprintf;

use const CASE_UPPER;

final class RouteResult
{
    public const NAME_REPLACE = 'replace';
    public const NAME_PREPEND = 'prepend';
    public const NAME_APPEND = 'append';

    /**
     * @var string[]
     */
    private $allowedMethods = [];

    /**
     * @var bool Success state of routing
     */
    private $success;

    /**
     * Match parameters.
     *
     * @var array
     */
    private $matchedParams = [];

    /**
     * Matched route name.
     *
     * @var null|string
     */
    private $matchedRouteName;

    /**
     * Create successful routing result
     */
    public static function fromRouteMatch(array $matchedParams, string $routeName = null) : self
    {
        $result = new self();
        $result->success = true;
        $result->matchedParams = $matchedParams;
        $result->matchedRouteName = $routeName;
        return $result;
    }

    /**
     * Create failed routing result
     */
    public static function fromRouteFailure() : self
    {
        $result = new self();
        $result->success = false;
        return $result;
    }

    /**
     * Create routing failure result where http method is not allowed for the
     * otherwise routable request
     *
     * @throws DomainException
     */
    public static function fromMethodFailure(array $allowedMethods) : self
    {
        if (empty($allowedMethods)) {
            throw new DomainException('Method failure requires list of allowed methods');
        }
        $result = new self();
        $result->success = false;
        $result->setAllowedMethods($allowedMethods);
        return $result;
    }

    /**
     * Is this a routing success result?
     */
    public function isSuccess() : bool
    {
        return $this->success;
    }

    /**
     * Is this a routing failure result?
     */
    public function isFailure() : bool
    {
        return ! $this->success;
    }

    /**
     * Is this a result for failed routing due to HTTP method?
     */
    public function isMethodFailure() : bool
    {
        if ($this->isSuccess() || empty($this->allowedMethods)) {
            return false;
        }
        return true;
    }

    /**
     * Produce a new route result with provided route name. Can only be used
     * with successful result.
     *
     * @param string $flag Signifies mode of setting route name:
     *     - {@see RouteResult::NAME_REPLACE} replaces existing route name
     *     - {@see RouteResult::NAME_PREPEND} prepends as a parent route part name.
     *     - {@see RouteResult::NAME_APPEND} appends as a child route part name.
     * @throws DomainException
     * @throws RuntimeException
     */
    public function withMatchedRouteName(string $routeName, $flag = self::NAME_REPLACE) : self
    {
        if (empty($routeName)) {
            throw new DomainException('Route name cannot be empty');
        }
        if (! $this->isSuccess()) {
            throw new RuntimeException('Only successful routing can have matched route name');
        }
        $result = clone $this;

        // If no matched route name is set, simply replace value
        if ($flag === self::NAME_REPLACE || $this->matchedRouteName === null) {
            $result->matchedRouteName = $routeName;
            return $result;
        }

        if ($flag === self::NAME_PREPEND) {
            $routeName = sprintf('%s/%s', $routeName, $this->matchedRouteName);
        } elseif ($flag === self::NAME_APPEND) {
            $routeName = sprintf('%s/%s', $this->matchedRouteName, $routeName);
        } else {
            throw new DomainException('Unknown flag for setting matched route name');
        }
        $result->matchedRouteName = $routeName;

        return $result;
    }

    /**
     * Produce a new route result with provided matched parameters. Can only be
     * used with successful result.
     *
     * @throws RuntimeException
     */
    public function withMatchedParams(array $params) : self
    {
        if (! $this->isSuccess()) {
            throw new RuntimeException('Only successful routing can have matched params');
        }
        $result = clone $this;
        $result->matchedParams = $params;
        return $result;
    }

    /**
     * Matched route name on successful routing.
     * Can be null. Route name is normally set by the route stack and can differ
     * for same route instance if it is used in several places.
     */
    public function getMatchedRouteName() : ?string
    {
        return $this->matchedRouteName;
    }

    /**
     * Matched parameters on successful routing
     */
    public function getMatchedParams() : array
    {
        return $this->matchedParams;
    }

    /**
     * Returns list of allowed methods on method failure.
     */
    public function getAllowedMethods() : array
    {
        return $this->allowedMethods;
    }

    /**
     * Helper function to deduplicate and normalize HTTP method names
     */
    private function setAllowedMethods(array $methods) : void
    {
        $methods = array_keys(array_change_key_case(
            array_flip($methods),
            CASE_UPPER
        ));
        $this->allowedMethods = $methods;
    }

    /**
     * Disallow new-ing route result
     */
    private function __construct()
    {
    }
}
