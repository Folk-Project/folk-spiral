<?php

/**
 * PHPStan stubs for Cycle ORM (optional dependency).
 */

namespace Cycle\ORM;

interface ORMInterface
{
    public function getHeap(): HeapInterface;
}

interface HeapInterface
{
    public function clean(): void;
}
