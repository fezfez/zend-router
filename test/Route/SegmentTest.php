<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\I18n\Translator\Loader\FileLoaderInterface;
use Zend\I18n\Translator\TextDomain;
use Zend\I18n\Translator\Translator;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Exception\RuntimeException;
use Zend\Router\PartialRouteResult;
use Zend\Router\Route\PartialRouteTrait;
use Zend\Router\Route\Segment;
use Zend\Router\RouteResult;
use ZendTest\Router\FactoryTester;
use ZendTest\Router\Route\TestAsset\RouteTestDefinition;

use function implode;

/**
 * @covers \Zend\Router\Route\Segment
 */
class SegmentTest extends TestCase
{
    use PartialRouteTrait;
    use RouteTestTrait;

    public function getRouteTestDefinitions() : iterable
    {
        $params = ['foo' => 'bar'];
        yield 'simple match' => (new RouteTestDefinition(
            new Segment('/:foo'),
            new Uri('/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'no match without leading slash' => (new RouteTestDefinition(
            new Segment(':foo'),
            new Uri('/bar/')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        yield 'partial match with trailing slash' => (new RouteTestDefinition(
            new Segment('/:foo'),
            new Uri('/bar/')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch(['foo' => 'bar'], 0, 4)
            );

        $params = ['foo' => 'bar'];
        yield 'offset skips beginning' => (new RouteTestDefinition(
            new Segment(':foo'),
            new Uri('/bar')
        ))
            ->usePathOffset(1)
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 1, 3)
            );

        $params = ['foo' => 'bar', 'baz' => 'qux'];
        yield 'match merges default parameters' => (new RouteTestDefinition(
            new Segment('/:foo', [], ['baz' => 'qux']),
            new Uri('/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar'];
        yield 'match overrides default parameters' => (new RouteTestDefinition(
            new Segment('/:foo', [], ['foo' => 'baz']),
            new Uri('/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'constraints prevent match' => (new RouteTestDefinition(
            new Segment('/:foo', ['foo' => '\d+']),
            new Uri('/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteFailure()
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteFailure()
            );

        $params = ['foo' => '123'];
        yield 'constraints allow match' => (new RouteTestDefinition(
            new Segment('/:foo', ['foo' => '\d+']),
            new Uri('/123')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'foo-bar'];
        yield 'constraints override non standard delimiter' => (new RouteTestDefinition(
            new Segment('/:foo{-}/bar', ['foo' => '[^/]+']),
            new Uri('/foo-bar/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar', 'bar' => 'baz'];
        yield 'constraints with parentheses dont break parameter map' => (new RouteTestDefinition(
            new Segment('/:foo/:bar', ['foo' => '(bar)']),
            new Uri('/bar/baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield 'simple match with optional parameter' => (new RouteTestDefinition(
            new Segment('/[:foo]', [], ['foo' => 'bar']),
            new Uri('/')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch(['foo' => 'bar'])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch(['foo' => 'bar'], 0, 1)
            );

        yield 'optional parameter is ignored' => (new RouteTestDefinition(
            new Segment('/:foo[/:baz]'),
            new Uri('/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch(['foo' => 'bar'])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch(['foo' => 'bar'], 0, 4)
            );

        $params = ['foo' => 'bar', 'bar' => 'baz'];
        yield 'optional parameter is provided with default' => (new RouteTestDefinition(
            new Segment('/:foo[/:bar]', [], ['bar' => 'baz']),
            new Uri('/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar', 'bar' => 'baz'];
        yield 'optional parameter is consumed' => (new RouteTestDefinition(
            new Segment('/:foo[/:bar]'),
            new Uri('/bar/baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar', 'bar' => 'baz'];
        yield 'optional group is discared with missing parameter' => (new RouteTestDefinition(
            new Segment('/:foo[/:bar/:baz]', [], ['bar' => 'baz']),
            new Uri('/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar', 'bar' => 'baz', 'baz' => 'bat'];
        yield 'optional group within optional group is ignored' => (new RouteTestDefinition(
            new Segment('/:foo[/:bar[/:baz]]', [], ['bar' => 'baz', 'baz' => 'bat']),
            new Uri('/bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['bar' => 'baz'];
        yield 'non standard delimiter before parameter' => (new RouteTestDefinition(
            new Segment('/foo-:bar'),
            new Uri('/foo-baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar', 'bar' => 'baz'];
        yield 'non standard delimiter between parameters' => (new RouteTestDefinition(
            new Segment('/:foo{-}-:bar'),
            new Uri('/bar-baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar', 'bar' => 'baz', 'baz' => 'bat'];
        yield 'non standard delimiter before optional parameter' => (new RouteTestDefinition(
            new Segment('/:foo{-/}[-:bar]/:baz'),
            new Uri('/bar-baz/bat')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'bar', 'baz' => 'bat'];
        yield 'non standard delimiter before ignored optional parameter' => (new RouteTestDefinition(
            new Segment('/:foo{-/}[-:bar]/:baz'),
            new Uri('/bar/bat')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo-bar' => 'baz'];
        yield 'parameter with dash in name' => (new RouteTestDefinition(
            new Segment('/:foo-bar'),
            new Uri('/baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => 'foo bar'];
        yield 'url encoded parameters are decoded' => (new RouteTestDefinition(
            new Segment('/:foo'),
            new Uri('/foo%20bar')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['foo' => "!$&'()*,-.:;=@_~+"];
        yield 'urlencode flaws corrected' => (new RouteTestDefinition(
            new Segment('/:foo'),
            new Uri("/!$&'()*,-.:;=@_~+")
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        $params = ['bar' => 'bar', 'baz' => 'baz'];
        yield 'empty matches are replaced with defaults' => (new RouteTestDefinition(
            new Segment('/foo[/:bar]/baz-:baz', [], ['bar' => 'bar']),
            new Uri('/foo/baz-baz')
        ))
            ->expectMatchResult(
                RouteResult::fromRouteMatch($params)
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch($params, 0, 4)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useParamsForAssemble($params);

        yield from $this->getL10nRouteTestDefinitions();
    }

    public function getL10nRouteTestDefinitions() : iterable
    {
        // @codingStandardsIgnoreStart
        $translator = new Translator();
        $translator->setLocale('en-US');
        $enLoader = $this->createMock(FileLoaderInterface::class);
        $deLoader = $this->createMock(FileLoaderInterface::class);
        $domainLoader = $this->createMock(FileLoaderInterface::class);
        $enLoader->expects($this->any())->method('load')->willReturn(new TextDomain(['fw' => 'framework']));
        $deLoader->expects($this->any())->method('load')->willReturn(new TextDomain(['fw' => 'baukasten']));
        $domainLoader->expects($this->any())->method('load')->willReturn(new TextDomain(['fw' => 'fw-alternative']));
        $translator->getPluginManager()->setService('test-en', $enLoader);
        $translator->getPluginManager()->setService('test-de', $deLoader);
        $translator->getPluginManager()->setService('test-domain', $domainLoader);
        $translator->addTranslationFile('test-en', null, 'default', 'en-US');
        $translator->addTranslationFile('test-de', null, 'default', 'de-DE');
        $translator->addTranslationFile('test-domain', null, 'alternative', 'en-US');
        // @codingStandardsIgnoreEnd

        yield 'translate with default locale' => (new RouteTestDefinition(
            new Segment('/{fw}', [], []),
            new Uri('/framework')
        ))
            ->useMatchOptions(['translator' => $translator])
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 10)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useOptionsForAssemble(['translator' => $translator]);

        yield 'translate with default locale' => (new RouteTestDefinition(
            new Segment('/{fw}', [], []),
            new Uri('/baukasten')
        ))
            ->useMatchOptions(['translator' => $translator, 'locale' => 'de-DE'])
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 10)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useOptionsForAssemble(['translator' => $translator, 'locale' => 'de-DE']);

        yield 'translate uses message id as fallback' => (new RouteTestDefinition(
            new Segment('/{fw}', [], []),
            new Uri('/fw')
        ))
            ->useMatchOptions(['translator' => $translator, 'locale' => 'fr-FR'])
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 10)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useOptionsForAssemble(['translator' => $translator, 'locale' => 'fr-FR']);

        yield 'translate with specific text domain' => (new RouteTestDefinition(
            new Segment('/{fw}', [], []),
            new Uri('/fw-alternative')
        ))
            ->useMatchOptions(['translator' => $translator, 'text_domain' => 'alternative'])
            ->expectMatchResult(
                RouteResult::fromRouteMatch([])
            )
            ->expectPartialMatchResult(
                PartialRouteResult::fromRouteMatch([], 0, 10)
            )
            ->shouldAssembleAndExpectResultSameAsUriForMatching()
            ->useOptionsForAssemble(['translator' => $translator, 'text_domain' => 'alternative']);
    }

    public static function parseExceptionsProvider() : array
    {
        return [
            'unbalanced-brackets' => [
                '[',
                RuntimeException::class,
                'Found unbalanced brackets',
            ],
            'closing-bracket-without-opening-bracket' => [
                ']',
                RuntimeException::class,
                'Found closing bracket without matching opening bracket',
            ],
            'empty-parameter-name' => [
                ':',
                RuntimeException::class,
                'Found empty parameter name',
            ],
            'translated-literal-without-closing-backet' => [
                '{test',
                RuntimeException::class,
                'Translated literal missing closing bracket',
            ],
        ];
    }

    /**
     * @dataProvider parseExceptionsProvider
     */
    public function testParseExceptions(string $route, string $exceptionName, string $exceptionMessage)
    {
        $this->expectException($exceptionName);
        $this->expectExceptionMessage($exceptionMessage);
        new Segment($route);
    }

    public function testAssemblingWithMissingParameterInRoot()
    {
        $uri = new Uri();
        $route = new Segment('/:foo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing parameter "foo"');
        $route->assemble($uri);
    }

    public function testTranslatedAssemblingThrowsExceptionWithoutTranslator()
    {
        $uri = new Uri();
        $route = new Segment('/{foo}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No translator provided');
        $route->assemble($uri);
    }

    public function testTranslatedMatchingThrowsExceptionWithoutTranslator()
    {
        $route = new Segment('/{foo}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No translator provided');
        $route->match(new ServerRequest());
    }

    public function testAssemblingWithExistingChild()
    {
        $uri = new Uri();
        $route = new Segment('/[:foo]', [], ['foo' => 'bar']);
        $path = $route->assemble($uri, [], ['has_child' => true]);

        $this->assertEquals('/bar', $path);
    }

    public function testGetAssembledParams()
    {
        $uri = new Uri();
        $route = new Segment('/:foo');
        $route->assemble($uri, ['foo' => 'bar', 'baz' => 'bat']);
        $this->assertEquals(['foo'], $route->getLastAssembledParams());
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Segment::class,
            [
                'route' => 'Missing "route" in options array',
            ],
            [
                'route' => '/:foo[/:bar{-}]',
                'constraints' => ['foo' => 'bar'],
            ]
        );
    }

    public function testRawDecode()
    {
        // verify all characters which don't absolutely require encoding pass through match unchanged
        // this includes every character other than #, %, / and ?
        $raw = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`-=[]\\;\',.~!@$^&*()_+{}|:"<>';
        $request = new ServerRequest([], [], new Uri('http://example.com/' . $raw));
        $route = new Segment('/:foo');
        $result = $route->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($raw, $result->getMatchedParams()['foo']);
    }

    public function testEncodedDecode()
    {
        // @codingStandardsIgnoreStart
        // every character
        $in = '%61%62%63%64%65%66%67%68%69%6a%6b%6c%6d%6e%6f%70%71%72%73%74%75%76%77%78%79%7a%41%42%43%44%45%46%47%48%49%4a%4b%4c%4d%4e%4f%50%51%52%53%54%55%56%57%58%59%5a%30%31%32%33%34%35%36%37%38%39%60%2d%3d%5b%5d%5c%3b%27%2c%2e%2f%7e%21%40%23%24%25%5e%26%2a%28%29%5f%2b%7b%7d%7c%3a%22%3c%3e%3f';
        $out = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`-=[]\\;\',./~!@#$%^&*()_+{}|:"<>?';
        // @codingStandardsIgnoreEnd

        $request = new ServerRequest([], [], new Uri('http://example.com/' . $in));
        $route = new Segment('/:foo');
        $result = $route->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($out, $result->getMatchedParams()['foo']);
    }

    public function testEncodeCache()
    {
        $uri = new Uri();
        $params1 = ['p1' => 6.123, 'p2' => 7];
        $uri1 = 'example.com/' . implode('/', $params1);
        $params2 = ['p1' => 6, 'p2' => 'test'];
        $uri2 = 'example.com/' . implode('/', $params2);

        $route = new Segment('example.com/:p1/:p2');
        $request = new ServerRequest([], [], new Uri($uri1));
        $route->match($request);
        $this->assertSame($uri1, $route->assemble($uri, $params1)->getPath());

        $request = $request->withUri(new Uri($uri2));
        $route->match($request);
        $this->assertSame($uri2, $route->assemble($uri, $params2)->getPath());
    }

    public function testRejectsNegativePathOffset()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        $request = $this->prophesize(ServerRequestInterface::class);
        $route = new Segment('/foo');
        $route->partialMatch($request->reveal(), -1);
    }
}
