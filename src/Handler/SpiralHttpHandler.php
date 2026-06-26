<?php

declare(strict_types=1);

namespace Folk\Spiral\Handler;

use Folk\Sdk\Folk;
use Folk\Sdk\Http\HttpModeHandler;
use Folk\Sdk\Http\HttpRequest;
use Folk\Sdk\Http\HttpResponse;
use Folk\Sdk\Http\StreamedBody;
use Folk\Sdk\Http\StreamLimitExceededException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Http\Http;

final class SpiralHttpHandler implements HttpModeHandler
{
    private const CHUNK = 65536;

    private readonly Container $container;
    private readonly ServerRequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly ?UploadedFileFactoryInterface $uploadedFileFactory;
    private readonly Http $http;

    public function __construct(
        ContainerInterface $container,
        private readonly int $maxRequestBytes = 0,
        /** @var array<string, int> */
        private readonly array $pathLimits = [],
    ) {
        if (!$container instanceof Container) {
            throw new \InvalidArgumentException('SpiralHttpHandler requires Spiral\Core\Container');
        }

        $this->container = $container;
        $this->requestFactory = $container->get(ServerRequestFactoryInterface::class);
        $this->streamFactory = $container->get(StreamFactoryInterface::class);
        $this->uploadedFileFactory = $container->has(UploadedFileFactoryInterface::class)
            ? $container->get(UploadedFileFactoryInterface::class)
            : null;
        $this->http = $container->get(Http::class);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $streamed = null;
        try {
            $psrRequest = $this->folkToPsr($request, $streamed);

            return $this->container->runScope(
                new Scope('http'),
                function () use ($psrRequest): HttpResponse {
                    $psrResponse = $this->http->handle($psrRequest);
                    return $this->psrToFolk($psrResponse);
                },
            );
        } catch (StreamLimitExceededException $e) {
            return new HttpResponse(
                status: 413,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(['error' => $e->getMessage()]) ?: '{}',
            );
        } finally {
            $streamed?->cleanup();
        }
    }

    private function folkToPsr(HttpRequest $request, ?StreamedBody &$streamed): ServerRequestInterface
    {
        $psrRequest = $this->requestFactory->createServerRequest($request->method, $request->uri);

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        if ($request->multipart) {
            $streamed = new StreamedBody(StreamedBody::resolveLimit($request->uri, $this->maxRequestBytes, $this->pathLimits));
            $streamed->drainMultipart();
            if ($streamed->post !== []) {
                $psrRequest = $psrRequest->withParsedBody($streamed->post);
            }
            $files = $this->buildUploadedFiles($streamed);
            if ($files !== []) {
                $psrRequest = $psrRequest->withUploadedFiles($files);
            }
        } elseif ($request->bodyStream) {
            $streamed = new StreamedBody(StreamedBody::resolveLimit($request->uri, $this->maxRequestBytes, $this->pathLimits));
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($streamed->readRaw()));
        } elseif ($request->body !== '') {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        return $psrRequest;
    }

    /**
     * @return array<string, \Psr\Http\Message\UploadedFileInterface>
     */
    private function buildUploadedFiles(StreamedBody $streamed): array
    {
        if ($this->uploadedFileFactory === null) {
            return [];
        }
        $files = [];
        foreach ($streamed->files as $file) {
            if ($file->field === null) {
                continue;
            }
            $stream = $this->streamFactory->createStreamFromFile($file->tmpPath, 'rb');
            $files[$file->field] = $this->uploadedFileFactory->createUploadedFile(
                $stream,
                $file->size,
                \UPLOAD_ERR_OK,
                $file->originalName,
                $file->contentType,
            );
        }

        return $files;
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

        // Unknown size, or explicit `X-Folk-Stream: yes` → pipe chunk by chunk.
        if ($body->getSize() === null || strcasecmp($response->getHeaderLine('X-Folk-Stream'), 'yes') === 0) {
            Folk::writeHead($response->getStatusCode(), $headers);
            while (!$body->eof()) {
                $chunk = $body->read(self::CHUNK);
                if ($chunk !== '') {
                    Folk::write($chunk);
                }
            }
            Folk::end();

            return HttpResponse::alreadyStreamed();
        }

        return new HttpResponse(
            status: $response->getStatusCode(),
            headers: $headers,
            body: $body->getContents(),
        );
    }
}
