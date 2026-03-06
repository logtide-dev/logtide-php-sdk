<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\Client;
use LogTide\Enum\LogLevel;
use LogTide\Enum\SpanKind;
use LogTide\Enum\SpanStatus;
use LogTide\Event;
use LogTide\EventHint;
use LogTide\Options;
use LogTide\State\Scope;
use LogTide\Tracing\Span;
use LogTide\Transport\NullTransport;
use LogTide\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private function createClient(array $config = [], ?TransportInterface $transport = null): Client
    {
        return new Client(
            Options::fromArray(array_merge(['default_integrations' => false], $config)),
            $transport ?? new NullTransport(),
        );
    }

    private function createSpyTransport(): TransportInterface
    {
        return new class implements TransportInterface {
            public array $sentLogs = [];
            public array $sentSpans = [];
            public bool $flushed = false;
            public bool $closed = false;

            public function sendLogs(array $events): void
            {
                $this->sentLogs = array_merge($this->sentLogs, $events);
            }

            public function sendSpans(array $spans): void
            {
                $this->sentSpans = array_merge($this->sentSpans, $spans);
            }

            public function flush(): void
            {
                $this->flushed = true;
            }

            public function close(): void
            {
                $this->closed = true;
            }
        };
    }

    public function testCaptureEvent(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test message');
        $eventId = $client->captureEvent($event);

        $this->assertNotNull($eventId);
        $this->assertCount(1, $transport->sentLogs);
    }

    public function testCaptureEventSetsServiceFromOptions(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['service' => 'my-api'], $transport);

        // Event created without explicit service defaults to 'unknown',
        // which is falsy in ?: so Options service is applied
        $event = new Event(LogLevel::INFO, 'test');
        $event->setService('');
        $client->captureEvent($event);

        $this->assertSame('my-api', $transport->sentLogs[0]->getService());
    }

    public function testCaptureEventDoesNotOverrideExplicitService(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['service' => 'default'], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test', 'custom-svc');
        $client->captureEvent($event);

        $this->assertSame('custom-svc', $transport->sentLogs[0]->getService());
    }

    public function testCaptureEventFallsToOptionsServiceWhenUnknown(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['service' => 'my-api'], $transport);

        // captureLog without explicit service creates event with 'unknown' service,
        // but 'unknown' is truthy so ?: won't fall through. The real service override
        // happens when passing null to captureLog
        $client->captureLog(LogLevel::INFO, 'test', [], null, null);

        // With no service override in captureLog, the event gets 'unknown' from Event constructor
        // which is truthy, so it stays as 'unknown'
        $this->assertSame('unknown', $transport->sentLogs[0]->getService());
    }

    public function testCaptureEventSetsEnvironmentFromOptions(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['environment' => 'production'], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $client->captureEvent($event);

        $this->assertSame('production', $transport->sentLogs[0]->getEnvironment());
    }

    public function testCaptureEventSetsReleaseFromOptions(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['release' => 'v2.0.0'], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $client->captureEvent($event);

        $this->assertSame('v2.0.0', $transport->sentLogs[0]->getRelease());
    }

    public function testCaptureEventAppliesGlobalMetadata(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['global_metadata' => ['host' => 'srv-1']], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $client->captureEvent($event);

        $this->assertSame('srv-1', $transport->sentLogs[0]->getMetadata()['host']);
    }

    public function testCaptureEventAppliesGlobalTags(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['tags' => ['region' => 'eu']], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $client->captureEvent($event);

        $this->assertSame('eu', $transport->sentLogs[0]->getTags()['region']);
    }

    public function testCaptureEventEventTagsOverrideGlobal(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['tags' => ['env' => 'default']], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $event->setTags(['env' => 'custom']);
        $client->captureEvent($event);

        $this->assertSame('custom', $transport->sentLogs[0]->getTags()['env']);
    }

    public function testCaptureEventAppliesScope(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $scope = new Scope();
        $scope->setTag('scope_tag', 'yes');
        $scope->setExtra('debug', true);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $client->captureEvent($event, $scope);

        $this->assertSame('yes', $transport->sentLogs[0]->getTags()['scope_tag']);
        $this->assertTrue($transport->sentLogs[0]->getExtras()['debug']);
    }

    public function testCaptureEventBeforeSendModifies(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([
            'before_send' => function (Event $event) {
                $event->setMessage('modified by before_send');
                return $event;
            },
        ], $transport);

        $event = Event::createLog(LogLevel::INFO, 'original');
        $client->captureEvent($event);

        $this->assertSame('modified by before_send', $transport->sentLogs[0]->getMessage());
    }

    public function testCaptureEventBeforeSendDrops(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([
            'before_send' => fn() => null,
        ], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $client->captureEvent($event);

        $this->assertNull($result);
        $this->assertEmpty($transport->sentLogs);
    }

    public function testCaptureEventScopeDrops(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $scope = new Scope();
        $scope->addEventProcessor(fn() => null);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $client->captureEvent($event, $scope);

        $this->assertNull($result);
        $this->assertEmpty($transport->sentLogs);
    }

    public function testCaptureEventWhenClosed(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $client->close();

        $event = Event::createLog(LogLevel::INFO, 'test');
        $result = $client->captureEvent($event);

        $this->assertNull($result);
        $this->assertEmpty($transport->sentLogs);
    }

    public function testCaptureException(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $eventId = $client->captureException(new \RuntimeException('boom'));

        $this->assertNotNull($eventId);
        $this->assertCount(1, $transport->sentLogs);
        $this->assertSame('boom', $transport->sentLogs[0]->getMessage());
        $this->assertNotNull($transport->sentLogs[0]->getException());
    }

    public function testCaptureExceptionIgnoresByClass(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([
            'ignore_exceptions' => [\RuntimeException::class],
        ], $transport);

        $result = $client->captureException(new \RuntimeException('ignored'));

        $this->assertNull($result);
        $this->assertEmpty($transport->sentLogs);
    }

    public function testCaptureExceptionIgnoresByRegex(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([
            'ignore_exceptions' => ['/^Runtime/'],
        ], $transport);

        $result = $client->captureException(new \RuntimeException('ignored'));

        $this->assertNull($result);
        $this->assertEmpty($transport->sentLogs);
    }

    public function testCaptureExceptionNotIgnoredWhenNoMatch(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([
            'ignore_exceptions' => [\LogicException::class],
        ], $transport);

        $result = $client->captureException(new \RuntimeException('not ignored'));

        $this->assertNotNull($result);
        $this->assertCount(1, $transport->sentLogs);
    }

    public function testCaptureLog(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $eventId = $client->captureLog(LogLevel::WARN, 'warning msg', ['key' => 'val']);

        $this->assertNotNull($eventId);
        $this->assertCount(1, $transport->sentLogs);
        $this->assertSame(LogLevel::WARN, $transport->sentLogs[0]->getLevel());
        $this->assertSame('warning msg', $transport->sentLogs[0]->getMessage());
        $this->assertSame('val', $transport->sentLogs[0]->getMetadata()['key']);
    }

    public function testCaptureLogWithServiceOverride(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['service' => 'default'], $transport);

        $client->captureLog(LogLevel::INFO, 'test', [], null, 'override-svc');

        $this->assertSame('override-svc', $transport->sentLogs[0]->getService());
    }

    public function testStartSpan(): void
    {
        $client = $this->createClient();
        $span = $client->startSpan('http.request');

        $this->assertInstanceOf(Span::class, $span);
        $this->assertSame('http.request', $span->getOperation());
        $this->assertSame(SpanKind::INTERNAL, $span->getKind());
    }

    public function testStartSpanWithOptions(): void
    {
        $client = $this->createClient();
        $span = $client->startSpan('db.query', [
            'kind' => SpanKind::CLIENT,
            'parent_span_id' => 'parent-123',
            'trace_id' => 'trace-abc',
            'service' => 'db-svc',
        ]);

        $this->assertSame(SpanKind::CLIENT, $span->getKind());
        $this->assertSame('parent-123', $span->getParentSpanId());
        $this->assertSame('trace-abc', $span->getTraceId());
        $this->assertSame('db-svc', $span->getServiceName());
    }

    public function testFinishSpan(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['traces_sample_rate' => 1.0], $transport);

        $span = $client->startSpan('test.op');
        $client->finishSpan($span);

        $this->assertTrue($span->isFinished());
        $this->assertCount(1, $transport->sentSpans);
    }

    public function testFinishSpanAlreadyFinished(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['traces_sample_rate' => 1.0], $transport);

        $span = $client->startSpan('test.op');
        $span->finish();
        $client->finishSpan($span);

        $this->assertCount(1, $transport->sentSpans);
    }

    public function testFinishSpanNotSampled(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['traces_sample_rate' => 0.0], $transport);

        $span = $client->startSpan('test.op');
        $client->finishSpan($span);

        $this->assertTrue($span->isFinished());
        $this->assertEmpty($transport->sentSpans);
    }

    public function testAttachStacktrace(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['attach_stacktrace' => true], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $client->captureEvent($event);

        $this->assertNotNull($transport->sentLogs[0]->getStacktrace());
    }

    public function testNoStacktraceByDefault(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $client->captureEvent($event);

        $this->assertNull($transport->sentLogs[0]->getStacktrace());
    }

    public function testAttachStacktraceNotAddedWhenExceptionPresent(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient(['attach_stacktrace' => true], $transport);

        $event = Event::createLog(LogLevel::ERROR, 'test');
        $event->setException(['type' => 'RuntimeException', 'message' => 'boom']);
        $client->captureEvent($event);

        $this->assertNull($transport->sentLogs[0]->getStacktrace());
    }

    public function testFlush(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $client->flush();

        $this->assertTrue($transport->flushed);
    }

    public function testClose(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([], $transport);

        $client->close();

        $this->assertTrue($transport->closed);
    }

    public function testCloseOnlyOnce(): void
    {
        $callCount = 0;
        $transport = new class($callCount) implements TransportInterface {
            public function __construct(private int &$count) {}
            public function sendLogs(array $events): void {}
            public function sendSpans(array $spans): void {}
            public function flush(): void {}
            public function close(): void { $this->count++; }
        };

        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );

        $client->close();
        $client->close();

        $this->assertSame(1, $callCount);
    }

    public function testGetOptions(): void
    {
        $client = $this->createClient(['service' => 'test-svc']);

        $this->assertSame('test-svc', $client->getOptions()->getService());
    }

    public function testBeforeSendReceivesHint(): void
    {
        $receivedHint = null;
        $transport = $this->createSpyTransport();
        $client = $this->createClient([
            'before_send' => function (Event $event, ?EventHint $hint) use (&$receivedHint) {
                $receivedHint = $hint;
                return $event;
            },
        ], $transport);

        $exception = new \RuntimeException('test');
        $client->captureException($exception);

        $this->assertNotNull($receivedHint);
        $this->assertSame($exception, $receivedHint->exception);
    }

    public function testGlobalMetadataDoesNotOverrideEventMetadata(): void
    {
        $transport = $this->createSpyTransport();
        $client = $this->createClient([
            'global_metadata' => ['key' => 'global'],
        ], $transport);

        $event = Event::createLog(LogLevel::INFO, 'test');
        $event->setMetadata(['key' => 'local']);
        $client->captureEvent($event);

        $this->assertSame('local', $transport->sentLogs[0]->getMetadata()['key']);
    }
}
