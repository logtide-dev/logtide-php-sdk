<?php

declare(strict_types=1);

namespace LogTide\Integration;

use LogTide\Enum\LogLevel;
use LogTide\LogtideSdk;
use LogTide\Serializer\ErrorSerializer;

final class ErrorListenerIntegration implements IntegrationInterface
{
    private mixed $previousHandler = null;
    private bool $registered = false;

    public function getName(): string
    {
        return 'error_listener';
    }

    public function setupOnce(): void
    {
        if ($this->registered) {
            return;
        }

        $this->previousHandler = set_error_handler(function (
            int $severity,
            string $message,
            string $file,
            int $line,
        ): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            $hub = LogtideSdk::getCurrentHub();
            $level = self::severityToLevel($severity);
            $error = ErrorSerializer::serializePhpError($severity, $message, $file, $line);

            $event = \LogTide\Event::createLog($level, $message);
            $event->setException($error);
            $hub->captureEvent($event);

            if ($this->previousHandler !== null) {
                return (bool) ($this->previousHandler)($severity, $message, $file, $line);
            }

            return false;
        });

        $this->registered = true;
    }

    public function teardown(): void
    {
        if ($this->registered) {
            restore_error_handler();
            $this->registered = false;
        }
    }

    private static function severityToLevel(int $severity): LogLevel
    {
        return match (true) {
            (bool) ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) => LogLevel::CRITICAL,
            (bool) ($severity & (E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_RECOVERABLE_ERROR)) => LogLevel::WARN,
            (bool) ($severity & (E_NOTICE | E_USER_NOTICE | E_STRICT | E_DEPRECATED | E_USER_DEPRECATED)) => LogLevel::INFO,
            default => LogLevel::ERROR,
        };
    }
}
