<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit;

use NombaOne\Version;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testPackageAutoloadsAndExposesVersion(): void
    {
        // Resolves the installed tag, or the dev fallback when run from source —
        // either way a semver string.
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', Version::get());
    }
}
