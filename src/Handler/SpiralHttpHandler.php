<?php

declare(strict_types=1);

namespace Folk\Spiral\Handler;

use Folk\Sdk\Http\HttpModeHandler;
use Folk\Sdk\Http\HttpRequest;
use Folk\Sdk\Http\HttpResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Http\Http;

final class SpiralHttpHandler implements HttpModeHandler
{
    private readonly Container $container;
    private readonly ServerRequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly Http $http;

    public function __construct(ContainerInterface $container)
    {
        if (!$container instanceof Container) {
            throw new \InvalidArgumentException('SpiralHttpHandler requires Spiral\Core\Container');
        }

        $this->container = $container;
        $this->requestFactory = $container->get(ServerRequestFactoryInterface::class);
        $this->streamFactory = $container->get(StreamFactoryInterface::class);
        $this->http = $container->get(Http::class);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $psrRequest = $this->folkToPsr($request);

        return $this->container->runScope(
            new Scope('http'),
            function () use ($psrRequest): HttpResponse {
                $psrResponse = $this->http->handle($psrRequest);
                return $this->psrToFolk($psrResponse);
            },
        );
    }

    private function folkToPsr(HttpRequest $request): ServerRequestInterface
    {
        $psrRequest = $this->requestFactory->createServerRequest($request->method, $request->uri);

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        if ($request->body !== '') {
            $body = $this->streamFactory->createStream($request->body);
            $psrRequest = $psrRequest->withBody($body);
        }

        return $psrRequest;
    }

    private function psrToFolk(ResponseInterface $response): HttpResponse
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = \implode(', ', $values);
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        return new HttpResponse(
            status: $response->getStatusCode(),
            headers: $headers,
            body: $body->getContents(),
        );
    }
}
