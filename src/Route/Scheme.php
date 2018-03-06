<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace Zend\Router\Route;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\PartialRouteResult;
use Zend\Stdlib\ArrayUtils;

use function is_array;

/**
 * Scheme route.
 */
class Scheme implements PartialRouteInterface
{
    use PartialRouteTrait;

    /**
     * Scheme to match.
     *
     * @var string
     */
    protected $scheme;

    /**
     * Default values.
     *
     * @var array
     */
    protected $defaults;

    /**
     * Create a new scheme route.
     */
    public function __construct(string $scheme, array $defaults = [])
    {
        $this->scheme = $scheme;
        $this->defaults = $defaults;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function factory(iterable $options = []) : self
    {
        if (! is_array($options)) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (! isset($options['scheme'])) {
            throw new InvalidArgumentException('Missing "scheme" in options array');
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['scheme'], $options['defaults']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function partialMatch(Request $request, int $pathOffset = 0, array $options = []) : PartialRouteResult
    {
        if ($pathOffset < 0) {
            throw new InvalidArgumentException('Path offset cannot be negative');
        }
        $uri = $request->getUri();
        $scheme = $uri->getScheme();

        if ($scheme !== $this->scheme) {
            return PartialRouteResult::fromRouteFailure();
        }

        return PartialRouteResult::fromRouteMatch($this->defaults, $pathOffset, 0);
    }

    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        return $uri->withScheme($this->scheme);
    }

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
