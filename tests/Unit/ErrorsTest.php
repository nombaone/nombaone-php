<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit;

use NombaOne\ErrorCode;
use NombaOne\Exceptions\ApiException;
use NombaOne\Exceptions\AuthenticationException;
use NombaOne\Exceptions\BadRequestException;
use NombaOne\Exceptions\ConflictException;
use NombaOne\Exceptions\NotFoundException;
use NombaOne\Exceptions\PermissionDeniedException;
use NombaOne\Exceptions\RateLimitException;
use NombaOne\Exceptions\ServerException;
use NombaOne\Exceptions\ValidationException;
use NombaOne\Http\Request;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ErrorsTest extends TestCase
{
    use MakesTestClient;

    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function statusToClass(): iterable
    {
        yield '400' => [400, BadRequestException::class];
        yield '401' => [401, AuthenticationException::class];
        yield '403' => [403, PermissionDeniedException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, ValidationException::class];
        yield '500' => [500, ServerException::class];
        yield '503' => [503, ServerException::class];
    }

    /**
     * @param class-string<ApiException> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('statusToClass')]
    public function testMapsStatusToTypedSubclass(int $status, string $expected): void
    {
        $http = new RecordingHttpClient();
        $http->fail($status, ['code' => 'SOME_CODE']);

        try {
            $this->makeClient($http, ['maxRetries' => 0])->send(new Request('GET', '/x'));
            $this->fail("Expected {$expected}.");
        } catch (ApiException $e) {
            $this->assertInstanceOf($expected, $e);
            $this->assertSame($status, $e->statusCode);
        }
    }

    public function testFoldsHintIntoTheMessageAndExposesAllFields(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(404, [
            'code' => 'CUSTOMER_NOT_FOUND',
            'message' => 'No customer with that id',
            'hint' => 'Check the id and that your key matches the environment.',
            'docUrl' => 'https://docs.nombaone.com/errors#CUSTOMER_NOT_FOUND',
        ]);

        try {
            $this->makeClient($http, ['maxRetries' => 0])->send(new Request('GET', '/customers/x'));
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame(ErrorCode::CUSTOMER_NOT_FOUND, $e->errorCode);
            $this->assertSame('Check the id and that your key matches the environment.', $e->hint);
            $this->assertSame('https://docs.nombaone.com/errors#CUSTOMER_NOT_FOUND', $e->docUrl);
            $this->assertStringContainsString('No customer with that id', $e->getMessage());
            $this->assertStringContainsString($e->hint, $e->getMessage());
            $this->assertSame('req_test', $e->requestId);
        }
    }

    public function testExposesPerFieldValidationErrorsOn422(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(422, [
            'code' => 'CLIENT_VALIDATION_FAILED',
            'fields' => ['email' => ['Invalid email'], 'name' => ['Required']],
        ]);

        try {
            $this->makeClient($http, ['maxRetries' => 0])->send(new Request('POST', '/customers', body: []));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['email' => ['Invalid email'], 'name' => ['Required']], $e->fields);
        }
    }

    public function testExposesRateLimitMetadataFromHeaders(): void
    {
        $http = new RecordingHttpClient();
        $http->fail(429, ['code' => 'RATE_LIMIT_EXCEEDED'], [
            'Retry-After' => '7',
            'X-RateLimit-Limit' => '60',
            'X-RateLimit-Remaining' => '0',
        ]);

        try {
            $this->makeClient($http, ['maxRetries' => 0])->send(new Request('GET', '/customers'));
            $this->fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            $this->assertSame(7, $e->retryAfter);
            $this->assertSame(60, $e->limit);
            $this->assertSame(0, $e->remaining);
        }
    }

    public function testFallsBackToDefaultCodeWhenBodyIsNotJson(): void
    {
        // A proxy 502 HTML page must degrade to a typed ServerError, never crash.
        $http = new RecordingHttpClient();
        $http->respond(502, '<html><body>Bad Gateway</body></html>');

        try {
            $this->makeClient($http, ['maxRetries' => 0])->send(new Request('GET', '/customers'));
            $this->fail('Expected a ServerException.');
        } catch (ServerException $e) {
            $this->assertSame(ErrorCode::SYSTEM_UPSTREAM_ERROR, $e->errorCode);
            $this->assertSame(502, $e->statusCode);
        }
    }

    public function testFallsBackToRequestIdHeaderWhenBodyLacksMeta(): void
    {
        $http = new RecordingHttpClient();
        $http->respond(500, '', ['X-Request-Id' => 'req_from_header']);

        try {
            $this->makeClient($http, ['maxRetries' => 0])->send(new Request('GET', '/customers'));
            $this->fail('Expected a ServerException.');
        } catch (ServerException $e) {
            $this->assertSame('req_from_header', $e->requestId);
            $this->assertSame(ErrorCode::SYSTEM_INTERNAL_ERROR, $e->errorCode);
        }
    }

    public function testKnownErrorCodeCatalogHasSeventyTwoEntries(): void
    {
        $this->assertCount(72, ErrorCode::all());
        $this->assertTrue(ErrorCode::isKnown(ErrorCode::API_KEY_HOST_MISMATCH));
        $this->assertFalse(ErrorCode::isKnown('SOME_FUTURE_CODE'));
    }
}
