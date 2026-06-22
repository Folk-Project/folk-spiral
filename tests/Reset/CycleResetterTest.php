<?php

declare(strict_types=1);

namespace Folk\Spiral\Tests\Reset;

use Folk\Spiral\Reset\CycleResetter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class CycleResetterTest extends TestCase
{
    public function testSilentlySwallowsExceptionWhenOrmNotAvailable(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willThrowException(new \RuntimeException('cycle not installed'));

        (new CycleResetter($container))->reset();

        self::assertTrue(true);
    }
}
