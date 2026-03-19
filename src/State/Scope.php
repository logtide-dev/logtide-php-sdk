<?php

declare(strict_types=1);

namespace LogTide\State;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\Breadcrumb\BreadcrumbBuffer;
use LogTide\Enum\LogLevel;
use LogTide\Event;
use LogTide\Tracing\PropagationContext;
use LogTide\Tracing\Span;

final class Scope
{
    private array $tags = [];
    private array $extras = [];
    private array $user = [];
    private ?LogLevel $level = null;
    private ?Span $span = null;
    private BreadcrumbBuffer $breadcrumbs;
    private PropagationContext $propagationContext;

    /** @var array<callable(Event): ?Event> */
    private array $eventProcessors = [];

    /** @var array<callable(Event): ?Event> */
    private static array $globalEventProcessors = [];

    public function __construct(int $maxBreadcrumbs = 100)
    {
        $this->breadcrumbs = new BreadcrumbBuffer($maxBreadcrumbs);
        $this->propagationContext = PropagationContext::create();
    }

    public function setTag(string $key, string $value): void
    {
        $this->tags[$key] = $value;
    }

    public function setTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }

    public function removeTag(string $key): void
    {
        unset($this->tags[$key]);
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setExtra(string $key, mixed $value): void
    {
        $this->extras[$key] = $value;
    }

    public function setExtras(array $extras): void
    {
        $this->extras = array_merge($this->extras, $extras);
    }

    public function getExtras(): array
    {
        return $this->extras;
    }

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function getUser(): array
    {
        return $this->user;
    }

    public function setLevel(?LogLevel $level): void
    {
        $this->level = $level;
    }

    public function getLevel(): ?LogLevel
    {
        return $this->level;
    }

    public function setSpan(?Span $span): void
    {
        $this->span = $span;
    }

    public function getSpan(): ?Span
    {
        return $this->span;
    }

    public function addBreadcrumb(Breadcrumb $breadcrumb): void
    {
        $this->breadcrumbs->add($breadcrumb);
    }

    public function getBreadcrumbs(): BreadcrumbBuffer
    {
        return $this->breadcrumbs;
    }

    public function clearBreadcrumbs(): void
    {
        $this->breadcrumbs->clear();
    }

    public function setPropagationContext(PropagationContext $context): void
    {
        $this->propagationContext = $context;
    }

    public function getPropagationContext(): PropagationContext
    {
        return $this->propagationContext;
    }

    /** @param callable(Event): ?Event $processor */
    public function addEventProcessor(callable $processor): void
    {
        $this->eventProcessors[] = $processor;
    }

    /** @param callable(Event): ?Event $processor */
    public static function addGlobalEventProcessor(callable $processor): void
    {
        self::$globalEventProcessors[] = $processor;
    }

    public function applyToEvent(Event $event): ?Event
    {
        if (!empty($this->tags)) {
            $event->setTags(array_merge($event->getTags(), $this->tags));
        }

        if (!empty($this->extras)) {
            $event->setExtras(array_merge($event->getExtras(), $this->extras));
        }

        if (!empty($this->user)) {
            $event->addMetadata('user', $this->user);
        }

        if ($this->level !== null) {
            $event->setLevel($this->level);
        }

        $traceId = $this->span?->getTraceId() ?? $this->propagationContext->getTraceId();
        if ($event->getTraceId() === null) {
            $event->setTraceId($traceId);
        }

        $spanId = $this->span?->getSpanId() ?? $this->propagationContext->getSpanId();
        if ($event->getSpanId() === null) {
            $event->setSpanId($spanId);
        }

        if ($this->breadcrumbs->count() > 0) {
            $event->setBreadcrumbs($this->breadcrumbs->toArray());
        }

        foreach ($this->eventProcessors as $processor) {
            $event = $processor($event);
            if ($event === null) {
                return null;
            }
        }

        foreach (self::$globalEventProcessors as $processor) {
            $event = $processor($event);
            if ($event === null) {
                return null;
            }
        }

        return $event;
    }

    public function clear(): void
    {
        $this->tags = [];
        $this->extras = [];
        $this->user = [];
        $this->level = null;
        $this->span = null;
        $this->breadcrumbs->clear();
        $this->eventProcessors = [];
        $this->propagationContext = PropagationContext::create();
    }

    public function clone(): self
    {
        $clone = new self();
        $clone->tags = $this->tags;
        $clone->extras = $this->extras;
        $clone->user = $this->user;
        $clone->level = $this->level;
        $clone->span = $this->span;
        $clone->breadcrumbs = $this->breadcrumbs->clone();
        $clone->propagationContext = $this->propagationContext;
        $clone->eventProcessors = $this->eventProcessors;
        return $clone;
    }
}
