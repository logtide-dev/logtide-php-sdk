<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Breadcrumb;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\Breadcrumb\BreadcrumbBuffer;
use LogTide\Enum\BreadcrumbType;
use LogTide\Enum\LogLevel;
use PHPUnit\Framework\TestCase;

final class BreadcrumbBufferTest extends TestCase
{
    public function testAddAndRetrieve(): void
    {
        $buffer = new BreadcrumbBuffer(10);
        $breadcrumb = new Breadcrumb(BreadcrumbType::HTTP, 'GET /api');

        $buffer->add($breadcrumb);

        $this->assertSame(1, $buffer->count());
        $this->assertSame($breadcrumb, $buffer->getAll()[0]);
    }

    public function testMaxSizeEvictsOldest(): void
    {
        $buffer = new BreadcrumbBuffer(3);

        $buffer->add(new Breadcrumb(BreadcrumbType::HTTP, 'first'));
        $buffer->add(new Breadcrumb(BreadcrumbType::HTTP, 'second'));
        $buffer->add(new Breadcrumb(BreadcrumbType::HTTP, 'third'));
        $buffer->add(new Breadcrumb(BreadcrumbType::HTTP, 'fourth'));

        $this->assertSame(3, $buffer->count());
        $this->assertSame('second', $buffer->getAll()[0]->message);
        $this->assertSame('fourth', $buffer->getAll()[2]->message);
    }

    public function testClear(): void
    {
        $buffer = new BreadcrumbBuffer(10);
        $buffer->add(new Breadcrumb(BreadcrumbType::HTTP, 'test'));
        $buffer->add(new Breadcrumb(BreadcrumbType::HTTP, 'test2'));

        $buffer->clear();

        $this->assertSame(0, $buffer->count());
        $this->assertEmpty($buffer->getAll());
    }

    public function testToArray(): void
    {
        $buffer = new BreadcrumbBuffer(10);
        $buffer->add(new Breadcrumb(
            BreadcrumbType::QUERY,
            'SELECT * FROM users',
            category: 'db',
            level: LogLevel::DEBUG,
            data: ['duration_ms' => 5.2],
        ));

        $arr = $buffer->toArray();

        $this->assertCount(1, $arr);
        $this->assertSame('query', $arr[0]['type']);
        $this->assertSame('SELECT * FROM users', $arr[0]['message']);
        $this->assertSame('db', $arr[0]['category']);
        $this->assertSame('debug', $arr[0]['level']);
        $this->assertSame(5.2, $arr[0]['data']['duration_ms']);
    }

    public function testCloneIsIndependent(): void
    {
        $buffer = new BreadcrumbBuffer(10);
        $buffer->add(new Breadcrumb(BreadcrumbType::HTTP, 'original'));

        $clone = $buffer->clone();
        $clone->add(new Breadcrumb(BreadcrumbType::HTTP, 'cloned'));

        $this->assertSame(1, $buffer->count());
        $this->assertSame(2, $clone->count());
    }
}
