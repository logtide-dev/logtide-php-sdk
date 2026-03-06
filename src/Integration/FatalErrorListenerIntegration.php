<?php

declare(strict_types=1);

namespace LogTide\Integration;

use LogTide\Enum\LogLevel;
use LogTide\LogtideSdk;
use LogTide\Serializer\ErrorSerializer;

final class FatalErrorListenerIntegration implements IntegrationInterface
{
    private static bool $registered = false;
    private static string $reservedMemory = '';

    private const FATAL_ERRORS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    public function getName(): string
    {
        return 'fatal_error_listener';
    }

    public function setupOnce(): void
    {
        if (self::$registered) {
            return;
        }

        // Reserve 16KB to handle fatal errors even under memory pressure
        self::$reservedMemory = str_repeat('x', 16384);

        register_shutdown_function(function (): void {
            self::$reservedMemory = '';

            $error = error_get_last();
            if ($error === null || !($error['type'] & self::FATAL_ERRORS)) {
                return;
            }

            $hub = LogtideSdk::getCurrentHub();
            $serialized = ErrorSerializer::serializePhpError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line'],
            );

            $event = \LogTide\Event::createLog(LogLevel::CRITICAL, $error['message']);
            $event->setException($serialized);
            $hub->captureEvent($event);
            $hub->flush();
        });

        self::$registered = true;
    }

    public function teardown(): void
    {
        // Shutdown functions cannot be unregistered
    }
}
