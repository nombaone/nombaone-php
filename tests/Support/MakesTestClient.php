<?php

declare(strict_types=1);

namespace NombaOne\Tests\Support;

use NombaOne\Nombaone;

/**
 * Builds a {@see Nombaone} client wired to a {@see RecordingHttpClient}, with a
 * fixed sandbox key, a test host, and a no-op retry sleeper so retry tests run
 * instantly.
 */
trait MakesTestClient
{
    /**
     * @param array<string, mixed> $options
     */
    protected function makeClient(RecordingHttpClient $http, array $options = []): Nombaone
    {
        return new Nombaone('nbo_sandbox_unit_test_key', array_merge([
            'httpClient' => $http,
            'baseUrl' => 'http://api.test',
            'sleeper' => static function (int $milliseconds): void {
            },
        ], $options));
    }
}
