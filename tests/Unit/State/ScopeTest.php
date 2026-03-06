<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\State;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\Enum\BreadcrumbType;
use LogTide\Enum\LogLevel;
use LogTide\Event;
use LogTide\State\Scope;
use LogTide\Tracing\PropagationContext;
use LogTide\Tracing\Span;
use PHPUnit\Framework\TestCase;

final class ScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(Scope::class, 'globalEventProcessors');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    public function testSetAndGetTag(): void
    {
        $scope = new Scope();
        $scope->setTag('env', 'prod');

        $this->assertSame(['env' => 'prod'], $scope->getTags());
    }

    public function testSetTagsMerges(): void
    {
        $scope = new Scope();
        $scope->setTag('a', '1');
        $scope->setTags(['b' => '2', 'c' => '3']);

        $this->assertSame(['a' => '1', 'b' => '2', 'c' => '3'], $scope->getTags());
    }

    public function testRemoveTag(): void
    {
        $scope = new Scope();
        $scope->setTags(['a' => '1', 'b' => '2']);
        $scope->removeTag('a');

        $this->assertSame(['b' => '2'], $scope->getTags());
    }

    public function testSetAndGetExtra(): void
    {
        $scope = new Scope();
        $scope->setExtra('debug', true);

        $this->assertSame(['debug' => true], $scope->getExtras());
    }

    public function testSetExtrasMerges(): void
    {
        $scope = new Scope();
        $scope->setExtra('a', 1);
        $scope->setExtras(['b' => 2]);

        $this->assertSame(['a' => 1, 'b' => 2], $scope->getExtras());
    }

    public function testSetAndGetUser(): void
    {
        $scope = new Scope();
        $scope->setUser(['id' => '42', 'email' => 'test@example.com']);

        $this->assertSame(['id' => '42', 'email' => 'test@example.com'], $scope->getUser());
    }

    public function testSetAndGetLevel(): void
    {
        $scope = new Scope();
        $this->assertNull($scope->getLevel());

        $scope->setLevel(LogLevel::WARN);
        $this->assertSame(LogLevel::WARN, $scope->getLevel());

        $scope->setLevel(null);
        $this->assertNull($scope->getLevel());
    }

    public function testSetAndGetSpan(): void
    {
        $scope = new Scope();
        $this->assertNull($scope->getSpan());

        $span = new Span('test.op');
        $scope->setSpan($span);
        $this->assertSame($span, $scope->getSpan());
    }

    public function testBreadcrumbs(): void
    {
        $scope = new Scope(5);
        $scope->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'GET /'));
        $scope->addBreadcrumb(new Breadcrumb(BreadcrumbType::QUERY, 'SELECT 1'));

        $this->assertSame(2, $scope->getBreadcrumbs()->count());
    }

    public function testClearBreadcrumbs(): void
    {
        $scope = new Scope();
        $scope->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'GET /'));
        $scope->clearBreadcrumbs();

        $this->assertSame(0, $scope->getBreadcrumbs()->count());
    }

    public function testPropagationContext(): void
    {
        $scope = new Scope();
        $ctx = $scope->getPropagationContext();

        $this->assertNotEmpty($ctx->getTraceId());
        $this->assertNotEmpty($ctx->getSpanId());
    }

    public function testSetPropagationContext(): void
    {
        $scope = new Scope();
        $ctx = PropagationContext::fromTraceparent('00-' . str_repeat('a', 32) . '-' . str_repeat('b', 16) . '-01');

        $scope->setPropagationContext($ctx);
        $this->assertSame($ctx, $scope->getPropagationContext());
    }

    public function testApplyToEventSetsTags(): void
    {
        $scope = new Scope();
        $scope->setTags(['env' => 'prod', 'region' => 'eu']);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertSame(['env' => 'prod', 'region' => 'eu'], $result->getTags());
    }

    public function testApplyToEventMergesTagsWithExisting(): void
    {
        $scope = new Scope();
        $scope->setTag('scope_tag', 'yes');

        $event = Event::createLog(LogLevel::INFO, 'test');
        $event->setTags(['event_tag' => 'yes']);

        $result = $scope->applyToEvent($event);

        $this->assertSame('yes', $result->getTags()['scope_tag']);
        $this->assertSame('yes', $result->getTags()['event_tag']);
    }

    public function testApplyToEventSetsExtras(): void
    {
        $scope = new Scope();
        $scope->setExtras(['debug_info' => 'abc']);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertSame(['debug_info' => 'abc'], $result->getExtras());
    }

    public function testApplyToEventSetsUserInMetadata(): void
    {
        $scope = new Scope();
        $scope->setUser(['id' => '42']);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertSame(['id' => '42'], $result->getMetadata()['user']);
    }

    public function testApplyToEventOverridesLevel(): void
    {
        $scope = new Scope();
        $scope->setLevel(LogLevel::CRITICAL);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertSame(LogLevel::CRITICAL, $result->getLevel());
    }

    public function testApplyToEventDoesNotOverrideLevelWhenNull(): void
    {
        $scope = new Scope();

        $event = Event::createLog(LogLevel::WARN, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertSame(LogLevel::WARN, $result->getLevel());
    }

    public function testApplyToEventSetsTraceIdFromPropagationContext(): void
    {
        $scope = new Scope();

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertNotNull($result->getTraceId());
        $this->assertSame($scope->getPropagationContext()->getTraceId(), $result->getTraceId());
    }

    public function testApplyToEventSetsTraceIdFromSpan(): void
    {
        $scope = new Scope();
        $span = new Span('test.op');
        $scope->setSpan($span);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertSame($span->getTraceId(), $result->getTraceId());
    }

    public function testApplyToEventDoesNotOverrideExistingTraceId(): void
    {
        $scope = new Scope();

        $event = Event::createLog(LogLevel::INFO, 'test');
        $event->setTraceId('existing-trace');
        $result = $scope->applyToEvent($event);

        $this->assertSame('existing-trace', $result->getTraceId());
    }

    public function testApplyToEventSetsBreadcrumbs(): void
    {
        $scope = new Scope();
        $scope->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'GET /'));

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertCount(1, $result->getBreadcrumbs());
    }

    public function testEventProcessorCanModifyEvent(): void
    {
        $scope = new Scope();
        $scope->addEventProcessor(function (Event $event): Event {
            $event->setMessage('modified');
            return $event;
        });

        $event = Event::createLog(LogLevel::INFO, 'original');
        $result = $scope->applyToEvent($event);

        $this->assertSame('modified', $result->getMessage());
    }

    public function testEventProcessorCanDropEvent(): void
    {
        $scope = new Scope();
        $scope->addEventProcessor(function (Event $event): ?Event {
            return null;
        });

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertNull($result);
    }

    public function testGlobalEventProcessor(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event): Event {
            $event->addMetadata('global', true);
            return $event;
        });

        $scope = new Scope();
        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $scope->applyToEvent($event);

        $this->assertTrue($result->getMetadata()['global']);
    }

    public function testGlobalEventProcessorCanDropEvent(): void
    {
        Scope::addGlobalEventProcessor(fn() => null);

        $scope = new Scope();
        $event = Event::createLog(LogLevel::INFO, 'test');

        $this->assertNull($scope->applyToEvent($event));
    }

    public function testClearResets(): void
    {
        $scope = new Scope();
        $scope->setTags(['a' => '1']);
        $scope->setExtras(['b' => 2]);
        $scope->setUser(['id' => '42']);
        $scope->setLevel(LogLevel::ERROR);
        $scope->setSpan(new Span('test'));
        $scope->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'GET /'));
        $scope->addEventProcessor(fn($e) => $e);

        $oldCtx = $scope->getPropagationContext();
        $scope->clear();

        $this->assertEmpty($scope->getTags());
        $this->assertEmpty($scope->getExtras());
        $this->assertEmpty($scope->getUser());
        $this->assertNull($scope->getLevel());
        $this->assertNull($scope->getSpan());
        $this->assertSame(0, $scope->getBreadcrumbs()->count());
        $this->assertNotSame($oldCtx, $scope->getPropagationContext());
    }

    public function testCloneIsIndependent(): void
    {
        $scope = new Scope();
        $scope->setTag('env', 'prod');
        $scope->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'original'));

        $clone = $scope->clone();
        $clone->setTag('env', 'staging');
        $clone->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'cloned'));

        $this->assertSame('prod', $scope->getTags()['env']);
        $this->assertSame(1, $scope->getBreadcrumbs()->count());
        $this->assertSame('staging', $clone->getTags()['env']);
        $this->assertSame(2, $clone->getBreadcrumbs()->count());
    }

    public function testCloneSharesPropagationContext(): void
    {
        $scope = new Scope();
        $clone = $scope->clone();

        $this->assertSame(
            $scope->getPropagationContext()->getTraceId(),
            $clone->getPropagationContext()->getTraceId(),
        );
    }

    public function testMultipleEventProcessorsRunInOrder(): void
    {
        $scope = new Scope();
        $scope->addEventProcessor(function (Event $event): Event {
            $event->setMessage($event->getMessage() . ' A');
            return $event;
        });
        $scope->addEventProcessor(function (Event $event): Event {
            $event->setMessage($event->getMessage() . ' B');
            return $event;
        });

        $event = Event::createLog(LogLevel::INFO, 'start');
        $result = $scope->applyToEvent($event);

        $this->assertSame('start A B', $result->getMessage());
    }

    public function testEventProcessorDropStopsChain(): void
    {
        $called = false;
        $scope = new Scope();
        $scope->addEventProcessor(fn() => null);
        $scope->addEventProcessor(function () use (&$called) {
            $called = true;
            return null;
        });

        $event = Event::createLog(LogLevel::INFO, 'test');
        $scope->applyToEvent($event);

        $this->assertFalse($called);
    }
}
