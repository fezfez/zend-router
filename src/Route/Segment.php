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
use Zend\I18n\Translator\TranslatorInterface as Translator;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\PartialRouteResult;
use Zend\Stdlib\ArrayUtils;

use function array_merge;
use function count;
use function is_array;
use function preg_match;
use function preg_quote;
use function rawurldecode;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function strlen;
use function strtr;

/**
 * Segment route.
 */
class Segment implements PartialRouteInterface
{
    use PartialRouteTrait;

    /**
     * Cache for the encode output.
     *
     * @var array
     */
    protected static $cacheEncode = [];

    /**
     * Map of allowed special chars in path segments.
     *
     * http://tools.ietf.org/html/rfc3986#appendix-A
     * segement      = *pchar
     * pchar         = unreserved / pct-encoded / sub-delims / ":" / "@"
     * unreserved    = ALPHA / DIGIT / "-" / "." / "_" / "~"
     * sub-delims    = "!" / "$" / "&" / "'" / "(" / ")"
     *               / "*" / "+" / "," / ";" / "="
     *
     * @var array
     */
    protected static $urlencodeCorrectionMap = [
        '%21' => '!', // sub-delims
        '%24' => '$', // sub-delims
        '%26' => '&', // sub-delims
        '%27' => "'", // sub-delims
        '%28' => '(', // sub-delims
        '%29' => ')', // sub-delims
        '%2A' => '*', // sub-delims
        '%2B' => '+', // sub-delims
        '%2C' => ',', // sub-delims
        //      '%2D' => "-", // unreserved - not touched by rawurlencode
        //      '%2E' => ".", // unreserved - not touched by rawurlencode
        '%3A' => ':', // pchar
        '%3B' => ';', // sub-delims
        '%3D' => '=', // sub-delims
        '%40' => '@', // pchar
        //      '%5F' => "_", // unreserved - not touched by rawurlencode
        //      '%7E' => "~", // unreserved - not touched by rawurlencode
    ];

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
     * Translation keys used in the regex.
     *
     * @var array
     */
    protected $translationKeys = [];

    /**
     * Create a new regex route.
     */
    public function __construct(string $route, array $constraints = [], array $defaults = [])
    {
        $this->defaults = $defaults;
        $this->parts = $this->parseRouteDefinition($route);
        $this->regex = $this->buildRegex($this->parts, $constraints);
    }

    /**
     * factory(): defined by RouteInterface interface.
     *
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
     * @throws RuntimeException
     */
    protected function parseRouteDefinition(string $def) : array
    {
        $currentPos = 0;
        $length = strlen($def);
        $parts = [];
        $levelParts = [&$parts];
        $level = 0;

        while ($currentPos < $length) {
            preg_match('(\G(?P<literal>[^:{\[\]]*)(?P<token>[:{\[\]]|$))', $def, $matches, 0, $currentPos);

            $currentPos += strlen($matches[0]);

            if (! empty($matches['literal'])) {
                $levelParts[$level][] = ['literal', $matches['literal']];
            }

            if ($matches['token'] === ':') {
                if (! preg_match(
                    '(\G(?P<name>[^:/{\[\]]+)(?:{(?P<delimiters>[^}]+)})?:?)',
                    $def,
                    $matches,
                    0,
                    $currentPos
                )) {
                    throw new RuntimeException('Found empty parameter name');
                }

                $levelParts[$level][] = [
                    'parameter',
                    $matches['name'],
                    isset($matches['delimiters']) ? $matches['delimiters'] : null,
                ];

                $currentPos += strlen($matches[0]);
            } elseif ($matches['token'] === '{') {
                if (! preg_match('(\G(?P<literal>[^}]+)\})', $def, $matches, 0, $currentPos)) {
                    throw new RuntimeException('Translated literal missing closing bracket');
                }

                $currentPos += strlen($matches[0]);

                $levelParts[$level][] = ['translated-literal', $matches['literal']];
            } elseif ($matches['token'] === '[') {
                $levelParts[$level][] = ['optional', []];
                $levelParts[$level + 1] = &$levelParts[$level][count($levelParts[$level]) - 1][1];

                $level++;
            } elseif ($matches['token'] === ']') {
                unset($levelParts[$level]);
                $level--;

                if ($level < 0) {
                    throw new RuntimeException('Found closing bracket without matching opening bracket');
                }
            } else {
                break;
            }
        }

        if ($level > 0) {
            throw new RuntimeException('Found unbalanced brackets');
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
                        $regex .= '(' . $groupName . '[^/]+)';
                    } else {
                        $regex .= '(' . $groupName . '[^' . $part[2] . ']+)';
                    }

                    $this->paramMap['param' . $groupIndex++] = $part[1];
                    break;

                case 'optional':
                    $regex .= '(?:' . $this->buildRegex($part[1], $constraints, $groupIndex) . ')?';
                    break;

                case 'translated-literal':
                    $regex .= '#' . $part[1] . '#';
                    $this->translationKeys[] = $part[1];
                    break;
            }
        }

        return $regex;
    }

    /**
     * Build a path.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function buildPath(
        array $parts,
        array $mergedParams,
        bool $isOptional,
        bool $hasChild,
        array $options
    ) : string {
        if ($this->translationKeys) {
            if (! isset($options['translator']) || ! $options['translator'] instanceof Translator) {
                throw new RuntimeException('No translator provided');
            }

            $translator = $options['translator'];
            $textDomain = isset($options['text_domain']) ? $options['text_domain'] : 'default';
            $locale = isset($options['locale']) ? $options['locale'] : null;
        }

        $path = '';
        $skip = true;
        $skippable = false;

        foreach ($parts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $path .= $part[1];
                    break;

                case 'parameter':
                    $skippable = true;

                    if (! isset($mergedParams[$part[1]])) {
                        if (! $isOptional || $hasChild) {
                            throw new InvalidArgumentException(sprintf('Missing parameter "%s"', $part[1]));
                        }

                        return '';
                    } elseif (! $isOptional
                        || $hasChild
                        || ! isset($this->defaults[$part[1]])
                        || $this->defaults[$part[1]] !== $mergedParams[$part[1]]
                    ) {
                        $skip = false;
                    }

                    $path .= $this->encode((string) $mergedParams[$part[1]]);

                    $this->assembledParams[] = $part[1];
                    break;

                case 'optional':
                    $skippable = true;
                    $optionalPart = $this->buildPath($part[1], $mergedParams, true, $hasChild, $options);

                    if ($optionalPart !== '') {
                        $path .= $optionalPart;
                        $skip = false;
                    }
                    break;

                case 'translated-literal':
                    $path .= $translator->translate($part[1], $textDomain, $locale);
                    break;
            }
        }

        if ($isOptional && $skippable && $skip) {
            return '';
        }

        return $path;
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function partialMatch(Request $request, int $pathOffset = 0, array $options = []) : PartialRouteResult
    {
        if ($pathOffset < 0) {
            throw new InvalidArgumentException('Path offset cannot be negative');
        }
        $uri = $request->getUri();
        $path = $uri->getPath();

        $regex = $this->regex;

        if ($this->translationKeys) {
            if (! isset($options['translator']) || ! $options['translator'] instanceof Translator) {
                throw new RuntimeException('No translator provided');
            }

            $translator = $options['translator'];
            $textDomain = $options['text_domain'] ?? 'default';
            $locale = $options['locale'] ?? $options['parent_match_params'] ?? null;

            foreach ($this->translationKeys as $key) {
                $regex = str_replace('#' . $key . '#', $translator->translate($key, $textDomain, $locale), $regex);
            }
        }

        // needs to be urlencoded to match urlencoded non-latin characters
        $result = preg_match('(\G' . $regex . ')', $path, $matches, 0, $pathOffset);

        if (! $result) {
            return PartialRouteResult::fromRouteFailure();
        }

        $matchedLength = strlen($matches[0]);
        $params = [];

        foreach ($this->paramMap as $index => $name) {
            if (isset($matches[$index]) && $matches[$index] !== '') {
                $params[$name] = $this->decode($matches[$index]);
            }
        }

        return PartialRouteResult::fromRouteMatch(array_merge($this->defaults, $params), $pathOffset, $matchedLength);
    }

    public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
    {
        $this->assembledParams = [];

        $path = $this->buildPath(
            $this->parts,
            array_merge($this->defaults, $params),
            false,
            isset($options['has_child']) ? $options['has_child'] : false,
            $options
        );

        return $uri->withPath($uri->getPath() . $path);
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

    /**
     * Encode a path segment.
     *
     * @todo replace with the version from diactoros
     */
    protected function encode(string $value) : string
    {
        $key = (string) $value;
        if (! isset(static::$cacheEncode[$key])) {
            static::$cacheEncode[$key] = rawurlencode($value);
            static::$cacheEncode[$key] = strtr(static::$cacheEncode[$key], static::$urlencodeCorrectionMap);
        }
        return static::$cacheEncode[$key];
    }

    /**
     * Decode a path segment.
     */
    protected function decode(string $value) : string
    {
        return rawurldecode($value);
    }
}
