<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit;

use LogTide\Client;
use LogTide\ClientBuilder;
use LogTide\HttpClient\HttpClientInterface;
use LogTide\HttpClient\HttpResponse;
use LogTide\Transport\NullTransport;
use LogTide\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

final class ClientBuilderTest extends TestCase
{
    public function testCreateFromConfig(): void
    {
        $builder = ClientBuilder::create(['service' => 'test-svc']);

        $this->assertSame('test-svc', $builder->getOptions()->getService());
    }

    public function testGetClientReturnsClient(): void
    {
        $builder = ClientBuilder::create(['default_integrations' => false]);
        $client = $builder->getClient();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testCustomTransport(): void
    {
        $transport = new NullTransport();
        $builder = ClientBuilder::create(['default_integrations' => false]);
        $builder->setTransport($transport);

        $client = $builder->getClient();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testTransportFromOptions(): void
    {
        $transport = new NullTransport();
        $builder = ClientBuilder::create([
            'default_integrations' => false,
            'transport' => $transport,
        ]);

        $client = $builder->getClient();
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testNullTransportWhenNoCredentials(): void
    {
        $builder = ClientBuilder::create(['default_integrations' => false]);
        $client = $builder->getClient();

        // With no api_url or api_key, should use NullTransport and not fail
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testSetHttpClient(): void
    {
        $httpClient = new class implements HttpClientInterface {
            public function post(string $url, array $headers, string $body): HttpResponse
            {
                return new HttpResponse(200, '{}');
            }
        };

        $builder = ClientBuilder::create([
            'default_integrations' => false,
            'api_url' => 'http://localhost',
            'api_key' => 'lp_test',
        ]);
        $builder->setHttpClient($httpClient);
        $client = $builder->getClient();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testBuilderReturnsSelf(): void
    {
        $builder = ClientBuilder::create([]);

        $result = $builder->setTransport(new NullTransport());
        $this->assertSame($builder, $result);
    }
}
