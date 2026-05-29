<?php

declare(strict_types=1);

namespace Folk\Spiral\Jobs;

use Folk\Sdk\Jobs\JobsModeHandler;
use Psr\Container\ContainerInterface;

/**
 * Handles jobs.process RPC calls from folk-plugin-jobs.
 *
 * Receives job payload with 'job' class and 'payload' data,
 * resolves the handler from Spiral container and invokes it.
 */
final class SpiralJobHandler implements JobsModeHandler
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function process(mixed $payload): mixed
    {
        $data = \is_array($payload) ? $payload : \json_decode((string) $payload, true);

        // Rust jobs plugin sends {payload: "..."} wrapper
        if (isset($data['payload']) && \is_string($data['payload'])) {
            $data = \json_decode($data['payload'], true) ?? $data;
        }

        $jobClass = $data['job'] ?? throw new \RuntimeException('Missing job class in payload');
        $jobPayload = $data['payload'] ?? $data['data'] ?? [];

        $handler = $this->container->get($jobClass);
        $handler->handle($jobPayload);

        return ['status' => 'ok'];
    }
}
