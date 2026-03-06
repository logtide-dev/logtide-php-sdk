<?php

declare(strict_types=1);

namespace LogTide;

use LogTide\Enum\LogLevel;

final class Event
{
    private string $id;
    private string $time;
    private string $service;
    private LogLevel $level;
    private string $message;
    private array $metadata = [];
    private ?string $traceId = null;
    private ?string $spanId = null;
    private array $tags = [];
    private array $extras = [];
    private array $breadcrumbs = [];
    private ?array $exception = null;
    private ?array $stacktrace = null;
    private ?string $environment = null;
    private ?string $release = null;

    public function __construct(
        LogLevel $level,
        string $message,
        ?string $service = null,
    ) {
        $this->id = bin2hex(random_bytes(16));
        $this->time = (new \DateTimeImmutable())->format('c');
        $this->level = $level;
        $this->message = $message;
        $this->service = $service ?? 'unknown';
    }

    public static function createLog(LogLevel $level, string $message, ?string $service = null): self
    {
        return new self($level, $message, $service);
    }

    public static function createError(\Throwable $exception, ?string $service = null): self
    {
        return new self(LogLevel::ERROR, $exception->getMessage(), $service);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function setTime(string $time): void
    {
        $this->time = $time;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function setService(string $service): void
    {
        $this->service = $service;
    }

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function setLevel(LogLevel $level): void
    {
        $this->level = $level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function setTraceId(?string $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function getSpanId(): ?string
    {
        return $this->spanId;
    }

    public function setSpanId(?string $spanId): void
    {
        $this->spanId = $spanId;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getExtras(): array
    {
        return $this->extras;
    }

    public function setExtras(array $extras): void
    {
        $this->extras = $extras;
    }

    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    public function setBreadcrumbs(array $breadcrumbs): void
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    public function getException(): ?array
    {
        return $this->exception;
    }

    public function setException(?array $exception): void
    {
        $this->exception = $exception;
    }

    public function getStacktrace(): ?array
    {
        return $this->stacktrace;
    }

    public function setStacktrace(?array $stacktrace): void
    {
        $this->stacktrace = $stacktrace;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): void
    {
        $this->environment = $environment;
    }

    public function getRelease(): ?string
    {
        return $this->release;
    }

    public function setRelease(?string $release): void
    {
        $this->release = $release;
    }

    public function toArray(): array
    {
        $data = [
            'time' => $this->time,
            'service' => $this->service,
            'level' => $this->level->value,
            'message' => $this->message,
            'metadata' => $this->buildMetadata(),
        ];

        if ($this->traceId !== null) {
            $data['trace_id'] = $this->traceId;
        }

        if ($this->spanId !== null) {
            $data['span_id'] = $this->spanId;
        }

        return $data;
    }

    private function buildMetadata(): array
    {
        $meta = $this->metadata;

        if (!empty($this->tags)) {
            $meta['tags'] = $this->tags;
        }

        if (!empty($this->extras)) {
            $meta['extra'] = $this->extras;
        }

        if ($this->exception !== null) {
            $meta['exception'] = $this->exception;
        }

        if ($this->stacktrace !== null) {
            $meta['stacktrace'] = $this->stacktrace;
        }

        if (!empty($this->breadcrumbs)) {
            $meta['breadcrumbs'] = $this->breadcrumbs;
        }

        if ($this->environment !== null) {
            $meta['environment'] = $this->environment;
        }

        if ($this->release !== null) {
            $meta['release'] = $this->release;
        }

        return $meta;
    }
}
