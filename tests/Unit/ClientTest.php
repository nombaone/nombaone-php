<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit;

use NombaOne\Enums\Mode;
use NombaOne\Exceptions\NombaOneException;
use NombaOne\Nombaone;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ClientTest extends TestCase
{
    use MakesTestClient;

    public function testDerivesSandboxHostFromKeyPrefix(): void
    {
        $client = new Nombaone('nbo_sandbox_abc', ['httpClient' => new RecordingHttpClient()]);

        $this->assertSame(Mode::Sandbox, $client->mode);
        $this->assertSame('https://sandbox.api.nombaone.xyz', $client->baseUrl);
    }

    public function testDerivesLiveHostFromKeyPrefix(): void
    {
        $client = new Nombaone('nbo_live_abc', ['httpClient' => new RecordingHttpClient()]);

        $this->assertSame(Mode::Live, $client->mode);
        $this->assertSame('https://api.nombaone.xyz', $client->baseUrl);
    }

    public function testExplicitBaseUrlWinsAndTrailingSlashesAreTrimmed(): void
    {
        $client = new Nombaone('nbo_sandbox_abc', [
            'httpClient' => new RecordingHttpClient(),
            'baseUrl' => 'http://localhost:8611///',
        ]);

        $this->assertSame('http://localhost:8611', $client->baseUrl);
        $this->assertSame(Mode::Sandbox, $client->mode);
    }

    public function testMissingKeyThrowsActionableError(): void
    {
        $previous = getenv('NOMBAONE_API_KEY');
        putenv('NOMBAONE_API_KEY');

        try {
            $this->expectException(NombaOneException::class);
            $this->expectExceptionMessageMatches('/Missing API key/');
            new Nombaone(null, ['httpClient' => new RecordingHttpClient()]);
        } finally {
            if (is_string($previous)) {
                putenv("NOMBAONE_API_KEY={$previous}");
            }
        }
    }

    public function testUnknownPrefixWithoutBaseUrlThrows(): void
    {
        $this->expectException(NombaOneException::class);
        $this->expectExceptionMessageMatches('/Unrecognized API key format/');

        new Nombaone('sk_live_wrong_vendor', ['httpClient' => new RecordingHttpClient()]);
    }

    public function testUnknownPrefixWithBaseUrlIsAllowedAndDefaultsToSandbox(): void
    {
        $client = new Nombaone('sk_live_wrong_vendor', [
            'httpClient' => new RecordingHttpClient(),
            'baseUrl' => 'http://api.test',
        ]);

        $this->assertSame(Mode::Sandbox, $client->mode);
        $this->assertSame('http://api.test', $client->baseUrl);
    }

    public function testKeyArgumentCanBePassedAsOptionsArray(): void
    {
        $client = new Nombaone(['apiKey' => 'nbo_live_abc', 'httpClient' => new RecordingHttpClient()]);

        $this->assertSame(Mode::Live, $client->mode);
    }

    public function testReadsKeyFromEnvironmentWhenNotPassed(): void
    {
        $previous = getenv('NOMBAONE_API_KEY');
        putenv('NOMBAONE_API_KEY=nbo_sandbox_from_env');

        try {
            $client = new Nombaone(null, ['httpClient' => new RecordingHttpClient()]);
            $this->assertSame(Mode::Sandbox, $client->mode);
        } finally {
            if (is_string($previous)) {
                putenv("NOMBAONE_API_KEY={$previous}");
            } else {
                putenv('NOMBAONE_API_KEY');
            }
        }
    }
}
