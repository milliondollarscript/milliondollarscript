<?php

declare(strict_types=1);

namespace MillionDollarScript\V3\Support;

/**
 * Which build of this plugin is running.
 *
 * The version itself lives in the plugin bootstrap and in mds3.version.json, which the
 * release tooling bumps together. This is the only reader, so a record written by one
 * build can be attributed to that build wherever the record is read - a migration run,
 * a report, or a support export. The fallback is explicit: the runtime test entry point
 * loads src/ without the bootstrap, and an unknown build must read as unknown rather
 * than as an invented version.
 */
final class Build
{
    public static function version(): string
    {
        return defined('MILLION_DOLLAR_SCRIPT_VERSION')
            ? (string) MILLION_DOLLAR_SCRIPT_VERSION
            : 'unknown';
    }
}
