<?php

declare(strict_types=1);

namespace LogTide\Monolog;

use LogTide\Enum\LogLevel;
use LogTide\LogtideSdk;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class LogtideHandler extends AbstractProcessingHandler
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
        $client = $hub->getClient();
        if ($client === null) {
            return;
        }

        $level = self::mapLevel($record->level);
        $metadata = $record->context;

        if (!empty($record->extra)) {
            $metadata['extra'] = $record->extra;
        }

        $hub->captureLog($level, $record->message, $metadata);
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
