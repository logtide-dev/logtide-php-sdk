<?php

declare(strict_types=1);

namespace LogTide\Integration;

interface IntegrationInterface
{
    public function getName(): string;

    public function setupOnce(): void;

    public function teardown(): void;
}
