<?php

declare(strict_types=1);

namespace LogTide\Enum;

enum BreadcrumbType: string
{
    case HTTP = 'http';
    case NAVIGATION = 'navigation';
    case CONSOLE = 'console';
    case ERROR = 'error';
    case QUERY = 'query';
    case CUSTOM = 'custom';
}
