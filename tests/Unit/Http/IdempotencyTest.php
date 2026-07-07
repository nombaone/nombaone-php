<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit\Http;

use NombaOne\Http\Request;
use NombaOne\Http\RequestOptions;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class IdempotencyTest extends TestCase
{
    use MakesTestClient;

    private const UUID_RE = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function testAutoGeneratesAUuidIdempotencyKeyOnEveryPost(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([]);

        $this->makeClient($http)->send(new Request('POST', '/customers', body: ['name' => 'Ada']));

        $this->assertMatchesRegularExpression(self::UUID_RE, (string) $http->calls[0]->header('Idempotency-Key'));
    }

    public function testReusesTheSameKeyAcrossAutomaticRetries(): void
    {
        // The money-safety invariant: a retried POST must never mint a new key.
        $http = new RecordingHttpClient();
        $http->fail(500, ['code' => 'SYSTEM_INTERNAL_ERROR'])
            ->fail(503, ['code' => 'SYSTEM_UPSTREAM_ERROR'])
            ->ok([]);

        $this->makeClient($http)->send(new Request('POST', '/subscriptions', body: []));

        $keys = array_map(static fn ($call) => $call->header('Idempotency-Key'), $http->calls);
        $this->assertCount(3, $keys);
        $this->assertMatchesRegularExpression(self::UUID_RE, (string) $keys[0]);
        $this->assertCount(1, array_unique($keys));
    }

    public function testGeneratesAFreshKeyForEachSeparateLogicalCall(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([])->ok([]);

        $client = $this->makeClient($http);
        $client->send(new Request('POST', '/customers', body: []));
        $client->send(new Request('POST', '/customers', body: []));

        $this->assertNotSame($http->calls[0]->header('Idempotency-Key'), $http->calls[1]->header('Idempotency-Key'));
    }

    public function testHonorsAnExplicitIdempotencyKey(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([]);

        $this->makeClient($http)->send(new Request(
            'POST',
            '/settlements/payout',
            body: ['amountInKobo' => 100_000],
            options: new RequestOptions(idempotencyKey: 'payout-2026-07-05-001'),
        ));

        $this->assertSame('payout-2026-07-05-001', $http->calls[0]->header('Idempotency-Key'));
    }

    public function testDoesNotAttachAKeyToGetPatchOrDelete(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([])->ok([])->ok([]);

        $client = $this->makeClient($http);
        $client->send(new Request('GET', '/customers'));
        $client->send(new Request('PATCH', '/customers/x', body: []));
        $client->send(new Request('DELETE', '/customers/x/discount'));

        foreach ($http->calls as $call) {
            $this->assertNull($call->header('Idempotency-Key'));
        }
    }
}
