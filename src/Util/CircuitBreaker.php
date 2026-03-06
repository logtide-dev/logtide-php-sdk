<?php

declare(strict_types=1);

namespace LogTide\Util;

use LogTide\Enum\CircuitState;

final class CircuitBreaker
{
    private CircuitState $state = CircuitState::CLOSED;
    private int $failureCount = 0;
    private float $lastFailureTime = 0;

    public function __construct(
        private readonly int $threshold = 5,
        private readonly int $resetMs = 30000,
    ) {
    }

    public function canAttempt(): bool
    {
        if ($this->state === CircuitState::CLOSED) {
            return true;
        }

        if ($this->state === CircuitState::OPEN) {
            $elapsed = (microtime(true) - $this->lastFailureTime) * 1000;
            if ($elapsed >= $this->resetMs) {
                $this->state = CircuitState::HALF_OPEN;
                return true;
            }
            return false;
        }

        return true; // HALF_OPEN
    }

    public function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = CircuitState::CLOSED;
    }

    public function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);

        if ($this->failureCount >= $this->threshold) {
            $this->state = CircuitState::OPEN;
        }
    }

    public function getState(): CircuitState
    {
        return $this->state;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }
}
