<?php

declare(strict_types=1);

namespace Folk\Spiral\Tests\Reset;

use Folk\Spiral\Reset\FinalizerResetter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Spiral\Boot\FinalizerInterface;

final class FinalizerResetterTest extends TestCase
{
    public function testCallsFinalizeOnReset(): void
    {
        $finalizer = $this->createMock(FinalizerInterface::class);
        $finalizer->expects(self::once())->method('finalize')->with(false);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(FinalizerInterface::class)->willReturn($finalizer);

        (new FinalizerResetter($container))->reset();
    }

    public function testSilentlySwallowsExceptionWhenFinalizerUnavailable(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willThrowException(new \RuntimeException('not bound'));

        (new FinalizerResetter($container))->reset();

        self::assertTrue(true);
    }
}
