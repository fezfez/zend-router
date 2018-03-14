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

use function array_merge;
use function is_array;
use function is_int;
use function is_numeric;
use function preg_match;
use function rawurldecode;
use function rawurlencode;
use function str_replace;
use function strlen;
use function strpos;

/**
 * Regex route.
 */
class Regex implements PartialRouteInterface
{
    use PartialRouteTrait;

    /**
     * Regex to match.
     *
     * @var string
     */
    protected $regex;

    /**
     * Default values.
     *
     * @var array
     */
    protected $defaults;

    /**
     * Specification for URL assembly.
     *
     * Parameters accepting substitutions should be denoted as "%key%"
     *
     * @var string
     */
    protected $spec;

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    /**
     * Create a new regex route.
     */
    public function __construct(string $regex, string $spec, array $defaults = [])
    {
        $this->regex = $regex;
        $this->spec = $spec;
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

        if (! isset($options['regex'])) {
            throw new InvalidArgumentException('Missing "regex" in options array');
        }

        if (! isset($options['spec'])) {
            throw new InvalidArgumentException('Missing "spec" in options array');
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['regex'], $options['spec'], $options['defaults']);
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
        $path = $uri->getPath();

        $result = preg_match('(\G' . $this->regex . ')', $path, $matches, 0, $pathOffset);

        if (! $result) {
            return PartialRouteResult::fromRouteFailure();
        }

        $matchedLength = strlen($matches[0]);

        foreach ($matches as $key => $value) {
            if (is_numeric($key) || is_int($key) || $value === '') {
                unset($matches[$key]);
            } else {
                $matches[$key] = rawurldecode($value);
            }
        }

        return PartialRouteResult::fromRouteMatch(array_merge($this->defaults, $matches), $pathOffset, $matchedLength);
    }

    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        $url = $this->spec;
        $mergedParams = array_merge($this->defaults, $params);
        $this->assembledParams = [];

        foreach ($mergedParams as $key => $value) {
            $spec = '%' . $key . '%';

            if (strpos($url, $spec) !== false) {
                $url = str_replace($spec, rawurlencode($value), $url);

                $this->assembledParams[] = $key;
            }
        }

        return $uri->withPath($uri->getPath() . $url);
    }

    public function getLastAssembledParams() : array
    {
        return $this->assembledParams;
    }

    /**
     * @deprecated
     */
    public function getAssembledParams() : array
    {
        return $this->getLastAssembledParams();
    }
}
