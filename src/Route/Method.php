<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Router\Route;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\PartialRouteResult;
use Zend\Stdlib\ArrayUtils;

use function array_map;
use function explode;
use function in_array;
use function is_array;
use function strtoupper;

/**
 * Method route.
 */
class Method implements PartialRouteInterface
{
    use PartialRouteTrait;

    public const OPTION_FORCE_METHOD_FAILURE = 'force_method_failure';

    /**
     * Verb to match.
     *
     * @var string
     */
    protected $verb;

    /**
     * Default values.
     *
     * @var array
     */
    protected $defaults;

    /**
     * Create a new method route.
     */
    public function __construct(string $verb, array $defaults = [])
    {
        $this->verb = $verb;
        $this->defaults = $defaults;
    }

    /**
     * Create a new method route.
     *
     * @throws InvalidArgumentException
     */
    public static function factory(iterable $options = []) : self
    {
        if (! is_array($options)) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (! isset($options['verb'])) {
            throw new InvalidArgumentException('Missing "verb" in options array');
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['verb'], $options['defaults']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function partialMatch(Request $request, int $pathOffset = 0, array $options = []) : PartialRouteResult
    {
        if ($pathOffset < 0) {
            throw new InvalidArgumentException('Path offset cannot be negative');
        }
        $requestVerb = strtoupper($request->getMethod());
        $matchVerbs = explode(',', strtoupper($this->verb));
        $matchVerbs = array_map('trim', $matchVerbs);

        $forceFailure = $options[self::OPTION_FORCE_METHOD_FAILURE] ?? false;
        if (! $forceFailure && in_array($requestVerb, $matchVerbs)) {
            return PartialRouteResult::fromRouteMatch($this->defaults, $pathOffset, 0, null, $matchVerbs);
        }

        return PartialRouteResult::fromMethodFailure($matchVerbs, $pathOffset, 0);
    }

    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        return $uri;
    }

    /**
     * Method routes are not using parameters to assemble uri
     */
    public function getLastAssembledParams() : array
    {
        return [];
    }

    /**
     * @deprecated
     */
    public function getAssembledParams() : array
    {
        return $this->getLastAssembledParams();
    }
}
