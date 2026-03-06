<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Monolog;

use LogTide\Client;
use LogTide\LogtideSdk;
use LogTide\Monolog\LogtideHandler;
use LogTide\Options;
use LogTide\State\Hub;
use LogTide\Transport\NullTransport;
use LogTide\Transport\TransportInterface;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class LogtideHandlerTest extends TestCase
{
    private function createSpyTransport(): TransportInterface
    {
        return new class implements TransportInterface {
            public array $sentLogs = [];
            public function sendLogs(array $events): void { $this->sentLogs = array_merge($this->sentLogs, $events); }
            public function sendSpans(array $spans): void {}
            public function flush(): void {}
            public function close(): void {}
        };
    }

    protected function tearDown(): void
    {
        LogtideSdk::reset();
    }

    public function testDoesNothingWithoutClient(): void
    {
        $hub = new Hub();
        LogtideSdk::setCurrentHub($hub);

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->info('test message');

        $this->assertNull($hub->getClient());
    }

    public function testForwardsLogToHub(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        $hub = new Hub($client);
        LogtideSdk::setCurrentHub($hub);

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->warning('something happened');

        $this->assertCount(1, $transport->sentLogs);
        $this->assertSame('something happened', $transport->sentLogs[0]->getMessage());
    }

    public function testMapsDebugLevel(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        LogtideSdk::setCurrentHub(new Hub($client));

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->debug('debug msg');

        $this->assertSame('debug', $transport->sentLogs[0]->getLevel()->value);
    }

    public function testMapsInfoLevel(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        LogtideSdk::setCurrentHub(new Hub($client));

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->info('info msg');

        $this->assertSame('info', $transport->sentLogs[0]->getLevel()->value);
    }

    public function testMapsWarningLevel(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        LogtideSdk::setCurrentHub(new Hub($client));

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->warning('warn msg');

        $this->assertSame('warn', $transport->sentLogs[0]->getLevel()->value);
    }

    public function testMapsErrorLevel(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        LogtideSdk::setCurrentHub(new Hub($client));

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->error('error msg');

        $this->assertSame('error', $transport->sentLogs[0]->getLevel()->value);
    }

    public function testMapsCriticalLevel(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        LogtideSdk::setCurrentHub(new Hub($client));

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->critical('critical msg');

        $this->assertSame('critical', $transport->sentLogs[0]->getLevel()->value);
    }

    public function testMapsEmergencyToCritical(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        LogtideSdk::setCurrentHub(new Hub($client));

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->emergency('emergency msg');

        $this->assertSame('critical', $transport->sentLogs[0]->getLevel()->value);
    }

    public function testPassesContextAsMetadata(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        LogtideSdk::setCurrentHub(new Hub($client));

        $logger = new Logger('test', [new LogtideHandler()]);
        $logger->info('msg', ['order_id' => 42]);

        $meta = $transport->sentLogs[0]->getMetadata();
        $this->assertSame(42, $meta['order_id']);
    }

    public function testRespectsMinLevel(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            $transport,
        );
        LogtideSdk::setCurrentHub(new Hub($client));

        $logger = new Logger('test', [new LogtideHandler(Level::Warning)]);
        $logger->debug('skipped');
        $logger->info('also skipped');
        $logger->warning('included');

        $this->assertCount(1, $transport->sentLogs);
        $this->assertSame('included', $transport->sentLogs[0]->getMessage());
    }
}
