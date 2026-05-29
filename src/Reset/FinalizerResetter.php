<?php

declare(strict_types=1);

namespace Folk\Spiral\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Psr\Container\ContainerInterface;
use Spiral\Boot\FinalizerInterface;

/**
 * Calls Spiral's FinalizerInterface between requests.
 *
 * This triggers all registered finalizers in the framework,
 * cleaning up scoped dependencies, closing connections, etc.
 */
final class FinalizerResetter implements ResettableInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function reset(): void
    {
        try {
            /** @var FinalizerInterface $finalizer */
            $finalizer = $this->container->get(FinalizerInterface::class);
            $finalizer->finalize(false);
        } catch (\Throwable) {
        }
    }
}
