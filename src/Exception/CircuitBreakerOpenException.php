<?php

declare(strict_types=1);

namespace LogTide\Exception;

class CircuitBreakerOpenException extends LogtideException
{
    public function __construct()
    {
        parent::__construct('Circuit breaker is open. Transport is temporarily unavailable.');
    }
}
