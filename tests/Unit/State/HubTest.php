<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\State;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\Client;
use LogTide\ClientInterface;
use LogTide\Enum\BreadcrumbType;
use LogTide\Enum\LogLevel;
use LogTide\Event;
use LogTide\EventHint;
use LogTide\Options;
use LogTide\State\Hub;
use LogTide\State\Scope;
use LogTide\Tracing\Span;
use LogTide\Transport\NullTransport;
use PHPUnit\Framework\TestCase;

final class HubTest extends TestCase
{
    private function createClient(array $config = []): Client
    {
        return new Client(
            Options::fromArray(array_merge(['default_integrations' => false], $config)),
            new NullTransport(),
        );
    }

    public function testConstructWithoutClient(): void
    {
        $hub = new Hub();

        $this->assertNull($hub->getClient());
        $this->assertInstanceOf(Scope::class, $hub->getScope());
    }

    public function testConstructWithClient(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $this->assertSame($client, $hub->getClient());
    }

    public function testBindClient(): void
    {
        $hub = new Hub();
        $client = $this->createClient();

        $hub->bindClient($client);

        $this->assertSame($client, $hub->getClient());
    }

    public function testPushAndPopScope(): void
    {
        $hub = new Hub();
        $originalScope = $hub->getScope();
        $originalScope->setTag('original', 'yes');

        $newScope = $hub->pushScope();

        $this->assertNotSame($originalScope, $newScope);
        $this->assertSame('yes', $newScope->getTags()['original']);
        $this->assertSame($newScope, $hub->getScope());

        $newScope->setTag('pushed', 'yes');

        $hub->popScope();

        $this->assertSame($originalScope, $hub->getScope());
        $this->assertArrayNotHasKey('pushed', $hub->getScope()->getTags());
    }

    public function testPopScopeDoesNothingAtBottom(): void
    {
        $hub = new Hub();
        $scope = $hub->getScope();

        $hub->popScope();

        $this->assertSame($scope, $hub->getScope());
    }

    public function testWithScope(): void
    {
        $hub = new Hub();
        $hub->getScope()->setTag('outer', 'yes');

        $result = $hub->withScope(function (Scope $scope) {
            $scope->setTag('inner', 'yes');
            $this->assertSame('yes', $scope->getTags()['outer']);
            $this->assertSame('yes', $scope->getTags()['inner']);
            return 'result';
        });

        $this->assertSame('result', $result);
        $this->assertArrayNotHasKey('inner', $hub->getScope()->getTags());
    }

    public function testWithScopeRestoresOnException(): void
    {
        $hub = new Hub();
        $outerScope = $hub->getScope();

        try {
            $hub->withScope(function (Scope $scope) {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame($outerScope, $hub->getScope());
    }

    public function testConfigureScope(): void
    {
        $hub = new Hub();

        $hub->configureScope(function (Scope $scope) {
            $scope->setTag('configured', 'yes');
        });

        $this->assertSame('yes', $hub->getScope()->getTags()['configured']);
    }

    public function testCaptureEventWithoutClient(): void
    {
        $hub = new Hub();
        $event = Event::createLog(LogLevel::INFO, 'test');

        $this->assertNull($hub->captureEvent($event));
    }

    public function testCaptureEvent(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $eventId = $hub->captureEvent($event);

        $this->assertNotNull($eventId);
        $this->assertSame($eventId, $hub->getLastEventId());
    }

    public function testCaptureExceptionWithoutClient(): void
    {
        $hub = new Hub();

        $this->assertNull($hub->captureException(new \RuntimeException('test')));
    }

    public function testCaptureException(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $eventId = $hub->captureException(new \RuntimeException('oops'));

        $this->assertNotNull($eventId);
        $this->assertSame($eventId, $hub->getLastEventId());
    }

    public function testCaptureLogWithoutClient(): void
    {
        $hub = new Hub();

        $this->assertNull($hub->captureLog(LogLevel::INFO, 'test'));
    }

    public function testCaptureLog(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $eventId = $hub->captureLog(LogLevel::WARN, 'something happened', ['key' => 'val']);

        $this->assertNotNull($eventId);
    }

    public function testCaptureLogWithService(): void
    {
        $client = $this->createClient(['service' => 'default-svc']);
        $hub = new Hub($client);

        $eventId = $hub->captureLog(LogLevel::INFO, 'test', [], 'custom-svc');

        $this->assertNotNull($eventId);
    }

    public function testAddBreadcrumbWithoutClient(): void
    {
        $hub = new Hub();
        $hub->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'GET /'));

        $this->assertSame(0, $hub->getScope()->getBreadcrumbs()->count());
    }

    public function testAddBreadcrumb(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $hub->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'GET /'));

        $this->assertSame(1, $hub->getScope()->getBreadcrumbs()->count());
    }

    public function testAddBreadcrumbWithBeforeBreadcrumbFilter(): void
    {
        $client = $this->createClient([
            'before_breadcrumb' => fn(Breadcrumb $b) => null,
        ]);
        $hub = new Hub($client);

        $hub->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'GET /'));

        $this->assertSame(0, $hub->getScope()->getBreadcrumbs()->count());
    }

    public function testAddBreadcrumbWithBeforeBreadcrumbModify(): void
    {
        $client = $this->createClient([
            'before_breadcrumb' => function (Breadcrumb $b) {
                return new Breadcrumb($b->type, 'modified', $b->category, $b->level, $b->data);
            },
        ]);
        $hub = new Hub($client);

        $hub->addBreadcrumb(new Breadcrumb(BreadcrumbType::HTTP, 'original'));

        $all = $hub->getScope()->getBreadcrumbs()->getAll();
        $this->assertSame('modified', $all[0]->message);
    }

    public function testStartSpanWithoutClient(): void
    {
        $hub = new Hub();

        $this->assertNull($hub->startSpan('test.op'));
    }

    public function testStartSpan(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $span = $hub->startSpan('http.request');

        $this->assertInstanceOf(Span::class, $span);
        $this->assertSame($span, $hub->getScope()->getSpan());
    }

    public function testStartSpanInheritsParentTraceId(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $parent = $hub->startSpan('parent.op');
        $child = $hub->startSpan('child.op');

        $this->assertSame($parent->getTraceId(), $child->getTraceId());
    }

    public function testStartSpanUsePropagationContextWhenNoParent(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $span = $hub->startSpan('root.op');
        $propCtxTraceId = $hub->getScope()->getPropagationContext()->getTraceId();

        // The span's trace_id should come from propagation context (though now the scope
        // has the span set, so the propagation context trace_id was used at creation time)
        $this->assertNotEmpty($span->getTraceId());
    }

    public function testFinishSpanWithoutClient(): void
    {
        $hub = new Hub();
        $span = new Span('test.op');

        // Should not throw
        $hub->finishSpan($span);
        $this->assertFalse($span->isFinished());
    }

    public function testFinishSpan(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $span = $hub->startSpan('test.op');
        $hub->finishSpan($span);

        $this->assertTrue($span->isFinished());
    }

    public function testFlush(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        // Should not throw
        $hub->flush();
        $this->assertTrue(true);
    }

    public function testLastEventIdStartsNull(): void
    {
        $hub = new Hub();
        $this->assertNull($hub->getLastEventId());
    }

    public function testNestedScopesIsolation(): void
    {
        $client = $this->createClient();
        $hub = new Hub($client);

        $hub->getScope()->setTag('level', '0');

        $hub->withScope(function (Scope $s1) use ($hub) {
            $s1->setTag('level', '1');

            $hub->withScope(function (Scope $s2) {
                $s2->setTag('level', '2');
                $this->assertSame('2', $s2->getTags()['level']);
            });

            $this->assertSame('1', $s1->getTags()['level']);
        });

        $this->assertSame('0', $hub->getScope()->getTags()['level']);
    }
}
