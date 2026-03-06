<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Tracing;

use LogTide\Tracing\PropagationContext;
use PHPUnit\Framework\TestCase;

final class PropagationContextTest extends TestCase
{
    public function testCreate(): void
    {
        $ctx = PropagationContext::create();

        $this->assertSame(32, strlen($ctx->getTraceId()));
        $this->assertSame(16, strlen($ctx->getSpanId()));
        $this->assertNull($ctx->getParentSpanId());
        $this->assertTrue($ctx->isSampled());
    }

    public function testCreateGeneratesUniqueIds(): void
    {
        $ctx1 = PropagationContext::create();
        $ctx2 = PropagationContext::create();

        $this->assertNotSame($ctx1->getTraceId(), $ctx2->getTraceId());
        $this->assertNotSame($ctx1->getSpanId(), $ctx2->getSpanId());
    }

    public function testFromTraceparentSampled(): void
    {
        $traceId = str_repeat('a', 32);
        $parentId = str_repeat('b', 16);
        $traceparent = "00-{$traceId}-{$parentId}-01";

        $ctx = PropagationContext::fromTraceparent($traceparent);

        $this->assertNotNull($ctx);
        $this->assertSame($traceId, $ctx->getTraceId());
        $this->assertSame($parentId, $ctx->getParentSpanId());
        $this->assertTrue($ctx->isSampled());
        $this->assertSame(16, strlen($ctx->getSpanId()));
        $this->assertNotSame($parentId, $ctx->getSpanId());
    }

    public function testFromTraceparentNotSampled(): void
    {
        $traceId = str_repeat('c', 32);
        $parentId = str_repeat('d', 16);
        $traceparent = "00-{$traceId}-{$parentId}-00";

        $ctx = PropagationContext::fromTraceparent($traceparent);

        $this->assertNotNull($ctx);
        $this->assertFalse($ctx->isSampled());
    }

    public function testFromTraceparentInvalidTooFewParts(): void
    {
        $this->assertNull(PropagationContext::fromTraceparent('00-abc'));
    }

    public function testFromTraceparentInvalidVersion(): void
    {
        $traceId = str_repeat('a', 32);
        $parentId = str_repeat('b', 16);

        $this->assertNull(PropagationContext::fromTraceparent("ff-{$traceId}-{$parentId}-01"));
    }

    public function testFromTraceparentInvalidTraceIdLength(): void
    {
        $parentId = str_repeat('b', 16);
        $this->assertNull(PropagationContext::fromTraceparent("00-shortid-{$parentId}-01"));
    }

    public function testFromTraceparentInvalidParentSpanIdLength(): void
    {
        $traceId = str_repeat('a', 32);
        $this->assertNull(PropagationContext::fromTraceparent("00-{$traceId}-short-01"));
    }

    public function testToTraceparentSampled(): void
    {
        $traceId = str_repeat('a', 32);
        $parentId = str_repeat('b', 16);
        $ctx = PropagationContext::fromTraceparent("00-{$traceId}-{$parentId}-01");

        $traceparent = $ctx->toTraceparent();

        $this->assertStringStartsWith('00-', $traceparent);
        $this->assertStringContainsString($traceId, $traceparent);
        $this->assertStringEndsWith('-01', $traceparent);
    }

    public function testToTraceparentNotSampled(): void
    {
        $traceId = str_repeat('a', 32);
        $parentId = str_repeat('b', 16);
        $ctx = PropagationContext::fromTraceparent("00-{$traceId}-{$parentId}-00");

        $this->assertStringEndsWith('-00', $ctx->toTraceparent());
    }

    public function testRoundTrip(): void
    {
        $ctx = PropagationContext::create();
        $traceparent = $ctx->toTraceparent();

        $parsed = PropagationContext::fromTraceparent($traceparent);

        $this->assertNotNull($parsed);
        $this->assertSame($ctx->getTraceId(), $parsed->getTraceId());
        $this->assertSame($ctx->isSampled(), $parsed->isSampled());
        $this->assertSame($ctx->getSpanId(), $parsed->getParentSpanId());
    }

    public function testToTraceparentFormat(): void
    {
        $ctx = PropagationContext::create();
        $traceparent = $ctx->toTraceparent();

        $parts = explode('-', $traceparent);
        $this->assertCount(4, $parts);
        $this->assertSame('00', $parts[0]);
        $this->assertSame(32, strlen($parts[1]));
        $this->assertSame(16, strlen($parts[2]));
        $this->assertMatchesRegularExpression('/^0[01]$/', $parts[3]);
    }

    public function testFromTraceparentFlagsBitmask(): void
    {
        $traceId = str_repeat('a', 32);
        $parentId = str_repeat('b', 16);

        // Flag '03' has bit 0 set, so sampled should be true
        $ctx = PropagationContext::fromTraceparent("00-{$traceId}-{$parentId}-03");
        $this->assertNotNull($ctx);
        $this->assertTrue($ctx->isSampled());

        // Flag '02' has bit 0 unset, so sampled should be false
        $ctx2 = PropagationContext::fromTraceparent("00-{$traceId}-{$parentId}-02");
        $this->assertNotNull($ctx2);
        $this->assertFalse($ctx2->isSampled());
    }
}
