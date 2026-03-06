<?php

declare(strict_types=1);

namespace LogTide\Integration;

use LogTide\LogtideSdk;

final class ExceptionListenerIntegration implements IntegrationInterface
{
    private mixed $previousHandler = null;
    private bool $registered = false;

    public function getName(): string
    {
        return 'exception_listener';
    }

    public function setupOnce(): void
    {
        if ($this->registered) {
            return;
        }

        $this->previousHandler = set_exception_handler(function (\Throwable $exception): void {
            $hub = LogtideSdk::getCurrentHub();
            $hub->captureException($exception);

            if ($this->previousHandler !== null) {
                ($this->previousHandler)($exception);
            }
        });

        $this->registered = true;
    }

    public function teardown(): void
    {
        if ($this->registered) {
            restore_exception_handler();
            $this->registered = false;
        }
    }
}
