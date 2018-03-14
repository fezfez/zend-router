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
use Zend\Router\Exception;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\PartialRouteResult;
use Zend\Stdlib\ArrayUtils;

use function array_merge;
use function count;
use function is_array;
use function preg_match;
use function preg_quote;
use function sprintf;
use function strlen;

/**
 * Hostname route.
 */
class Hostname implements PartialRouteInterface
{
    use PartialRouteTrait;

    /**
     * Parts of the route.
     *
     * @var array
     */
    protected $parts;

    /**
     * Regex used for matching the route.
     *
     * @var string
     */
    protected $regex;

    /**
     * Map from regex groups to parameter names.
     *
     * @var array
     */
    protected $paramMap = [];

    /**
     * Default values.
     *
     * @var array
     */
    protected $defaults;

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    /**
     * Create a new hostname route.
     */
    public function __construct(string $route, array $constraints = [], array $defaults = [])
    {
        $this->defaults = $defaults;
        $this->parts = $this->parseRouteDefinition($route);
        $this->regex = $this->buildRegex($this->parts, $constraints);
    }

    /**
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

        if (! isset($options['constraints'])) {
            $options['constraints'] = [];
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['route'], $options['constraints'], $options['defaults']);
    }

    /**
     * Parse a route definition.
     *
     * @throws Exception\RuntimeException
     */
    protected function parseRouteDefinition(string $def) : array
    {
        $currentPos = 0;
        $length = strlen($def);
        $parts = [];
        $levelParts = [&$parts];
        $level = 0;

        while ($currentPos < $length) {
            if (! preg_match('(\G(?P<literal>[a-z0-9-.]*)(?P<token>[:{\[\]]|$))', $def, $matches, 0, $currentPos)) {
                throw new Exception\RuntimeException('Matched hostname literal contains a disallowed character');
            }

            $currentPos += strlen($matches[0]);

            if (! empty($matches['literal'])) {
                $levelParts[$level][] = ['literal', $matches['literal']];
            }

            if ($matches['token'] === ':') {
                if (! preg_match(
                    '(\G(?P<name>[^:.{\[\]]+)(?:{(?P<delimiters>[^}]+)})?:?)',
                    $def,
                    $matches,
                    0,
                    $currentPos
                )) {
                    throw new Exception\RuntimeException('Found empty parameter name');
                }

                $levelParts[$level][] = [
                    'parameter',
                    $matches['name'],
                    isset($matches['delimiters']) ? $matches['delimiters'] : null,
                ];

                $currentPos += strlen($matches[0]);
            } elseif ($matches['token'] === '[') {
                $levelParts[$level][] = ['optional', []];
                $levelParts[$level + 1] = &$levelParts[$level][count($levelParts[$level]) - 1][1];

                $level++;
            } elseif ($matches['token'] === ']') {
                unset($levelParts[$level]);
                $level--;

                if ($level < 0) {
                    throw new Exception\RuntimeException('Found closing bracket without matching opening bracket');
                }
            } else {
                break;
            }
        }

        if ($level > 0) {
            throw new Exception\RuntimeException('Found unbalanced brackets');
        }

        return $parts;
    }

    /**
     * Build the matching regex from parsed parts.
     */
    protected function buildRegex(array $parts, array $constraints, int &$groupIndex = 1) : string
    {
        $regex = '';

        foreach ($parts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $regex .= preg_quote($part[1]);
                    break;

                case 'parameter':
                    $groupName = '?P<param' . $groupIndex . '>';

                    if (isset($constraints[$part[1]])) {
                        $regex .= '(' . $groupName . $constraints[$part[1]] . ')';
                    } elseif ($part[2] === null) {
                        $regex .= '(' . $groupName . '[^.]+)';
                    } else {
                        $regex .= '(' . $groupName . '[^' . $part[2] . ']+)';
                    }

                    $this->paramMap['param' . $groupIndex++] = $part[1];
                    break;

                case 'optional':
                    $regex .= '(?:' . $this->buildRegex($part[1], $constraints, $groupIndex) . ')?';
                    break;
            }
        }

        return $regex;
    }

    /**
     * Build host.
     *
     * @throws InvalidArgumentException
     */
    protected function buildHost(array $parts, array $mergedParams, bool $isOptional) : string
    {
        $host = '';
        $skip = true;
        $skippable = false;

        foreach ($parts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $host .= $part[1];
                    break;

                case 'parameter':
                    $skippable = true;

                    if (! isset($mergedParams[$part[1]])) {
                        if (! $isOptional) {
                            throw new InvalidArgumentException(sprintf('Missing parameter "%s"', $part[1]));
                        }

                        return '';
                    } elseif (! $isOptional
                        || ! isset($this->defaults[$part[1]])
                        || $this->defaults[$part[1]] !== $mergedParams[$part[1]]
                    ) {
                        $skip = false;
                    }

                    $host .= $mergedParams[$part[1]];

                    $this->assembledParams[] = $part[1];
                    break;

                case 'optional':
                    $skippable = true;
                    $optionalPart = $this->buildHost($part[1], $mergedParams, true);

                    if ($optionalPart !== '') {
                        $host .= $optionalPart;
                        $skip = false;
                    }
                    break;
            }
        }

        if ($isOptional && $skippable && $skip) {
            return '';
        }

        return $host;
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
        $host = $uri->getHost();

        $result = preg_match('(^' . $this->regex . '$)', $host, $matches);

        if (! $result) {
            return PartialRouteResult::fromRouteFailure();
        }

        $params = [];

        foreach ($this->paramMap as $index => $name) {
            if (isset($matches[$index]) && $matches[$index] !== '') {
                $params[$name] = $matches[$index];
            }
        }

        return PartialRouteResult::fromRouteMatch(array_merge($this->defaults, $params), $pathOffset, 0);
    }

    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        $this->assembledParams = [];

        return $uri->withHost($this->buildHost(
            $this->parts,
            array_merge($this->defaults, $params),
            false
        ));
    }

    /**
     * Get parameters used to assemble uri on the last assemble invocation.
     * Used during uri assembling by Part and Chain routes
     *
     * @internal
     */
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
