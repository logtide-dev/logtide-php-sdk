<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\Dsn;
use LogTide\Exception\InvalidDsnException;
use PHPUnit\Framework\TestCase;

final class DsnTest extends TestCase
{
    public function testParseValidDsn(): void
    {
        $dsn = Dsn::parse('https://lp_abc123@logtide.example.com');

        $this->assertSame('https://logtide.example.com', $dsn->apiUrl);
        $this->assertSame('lp_abc123', $dsn->apiKey);
    }

    public function testParseWithPort(): void
    {
        $dsn = Dsn::parse('http://lp_key123@localhost:8080');

        $this->assertSame('http://localhost:8080', $dsn->apiUrl);
        $this->assertSame('lp_key123', $dsn->apiKey);
    }

    public function testParseWithPath(): void
    {
        $dsn = Dsn::parse('https://lp_key@example.com/api/');

        $this->assertSame('https://example.com/api', $dsn->apiUrl);
        $this->assertSame('lp_key', $dsn->apiKey);
    }

    public function testParseInvalidDsnThrows(): void
    {
        $this->expectException(InvalidDsnException::class);
        Dsn::parse('not-a-valid-dsn');
    }

    public function testParseWithoutApiKeyPrefixThrows(): void
    {
        $this->expectException(InvalidDsnException::class);
        $this->expectExceptionMessage("must start with 'lp_'");
        Dsn::parse('https://bad_key@example.com');
    }

    public function testToString(): void
    {
        $dsn = Dsn::parse('https://lp_mykey@logtide.example.com');
        $this->assertSame('https://lp_mykey@logtide.example.com', (string) $dsn);
    }

    public function testToStringWithPort(): void
    {
        $dsn = Dsn::parse('http://lp_key@localhost:8080');
        $this->assertSame('http://lp_key@localhost:8080', (string) $dsn);
    }
}
