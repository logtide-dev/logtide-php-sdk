<?php

declare(strict_types=1);

namespace LogTide\Tracing;

use LogTide\Enum\SpanKind;
use LogTide\Enum\SpanStatus;

final class Span
{
    private readonly string $traceId;
    private readonly string $spanId;
    private readonly float $startTime;
    private ?float $endTime = null;
    private SpanStatus $status = SpanStatus::UNSET;
    private ?string $statusMessage = null;
    private array $attributes = [];
    private array $events = [];
    private bool $finished = false;

    public function __construct(
        private readonly string $operation,
        private readonly SpanKind $kind = SpanKind::INTERNAL,
        private readonly ?string $parentSpanId = null,
        ?string $traceId = null,
        private readonly ?string $serviceName = null,
    ) {
        $this->traceId = $traceId ?? (string) TraceId::generate();
        $this->spanId = (string) SpanId::generate();
        $this->startTime = microtime(true);
    }

    public function finish(?SpanStatus $status = null, ?string $statusMessage = null): void
    {
        if ($this->finished) {
            return;
        }

        $this->endTime = microtime(true);
        $this->finished = true;

        if ($status !== null) {
            $this->status = $status;
        }
        if ($statusMessage !== null) {
            $this->statusMessage = $statusMessage;
        }
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = array_merge($this->attributes, $attributes);
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        $this->events[] = [
            'name' => $name,
            'timeUnixNano' => (string) (microtime(true) * 1_000_000_000),
            'attributes' => $attributes,
        ];
    }

    public function setStatus(SpanStatus $status, ?string $message = null): void
    {
        $this->status = $status;
        $this->statusMessage = $message;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getKind(): SpanKind
    {
        return $this->kind;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    public function getDurationMs(): ?float
    {
        if ($this->endTime === null) {
            return null;
        }
        return ($this->endTime - $this->startTime) * 1000;
    }

    public function getStatus(): SpanStatus
    {
        return $this->status;
    }

    public function getStatusMessage(): ?string
    {
        return $this->statusMessage;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }
}
