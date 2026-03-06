<?php

declare(strict_types=1);

namespace LogTide\Tracing;

final class SpanId
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(8)));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
