<?php

declare(strict_types=1);

namespace LogTide;

/**
 * SDK identity stamped on every entry as `metadata.sdk` (spec 003 §3).
 * Keep SDK_VERSION in sync with the release tag.
 */
final class Version
{
    public const SDK_NAME = 'logtide-php';
    public const SDK_VERSION = '0.8.1';
}
