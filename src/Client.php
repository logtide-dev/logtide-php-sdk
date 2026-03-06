<?php

declare(strict_types=1);

namespace LogTide;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\Enum\BreadcrumbType;
use LogTide\Enum\LogLevel;
use LogTide\Enum\SpanKind;
use LogTide\Enum\SpanStatus;
use LogTide\Integration\IntegrationRegistry;
use LogTide\Serializer\ErrorSerializer;
use LogTide\State\Scope;
use LogTide\Tracing\Span;
use LogTide\Transport\TransportInterface;

final class Client implements ClientInterface
{
    private readonly TransportInterface $transport;
    private readonly IntegrationRegistry $integrationRegistry;
    private bool $closed = false;

    public function __construct(
        private readonly Options $options,
        TransportInterface $transport,
    ) {
        $this->transport = $transport;
        $this->integrationRegistry = IntegrationRegistry::getInstance();
        $this->integrationRegistry->setupIntegrations($options);
    }

    public function captureEvent(Event $event, ?Scope $scope = null, ?EventHint $hint = null): ?string
    {
        if ($this->closed) {
            return null;
        }

        $event = $this->prepareEvent($event, $scope, $hint);
        if ($event === null) {
            return null;
        }

        $this->transport->sendLogs([$event]);
        return $event->getId();
    }

    public function captureException(\Throwable $exception, ?Scope $scope = null): ?string
    {
        foreach ($this->options->getIgnoreExceptions() as $pattern) {
            if ($exception instanceof $pattern) {
                return null;
            }
            if (str_starts_with($pattern, '/') && @preg_match($pattern, get_class($exception))) {
                return null;
            }
        }

        $event = Event::createError($exception);
        $event->setException(ErrorSerializer::serialize($exception));

        $scope?->addBreadcrumb(new Breadcrumb(
            BreadcrumbType::ERROR,
            $exception->getMessage(),
            category: get_class($exception),
            level: LogLevel::ERROR,
        ));

        return $this->captureEvent($event, $scope, EventHint::fromException($exception));
    }

    public function captureLog(
        LogLevel $level,
        string $message,
        array $metadata = [],
        ?Scope $scope = null,
        ?string $service = null,
    ): ?string {
        $event = Event::createLog($level, $message, $service);
        $event->setMetadata($metadata);
        return $this->captureEvent($event, $scope);
    }

    public function startSpan(string $operation, array $options = []): Span
    {
        return new Span(
            operation: $operation,
            kind: $options['kind'] ?? SpanKind::INTERNAL,
            parentSpanId: $options['parent_span_id'] ?? null,
            traceId: $options['trace_id'] ?? null,
            serviceName: $options['service'] ?? null,
        );
    }

    public function finishSpan(Span $span): void
    {
        if (!$span->isFinished()) {
            $span->finish();
        }

        if ($this->shouldSampleTrace()) {
            $this->transport->sendSpans([$span]);
        }
    }

    public function flush(): void
    {
        $this->transport->flush();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->transport->close();
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    private function prepareEvent(Event $event, ?Scope $scope, ?EventHint $hint): ?Event
    {
        $event->setService($event->getService() ?: $this->options->getService());

        if ($this->options->getEnvironment() !== null) {
            $event->setEnvironment($this->options->getEnvironment());
        }
        if ($this->options->getRelease() !== null) {
            $event->setRelease($this->options->getRelease());
        }

        $globalMeta = $this->options->getGlobalMetadata();
        if (!empty($globalMeta)) {
            $event->setMetadata(array_merge($globalMeta, $event->getMetadata()));
        }

        $globalTags = $this->options->getTags();
        if (!empty($globalTags)) {
            $event->setTags(array_merge($globalTags, $event->getTags()));
        }

        if ($this->options->shouldAttachStacktrace() && $event->getException() === null && $event->getStacktrace() === null) {
            $event->setStacktrace(ErrorSerializer::parseStacktrace(new \Exception()));
        }

        if ($scope !== null) {
            $event = $scope->applyToEvent($event);
            if ($event === null) {
                return null;
            }
        }

        $beforeSend = $this->options->getBeforeSend();
        if ($beforeSend !== null) {
            $event = $beforeSend($event, $hint);
            if ($event === null) {
                return null;
            }
        }

        return $event;
    }

    private function shouldSampleTrace(): bool
    {
        $rate = $this->options->getTracesSampleRate();
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }
        return (mt_rand() / mt_getrandmax()) < $rate;
    }
}
