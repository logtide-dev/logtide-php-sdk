<?php

declare(strict_types=1);

namespace LogTide\State;

use LogTide\ClientInterface;
use LogTide\Enum\LogLevel;
use LogTide\Event;
use LogTide\EventHint;
use LogTide\Tracing\Span;

interface HubInterface
{
    public function getClient(): ?ClientInterface;

    public function captureEvent(Event $event, ?EventHint $hint = null): ?string;

    public function captureException(\Throwable $exception): ?string;

    public function captureLog(LogLevel $level, string $message, array $metadata = [], ?string $service = null): ?string;

    public function addBreadcrumb(\LogTide\Breadcrumb\Breadcrumb $breadcrumb): void;

    public function pushScope(): Scope;

    public function popScope(): void;

    public function withScope(callable $callback): mixed;

    public function configureScope(callable $callback): void;

    public function getScope(): Scope;

    public function startSpan(string $operation, array $options = []): ?Span;

    public function finishSpan(Span $span): void;

    public function flush(): void;
}
