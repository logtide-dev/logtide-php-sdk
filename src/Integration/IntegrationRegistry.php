<?php

declare(strict_types=1);

namespace LogTide\Integration;

use LogTide\Options;

final class IntegrationRegistry
{
    private static ?self $instance = null;

    /** @var array<string, IntegrationInterface> */
    private array $integrations = [];

    /** @var array<string, bool> */
    private array $installed = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        if (self::$instance !== null) {
            foreach (self::$instance->integrations as $integration) {
                $integration->teardown();
            }
        }
        self::$instance = null;
    }

    public function setupIntegrations(Options $options): void
    {
        $integrations = $this->resolveIntegrations($options);

        foreach ($integrations as $integration) {
            $name = $integration->getName();

            if (isset($this->installed[$name])) {
                continue;
            }

            $integration->setupOnce();
            $this->integrations[$name] = $integration;
            $this->installed[$name] = true;
        }
    }

    public function getIntegration(string $name): ?IntegrationInterface
    {
        return $this->integrations[$name] ?? null;
    }

    /** @return IntegrationInterface[] */
    public function getAll(): array
    {
        return $this->integrations;
    }

    /** @return IntegrationInterface[] */
    private function resolveIntegrations(Options $options): array
    {
        $defaults = [];
        if ($options->useDefaultIntegrations()) {
            $defaults = self::getDefaultIntegrations();
        }

        $userIntegrations = $options->getIntegrations();

        if ($userIntegrations === null) {
            return $defaults;
        }

        if ($userIntegrations instanceof \Closure) {
            return ($userIntegrations)($defaults);
        }

        return array_merge($defaults, $userIntegrations);
    }

    /** @return IntegrationInterface[] */
    public static function getDefaultIntegrations(): array
    {
        return [
            new \LogTide\Integration\ExceptionListenerIntegration(),
            new \LogTide\Integration\ErrorListenerIntegration(),
            new \LogTide\Integration\FatalErrorListenerIntegration(),
            new \LogTide\Integration\RequestIntegration(),
            new \LogTide\Integration\EnvironmentIntegration(),
        ];
    }
}
