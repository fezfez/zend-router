<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\Route\Scheme;
use ZendTest\Router\FactoryTester;

/**
 * @covers \Zend\Router\Route\Scheme
 */
class SchemeTest extends TestCase
{
    /**
     * @var ServerRequestInterface
     */
    private $request;

    protected function setUp()
    {
        $this->request = new ServerRequest([], [], null, null, 'php://memory');
    }

    public function testMatching()
    {
        $request = $this->request->withUri((new Uri())->withScheme('https'));

        $route = new Scheme('https');
        $result = $route->match($request);

        $this->assertTrue($result->isSuccess());
    }

    public function testMatchReturnsResultWithDefaultParameters()
    {
        $request = $this->request->withUri((new Uri())->withScheme('https'));

        $route = new Scheme('https', ['foo' => 'bar']);
        $result = $route->match($request);

        $this->assertEquals(['foo' => 'bar'], $result->getMatchedParams());
    }

    public function testNoMatchingOnDifferentScheme()
    {
        $request = $this->request->withUri((new Uri())->withScheme('http'));

        $route = new Scheme('https');
        $result = $route->match($request);

        $this->assertTrue($result->isFailure());
    }

    public function testAssembling()
    {
        $uri = new Uri();
        $route = new Scheme('https');
        $resultUri = $route->assemble($uri);

        $this->assertEquals('https', $resultUri->getScheme());
    }

    public function testGetAssembledParams()
    {
        $uri = new Uri();
        $route = new Scheme('https');
        $route->assemble($uri, ['foo' => 'bar']);

        $this->assertEquals([], $route->getLastAssembledParams());
        $this->assertEquals($route->getLastAssembledParams(), $route->getAssembledParams());
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Scheme::class,
            [
                'scheme' => 'Missing "scheme" in options array',
            ],
            [
                'scheme' => 'http',
            ]
        );
    }

    public function testRejectsNegativePathOffset()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        $request = $this->prophesize(ServerRequestInterface::class);
        $route = new Scheme('https');
        $route->partialMatch($request->reveal(), -1);
    }
}
