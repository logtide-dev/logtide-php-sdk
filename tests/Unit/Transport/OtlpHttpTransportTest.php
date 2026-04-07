<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Transport;

use LogTide\Enum\SpanKind;
use LogTide\HttpClient\HttpClientInterface;
use LogTide\HttpClient\HttpResponse;
use LogTide\Options;
use LogTide\Tracing\Span;
use LogTide\Transport\OtlpHttpTransport;
use PHPUnit\Framework\TestCase;

final class OtlpHttpTransportTest extends TestCase
{
    private function createSpyHttpClient(): HttpClientInterface
    {
        return new class implements HttpClientInterface {
            public ?string $lastUrl = null;
            public array $lastHeaders = [];
            public ?string $lastBody = null;

            public function post(string $url, array $headers, string $body): HttpResponse
            {
                $this->lastUrl = $url;
                $this->lastHeaders = $headers;
                $this->lastBody = $body;
                return new HttpResponse(200);
            }
        };
    }

    /**
     * OTLP requires `startTimeUnixNano` and `endTimeUnixNano` to be stringified
     * uint64 values (digits only). PHP's `(string) ($float * 1e9)` produces
     * scientific notation like "1.7755623882398E+18", which the OTLP backend
     * rejects.
     */
    public function testStartAndEndTimeUnixNanoAreIntegerStrings(): void
    {
        $http = $this->createSpyHttpClient();
        $options = Options::fromArray([
            'api_url' => 'https://example.com',
            'api_key' => 'test-key',
        ]);

        $transport = new OtlpHttpTransport(
            'https://example.com',
            'test-key',
            $http,
            $options,
        );

        $span = new Span('test.op', SpanKind::INTERNAL, serviceName: 'svc');
        $span->finish();

        $transport->sendSpans([$span]);

        $this->assertNotNull($http->lastBody);
        $payload = json_decode($http->lastBody, true);

        $otlpSpan = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

        $this->assertIsString($otlpSpan['startTimeUnixNano']);
        $this->assertMatchesRegularExpression(
            '/^\d+$/',
            $otlpSpan['startTimeUnixNano'],
            'startTimeUnixNano must be a digit-only stringified uint64'
        );

        $this->assertIsString($otlpSpan['endTimeUnixNano']);
        $this->assertMatchesRegularExpression(
            '/^\d+$/',
            $otlpSpan['endTimeUnixNano'],
            'endTimeUnixNano must be a digit-only stringified uint64'
        );
    }

    /**
     * Sanity check: end time must be >= start time when serialized as integer
     * strings (i.e. the formatting must preserve ordering).
     */
    public function testEndTimeGreaterThanStartTime(): void
    {
        $http = $this->createSpyHttpClient();
        $options = Options::fromArray([
            'api_url' => 'https://example.com',
            'api_key' => 'test-key',
        ]);

        $transport = new OtlpHttpTransport(
            'https://example.com',
            'test-key',
            $http,
            $options,
        );

        $span = new Span('test.op');
        usleep(1000);
        $span->finish();

        $transport->sendSpans([$span]);

        $payload = json_decode($http->lastBody ?? '', true);
        $otlpSpan = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

        // bccomp would be ideal but stick to native cmp on the digit strings
        $this->assertGreaterThanOrEqual(
            0,
            strcmp($otlpSpan['endTimeUnixNano'], $otlpSpan['startTimeUnixNano']),
            'endTimeUnixNano must be lexicographically >= startTimeUnixNano (same width digit strings)'
        );
    }
}
