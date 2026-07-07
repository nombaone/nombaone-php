<?php

declare(strict_types=1);

namespace NombaOne;

use Composer\InstalledVersions;

/**
 * The installed SDK version, surfaced in the `User-Agent` header
 * (`nombaone-php/<version>`) so requests are attributable in the dashboard.
 *
 * The single source of truth for the released version is the **git tag**
 * (Packagist derives the package version from it). At runtime we read that back
 * from Composer's installed metadata rather than hard-coding a second copy;
 * {@see FALLBACK} is used only when running from source (no installed tag).
 */
final class Version
{
    /** Dev fallback used only when no installed tag is available (running from source). */
    public const FALLBACK = '0.1.0';

    private function __construct()
    {
    }

    /** The resolved SDK version — the installed tag, or {@see FALLBACK} when run from source. */
    public static function get(): string
    {
        if (class_exists(InstalledVersions::class)) {
            try {
                $version = InstalledVersions::getPrettyVersion('nombaone/nombaone-php');
            } catch (\OutOfBoundsException) {
                $version = null;
            }
            if (is_string($version)) {
                $version = ltrim($version, 'v');
                if (preg_match('/^\d+\.\d+\.\d+/', $version) === 1) {
                    return $version;
                }
            }
        }

        return self::FALLBACK;
    }
}
