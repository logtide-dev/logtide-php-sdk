<?php

declare(strict_types=1);

namespace LogTide;

use LogTide\Exception\InvalidDsnException;

final class Dsn
{
    private function __construct(
        public readonly string $apiUrl,
        public readonly string $apiKey,
    ) {
    }

    public static function parse(string $dsn): self
    {
        $parts = parse_url($dsn);

        if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['user'])) {
            throw new InvalidDsnException("Invalid DSN: {$dsn}");
        }

        $apiKey = $parts['user'];
        if (!str_starts_with($apiKey, 'lp_')) {
            throw new InvalidDsnException("Invalid API key in DSN: must start with 'lp_'");
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        $apiUrl = "{$scheme}://{$host}{$port}{$path}";

        return new self($apiUrl, $apiKey);
    }

    public function __toString(): string
    {
        $parts = parse_url($this->apiUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $path = $parts['path'] ?? '';

        return "{$scheme}://{$this->apiKey}@{$host}{$port}{$path}";
    }
}
