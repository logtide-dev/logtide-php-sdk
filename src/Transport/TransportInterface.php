<?php

declare(strict_types=1);

namespace LogTide\Transport;

use LogTide\Event;
use LogTide\Tracing\Span;

interface TransportInterface
{
    /** @param Event[] $events */
    public function sendLogs(array $events): void;

    /** @param Span[] $spans */
    public function sendSpans(array $spans): void;

    public function flush(): void;

    public function close(): void;
}
