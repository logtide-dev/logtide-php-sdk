<?php

declare(strict_types=1);

namespace LogTide\Transport;

use LogTide\Event;
use LogTide\HttpClient\HttpClientInterface;
use LogTide\Options;
use LogTide\Tracing\Span;

final class OtlpHttpTransport implements TransportInterface
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly HttpClientInterface $httpClient,
        private readonly Options $options,
    ) {
    }

    public function sendLogs(array $events): void
    {
        // OTLP transport does not handle logs
    }

    public function sendSpans(array $spans): void
    {
        if (empty($spans)) {
            return;
        }

        $payload = $this->buildOtlpPayload($spans);

        $this->httpClient->post(
            "{$this->apiUrl}/v1/otlp/traces",
            [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->apiKey,
            ],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    public function flush(): void
    {
    }

    public function close(): void
    {
    }

    private function buildOtlpPayload(array $spans): array
    {
        $groupedByService = [];
        foreach ($spans as $span) {
            $service = $span->getServiceName() ?? $this->options->getService();
            $groupedByService[$service][] = $span;
        }

        $resourceSpans = [];
        foreach ($groupedByService as $service => $serviceSpans) {
            $otlpSpans = [];
            foreach ($serviceSpans as $span) {
                $otlpSpans[] = $this->spanToOtlp($span);
            }

            $resourceSpans[] = [
                'resource' => [
                    'attributes' => $this->buildResourceAttributes($service),
                ],
                'scopeSpans' => [
                    [
                        'scope' => [
                            'name' => 'logtide-php',
                            'version' => '1.0.0',
                        ],
                        'spans' => $otlpSpans,
                    ],
                ],
            ];
        }

        return ['resourceSpans' => $resourceSpans];
    }

    private function spanToOtlp(Span $span): array
    {
        $otlp = [
            'traceId' => $span->getTraceId(),
            'spanId' => $span->getSpanId(),
            'name' => $span->getOperation(),
            'kind' => $this->mapSpanKind($span->getKind()->value),
            'startTimeUnixNano' => (string) ($span->getStartTime() * 1_000_000_000),
            'endTimeUnixNano' => (string) (($span->getEndTime() ?? microtime(true)) * 1_000_000_000),
            'status' => [
                'code' => $this->mapStatusCode($span->getStatus()->value),
            ],
        ];

        if ($span->getParentSpanId() !== null) {
            $otlp['parentSpanId'] = $span->getParentSpanId();
        }

        if ($span->getStatusMessage() !== null) {
            $otlp['status']['message'] = $span->getStatusMessage();
        }

        $attributes = $span->getAttributes();
        if (!empty($attributes)) {
            $otlp['attributes'] = $this->toOtlpAttributes($attributes);
        }

        $events = $span->getEvents();
        if (!empty($events)) {
            $otlp['events'] = $events;
        }

        return $otlp;
    }

    private function buildResourceAttributes(string $service): array
    {
        $attrs = [
            ['key' => 'service.name', 'value' => ['stringValue' => $service]],
        ];

        if ($env = $this->options->getEnvironment()) {
            $attrs[] = ['key' => 'deployment.environment', 'value' => ['stringValue' => $env]];
        }

        if ($release = $this->options->getRelease()) {
            $attrs[] = ['key' => 'service.version', 'value' => ['stringValue' => $release]];
        }

        return $attrs;
    }

    private function toOtlpAttributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $key => $value) {
            $result[] = [
                'key' => $key,
                'value' => $this->toOtlpValue($value),
            ];
        }
        return $result;
    }

    private function toOtlpValue(mixed $value): array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        }
        if (is_int($value)) {
            return ['intValue' => (string) $value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if (is_bool($value)) {
            return ['boolValue' => $value];
        }
        return ['stringValue' => (string) $value];
    }

    private function mapSpanKind(string $kind): int
    {
        return match ($kind) {
            'INTERNAL' => 1,
            'SERVER' => 2,
            'CLIENT' => 3,
            'PRODUCER' => 4,
            'CONSUMER' => 5,
            default => 0,
        };
    }

    private function mapStatusCode(string $status): int
    {
        return match ($status) {
            'OK' => 1,
            'ERROR' => 2,
            default => 0,
        };
    }
}
