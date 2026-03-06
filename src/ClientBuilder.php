<?php

declare(strict_types=1);

namespace LogTide;

use LogTide\HttpClient\CurlHttpClient;
use LogTide\HttpClient\GuzzleHttpClient;
use LogTide\HttpClient\HttpClientInterface;
use LogTide\Transport\BatchTransport;
use LogTide\Transport\HttpTransport;
use LogTide\Transport\NullTransport;
use LogTide\Transport\OtlpHttpTransport;
use LogTide\Transport\TransportInterface;

final class ClientBuilder
{
    private ?HttpClientInterface $httpClient = null;
    private ?TransportInterface $transport = null;

    private function __construct(
        private readonly Options $options,
    ) {
    }

    public static function create(array $config): self
    {
        return new self(Options::fromArray($config));
    }

    public function setHttpClient(HttpClientInterface $httpClient): self
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function setTransport(TransportInterface $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    public function getClient(): Client
    {
        $transport = $this->buildTransport();
        return new Client($this->options, $transport);
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    private function buildTransport(): TransportInterface
    {
        if ($this->transport !== null) {
            return $this->transport;
        }

        $userTransport = $this->options->getTransport();
        if ($userTransport !== null) {
            return $userTransport;
        }

        if (empty($this->options->getApiUrl()) || empty($this->options->getApiKey())) {
            return new NullTransport();
        }

        $httpClient = $this->buildHttpClient();

        $logTransport = new HttpTransport(
            $this->options->getApiUrl(),
            $this->options->getApiKey(),
            $httpClient,
        );

        $spanTransport = new OtlpHttpTransport(
            $this->options->getApiUrl(),
            $this->options->getApiKey(),
            $httpClient,
            $this->options,
        );

        return new BatchTransport($logTransport, $spanTransport, $this->options);
    }

    private function buildHttpClient(): HttpClientInterface
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        if (class_exists(\GuzzleHttp\Client::class)) {
            return new GuzzleHttpClient(
                $this->options->getHttpTimeout(),
                $this->options->getHttpConnectTimeout(),
            );
        }

        return new CurlHttpClient(
            $this->options->getHttpTimeout(),
            $this->options->getHttpConnectTimeout(),
        );
    }
}
