<?php

declare(strict_types=1);

namespace LogTide\HttpClient;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
