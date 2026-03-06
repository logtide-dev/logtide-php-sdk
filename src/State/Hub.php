<?php

declare(strict_types=1);

namespace LogTide\State;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\ClientInterface;
use LogTide\Enum\LogLevel;
use LogTide\Event;
use LogTide\EventHint;
use LogTide\Tracing\Span;

final class Hub implements HubInterface
{
    /** @var array<array{client: ?ClientInterface, scope: Scope}> */
    private array $stack = [];

    private ?string $lastEventId = null;

    public function __construct(?ClientInterface $client = null, ?Scope $scope = null)
    {
        $this->stack[] = [
            'client' => $client,
            'scope' => $scope ?? new Scope(),
        ];
    }

    public function bindClient(ClientInterface $client): void
    {
        $this->stack[array_key_last($this->stack)]['client'] = $client;
    }

    public function getClient(): ?ClientInterface
    {
        return $this->stack[array_key_last($this->stack)]['client'] ?? null;
    }

    public function getScope(): Scope
    {
        return $this->stack[array_key_last($this->stack)]['scope'];
    }

    public function pushScope(): Scope
    {
        $currentScope = $this->getScope();
        $newScope = $currentScope->clone();

        $this->stack[] = [
            'client' => $this->getClient(),
            'scope' => $newScope,
        ];

        return $newScope;
    }

    public function popScope(): void
    {
        if (count($this->stack) <= 1) {
            return;
        }
        array_pop($this->stack);
    }

    public function withScope(callable $callback): mixed
    {
        $scope = $this->pushScope();
        try {
            return $callback($scope);
        } finally {
            $this->popScope();
        }
    }

    public function configureScope(callable $callback): void
    {
        $callback($this->getScope());
    }

    public function captureEvent(Event $event, ?EventHint $hint = null): ?string
    {
        $client = $this->getClient();
        if ($client === null) {
            return null;
        }

        $eventId = $client->captureEvent($event, $this->getScope(), $hint);
        $this->lastEventId = $eventId;
        return $eventId;
    }

    public function captureException(\Throwable $exception): ?string
    {
        $client = $this->getClient();
        if ($client === null) {
            return null;
        }

        $eventId = $client->captureException($exception, $this->getScope());
        $this->lastEventId = $eventId;
        return $eventId;
    }

    public function captureLog(
        LogLevel $level,
        string $message,
        array $metadata = [],
        ?string $service = null,
    ): ?string {
        $client = $this->getClient();
        if ($client === null) {
            return null;
        }

        $eventId = $client->captureLog($level, $message, $metadata, $this->getScope(), $service);
        $this->lastEventId = $eventId;
        return $eventId;
    }

    public function addBreadcrumb(Breadcrumb $breadcrumb): void
    {
        $client = $this->getClient();
        if ($client === null) {
            return;
        }

        $beforeBreadcrumb = $client->getOptions()->getBeforeBreadcrumb();
        if ($beforeBreadcrumb !== null) {
            $breadcrumb = $beforeBreadcrumb($breadcrumb);
            if ($breadcrumb === null) {
                return;
            }
        }

        $this->getScope()->addBreadcrumb($breadcrumb);
    }

    public function startSpan(string $operation, array $options = []): ?Span
    {
        $client = $this->getClient();
        if ($client === null) {
            return null;
        }

        $scope = $this->getScope();
        $parentSpan = $scope->getSpan();

        if ($parentSpan !== null) {
            $options['trace_id'] = $options['trace_id'] ?? $parentSpan->getTraceId();
            $options['parent_span_id'] = $options['parent_span_id'] ?? $parentSpan->getSpanId();
        } else {
            $options['trace_id'] = $options['trace_id'] ?? $scope->getPropagationContext()->getTraceId();
        }

        $span = $client->startSpan($operation, $options);
        $scope->setSpan($span);
        return $span;
    }

    public function finishSpan(Span $span): void
    {
        $client = $this->getClient();
        if ($client === null) {
            return;
        }

        $client->finishSpan($span);
    }

    public function flush(): void
    {
        $client = $this->getClient();
        $client?->flush();
    }

    public function getLastEventId(): ?string
    {
        return $this->lastEventId;
    }
}
