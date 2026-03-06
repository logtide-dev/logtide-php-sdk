<?php

declare(strict_types=1);

namespace LogTide;

use LogTide\State\Hub;
use LogTide\State\HubInterface;

final class LogtideSdk
{
    private static ?HubInterface $currentHub = null;

    private function __construct()
    {
    }

    public static function init(array $config): HubInterface
    {
        $builder = ClientBuilder::create($config);
        $client = $builder->getClient();

        $hub = new Hub($client);
        self::$currentHub = $hub;

        register_shutdown_function(function () use ($hub): void {
            $hub->flush();
        });

        return $hub;
    }

    public static function getCurrentHub(): HubInterface
    {
        if (self::$currentHub === null) {
            self::$currentHub = new Hub();
        }
        return self::$currentHub;
    }

    public static function setCurrentHub(HubInterface $hub): void
    {
        self::$currentHub = $hub;
    }

    public static function isInitialized(): bool
    {
        return self::$currentHub !== null && self::$currentHub->getClient() !== null;
    }

    public static function reset(): void
    {
        if (self::$currentHub !== null) {
            self::$currentHub->getClient()?->close();
        }
        self::$currentHub = null;
        Integration\IntegrationRegistry::reset();
    }
}
