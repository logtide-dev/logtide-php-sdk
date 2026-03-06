<?php

declare(strict_types=1);

namespace LogTide\HttpClient;

final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly float $timeout = 30.0,
        private readonly float $connectTimeout = 10.0,
    ) {
    }

    public function post(string $url, array $headers, string $body): HttpResponse
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $this->timeout,
            CURLOPT_CONNECTTIMEOUT => (int) $this->connectTimeout,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = [trim($parts[1])];
                }
                return strlen($header);
            },
        ]);

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new \RuntimeException("cURL request failed: {$error}");
        }

        return new HttpResponse($statusCode, (string) $responseBody, $responseHeaders);
    }
}
