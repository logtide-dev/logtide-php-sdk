<?php

declare(strict_types=1);

namespace LogTide\Integration;

use LogTide\Event;
use LogTide\State\Scope;

final class EnvironmentIntegration implements IntegrationInterface
{
    public function getName(): string
    {
        return 'environment';
    }

    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event): Event {
            $runtime = [
                'name' => 'php',
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
            ];

            $os = [
                'name' => PHP_OS_FAMILY,
                'version' => php_uname('r'),
                'machine' => php_uname('m'),
            ];

            $event->addMetadata('runtime', $runtime);
            $event->addMetadata('os', $os);

            if ($hostname = gethostname()) {
                $event->addMetadata('server_name', $hostname);
            }

            return $event;
        });
    }

    public function teardown(): void
    {
    }
}
