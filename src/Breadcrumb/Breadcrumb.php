<?php

declare(strict_types=1);

namespace LogTide\Breadcrumb;

use LogTide\Enum\BreadcrumbType;
use LogTide\Enum\LogLevel;

final class Breadcrumb
{
    public readonly float $timestamp;

    public function __construct(
        public readonly BreadcrumbType $type,
        public readonly string $message,
        public readonly ?string $category = null,
        public readonly ?LogLevel $level = null,
        public readonly array $data = [],
        ?float $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? microtime(true);
    }

    public function toArray(): array
    {
        $result = [
            'type' => $this->type->value,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
        ];

        if ($this->category !== null) {
            $result['category'] = $this->category;
        }

        if ($this->level !== null) {
            $result['level'] = $this->level->value;
        }

        if (!empty($this->data)) {
            $result['data'] = $this->data;
        }

        return $result;
    }
}
