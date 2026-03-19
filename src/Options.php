<?php

declare(strict_types=1);

namespace LogTide;

use LogTide\Exception\InvalidDsnException;
use LogTide\Integration\IntegrationInterface;
use LogTide\Transport\TransportInterface;

final class Options
{
    private ?Dsn $parsedDsn = null;
    private string $apiUrl = '';
    private string $apiKey = '';
    private string $service = 'unknown';
    private ?string $environment = null;
    private ?string $release = null;
    private int $batchSize = 100;
    private int $flushInterval = 5000;
    private int $maxBufferSize = 10000;
    private int $maxRetries = 3;
    private int $retryDelayMs = 1000;
    private int $circuitBreakerThreshold = 5;
    private int $circuitBreakerResetMs = 30000;
    private int $maxBreadcrumbs = 100;
    private float $tracesSampleRate = 1.0;
    private bool $debug = false;
    private bool $attachStacktrace = false;
    private bool $sendDefaultPii = false;
    private bool $defaultIntegrations = true;
    private array $tags = [];
    private array $globalMetadata = [];

    /** @var IntegrationInterface[]|(\Closure(IntegrationInterface[]): IntegrationInterface[])|null */
    private array|\Closure|null $integrations = null;

    private ?TransportInterface $transport = null;

    /** @var (\Closure(Event, EventHint|null): ?Event)|null */
    private ?\Closure $beforeSend = null;

    /** @var (\Closure(Breadcrumb\Breadcrumb): ?Breadcrumb\Breadcrumb)|null */
    private ?\Closure $beforeBreadcrumb = null;

    /** @var string[] */
    private array $ignoreExceptions = [];

    /** @var string[] */
    private array $inAppIncludedPaths = [];

    /** @var string[] */
    private array $inAppExcludedPaths = [];

    private float $httpTimeout = 30.0;
    private float $httpConnectTimeout = 10.0;

    public static function fromArray(array $config): self
    {
        $options = new self();

        if (isset($config['dsn'])) {
            $options->setDsn($config['dsn']);
        }
        if (isset($config['api_url'])) {
            $options->apiUrl = rtrim($config['api_url'], '/');
        }
        if (isset($config['api_key'])) {
            $options->apiKey = $config['api_key'];
        }

        $intKeys = [
            'batch_size' => 'batchSize',
            'flush_interval' => 'flushInterval',
            'max_buffer_size' => 'maxBufferSize',
            'max_retries' => 'maxRetries',
            'retry_delay_ms' => 'retryDelayMs',
            'circuit_breaker_threshold' => 'circuitBreakerThreshold',
            'circuit_breaker_reset_ms' => 'circuitBreakerResetMs',
            'max_breadcrumbs' => 'maxBreadcrumbs',
        ];

        $floatKeys = [
            'traces_sample_rate' => 'tracesSampleRate',
            'http_timeout' => 'httpTimeout',
            'http_connect_timeout' => 'httpConnectTimeout',
        ];

        $boolKeys = [
            'debug' => 'debug',
            'attach_stacktrace' => 'attachStacktrace',
            'send_default_pii' => 'sendDefaultPii',
            'default_integrations' => 'defaultIntegrations',
        ];

        $stringKeys = [
            'service' => 'service',
            'environment' => 'environment',
            'release' => 'release',
        ];

        $directKeys = [
            'tags' => 'tags',
            'global_metadata' => 'globalMetadata',
            'integrations' => 'integrations',
            'transport' => 'transport',
            'before_send' => 'beforeSend',
            'before_breadcrumb' => 'beforeBreadcrumb',
            'ignore_exceptions' => 'ignoreExceptions',
            'in_app_included_paths' => 'inAppIncludedPaths',
            'in_app_excluded_paths' => 'inAppExcludedPaths',
        ];

        foreach ($intKeys as $key => $prop) {
            if (array_key_exists($key, $config)) {
                $options->{$prop} = (int) $config[$key];
            }
        }
        foreach ($floatKeys as $key => $prop) {
            if (array_key_exists($key, $config)) {
                $options->{$prop} = (float) $config[$key];
            }
        }
        foreach ($boolKeys as $key => $prop) {
            if (array_key_exists($key, $config)) {
                $options->{$prop} = (bool) $config[$key];
            }
        }
        foreach ($stringKeys as $key => $prop) {
            if (array_key_exists($key, $config) && $config[$key] !== null) {
                $options->{$prop} = (string) $config[$key];
            }
        }
        foreach ($directKeys as $key => $prop) {
            if (array_key_exists($key, $config)) {
                $options->{$prop} = $config[$key];
            }
        }

        return $options;
    }

    public function setDsn(string $dsn): void
    {
        $this->parsedDsn = Dsn::parse($dsn);
        $this->apiUrl = $this->parsedDsn->apiUrl;
        $this->apiKey = $this->parsedDsn->apiKey;
    }

    public function getDsn(): ?Dsn
    {
        return $this->parsedDsn;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function getRelease(): ?string
    {
        return $this->release;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function getFlushInterval(): int
    {
        return $this->flushInterval;
    }

    public function getMaxBufferSize(): int
    {
        return $this->maxBufferSize;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getRetryDelayMs(): int
    {
        return $this->retryDelayMs;
    }

    public function getCircuitBreakerThreshold(): int
    {
        return $this->circuitBreakerThreshold;
    }

    public function getCircuitBreakerResetMs(): int
    {
        return $this->circuitBreakerResetMs;
    }

    public function getMaxBreadcrumbs(): int
    {
        return $this->maxBreadcrumbs;
    }

    public function getTracesSampleRate(): float
    {
        return $this->tracesSampleRate;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function shouldAttachStacktrace(): bool
    {
        return $this->attachStacktrace;
    }

    public function shouldSendDefaultPii(): bool
    {
        return $this->sendDefaultPii;
    }

    public function useDefaultIntegrations(): bool
    {
        return $this->defaultIntegrations;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getGlobalMetadata(): array
    {
        return $this->globalMetadata;
    }

    /** @return IntegrationInterface[]|(\Closure(IntegrationInterface[]): IntegrationInterface[])|null */
    public function getIntegrations(): array|\Closure|null
    {
        return $this->integrations;
    }

    public function getTransport(): ?TransportInterface
    {
        return $this->transport;
    }

    /** @return (\Closure(Event, EventHint|null): ?Event)|null */
    public function getBeforeSend(): ?\Closure
    {
        return $this->beforeSend;
    }

    /** @return (\Closure(Breadcrumb\Breadcrumb): ?Breadcrumb\Breadcrumb)|null */
    public function getBeforeBreadcrumb(): ?\Closure
    {
        return $this->beforeBreadcrumb;
    }

    /** @return string[] */
    public function getIgnoreExceptions(): array
    {
        return $this->ignoreExceptions;
    }

    /** @return string[] */
    public function getInAppIncludedPaths(): array
    {
        return $this->inAppIncludedPaths;
    }

    /** @return string[] */
    public function getInAppExcludedPaths(): array
    {
        return $this->inAppExcludedPaths;
    }

    public function getHttpTimeout(): float
    {
        return $this->httpTimeout;
    }

    public function getHttpConnectTimeout(): float
    {
        return $this->httpConnectTimeout;
    }
}
