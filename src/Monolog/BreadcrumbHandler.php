<?php

declare(strict_types=1);

namespace LogTide\Monolog;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\Enum\BreadcrumbType;
use LogTide\Enum\LogLevel;
use LogTide\LogtideSdk;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class BreadcrumbHandler extends AbstractProcessingHandler
{
    public function __construct(
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $hub = LogtideSdk::getCurrentHub();

        $breadcrumb = new Breadcrumb(
            type: BreadcrumbType::CONSOLE,
            message: $record->message,
            category: $record->channel,
            level: self::mapLevel($record->level),
            data: $record->context,
        );

        $hub->addBreadcrumb($breadcrumb);
    }

    private static function mapLevel(Level $level): LogLevel
    {
        return match (true) {
            $level->value >= Level::Critical->value => LogLevel::CRITICAL,
            $level->value >= Level::Error->value => LogLevel::ERROR,
            $level->value >= Level::Warning->value => LogLevel::WARN,
            $level->value >= Level::Info->value => LogLevel::INFO,
            default => LogLevel::DEBUG,
        };
    }
}
