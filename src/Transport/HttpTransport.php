<?php

declare(strict_types=1);

namespace LogTide\Transport;

use LogTide\Event;
use LogTide\HttpClient\HttpClientInterface;
use LogTide\Tracing\Span;

final class HttpTransport implements TransportInterface
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function sendLogs(array $events): void
    {
        if (empty($events)) {
            return;
        }

        $payload = json_encode([
            'logs' => array_map(fn(Event $e) => $e->toArray(), $events),
        ]);

        $this->httpClient->post(
            "{$this->apiUrl}/api/v1/ingest",
            [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->apiKey,
            ],
            $payload,
        );
    }

    public function sendSpans(array $spans): void
    {
        // Log transport does not handle spans
    }

    public function flush(): void
    {
        // No internal buffer
    }

    public function close(): void
    {
        // No-op
    }
}
