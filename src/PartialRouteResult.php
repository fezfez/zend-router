<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router;

use Psr\Http\Message\UriInterface;
use Zend\Router\Exception\DomainException;
use Zend\Router\Exception\RuntimeException;

use function array_change_key_case;
use function array_flip;
use function array_keys;

use const CASE_UPPER;

final class PartialRouteResult
{
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
     * @var string|null
     */
    private $matchedRouteName;

    /**
     * @var int
     */
    private $matchedPathLength = 0;

    /**
     * @var int uri path offset used for matching
     */
    private $pathOffset = 0;

    /**
     * Create successful routing result
     */
    public static function fromRouteMatch(
        array $matchedParams,
        int $pathOffset,
        int $matchedPathLength,
        string $routeName = null
    ) : PartialRouteResult {
        if ($pathOffset < 0) {
            throw new DomainException('Path offset cannot be negative');
        }
        if ($matchedPathLength < 0) {
            throw new DomainException('Matched path length cannot be negative');
        }
        $result = new self();
        $result->success = true;
        $result->matchedParams = $matchedParams;
        $result->matchedRouteName = $routeName;
        $result->pathOffset = $pathOffset;
        $result->matchedPathLength = $matchedPathLength;
        return $result;
    }

    /**
     * Create failed routing result
     */
    public static function fromRouteFailure() : PartialRouteResult
    {
        $result = new self();
        $result->success = false;
        return $result;
    }

    /**
     * Create routing failure result where http method is not allowed for the
     * otherwise routable request
     */
    public static function fromMethodFailure(
        array $allowedMethods,
        int $pathOffset,
        int $matchedPathLength
    ) : PartialRouteResult {
        if (empty($allowedMethods)) {
            throw new DomainException('Method failure requires list of allowed methods');
        }
        if ($pathOffset < 0) {
            throw new DomainException('Path offset cannot be negative');
        }
        if ($matchedPathLength < 0) {
            throw new DomainException('Matched path length cannot be negative');
        }

        $result = new self();
        $result->success = false;
        $result->setAllowedMethods($allowedMethods);
        $result->pathOffset = $pathOffset;
        $result->matchedPathLength = $matchedPathLength;
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
     * Checks if partial route result is a full match for the provided uri path.
     * Expects same uri as used for matching.
     */
    public function isFullPathMatch(UriInterface $uri) : bool
    {
        // non http method failure is no match. For edge case of empty uri path
        if ($this->isFailure() && ! $this->isMethodFailure()) {
            return false;
        }
        $pathLength = strlen($uri->getPath());
        return $pathLength === ($this->pathOffset + $this->matchedPathLength);
    }

    /**
     * Produce a new partial route result with provided route name. Can only be used
     * with successful result.
     *
     * @param string $flag Signifies mode of setting route name:
     *      - {@see RouteResult::NAME_REPLACE} replaces existing route name
     *      - {@see RouteResult::NAME_PREPEND} prepends as a parent route part name.
     *      - {@see RouteResult::NAME_APPEND} appends as a child route part name.
     */
    public function withMatchedRouteName(string $routeName, $flag = RouteResult::NAME_REPLACE) : PartialRouteResult
    {
        if (empty($routeName)) {
            throw new DomainException('Route name cannot be empty');
        }
        if (! $this->isSuccess()) {
            throw new RuntimeException('Only successful routing can have matched route name');
        }
        $result = clone $this;

        // If no matched route name is set, simply replace value
        if ($flag === RouteResult::NAME_REPLACE || $this->matchedRouteName === null) {
            $result->matchedRouteName = $routeName;
            return $result;
        }

        if ($flag === RouteResult::NAME_PREPEND) {
            $routeName = sprintf('%s/%s', $routeName, $this->matchedRouteName);
        } elseif ($flag === RouteResult::NAME_APPEND) {
            $routeName = sprintf('%s/%s', $this->matchedRouteName, $routeName);
        } else {
            throw new DomainException('Unknown flag for setting matched route name');
        }
        $result->matchedRouteName = $routeName;

        return $result;
    }

    /**
     * Produce a new partial route result with provided matched parameters. Can only be
     * used with successful result.
     */
    public function withMatchedParams(array $params) : PartialRouteResult
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
     * Offset used for partial routing matching
     */
    public function getUsedPathOffset() : int
    {
        return $this->pathOffset;
    }

    /**
     * Matched uri path length, starting from offset
     */
    public function getMatchedPathLength() : int
    {
        return $this->matchedPathLength;
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
