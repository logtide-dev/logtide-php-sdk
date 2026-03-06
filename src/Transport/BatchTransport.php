<?php

declare(strict_types=1);

namespace LogTide\Transport;

use LogTide\Event;
use LogTide\Options;
use LogTide\Tracing\Span;
use LogTide\Util\CircuitBreaker;

final class BatchTransport implements TransportInterface
{
    /** @var Event[] */
    private array $logBuffer = [];

    /** @var Span[] */
    private array $spanBuffer = [];

    private readonly CircuitBreaker $circuitBreaker;
    private readonly int $batchSize;
    private readonly int $maxBufferSize;
    private readonly int $maxRetries;
    private readonly int $retryDelayMs;
    private readonly bool $debug;

    private int $logsSent = 0;
    private int $logsDropped = 0;
    private int $errors = 0;
    private int $retries = 0;

    public function __construct(
        private readonly TransportInterface $logTransport,
        private readonly TransportInterface $spanTransport,
        Options $options,
    ) {
        $this->batchSize = $options->getBatchSize();
        $this->maxBufferSize = $options->getMaxBufferSize();
        $this->maxRetries = $options->getMaxRetries();
        $this->retryDelayMs = $options->getRetryDelayMs();
        $this->debug = $options->isDebug();

        $this->circuitBreaker = new CircuitBreaker(
            $options->getCircuitBreakerThreshold(),
            $options->getCircuitBreakerResetMs(),
        );
    }

    public function sendLogs(array $events): void
    {
        foreach ($events as $event) {
            if (count($this->logBuffer) >= $this->maxBufferSize) {
                $this->logsDropped++;
                $this->debugLog("Buffer full, dropping log");
                continue;
            }
            $this->logBuffer[] = $event;
        }

        if (count($this->logBuffer) >= $this->batchSize) {
            $this->flushLogs();
        }
    }

    public function sendSpans(array $spans): void
    {
        foreach ($spans as $span) {
            if (count($this->spanBuffer) >= $this->maxBufferSize) {
                break;
            }
            $this->spanBuffer[] = $span;
        }

        if (count($this->spanBuffer) >= $this->batchSize) {
            $this->flushSpans();
        }
    }

    public function flush(): void
    {
        $this->flushLogs();
        $this->flushSpans();
    }

    public function close(): void
    {
        $this->flush();
    }

    public function getMetrics(): array
    {
        return [
            'logs_sent' => $this->logsSent,
            'logs_dropped' => $this->logsDropped,
            'errors' => $this->errors,
            'retries' => $this->retries,
            'buffer_size' => count($this->logBuffer),
            'circuit_breaker_state' => $this->circuitBreaker->getState()->value,
        ];
    }

    public function getCircuitBreaker(): CircuitBreaker
    {
        return $this->circuitBreaker;
    }

    private function flushLogs(): void
    {
        if (empty($this->logBuffer)) {
            return;
        }

        if (!$this->circuitBreaker->canAttempt()) {
            $this->debugLog("Circuit breaker OPEN, skipping log flush");
            return;
        }

        $logs = $this->logBuffer;
        $this->logBuffer = [];

        if ($this->sendWithRetry(fn() => $this->logTransport->sendLogs($logs))) {
            $this->logsSent += count($logs);
        } else {
            $this->reBuffer($logs);
        }
    }

    private function flushSpans(): void
    {
        if (empty($this->spanBuffer)) {
            return;
        }

        if (!$this->circuitBreaker->canAttempt()) {
            $this->debugLog("Circuit breaker OPEN, skipping span flush");
            return;
        }

        $spans = $this->spanBuffer;
        $this->spanBuffer = [];

        $this->sendWithRetry(fn() => $this->spanTransport->sendSpans($spans));
    }

    private function sendWithRetry(callable $send): bool
    {
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $send();
                $this->circuitBreaker->recordSuccess();
                return true;
            } catch (\Throwable $e) {
                $this->errors++;

                if ($attempt < $this->maxRetries) {
                    $this->retries++;
                    $delay = $this->retryDelayMs * (2 ** $attempt);
                    $this->debugLog("Retry " . ($attempt + 1) . "/{$this->maxRetries} after {$delay}ms: {$e->getMessage()}");
                    usleep($delay * 1000);
                }
            }
        }

        $this->circuitBreaker->recordFailure();
        return false;
    }

    /** @param Event[] $logs */
    private function reBuffer(array $logs): void
    {
        if (count($this->logBuffer) + count($logs) <= $this->maxBufferSize) {
            $this->logBuffer = array_merge($logs, $this->logBuffer);
        } else {
            $this->logsDropped += count($logs);
            $this->debugLog("Failed to re-buffer logs, buffer full");
        }
    }

    private function debugLog(string $message): void
    {
        if ($this->debug) {
            error_log("[LogTide] {$message}");
        }
    }
}
