<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Tracing;

use LogTide\Enum\SpanKind;
use LogTide\Enum\SpanStatus;
use LogTide\Tracing\Span;
use PHPUnit\Framework\TestCase;

final class SpanTest extends TestCase
{
    public function testCreation(): void
    {
        $span = new Span('http.request');

        $this->assertSame('http.request', $span->getOperation());
        $this->assertSame(SpanKind::INTERNAL, $span->getKind());
        $this->assertNull($span->getParentSpanId());
        $this->assertNull($span->getServiceName());
        $this->assertNotEmpty($span->getTraceId());
        $this->assertNotEmpty($span->getSpanId());
        $this->assertSame(32, strlen($span->getTraceId()));
        $this->assertSame(16, strlen($span->getSpanId()));
        $this->assertGreaterThan(0, $span->getStartTime());
        $this->assertFalse($span->isFinished());
        $this->assertNull($span->getEndTime());
        $this->assertNull($span->getDurationMs());
        $this->assertSame(SpanStatus::UNSET, $span->getStatus());
        $this->assertNull($span->getStatusMessage());
    }

    public function testCreationWithAllOptions(): void
    {
        $span = new Span(
            'db.query',
            SpanKind::CLIENT,
            'parent-123',
            'trace-abc',
            'my-service',
        );

        $this->assertSame('db.query', $span->getOperation());
        $this->assertSame(SpanKind::CLIENT, $span->getKind());
        $this->assertSame('parent-123', $span->getParentSpanId());
        $this->assertSame('trace-abc', $span->getTraceId());
        $this->assertSame('my-service', $span->getServiceName());
    }

    public function testFinish(): void
    {
        $span = new Span('test.op');
        usleep(1000);
        $span->finish();

        $this->assertTrue($span->isFinished());
        $this->assertNotNull($span->getEndTime());
        $this->assertGreaterThan(0, $span->getDurationMs());
        $this->assertGreaterThanOrEqual($span->getStartTime(), $span->getEndTime());
    }

    public function testFinishWithStatus(): void
    {
        $span = new Span('test.op');
        $span->finish(SpanStatus::ERROR, 'something failed');

        $this->assertSame(SpanStatus::ERROR, $span->getStatus());
        $this->assertSame('something failed', $span->getStatusMessage());
    }

    public function testFinishOnlyOnce(): void
    {
        $span = new Span('test.op');
        $span->finish(SpanStatus::OK);

        $endTime = $span->getEndTime();
        usleep(1000);

        $span->finish(SpanStatus::ERROR);

        $this->assertSame($endTime, $span->getEndTime());
        $this->assertSame(SpanStatus::OK, $span->getStatus());
    }

    public function testSetAttribute(): void
    {
        $span = new Span('test.op');
        $span->setAttribute('http.method', 'GET');
        $span->setAttribute('http.status_code', 200);

        $this->assertSame('GET', $span->getAttributes()['http.method']);
        $this->assertSame(200, $span->getAttributes()['http.status_code']);
    }

    public function testSetAttributes(): void
    {
        $span = new Span('test.op');
        $span->setAttribute('existing', 'value');
        $span->setAttributes(['a' => 1, 'b' => 2]);

        $attrs = $span->getAttributes();
        $this->assertSame('value', $attrs['existing']);
        $this->assertSame(1, $attrs['a']);
        $this->assertSame(2, $attrs['b']);
    }

    public function testAddEvent(): void
    {
        $span = new Span('test.op');
        $span->addEvent('cache.miss', ['key' => 'user:42']);

        $events = $span->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame('cache.miss', $events[0]['name']);
        $this->assertSame(['key' => 'user:42'], $events[0]['attributes']);
        $this->assertArrayHasKey('timeUnixNano', $events[0]);
    }

    /**
     * OTLP requires `timeUnixNano` to be a stringified uint64 (digits only).
     * Casting `(string)(microtime(true) * 1e9)` produces scientific notation
     * like "1.7755623882398E+18", which the OTLP backend rejects.
     */
    public function testAddEventTimeUnixNanoIsIntegerString(): void
    {
        $span = new Span('test.op');
        $span->addEvent('cache.miss');

        $timeUnixNano = $span->getEvents()[0]['timeUnixNano'];

        $this->assertIsString($timeUnixNano);
        $this->assertMatchesRegularExpression(
            '/^\d+$/',
            $timeUnixNano,
            'timeUnixNano must be a digit-only stringified uint64, not scientific notation'
        );
    }

    public function testMultipleEvents(): void
    {
        $span = new Span('test.op');
        $span->addEvent('event1');
        $span->addEvent('event2');
        $span->addEvent('event3');

        $this->assertCount(3, $span->getEvents());
    }

    public function testSetStatus(): void
    {
        $span = new Span('test.op');
        $span->setStatus(SpanStatus::OK);

        $this->assertSame(SpanStatus::OK, $span->getStatus());
        $this->assertNull($span->getStatusMessage());
    }

    public function testSetStatusWithMessage(): void
    {
        $span = new Span('test.op');
        $span->setStatus(SpanStatus::ERROR, 'deadline exceeded');

        $this->assertSame(SpanStatus::ERROR, $span->getStatus());
        $this->assertSame('deadline exceeded', $span->getStatusMessage());
    }

    public function testDurationMsCalculation(): void
    {
        $span = new Span('test.op');
        usleep(10000); // 10ms
        $span->finish();

        $duration = $span->getDurationMs();
        $this->assertGreaterThanOrEqual(5.0, $duration);
        $this->assertLessThan(500.0, $duration);
    }

    public function testUnfinishedSpanDurationIsNull(): void
    {
        $span = new Span('test.op');
        $this->assertNull($span->getDurationMs());
    }

    public function testTraceIdGeneratedWhenNotProvided(): void
    {
        $span1 = new Span('op1');
        $span2 = new Span('op2');

        $this->assertNotSame($span1->getTraceId(), $span2->getTraceId());
    }

    public function testSpanIdAlwaysUnique(): void
    {
        $span1 = new Span('op1');
        $span2 = new Span('op2');

        $this->assertNotSame($span1->getSpanId(), $span2->getSpanId());
    }

    public function testSharedTraceIdWhenProvided(): void
    {
        $traceId = str_repeat('a', 32);
        $span1 = new Span('op1', traceId: $traceId);
        $span2 = new Span('op2', traceId: $traceId);

        $this->assertSame($traceId, $span1->getTraceId());
        $this->assertSame($traceId, $span2->getTraceId());
        $this->assertNotSame($span1->getSpanId(), $span2->getSpanId());
    }
}
