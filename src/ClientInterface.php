<?php

declare(strict_types=1);

namespace LogTide;

use LogTide\Enum\LogLevel;
use LogTide\State\Scope;
use LogTide\Tracing\Span;

interface ClientInterface
{
    public function captureEvent(Event $event, ?Scope $scope = null, ?EventHint $hint = null): ?string;

    public function captureException(\Throwable $exception, ?Scope $scope = null): ?string;

    public function captureLog(LogLevel $level, string $message, array $metadata = [], ?Scope $scope = null): ?string;

    public function startSpan(string $operation, array $options = []): Span;

    public function finishSpan(Span $span): void;

    public function flush(): void;

    public function close(): void;

    public function getOptions(): Options;
}
