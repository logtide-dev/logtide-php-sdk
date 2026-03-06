<?php

declare(strict_types=1);

namespace LogTide\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class GuzzleHttpClient implements HttpClientInterface
{
    private readonly Client $client;

    public function __construct(float $timeout = 30.0, float $connectTimeout = 10.0)
    {
        $this->client = new Client([
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
        ]);
    }

    public function post(string $url, array $headers, string $body): HttpResponse
    {
        try {
            $response = $this->client->post($url, [
                'headers' => $headers,
                'body' => $body,
            ]);

            return new HttpResponse(
                $response->getStatusCode(),
                $response->getBody()->getContents(),
                $response->getHeaders(),
            );
        } catch (GuzzleException $e) {
            throw new \RuntimeException("HTTP request failed: {$e->getMessage()}", 0, $e);
        }
    }
}
