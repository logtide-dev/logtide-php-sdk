<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\LogtideSdk;
use LogTide\State\Hub;
use LogTide\State\HubInterface;
use LogTide\Transport\NullTransport;
use PHPUnit\Framework\TestCase;

final class LogtideSdkTest extends TestCase
{
    protected function tearDown(): void
    {
        LogtideSdk::reset();
    }

    public function testGetCurrentHubWithoutInit(): void
    {
        $hub = LogtideSdk::getCurrentHub();

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertNull($hub->getClient());
    }

    public function testIsNotInitializedByDefault(): void
    {
        $this->assertFalse(LogtideSdk::isInitialized());
    }

    public function testInitReturnsHub(): void
    {
        $hub = LogtideSdk::init([
            'default_integrations' => false,
            'transport' => new NullTransport(),
        ]);

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertTrue(LogtideSdk::isInitialized());
    }

    public function testInitSetsCurrentHub(): void
    {
        $hub = LogtideSdk::init([
            'default_integrations' => false,
            'transport' => new NullTransport(),
        ]);

        $this->assertSame($hub, LogtideSdk::getCurrentHub());
    }

    public function testSetCurrentHub(): void
    {
        $hub = new Hub();
        LogtideSdk::setCurrentHub($hub);

        $this->assertSame($hub, LogtideSdk::getCurrentHub());
    }

    public function testResetClearsState(): void
    {
        LogtideSdk::init([
            'default_integrations' => false,
            'transport' => new NullTransport(),
        ]);

        $this->assertTrue(LogtideSdk::isInitialized());

        LogtideSdk::reset();

        $this->assertFalse(LogtideSdk::isInitialized());
    }

    public function testReInitOverridesPrevious(): void
    {
        $hub1 = LogtideSdk::init([
            'default_integrations' => false,
            'transport' => new NullTransport(),
            'service' => 'first',
        ]);

        $hub2 = LogtideSdk::init([
            'default_integrations' => false,
            'transport' => new NullTransport(),
            'service' => 'second',
        ]);

        $this->assertNotSame($hub1, $hub2);
        $this->assertSame($hub2, LogtideSdk::getCurrentHub());
    }

    public function testGetCurrentHubReturnsConsistentInstance(): void
    {
        $hub1 = LogtideSdk::getCurrentHub();
        $hub2 = LogtideSdk::getCurrentHub();

        $this->assertSame($hub1, $hub2);
    }

    public function testInitWithDsn(): void
    {
        $hub = LogtideSdk::init([
            'dsn' => 'https://lp_testkey@example.com',
            'default_integrations' => false,
        ]);

        $this->assertTrue(LogtideSdk::isInitialized());
        $this->assertSame('lp_testkey', $hub->getClient()->getOptions()->getApiKey());
    }
}
