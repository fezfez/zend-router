<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route\TestAsset;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\PartialRouteInterface;
use Zend\Router\PartialRouteResult;
use Zend\Router\RouteInterface;
use Zend\Router\RouteResult;

/**
 * Helper class to define multiple route configurations for testing
 */
final class RouteTestDefinition
{
    /**
     * @var RouteInterface
     */
    private $route;

    /**
     * @var ServerRequestInterface
     */
    private $matchRequest;

    /**
     * @var ?RouteResult
     */
    private $matchResult;

    /**
     * @var ?PartialRouteResult
     */
    private $partialMatchResult;

    /**
     * @var int
     */
    private $pathOffset = 0;

    /**
     * @var array
     */
    private $matchOptions = [];

    /**
     * @var ?UriInterface
     */
    private $assembleWithUri;

    /**
     * @var ?UriInterface
     */
    private $assembleResult;

    /**
     * @var array
     */
    private $assembleParams = [];

    /**
     * @var array
     */
    private $assembleOptions = [];

    public function __construct(RouteInterface $route, $requestOrUriToMatch)
    {
        $this->route = $route;
        if ($requestOrUriToMatch instanceof ServerRequestInterface) {
            $this->matchRequest = $requestOrUriToMatch;
        } elseif ($requestOrUriToMatch instanceof UriInterface) {
            $this->matchRequest = new ServerRequest([], [], $requestOrUriToMatch, 'GET', 'php://memory');
        } else {
            throw new Exception('Must provide server request or uri interface to use for matching');
        }
    }

    public function getRoute() : RouteInterface
    {
        return $this->route;
    }

    public function getRequestToMatch() : ServerRequestInterface
    {
        return $this->matchRequest;
    }

    public function expectMatchResult(RouteResult $result) : self
    {
        $this->matchResult = $result;
        return $this;
    }

    public function getExpectedMatchResult() : RouteResult
    {
        if (! $this->matchResult) {
            throw new Exception(
                'Expected match result is not provided. Set it with RouteTestDefinition::expectMatchResult()'
            );
        }
        return $this->matchResult;
    }


    public function expectPartialMatchResult(PartialRouteResult $result) : self
    {
        if (! $this->route instanceof PartialRouteInterface) {
            throw new Exception('Only partial route can match partially');
        }

        $this->partialMatchResult = $result;
        return $this;
    }

    public function getExpectedPartialMatchResult() : PartialRouteResult
    {
        if (! $this->route instanceof PartialRouteInterface) {
            throw new Exception('No expected partial match result. Only partial route can match partially');
        }
        if (! $this->partialMatchResult) {
            throw new Exception(
                'Expected partial match result is not provided. Set it with'
                . ' RouteTestDefinition::expectPartialMatchResult()'
            );
        }
        return $this->partialMatchResult;
    }

    public function usePathOffset(int $pathOffset) : self
    {
        $this->pathOffset = $pathOffset;
        return $this;
    }

    public function getPathOffset() : int
    {
        return $this->pathOffset;
    }

    public function useMatchOptions(array $options) : self
    {
        $this->matchOptions = $options;
        return $this;
    }

    public function getMatchOptions() : array
    {
        return $this->matchOptions;
    }

    public function shouldAssembleAndExpectResult(UriInterface $uri) : self
    {
        $this->assembleResult = $uri;
        return $this;
    }

    public function shouldAssembleAndExpectResultSameAsUriForMatching() : self
    {
        $this->shouldAssembleAndExpectResult($this->getRequestToMatch()->getUri());
        return $this;
    }

    public function getExpectedAssembleResult() : ?UriInterface
    {
        return $this->assembleResult;
    }

    public function useUriForAssemble(UriInterface $uri) : self
    {
        $this->assembleWithUri = $uri;
        return $this;
    }

    public function getUriForAssemble() : UriInterface
    {
        return $this->assembleWithUri ?? new Uri();
    }

    public function useParamsForAssemble(array $assembleParams) : self
    {
        $this->assembleParams = $assembleParams;
        return $this;
    }

    public function getParamsForAssemble() : array
    {
        return $this->assembleParams;
    }

    public function useOptionsForAssemble(array $assembleOptions) : self
    {
        $this->assembleOptions = $assembleOptions;
        return $this;
    }

    public function getOptionsForAssemble() : array
    {
        return $this->assembleOptions;
    }
}
