<?php

declare(strict_types=1);

namespace Folk\Spiral\Jobs;

use Folk\Sdk\Jobs\JobsModeHandler;
use Folk\Sdk\Uuid;
use Spiral\Queue\HandlerRegistryInterface;

/**
 * Consume side of the native Spiral queue bridge.
 *
 * folk-plugin-jobs delivers {queue, payload} to jobs.process; payload carries
 * {job, id, payload} produced by {@see FolkQueueDriver}. The job type is
 * resolved through Spiral's HandlerRegistryInterface and dispatched via the
 * native HandlerInterface::handle($name, $id, $payload) contract — the same
 * path `spiral/roadrunner-bridge` uses to consume RoadRunner jobs.
 */
final class FolkQueueHandler implements JobsModeHandler
{
    public function __construct(
        private readonly HandlerRegistryInterface $registry,
    ) {}

    public function process(mixed $payload): mixed
    {
        $data = \is_array($payload) ? $payload : (array) \json_decode((string) $payload, true);

        // Unwrap the {payload: "..."} envelope from folk-plugin-jobs.
        if (isset($data['payload']) && \is_string($data['payload'])) {
            /** @var array<string, mixed> $data */
            $data = (array) \json_decode($data['payload'], true);
        }

        $name = (string) ($data['job'] ?? '');
        if ($name === '') {
            throw new \RuntimeException('Missing job type in payload');
        }

        $id = isset($data['id']) && \is_string($data['id']) && $data['id'] !== ''
            ? $data['id']
            : Uuid::v7();

        /** @var array<string, mixed> $jobPayload */
        $jobPayload = $data['payload'] ?? $data['data'] ?? [];

        $this->registry->getHandler($name)->handle($name, $id, $jobPayload);

        return ['status' => 'ok'];
    }
}
