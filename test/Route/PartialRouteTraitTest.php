<?php
/**
 * @see       https://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-router/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Router\Route;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;
use Zend\Router\Exception\InvalidArgumentException;
use Zend\Router\PartialRouteInterface;
use Zend\Router\PartialRouteResult;
use Zend\Router\Route\PartialRouteTrait;

use function strlen;

/**
 * @covers \Zend\Router\Route\PartialRouteTrait
 */
class PartialRouteTraitTest extends TestCase
{
    /**
     * @var PartialRouteInterface
     */
    private $partial;

    /**
     * @var ServerRequestInterface
     */
    private $request;

    protected function setUp()
    {
        $this->request = new ServerRequest([], [], new Uri('/path'));
        $this->partial = new class() implements PartialRouteInterface {
            use PartialRouteTrait;

            /**
             * @var ObjectProphecy
             */
            public $prophecy;

            public function partialMatch(
                ServerRequestInterface $request,
                int $pathOffset = 0,
                array $options = []
            ) : PartialRouteResult {
                return $this->prophecy->reveal()->partialMatch($request, $pathOffset, $options);
            }

            public function getLastAssembledParams() : array
            {
                return $this->prophecy->reveal()->getLastAssembledParams();
            }

            public function assemble(UriInterface $uri, array $params = [], array $options = []) : UriInterface
            {
                return $this->prophecy->reveal()->assemble($uri, $params, $options);
            }
        };

        $this->partial->prophecy = $this->prophesize(PartialRouteInterface::class);
    }

    public function testInvokesPartialMatchWithMatchParameters()
    {
        $partialResult = PartialRouteResult::fromRouteMatch([], 5, 0);
        $this->partial->prophecy
                      ->partialMatch($this->request, 5, ['foo' => 'bar'])
                      ->shouldBeCalled()
                      ->willReturn($partialResult);

        $this->partial->match($this->request, 5, ['foo' => 'bar']);
    }

    public function testRejectsNegativePathOffset()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path offset cannot be negative');
        $this->partial->match($this->request, -1);
    }

    public function testReturnsSuccessOnPartialRouteMatchWithFullPathMatch()
    {
        $pathLength = strlen($this->request->getUri()->getPath());
        $this->partial->prophecy
                      ->partialMatch($this->request, 0, [])
                      ->shouldBeCalled()
                      ->willReturn(PartialRouteResult::fromRouteMatch([], 0, $pathLength));

        $result = $this->partial->match($this->request);
        $this->assertTrue($result->isSuccess());
    }

    public function testReturnsParametersAndRouteNameFromPartialRouteMatch()
    {
        $pathLength = strlen($this->request->getUri()->getPath());
        $this->partial->prophecy
                      ->partialMatch($this->request, 0, [])
                      ->shouldBeCalled()
                      ->willReturn(PartialRouteResult::fromRouteMatch(['foo' => 'bar'], 0, $pathLength, 'routename'));

        $result = $this->partial->match($this->request);
        $this->assertEquals(['foo' => 'bar'], $result->getMatchedParams());
        $this->assertEquals('routename', $result->getMatchedRouteName());
    }

    public function testReturnsFailureOnPartialRouteMatchWithPartialPathMatch()
    {
        $pathLength = strlen($this->request->getUri()->getPath());
        $this->partial->prophecy
                      ->partialMatch($this->request, 0, [])
                      ->shouldBeCalled()
                      ->willReturn(PartialRouteResult::fromRouteMatch([], 0, $pathLength - 1));

        $result = $this->partial->match($this->request);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testReturnsFailureOnPartialFailure()
    {
        $this->partial->prophecy
                      ->partialMatch($this->request, 0, [])
                      ->shouldBeCalled()
                      ->willReturn(PartialRouteResult::fromRouteFailure());
        $result = $this->partial->match($this->request);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testReturnsFailureOnPartialFailureWithFullPathMatch()
    {
        $pathLength = strlen($this->request->getUri()->getPath());
        $this->partial->prophecy
                      ->partialMatch($this->request, $pathLength, [])
                      ->shouldBeCalled()
                      ->willReturn(PartialRouteResult::fromRouteFailure());
        $result = $this->partial->match($this->request, $pathLength);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testReturnsMethodFailureOnPartialMethodFailureWithFullPathMatch()
    {
        $pathLength = strlen($this->request->getUri()->getPath());
        $this->partial->prophecy
                      ->partialMatch($this->request, 0, [])
                      ->shouldBeCalled()
                      ->willReturn(PartialRouteResult::fromMethodFailure(['GET', 'POST'], 0, $pathLength));

        $result = $this->partial->match($this->request);
        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['GET', 'POST'], $result->getAllowedMethods());
    }

    public function testReturnsFailureOnPartialMethodFailure()
    {
        $pathLength = strlen($this->request->getUri()->getPath());
        $this->partial->prophecy
                      ->partialMatch($this->request, 0, [])
                      ->shouldBeCalled()
                      ->willReturn(PartialRouteResult::fromMethodFailure(['GET', 'POST'], 0, $pathLength - 1));
        $result = $this->partial->match($this->request, 0, []);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }
}
