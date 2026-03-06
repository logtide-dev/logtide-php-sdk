<?php

declare(strict_types=1);

namespace LogTide\Enum;

enum SpanKind: string
{
    case INTERNAL = 'INTERNAL';
    case SERVER = 'SERVER';
    case CLIENT = 'CLIENT';
    case PRODUCER = 'PRODUCER';
    case CONSUMER = 'CONSUMER';
}
