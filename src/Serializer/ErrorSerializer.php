<?php

declare(strict_types=1);

namespace LogTide\Serializer;

final class ErrorSerializer
{
    public static function serialize(\Throwable $exception): array
    {
        $result = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stacktrace' => self::parseStacktrace($exception),
        ];

        if ($previous = $exception->getPrevious()) {
            $result['cause'] = self::serialize($previous);
        }

        return $result;
    }

    public static function parseStacktrace(\Throwable $exception): array
    {
        $frames = [];

        foreach ($exception->getTrace() as $frame) {
            $entry = [];

            if (isset($frame['file'])) {
                $entry['file'] = $frame['file'];
            }
            if (isset($frame['line'])) {
                $entry['line'] = $frame['line'];
            }
            if (isset($frame['class'])) {
                $entry['class'] = $frame['class'];
            }
            if (isset($frame['function'])) {
                $entry['function'] = $frame['function'];
            }
            if (isset($frame['type'])) {
                $entry['type'] = $frame['type'];
            }

            $frames[] = $entry;
        }

        return $frames;
    }

    public static function serializePhpError(int $severity, string $message, string $file, int $line): array
    {
        return [
            'type' => self::errorLevelToString($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ];
    }

    private static function errorLevelToString(int $level): string
    {
        return match ($level) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'E_ERROR',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'E_WARNING',
            E_NOTICE, E_USER_NOTICE => 'E_NOTICE',
            E_STRICT => 'E_STRICT',
            E_DEPRECATED, E_USER_DEPRECATED => 'E_DEPRECATED',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            default => 'E_UNKNOWN',
        };
    }
}
