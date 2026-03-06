<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Transport;

use LogTide\Enum\CircuitState;
use LogTide\Enum\LogLevel;
use LogTide\Event;
use LogTide\Options;
use LogTide\Tracing\Span;
use LogTide\Transport\BatchTransport;
use LogTide\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

final class BatchTransportTest extends TestCase
{
    private function createSpyTransport(): TransportInterface
    {
        return new class implements TransportInterface {
            public array $sentLogs = [];
            public array $sentSpans = [];

            public function sendLogs(array $events): void
            {
                $this->sentLogs[] = $events;
            }

            public function sendSpans(array $spans): void
            {
                $this->sentSpans[] = $spans;
            }

            public function flush(): void {}
            public function close(): void {}
        };
    }

    private function createFailingTransport(int $failCount = PHP_INT_MAX): TransportInterface
    {
        return new class($failCount) implements TransportInterface {
            private int $attempts = 0;

            public function __construct(private readonly int $failCount) {}

            public function sendLogs(array $events): void
            {
                $this->attempts++;
                if ($this->attempts <= $this->failCount) {
                    throw new \RuntimeException("Transport error #{$this->attempts}");
                }
            }

            public function sendSpans(array $spans): void
            {
                throw new \RuntimeException('Span transport error');
            }

            public function flush(): void {}
            public function close(): void {}
        };
    }

    private function createBatchTransport(
        ?TransportInterface $logTransport = null,
        ?TransportInterface $spanTransport = null,
        array $options = [],
    ): BatchTransport {
        $defaults = [
            'batch_size' => 3,
            'max_buffer_size' => 10,
            'max_retries' => 0,
            'retry_delay_ms' => 1,
        ];

        return new BatchTransport(
            $logTransport ?? $this->createSpyTransport(),
            $spanTransport ?? $this->createSpyTransport(),
            Options::fromArray(array_merge($defaults, $options)),
        );
    }

    private function createEvent(string $msg = 'test'): Event
    {
        return Event::createLog(LogLevel::INFO, $msg);
    }

    public function testBuffersUntilBatchSize(): void
    {
        $logTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport($logTransport);

        $batch->sendLogs([$this->createEvent()]);
        $batch->sendLogs([$this->createEvent()]);

        $this->assertEmpty($logTransport->sentLogs);

        $batch->sendLogs([$this->createEvent()]);

        $this->assertCount(1, $logTransport->sentLogs);
        $this->assertCount(3, $logTransport->sentLogs[0]);
    }

    public function testFlushSendsBufferedLogs(): void
    {
        $logTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport($logTransport);

        $batch->sendLogs([$this->createEvent()]);
        $this->assertEmpty($logTransport->sentLogs);

        $batch->flush();

        $this->assertCount(1, $logTransport->sentLogs);
    }

    public function testFlushSendsBufferedSpans(): void
    {
        $spanTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport(null, $spanTransport);

        $span = new Span('test.op');
        $span->finish();
        $batch->sendSpans([$span]);

        $this->assertEmpty($spanTransport->sentSpans);

        $batch->flush();

        $this->assertCount(1, $spanTransport->sentSpans);
    }

    public function testFlushEmptyBuffersNoOp(): void
    {
        $logTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport($logTransport);

        $batch->flush();

        $this->assertEmpty($logTransport->sentLogs);
    }

    public function testDropsLogsWhenBufferFull(): void
    {
        $logTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport($logTransport, null, [
            'batch_size' => 100,
            'max_buffer_size' => 3,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $batch->sendLogs([$this->createEvent("msg-{$i}")]);
        }

        $metrics = $batch->getMetrics();
        $this->assertSame(2, $metrics['logs_dropped']);
        $this->assertSame(3, $metrics['buffer_size']);
    }

    public function testDropsSpansWhenBufferFull(): void
    {
        $spanTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport(null, $spanTransport, [
            'batch_size' => 100,
            'max_buffer_size' => 2,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $span = new Span("op-{$i}");
            $span->finish();
            $batch->sendSpans([$span]);
        }

        $batch->flush();
        $this->assertCount(2, $spanTransport->sentSpans[0]);
    }

    public function testRetriesOnFailure(): void
    {
        $logTransport = $this->createFailingTransport(1);
        $batch = $this->createBatchTransport($logTransport, null, [
            'max_retries' => 2,
            'retry_delay_ms' => 1,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $batch->sendLogs([$this->createEvent()]);
        }

        $metrics = $batch->getMetrics();
        $this->assertSame(3, $metrics['logs_sent']);
        $this->assertSame(1, $metrics['retries']);
    }

    public function testCircuitBreakerOpensAfterFailures(): void
    {
        $logTransport = $this->createFailingTransport();
        $batch = $this->createBatchTransport($logTransport, null, [
            'batch_size' => 1,
            'max_retries' => 0,
            'circuit_breaker_threshold' => 2,
            'circuit_breaker_reset_ms' => 60000,
        ]);

        $batch->sendLogs([$this->createEvent()]);
        $batch->sendLogs([$this->createEvent()]);

        $this->assertSame(CircuitState::OPEN, $batch->getCircuitBreaker()->getState());
    }

    public function testSkipsFlushWhenCircuitOpen(): void
    {
        $logTransport = $this->createFailingTransport();
        $batch = $this->createBatchTransport($logTransport, null, [
            'batch_size' => 1,
            'max_retries' => 0,
            'circuit_breaker_threshold' => 1,
            'circuit_breaker_reset_ms' => 60000,
        ]);

        $batch->sendLogs([$this->createEvent()]);

        $this->assertSame(CircuitState::OPEN, $batch->getCircuitBreaker()->getState());

        // Next send buffers but does not flush
        $batch->sendLogs([$this->createEvent()]);
        $batch->sendLogs([$this->createEvent()]);
        $batch->sendLogs([$this->createEvent()]);

        $metrics = $batch->getMetrics();
        $this->assertSame(1, $metrics['errors']);
    }

    public function testReBuffersOnFailure(): void
    {
        $logTransport = $this->createFailingTransport();
        $batch = $this->createBatchTransport($logTransport, null, [
            'batch_size' => 2,
            'max_buffer_size' => 10,
            'max_retries' => 0,
        ]);

        $batch->sendLogs([$this->createEvent(), $this->createEvent()]);

        $metrics = $batch->getMetrics();
        $this->assertSame(2, $metrics['buffer_size']);
        $this->assertSame(0, $metrics['logs_dropped']);
    }

    public function testReBufferDropsWhenBufferFull(): void
    {
        $logTransport = $this->createFailingTransport();
        $batch = $this->createBatchTransport($logTransport, null, [
            'batch_size' => 2,
            'max_buffer_size' => 2,
            'max_retries' => 0,
        ]);

        // Buffer 2, trigger flush, fails, tries to re-buffer but buffer now has new items
        $batch->sendLogs([$this->createEvent(), $this->createEvent()]);

        // Buffer was emptied for flush, then re-buffered since max_buffer_size allows
        $metrics = $batch->getMetrics();
        $this->assertSame(0, $metrics['logs_sent']);
    }

    public function testCloseFlushes(): void
    {
        $logTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport($logTransport);

        $batch->sendLogs([$this->createEvent()]);
        $batch->close();

        $this->assertCount(1, $logTransport->sentLogs);
    }

    public function testMetricsInitial(): void
    {
        $batch = $this->createBatchTransport();

        $metrics = $batch->getMetrics();

        $this->assertSame(0, $metrics['logs_sent']);
        $this->assertSame(0, $metrics['logs_dropped']);
        $this->assertSame(0, $metrics['errors']);
        $this->assertSame(0, $metrics['retries']);
        $this->assertSame(0, $metrics['buffer_size']);
        $this->assertSame('closed', $metrics['circuit_breaker_state']);
    }

    public function testMetricsAfterSuccessfulSend(): void
    {
        $logTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport($logTransport, null, ['batch_size' => 2]);

        $batch->sendLogs([$this->createEvent(), $this->createEvent()]);

        $metrics = $batch->getMetrics();
        $this->assertSame(2, $metrics['logs_sent']);
        $this->assertSame(0, $metrics['buffer_size']);
    }

    public function testMultipleBatches(): void
    {
        $logTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport($logTransport, null, ['batch_size' => 2]);

        for ($i = 0; $i < 6; $i++) {
            $batch->sendLogs([$this->createEvent()]);
        }

        $this->assertCount(3, $logTransport->sentLogs);
        $this->assertSame(6, $batch->getMetrics()['logs_sent']);
    }

    public function testSpanAutoFlushAtBatchSize(): void
    {
        $spanTransport = $this->createSpyTransport();
        $batch = $this->createBatchTransport(null, $spanTransport, ['batch_size' => 2]);

        for ($i = 0; $i < 4; $i++) {
            $span = new Span("op-{$i}");
            $span->finish();
            $batch->sendSpans([$span]);
        }

        $this->assertCount(2, $spanTransport->sentSpans);
    }
}
