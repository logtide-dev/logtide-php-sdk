<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Integration;

use LogTide\Integration\IntegrationInterface;
use LogTide\Integration\IntegrationRegistry;
use LogTide\Options;
use PHPUnit\Framework\TestCase;

final class IntegrationRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        IntegrationRegistry::reset();
    }

    private function createDummyIntegration(string $name): IntegrationInterface
    {
        return new class($name) implements IntegrationInterface {
            public bool $setupCalled = false;
            public bool $teardownCalled = false;

            public function __construct(private readonly string $name) {}

            public function getName(): string { return $this->name; }

            public function setupOnce(): void { $this->setupCalled = true; }

            public function teardown(): void { $this->teardownCalled = true; }
        };
    }

    public function testSingleton(): void
    {
        $a = IntegrationRegistry::getInstance();
        $b = IntegrationRegistry::getInstance();

        $this->assertSame($a, $b);
    }

    public function testResetCreatesNewInstance(): void
    {
        $a = IntegrationRegistry::getInstance();
        IntegrationRegistry::reset();
        $b = IntegrationRegistry::getInstance();

        $this->assertNotSame($a, $b);
    }

    public function testSetupIntegrationsCallsSetupOnce(): void
    {
        $integration = $this->createDummyIntegration('test');
        $options = Options::fromArray([
            'default_integrations' => false,
            'integrations' => [$integration],
        ]);

        IntegrationRegistry::getInstance()->setupIntegrations($options);

        $this->assertTrue($integration->setupCalled);
    }

    public function testSetupOnceCalledOnlyOnce(): void
    {
        $callCount = 0;
        $integration = new class($callCount) implements IntegrationInterface {
            public function __construct(private int &$count) {}
            public function getName(): string { return 'counter'; }
            public function setupOnce(): void { $this->count++; }
            public function teardown(): void {}
        };

        $options = Options::fromArray([
            'default_integrations' => false,
            'integrations' => [$integration],
        ]);

        $registry = IntegrationRegistry::getInstance();
        $registry->setupIntegrations($options);
        $registry->setupIntegrations($options);

        $this->assertSame(1, $callCount);
    }

    public function testGetIntegration(): void
    {
        $integration = $this->createDummyIntegration('my_integration');
        $options = Options::fromArray([
            'default_integrations' => false,
            'integrations' => [$integration],
        ]);

        $registry = IntegrationRegistry::getInstance();
        $registry->setupIntegrations($options);

        $this->assertSame($integration, $registry->getIntegration('my_integration'));
        $this->assertNull($registry->getIntegration('nonexistent'));
    }

    public function testGetAll(): void
    {
        $int1 = $this->createDummyIntegration('one');
        $int2 = $this->createDummyIntegration('two');

        $options = Options::fromArray([
            'default_integrations' => false,
            'integrations' => [$int1, $int2],
        ]);

        $registry = IntegrationRegistry::getInstance();
        $registry->setupIntegrations($options);

        $all = $registry->getAll();
        $this->assertCount(2, $all);
        $this->assertSame($int1, $all['one']);
        $this->assertSame($int2, $all['two']);
    }

    public function testResetCallsTeardown(): void
    {
        $integration = $this->createDummyIntegration('teardown_test');
        $options = Options::fromArray([
            'default_integrations' => false,
            'integrations' => [$integration],
        ]);

        IntegrationRegistry::getInstance()->setupIntegrations($options);
        IntegrationRegistry::reset();

        $this->assertTrue($integration->teardownCalled);
    }

    public function testDefaultIntegrations(): void
    {
        $defaults = IntegrationRegistry::getDefaultIntegrations();

        $this->assertNotEmpty($defaults);

        $names = array_map(fn($i) => $i->getName(), $defaults);
        $this->assertContains('exception_listener', $names);
        $this->assertContains('error_listener', $names);
        $this->assertContains('fatal_error_listener', $names);
    }

    public function testUserIntegrationsMergeWithDefaults(): void
    {
        $custom = $this->createDummyIntegration('custom');
        $options = Options::fromArray([
            'default_integrations' => true,
            'integrations' => [$custom],
        ]);

        $registry = IntegrationRegistry::getInstance();
        $registry->setupIntegrations($options);

        $this->assertNotNull($registry->getIntegration('custom'));
        $this->assertNotNull($registry->getIntegration('exception_listener'));
    }

    public function testIntegrationsCallable(): void
    {
        $custom = $this->createDummyIntegration('only_this');
        $options = Options::fromArray([
            'default_integrations' => true,
            'integrations' => function (array $defaults) use ($custom): array {
                return [$custom];
            },
        ]);

        $registry = IntegrationRegistry::getInstance();
        $registry->setupIntegrations($options);

        $this->assertNotNull($registry->getIntegration('only_this'));
        $this->assertNull($registry->getIntegration('exception_listener'));
    }

    public function testNoDefaultIntegrations(): void
    {
        $options = Options::fromArray([
            'default_integrations' => false,
        ]);

        $registry = IntegrationRegistry::getInstance();
        $registry->setupIntegrations($options);

        $this->assertEmpty($registry->getAll());
    }
}
