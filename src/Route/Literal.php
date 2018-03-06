<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
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
use function strlen;
use function strpos;

/**
 * Literal route.
 */
class Literal implements PartialRouteInterface
{
    use PartialRouteTrait;

    /**
     * Uri path to match
     *
     * @var string
     */
    private $path;

    /**
     * Default values.
     *
     * @var array
     */
    private $defaults;

    /**
     * Create a new literal route.
     *
     * @throws InvalidArgumentException on empty path
     */
    public function __construct(string $path, array $defaults = [])
    {
        if (empty($path)) {
            throw new InvalidArgumentException('Literal uri path part cannot be empty');
        }
        $this->path = $path;
        $this->defaults = $defaults;
    }

    /**
     * @todo provide factory for route plugin manager
     * @throws InvalidArgumentException
     */
    public static function factory(iterable $options = []) : self
    {
        if (! is_array($options)) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (! isset($options['route'])) {
            throw new InvalidArgumentException('Missing "route" in options array');
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['route'], $options['defaults']);
    }

    /**
     * Attempt to match ServerRequestInterface by checking for literal
     * path segment at offset position.
     *
     * @throws InvalidArgumentException
     */
    public function partialMatch(Request $request, int $pathOffset = 0, array $options = []) : PartialRouteResult
    {
        if ($pathOffset < 0) {
            throw new InvalidArgumentException('Path offset cannot be negative');
        }
        $path = $request->getUri()->getPath();

        if (strpos($path, $this->path, $pathOffset) === $pathOffset) {
            return PartialRouteResult::fromRouteMatch($this->defaults, $pathOffset, strlen($this->path));
        }
        return PartialRouteResult::fromRouteFailure();
    }

    /**
     * Assemble url by appending literal path part
     */
    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        return $uri->withPath($uri->getPath() . $this->path);
    }

    /**
     * Literal routes are not using parameters to assemble uri
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
