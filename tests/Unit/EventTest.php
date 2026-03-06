<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\Enum\LogLevel;
use LogTide\Event;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    public function testCreateLog(): void
    {
        $event = Event::createLog(LogLevel::INFO, 'test message', 'my-service');

        $this->assertSame(LogLevel::INFO, $event->getLevel());
        $this->assertSame('test message', $event->getMessage());
        $this->assertSame('my-service', $event->getService());
        $this->assertNotEmpty($event->getId());
        $this->assertNotEmpty($event->getTime());
    }

    public function testCreateLogDefaultService(): void
    {
        $event = Event::createLog(LogLevel::DEBUG, 'msg');
        $this->assertSame('unknown', $event->getService());
    }

    public function testCreateError(): void
    {
        $exception = new \RuntimeException('something broke');
        $event = Event::createError($exception);

        $this->assertSame(LogLevel::ERROR, $event->getLevel());
        $this->assertSame('something broke', $event->getMessage());
    }

    public function testSettersAndGetters(): void
    {
        $event = Event::createLog(LogLevel::WARN, 'test');

        $event->setService('api');
        $this->assertSame('api', $event->getService());

        $event->setLevel(LogLevel::CRITICAL);
        $this->assertSame(LogLevel::CRITICAL, $event->getLevel());

        $event->setMetadata(['key' => 'value']);
        $this->assertSame(['key' => 'value'], $event->getMetadata());

        $event->addMetadata('extra', 'data');
        $this->assertSame('data', $event->getMetadata()['extra']);

        $event->setTraceId('trace-123');
        $this->assertSame('trace-123', $event->getTraceId());

        $event->setSpanId('span-456');
        $this->assertSame('span-456', $event->getSpanId());

        $event->setTags(['env' => 'prod']);
        $this->assertSame(['env' => 'prod'], $event->getTags());

        $event->setExtras(['debug' => true]);
        $this->assertSame(['debug' => true], $event->getExtras());

        $event->setEnvironment('staging');
        $this->assertSame('staging', $event->getEnvironment());

        $event->setRelease('v2.0');
        $this->assertSame('v2.0', $event->getRelease());
    }

    public function testToArray(): void
    {
        $event = Event::createLog(LogLevel::ERROR, 'fail', 'payments');
        $event->setTraceId('trace-abc');
        $event->setSpanId('span-def');
        $event->setMetadata(['order_id' => 42]);

        $arr = $event->toArray();

        $this->assertSame('payments', $arr['service']);
        $this->assertSame('error', $arr['level']);
        $this->assertSame('fail', $arr['message']);
        $this->assertSame('trace-abc', $arr['trace_id']);
        $this->assertSame('span-def', $arr['span_id']);
        $this->assertSame(42, $arr['metadata']['order_id']);
        $this->assertArrayHasKey('time', $arr);
    }

    public function testToArrayWithoutOptionals(): void
    {
        $event = Event::createLog(LogLevel::INFO, 'basic');
        $arr = $event->toArray();

        $this->assertArrayNotHasKey('trace_id', $arr);
        $this->assertArrayNotHasKey('span_id', $arr);
    }

    public function testToArrayIncludesExceptionInMetadata(): void
    {
        $event = Event::createLog(LogLevel::ERROR, 'error');
        $event->setException(['type' => 'RuntimeException', 'message' => 'oops']);

        $arr = $event->toArray();
        $this->assertSame('RuntimeException', $arr['metadata']['exception']['type']);
    }

    public function testToArrayIncludesBreadcrumbsInMetadata(): void
    {
        $event = Event::createLog(LogLevel::INFO, 'test');
        $event->setBreadcrumbs([['type' => 'http', 'message' => 'GET /']]);

        $arr = $event->toArray();
        $this->assertCount(1, $arr['metadata']['breadcrumbs']);
    }

    public function testToArrayIncludesTagsAndExtras(): void
    {
        $event = Event::createLog(LogLevel::INFO, 'test');
        $event->setTags(['env' => 'prod']);
        $event->setExtras(['debug_info' => 'abc']);

        $arr = $event->toArray();
        $this->assertSame(['env' => 'prod'], $arr['metadata']['tags']);
        $this->assertSame(['debug_info' => 'abc'], $arr['metadata']['extra']);
    }
}
