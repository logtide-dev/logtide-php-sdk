<?php

declare(strict_types=1);

namespace LogTide\Transport;

final class NullTransport implements TransportInterface
{
    public function sendLogs(array $events): void
    {
    }

    public function sendSpans(array $spans): void
    {
    }

    public function flush(): void
    {
    }

    public function close(): void
    {
    }
}
