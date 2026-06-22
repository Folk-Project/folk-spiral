<?php

declare(strict_types=1);

namespace Folk\Spiral\Tests\Handler;

use Folk\Sdk\Http\HttpRequest;
use Folk\Spiral\Handler\SpiralHttpHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Http\Http;

final class SpiralHttpHandlerTest extends TestCase
{
    public function testConstructorRejectsNonSpiralContainer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SpiralHttpHandler requires Spiral\Core\Container');

        new SpiralHttpHandler($this->createMock(ContainerInterface::class));
    }

    public function testHandlesGetRequest(): void
    {
        $handler = $this->makeHandler(new Response(200, [], 'ok'));

        $response = $handler->handle(new HttpRequest('GET', '/', [], ''));

        self::assertSame(200, $response->status);
        self::assertSame('ok', $response->body);
    }

    public function testHandlesPostRequestWithBody(): void
    {
        $handler = $this->makeHandler(new Response(201, [], 'created'));

        $response = $handler->handle(new HttpRequest('POST', '/items', ['content-type' => 'application/json'], '{"x":1}'));

        self::assertSame(201, $response->status);
        self::assertSame('created', $response->body);
    }

    public function testReturns404Response(): void
    {
        $handler = $this->makeHandler(new Response(404));

        $response = $handler->handle(new HttpRequest('GET', '/missing', [], ''));

        self::assertSame(404, $response->status);
    }

    public function testForwardsResponseHeaders(): void
    {
        $handler = $this->makeHandler(new Response(200, ['X-Folk' => 'test'], 'ok'));

        $response = $handler->handle(new HttpRequest('GET', '/', [], ''));

        self::assertArrayHasKey('X-Folk', $response->headers);
        self::assertSame('test', $response->headers['X-Folk']);
    }

    private function makeHandler(Response $psrResponse): SpiralHttpHandler
    {
        $factory = new Psr17Factory();

        $http = $this->createMock(Http::class);
        $http->method('handle')->willReturn($psrResponse);

        $container = $this->createMock(Container::class);
        $container->method('get')->willReturnMap([
            [ServerRequestFactoryInterface::class, $factory],
            [StreamFactoryInterface::class, $factory],
            [Http::class, $http],
        ]);
        $container->method('runScope')->willReturnCallback(
            fn(Scope $scope, callable $fn) => $fn()
        );

        return new SpiralHttpHandler($container);
    }
}
