<?php

declare(strict_types=1);

namespace Folk\Spiral\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Psr\Container\ContainerInterface;

/**
 * Cleans Cycle ORM heap between requests to prevent entity memory leaks.
 */
final class CycleResetter implements ResettableInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function reset(): void
    {
        try {
            $orm = $this->container->get(\Cycle\ORM\ORMInterface::class);
            $orm->getHeap()->clean();
        } catch (\Throwable) {
        }
    }
}
