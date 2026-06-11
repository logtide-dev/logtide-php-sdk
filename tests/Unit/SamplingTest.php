<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\Client;
use LogTide\Enum\LogLevel;
use LogTide\Options;
use LogTide\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

/**
 * Per-entry log sampling (conformance C23), applied after before_send.
 */
final class SamplingTest extends TestCase
{
    /** @return TransportInterface&object{sentLogs: array<int, mixed>} */
    private function createSpyTransport(): TransportInterface
    {
        return new class implements TransportInterface {
            /** @var array<int, mixed> */
            public array $sentLogs = [];

            public function sendLogs(array $events): void
            {
                $this->sentLogs = array_merge($this->sentLogs, $events);
            }

            public function sendSpans(array $spans): void
            {
            }

            public function flush(): void
            {
            }

            public function close(): void
            {
            }
        };
    }

    public function testSampleRateZeroSendsNothing(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false, 'sample_rate' => 0.0]),
            $transport,
        );

        for ($i = 0; $i < 20; $i++) {
            $client->captureLog(LogLevel::INFO, 'nope');
        }

        self::assertCount(0, $transport->sentLogs);
    }

    public function testSampleRateOneSendsEverything(): void
    {
        $transport = $this->createSpyTransport();
        $client = new Client(
            Options::fromArray(['default_integrations' => false, 'sample_rate' => 1.0]),
            $transport,
        );

        for ($i = 0; $i < 5; $i++) {
            $client->captureLog(LogLevel::INFO, 'yes');
        }

        self::assertCount(5, $transport->sentLogs);
    }

    public function testSampleRateIsValidated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Options::fromArray(['sample_rate' => 1.5]);
    }
}
