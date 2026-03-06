<?php

declare(strict_types=1);

namespace LogTide\Enum;

enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARN = 'warn';
    case ERROR = 'error';
    case CRITICAL = 'critical';
}
