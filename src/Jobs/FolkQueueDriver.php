<?php

declare(strict_types=1);

namespace Folk\Spiral\Jobs;

use Folk\Sdk\Uuid;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;

/**
 * Native Spiral queue connection backed by Folk's jobs plugin.
 *
 * Implements `Spiral\Queue\QueueInterface`, so application code uses the
 * idiomatic `$queue->push(SendEmail::class, $payload, Options::onQueue('redis.emails')->withDelay(5))`.
 * The job is serialized and handed to folk-plugin-jobs via folk_call(); Folk
 * drives consumption (worker → jobs.process), resolved by {@see FolkQueueHandler}
 * through Spiral's HandlerRegistryInterface.
 *
 * Register it as a queue connection in `app/config/queue.php` so Spiral's
 * QueueManager routes through Folk:
 *
 *   return [
 *       'default' => 'folk',
 *       'connections' => [
 *           'folk' => ['driver' => \Folk\Spiral\Jobs\FolkQueueDriver::class],
 *       ],
 *   ];
 */
final class FolkQueueDriver implements QueueInterface
{
    public function __construct(
        private readonly string $defaultQueue = 'default',
    ) {}

    /**
     * @param string|class-string<\Spiral\Queue\HandlerInterface> $name
     * @param array<string, mixed> $payload
     */
    public function push(string $name, array $payload = [], ?OptionsInterface $options = null): string
    {
        $queue = $options?->getQueue() ?? $this->defaultQueue;
        $delay = $options?->getDelay() ?? 0;
        $id = Uuid::v7();

        \folk_call('jobs.push', \json_encode([
            'queue' => $queue,
            'payload' => \json_encode([
                'job' => $name,
                'id' => $id,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR),
            'delay' => $delay,
        ], JSON_THROW_ON_ERROR));

        return $id;
    }
}
