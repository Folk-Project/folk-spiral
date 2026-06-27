<?php

declare(strict_types=1);

namespace {
    // Stub the native folk_call() so the driver can be exercised without the
    // Folk extension loaded. Captures the last call.
    if (!\function_exists('folk_call')) {
        function folk_call(string $method, string $payload): string
        {
            $GLOBALS['__folk_test_calls'][] = ['method' => $method, 'payload' => $payload];

            return '{"status":"ok"}';
        }
    }
}

namespace Folk\Spiral\Tests\Jobs {

    use Folk\Spiral\Jobs\FolkQueueDriver;
    use PHPUnit\Framework\TestCase;
    use Spiral\Queue\Options;

    final class FolkQueueDriverTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__folk_test_calls'] = [];
        }

        public function testPushSerializesJobWithQueueAndDelay(): void
        {
            $driver = new FolkQueueDriver();

            $id = $driver->push(
                'App\\Job\\SendEmail',
                ['to' => 'user@example.com'],
                (new Options())->withQueue('redis.emails')->withDelay(5),
            );

            self::assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $id,
            );
            self::assertCount(1, $GLOBALS['__folk_test_calls']);
            $call = $GLOBALS['__folk_test_calls'][0];
            self::assertSame('jobs.push', $call['method']);

            $outer = \json_decode($call['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('redis.emails', $outer['queue']);
            self::assertSame(5, $outer['delay']);

            $inner = \json_decode($outer['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('App\\Job\\SendEmail', $inner['job']);
            self::assertSame($id, $inner['id']);
            self::assertSame(['to' => 'user@example.com'], $inner['payload']);
        }

        public function testPushDefaultsQueueAndZeroDelay(): void
        {
            $driver = new FolkQueueDriver();

            $driver->push('App\\Job\\Noop');

            $outer = \json_decode($GLOBALS['__folk_test_calls'][0]['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('default', $outer['queue']);
            self::assertSame(0, $outer['delay']);
        }

        public function testPushRespectsCustomDefaultQueue(): void
        {
            $driver = new FolkQueueDriver('embedded.heavy');

            $driver->push('App\\Job\\Noop');

            $outer = \json_decode($GLOBALS['__folk_test_calls'][0]['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('embedded.heavy', $outer['queue']);
        }
    }
}
