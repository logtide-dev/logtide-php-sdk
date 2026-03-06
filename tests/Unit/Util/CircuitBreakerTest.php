<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Util;

use LogTide\Enum\CircuitState;
use LogTide\Util\CircuitBreaker;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    public function testStartsClosed(): void
    {
        $cb = new CircuitBreaker(3, 1000);

        $this->assertSame(CircuitState::CLOSED, $cb->getState());
        $this->assertTrue($cb->canAttempt());
        $this->assertSame(0, $cb->getFailureCount());
    }

    public function testOpensAfterThreshold(): void
    {
        $cb = new CircuitBreaker(3, 1000);

        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame(CircuitState::CLOSED, $cb->getState());

        $cb->recordFailure();
        $this->assertSame(CircuitState::OPEN, $cb->getState());
        $this->assertFalse($cb->canAttempt());
    }

    public function testSuccessResetsToClosed(): void
    {
        $cb = new CircuitBreaker(2, 1000);

        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame(CircuitState::OPEN, $cb->getState());

        // Simulate time passing... manually set state for test
        // In real code, canAttempt() would transition to HALF_OPEN
        $cb->recordSuccess();

        $this->assertSame(CircuitState::CLOSED, $cb->getState());
        $this->assertSame(0, $cb->getFailureCount());
        $this->assertTrue($cb->canAttempt());
    }

    public function testTransitionsToHalfOpenAfterResetTime(): void
    {
        // Use a very short reset time
        $cb = new CircuitBreaker(1, 1);

        $cb->recordFailure();
        $this->assertSame(CircuitState::OPEN, $cb->getState());

        // Wait for reset
        usleep(2000); // 2ms, reset is 1ms

        $this->assertTrue($cb->canAttempt());
        $this->assertSame(CircuitState::HALF_OPEN, $cb->getState());
    }

    public function testHalfOpenSuccessCloses(): void
    {
        $cb = new CircuitBreaker(1, 1);

        $cb->recordFailure();
        usleep(2000);
        $cb->canAttempt(); // transitions to HALF_OPEN

        $cb->recordSuccess();
        $this->assertSame(CircuitState::CLOSED, $cb->getState());
    }

    public function testHalfOpenFailureReopens(): void
    {
        $cb = new CircuitBreaker(1, 1);

        $cb->recordFailure();
        usleep(2000);
        $cb->canAttempt(); // HALF_OPEN

        $cb->recordFailure();
        $this->assertSame(CircuitState::OPEN, $cb->getState());
    }

    public function testFailuresBelowThresholdStayClosed(): void
    {
        $cb = new CircuitBreaker(5, 1000);

        for ($i = 0; $i < 4; $i++) {
            $cb->recordFailure();
        }

        $this->assertSame(CircuitState::CLOSED, $cb->getState());
        $this->assertTrue($cb->canAttempt());
    }
}
