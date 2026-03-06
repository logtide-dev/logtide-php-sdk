<?php

declare(strict_types=1);

namespace LogTide;

final class EventHint
{
    public function __construct(
        public readonly ?\Throwable $exception = null,
        public readonly array $extra = [],
    ) {
    }

    public static function fromException(\Throwable $exception): self
    {
        return new self(exception: $exception);
    }
}
