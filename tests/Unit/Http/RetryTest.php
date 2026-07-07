<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit\Http;

use NombaOne\Exceptions\ConflictException;
use NombaOne\Exceptions\ConnectionException;
use NombaOne\Exceptions\NotFoundException;
use NombaOne\Exceptions\ServerException;
use NombaOne\Http\Request;
use NombaOne\Http\RequestOptions;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RetryTest extends TestCase
{
    use MakesTestClient;

    public function testRetriesOn5xxThenSucceeds(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(500, ['code' => 'SYSTEM_INTERNAL_ERROR'])
            ->fail(502, ['code' => 'SYSTEM_UPSTREAM_ERROR'])
            ->ok(['id' => 'nbo1']);

        $response = $this->makeClient($http)->send(new Request('POST', '/subscriptions', body: []));

        $this->assertCount(3, $http->calls);
        $this->assertSame('nbo1', $response->data['id']);
    }

    public function testRetriesOnTransportFailure(): void
    {
        $http = new RecordingHttpClient();
        $http->networkError()->ok(['id' => 'nbo1']);

        $response = $this->makeClient($http)->send(new Request('GET', '/customers/x'));

        $this->assertCount(2, $http->calls);
        $this->assertSame('nbo1', $response->data['id']);
    }

    public function testGivesUpAfterMaxRetriesOnPersistentServerError(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(500, ['code' => 'SYSTEM_INTERNAL_ERROR'])
            ->fail(500, ['code' => 'SYSTEM_INTERNAL_ERROR'])
            ->fail(500, ['code' => 'SYSTEM_INTERNAL_ERROR']);

        try {
            $this->makeClient($http)->send(new Request('GET', '/customers'));
            $this->fail('Expected a ServerException.');
        } catch (ServerException $e) {
            $this->assertSame(500, $e->statusCode);
            $this->assertCount(3, $http->calls); // 1 + 2 retries
        }
    }

    public function testGivesUpAfterMaxRetriesOnPersistentTransportFailure(): void
    {
        $http = new RecordingHttpClient();
        $http->networkError()->networkError()->networkError();

        $this->expectException(ConnectionException::class);
        try {
            $this->makeClient($http)->send(new Request('GET', '/customers'));
        } finally {
            $this->assertCount(3, $http->calls);
        }
    }

    public function testDoesNotRetryOtherClientErrors(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(404, ['code' => 'CUSTOMER_NOT_FOUND']);

        try {
            $this->makeClient($http)->send(new Request('GET', '/customers/x'));
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('CUSTOMER_NOT_FOUND', $e->errorCode);
            $this->assertCount(1, $http->calls);
        }
    }

    public function testRetries409OnlyWhenIdempotencyInProgress(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(409, ['code' => 'IDEMPOTENCY_IN_PROGRESS'])->ok(['id' => 'nbo1']);

        $response = $this->makeClient($http)->send(new Request('POST', '/subscriptions', body: []));

        $this->assertCount(2, $http->calls);
        $this->assertSame('nbo1', $response->data['id']);
    }

    public function testDoesNotRetryOtherConflicts(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(409, ['code' => 'CUSTOMER_EMAIL_TAKEN']);

        try {
            $this->makeClient($http)->send(new Request('POST', '/customers', body: []));
            $this->fail('Expected a ConflictException.');
        } catch (ConflictException $e) {
            $this->assertSame('CUSTOMER_EMAIL_TAKEN', $e->errorCode);
            $this->assertCount(1, $http->calls);
        }
    }

    public function testHonorsPerCallMaxRetriesOverride(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(500, ['code' => 'SYSTEM_INTERNAL_ERROR'])->ok(['id' => 'nbo1']);

        $response = $this->makeClient($http)->send(new Request(
            'GET',
            '/customers',
            options: new RequestOptions(maxRetries: 5),
        ));

        $this->assertCount(2, $http->calls);
        $this->assertSame('nbo1', $response->data['id']);
    }

    public function testHonorsRetryAfterHeaderWithoutFailing(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(429, ['code' => 'RATE_LIMIT_EXCEEDED'], ['Retry-After' => '0'])
            ->ok(['id' => 'nbo1']);

        $response = $this->makeClient($http)->send(new Request('GET', '/customers'));

        $this->assertCount(2, $http->calls);
        $this->assertSame('nbo1', $response->data['id']);
    }
}
