<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\Options;
use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = Options::fromArray([]);

        $this->assertSame('', $options->getApiUrl());
        $this->assertSame('', $options->getApiKey());
        $this->assertSame('unknown', $options->getService());
        $this->assertNull($options->getEnvironment());
        $this->assertNull($options->getRelease());
        $this->assertSame(100, $options->getBatchSize());
        $this->assertSame(10000, $options->getMaxBufferSize());
        $this->assertSame(3, $options->getMaxRetries());
        $this->assertSame(1000, $options->getRetryDelayMs());
        $this->assertSame(5, $options->getCircuitBreakerThreshold());
        $this->assertSame(30000, $options->getCircuitBreakerResetMs());
        $this->assertSame(100, $options->getMaxBreadcrumbs());
        $this->assertSame(1.0, $options->getTracesSampleRate());
        $this->assertFalse($options->isDebug());
        $this->assertFalse($options->shouldAttachStacktrace());
        $this->assertFalse($options->shouldSendDefaultPii());
        $this->assertTrue($options->useDefaultIntegrations());
    }

    public function testFromArrayWithDsn(): void
    {
        $options = Options::fromArray([
            'dsn' => 'https://lp_testkey@example.com',
        ]);

        $this->assertSame('https://example.com', $options->getApiUrl());
        $this->assertSame('lp_testkey', $options->getApiKey());
        $this->assertNotNull($options->getDsn());
    }

    public function testFromArrayWithApiUrlAndKey(): void
    {
        $options = Options::fromArray([
            'api_url' => 'http://localhost:8080/',
            'api_key' => 'lp_direct_key',
        ]);

        $this->assertSame('http://localhost:8080', $options->getApiUrl());
        $this->assertSame('lp_direct_key', $options->getApiKey());
    }

    public function testFromArraySetsAllOptions(): void
    {
        $options = Options::fromArray([
            'service' => 'my-service',
            'environment' => 'production',
            'release' => '1.0.0',
            'batch_size' => 50,
            'max_buffer_size' => 5000,
            'max_retries' => 5,
            'retry_delay_ms' => 2000,
            'traces_sample_rate' => 0.5,
            'debug' => true,
            'attach_stacktrace' => true,
            'tags' => ['region' => 'eu'],
            'global_metadata' => ['host' => 'server-1'],
        ]);

        $this->assertSame('my-service', $options->getService());
        $this->assertSame('production', $options->getEnvironment());
        $this->assertSame('1.0.0', $options->getRelease());
        $this->assertSame(50, $options->getBatchSize());
        $this->assertSame(5000, $options->getMaxBufferSize());
        $this->assertSame(5, $options->getMaxRetries());
        $this->assertSame(2000, $options->getRetryDelayMs());
        $this->assertSame(0.5, $options->getTracesSampleRate());
        $this->assertTrue($options->isDebug());
        $this->assertTrue($options->shouldAttachStacktrace());
        $this->assertSame(['region' => 'eu'], $options->getTags());
        $this->assertSame(['host' => 'server-1'], $options->getGlobalMetadata());
    }

    public function testTypeCoercionFromStrings(): void
    {
        $options = Options::fromArray([
            'batch_size' => '200',
            'traces_sample_rate' => '0.75',
            'debug' => '1',
        ]);

        $this->assertSame(200, $options->getBatchSize());
        $this->assertSame(0.75, $options->getTracesSampleRate());
        $this->assertTrue($options->isDebug());
    }

    public function testBeforeSendCallback(): void
    {
        $callback = fn($event) => $event;
        $options = Options::fromArray(['before_send' => $callback]);

        $this->assertSame($callback, $options->getBeforeSend());
    }

    public function testIgnoreExceptions(): void
    {
        $options = Options::fromArray([
            'ignore_exceptions' => [\RuntimeException::class, '/^App\\\\Custom/'],
        ]);

        $this->assertCount(2, $options->getIgnoreExceptions());
    }
}
