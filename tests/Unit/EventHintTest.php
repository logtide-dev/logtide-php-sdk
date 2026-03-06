<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\EventHint;
use PHPUnit\Framework\TestCase;

final class EventHintTest extends TestCase
{
    public function testFromException(): void
    {
        $exception = new \RuntimeException('test');
        $hint = EventHint::fromException($exception);

        $this->assertSame($exception, $hint->exception);
        $this->assertEmpty($hint->extra);
    }

    public function testConstructWithExtras(): void
    {
        $hint = new EventHint(extra: ['key' => 'value']);

        $this->assertNull($hint->exception);
        $this->assertSame(['key' => 'value'], $hint->extra);
    }

    public function testDefaults(): void
    {
        $hint = new EventHint();

        $this->assertNull($hint->exception);
        $this->assertEmpty($hint->extra);
    }
}
