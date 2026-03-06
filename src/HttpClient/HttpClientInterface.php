<?php

declare(strict_types=1);

namespace LogTide\HttpClient;

interface HttpClientInterface
{
    public function post(string $url, array $headers, string $body): HttpResponse;
}
