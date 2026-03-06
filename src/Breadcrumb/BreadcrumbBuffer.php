<?php

declare(strict_types=1);

namespace LogTide\Breadcrumb;

final class BreadcrumbBuffer
{
    /** @var Breadcrumb[] */
    private array $buffer = [];

    public function __construct(
        private readonly int $maxSize = 100,
    ) {
    }

    public function add(Breadcrumb $breadcrumb): void
    {
        $this->buffer[] = $breadcrumb;

        if (count($this->buffer) > $this->maxSize) {
            array_shift($this->buffer);
        }
    }

    /** @return Breadcrumb[] */
    public function getAll(): array
    {
        return $this->buffer;
    }

    /** @return array<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(fn(Breadcrumb $b) => $b->toArray(), $this->buffer);
    }

    public function clear(): void
    {
        $this->buffer = [];
    }

    public function count(): int
    {
        return count($this->buffer);
    }

    public function clone(): self
    {
        $clone = new self($this->maxSize);
        $clone->buffer = $this->buffer;
        return $clone;
    }
}
