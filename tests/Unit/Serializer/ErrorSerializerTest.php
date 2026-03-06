<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Serializer;

use LogTide\Serializer\ErrorSerializer;
use PHPUnit\Framework\TestCase;

final class ErrorSerializerTest extends TestCase
{
    public function testSerializeBasicException(): void
    {
        $exception = new \RuntimeException('test error', 42);
        $result = ErrorSerializer::serialize($exception);

        $this->assertSame('RuntimeException', $result['type']);
        $this->assertSame('test error', $result['message']);
        $this->assertSame(42, $result['code']);
        $this->assertStringContainsString('ErrorSerializerTest.php', $result['file']);
        $this->assertIsInt($result['line']);
        $this->assertIsArray($result['stacktrace']);
        $this->assertNotEmpty($result['stacktrace']);
    }

    public function testSerializeWithPreviousException(): void
    {
        $cause = new \InvalidArgumentException('root cause');
        $exception = new \RuntimeException('wrapper', 0, $cause);
        $result = ErrorSerializer::serialize($exception);

        $this->assertArrayHasKey('cause', $result);
        $this->assertSame('InvalidArgumentException', $result['cause']['type']);
        $this->assertSame('root cause', $result['cause']['message']);
    }

    public function testSerializeChainedExceptions(): void
    {
        $root = new \LogicException('level 0');
        $mid = new \RuntimeException('level 1', 0, $root);
        $top = new \Exception('level 2', 0, $mid);

        $result = ErrorSerializer::serialize($top);

        $this->assertSame('Exception', $result['type']);
        $this->assertSame('RuntimeException', $result['cause']['type']);
        $this->assertSame('LogicException', $result['cause']['cause']['type']);
    }

    public function testParseStacktrace(): void
    {
        $exception = new \RuntimeException('test');
        $frames = ErrorSerializer::parseStacktrace($exception);

        $this->assertIsArray($frames);
        $this->assertNotEmpty($frames);

        $first = $frames[0];
        $this->assertArrayHasKey('file', $first);
        $this->assertArrayHasKey('line', $first);
        $this->assertArrayHasKey('function', $first);
    }

    public function testSerializePhpError(): void
    {
        $result = ErrorSerializer::serializePhpError(
            E_WARNING,
            'Undefined variable',
            '/app/test.php',
            10,
        );

        $this->assertSame('E_WARNING', $result['type']);
        $this->assertSame('Undefined variable', $result['message']);
        $this->assertSame('/app/test.php', $result['file']);
        $this->assertSame(10, $result['line']);
    }

    public function testErrorLevelMapping(): void
    {
        $this->assertSame('E_ERROR', ErrorSerializer::serializePhpError(E_ERROR, '', '', 0)['type']);
        $this->assertSame('E_WARNING', ErrorSerializer::serializePhpError(E_WARNING, '', '', 0)['type']);
        $this->assertSame('E_NOTICE', ErrorSerializer::serializePhpError(E_NOTICE, '', '', 0)['type']);
        $this->assertSame('E_DEPRECATED', ErrorSerializer::serializePhpError(E_DEPRECATED, '', '', 0)['type']);
        $this->assertSame('E_STRICT', ErrorSerializer::serializePhpError(E_STRICT, '', '', 0)['type']);
    }
}
