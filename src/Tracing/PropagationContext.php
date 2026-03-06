<?php

declare(strict_types=1);

namespace LogTide\Tracing;

final class PropagationContext
{
    public function __construct(
        private readonly string $traceId,
        private readonly string $spanId,
        private readonly ?string $parentSpanId = null,
        private readonly bool $sampled = true,
    ) {
    }

    public static function create(): self
    {
        return new self(
            (string) TraceId::generate(),
            (string) SpanId::generate(),
        );
    }

    public static function fromTraceparent(string $traceparent): ?self
    {
        $parts = explode('-', $traceparent);
        if (count($parts) < 4) {
            return null;
        }

        [$version, $traceId, $parentSpanId, $flags] = $parts;

        if ($version !== '00' || strlen($traceId) !== 32 || strlen($parentSpanId) !== 16) {
            return null;
        }

        return new self(
            $traceId,
            (string) SpanId::generate(),
            $parentSpanId,
            ($flags & 1) === 1,
        );
    }

    public function toTraceparent(): string
    {
        $flags = $this->sampled ? '01' : '00';
        return "00-{$this->traceId}-{$this->spanId}-{$flags}";
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

    public function isSampled(): bool
    {
        return $this->sampled;
    }
}
