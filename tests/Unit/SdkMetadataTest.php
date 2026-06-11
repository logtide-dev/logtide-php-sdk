<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\Enum\LogLevel;
use LogTide\Event;
use LogTide\Version;
use PHPUnit\Framework\TestCase;

/**
 * Every entry must carry metadata.sdk = {name, version} (spec 003 §3).
 */
final class SdkMetadataTest extends TestCase
{
    public function testEventsCarrySdkMetadata(): void
    {
        $event = new Event(LogLevel::INFO, 'hello', 'svc');
        $data = $event->toArray();

        $meta = (array) $data['metadata'];
        self::assertArrayHasKey('sdk', $meta);
        self::assertSame('logtide-php', $meta['sdk']['name']);
        self::assertSame(Version::SDK_VERSION, $meta['sdk']['version']);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Version::SDK_VERSION);
    }

    public function testCallerProvidedSdkMetadataWins(): void
    {
        $event = new Event(LogLevel::INFO, 'hello', 'svc');
        $event->setMetadata(['sdk' => 'custom']);
        $data = $event->toArray();

        $meta = (array) $data['metadata'];
        self::assertSame('custom', $meta['sdk']);
    }
}
