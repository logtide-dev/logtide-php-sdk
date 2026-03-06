<?php

declare(strict_types=1);

namespace LogTide\Enum;

enum SpanStatus: string
{
    case UNSET = 'UNSET';
    case OK = 'OK';
    case ERROR = 'ERROR';
}
