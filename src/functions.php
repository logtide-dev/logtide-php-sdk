<?php

declare(strict_types=1);

namespace LogTide;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\Enum\LogLevel;
use LogTide\State\HubInterface;
use LogTide\State\Scope;
use LogTide\Tracing\PropagationContext;
use LogTide\Tracing\Span;

function init(array $config): HubInterface
{
    return LogtideSdk::init($config);
}

function captureException(\Throwable $exception): ?string
{
    return LogtideSdk::getCurrentHub()->captureException($exception);
}

function captureEvent(Event $event, ?EventHint $hint = null): ?string
{
    return LogtideSdk::getCurrentHub()->captureEvent($event, $hint);
}

function captureLog(LogLevel $level, string $message, array $metadata = [], ?string $service = null): ?string
{
    return LogtideSdk::getCurrentHub()->captureLog($level, $message, $metadata, $service);
}

function debug(string $message, array $metadata = [], ?string $service = null): ?string
{
    return captureLog(LogLevel::DEBUG, $message, $metadata, $service);
}

function info(string $message, array $metadata = [], ?string $service = null): ?string
{
    return captureLog(LogLevel::INFO, $message, $metadata, $service);
}

function warn(string $message, array $metadata = [], ?string $service = null): ?string
{
    return captureLog(LogLevel::WARN, $message, $metadata, $service);
}

function error(string $message, array $metadata = [], ?string $service = null): ?string
{
    return captureLog(LogLevel::ERROR, $message, $metadata, $service);
}

function critical(string $message, array $metadata = [], ?string $service = null): ?string
{
    return captureLog(LogLevel::CRITICAL, $message, $metadata, $service);
}

function addBreadcrumb(Breadcrumb $breadcrumb): void
{
    LogtideSdk::getCurrentHub()->addBreadcrumb($breadcrumb);
}

function withScope(callable $callback): mixed
{
    return LogtideSdk::getCurrentHub()->withScope($callback);
}

function configureScope(callable $callback): void
{
    LogtideSdk::getCurrentHub()->configureScope($callback);
}

function startSpan(string $operation, array $options = []): ?Span
{
    return LogtideSdk::getCurrentHub()->startSpan($operation, $options);
}

function finishSpan(Span $span): void
{
    LogtideSdk::getCurrentHub()->finishSpan($span);
}

function flush(): void
{
    LogtideSdk::getCurrentHub()->flush();
}

function getTraceparent(): string
{
    $scope = LogtideSdk::getCurrentHub()->getScope();
    $span = $scope->getSpan();

    if ($span !== null) {
        $flags = '01';
        return "00-{$span->getTraceId()}-{$span->getSpanId()}-{$flags}";
    }

    return $scope->getPropagationContext()->toTraceparent();
}

function continueTrace(string $traceparent): void
{
    $context = PropagationContext::fromTraceparent($traceparent);
    if ($context !== null) {
        LogtideSdk::getCurrentHub()->getScope()->setPropagationContext($context);
    }
}
