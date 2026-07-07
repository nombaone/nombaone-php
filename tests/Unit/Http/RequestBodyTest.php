<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit\Http;

use NombaOne\Http\Request;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RequestBodyTest extends TestCase
{
    use MakesTestClient;

    public function testEmptyBodyPostSerializesAsAJsonObjectNotArray(): void
    {
        // PHP's json_encode([]) is "[]", which the API rejects. No-body POSTs
        // (cancel/pause/resume/archive/…) must send "{}".
        $http = new RecordingHttpClient();
        $http->ok([]);

        $this->makeClient($http)->send(new Request('POST', '/subscriptions/x/cancel', body: []));

        $this->assertSame('{}', $http->calls[0]->rawBody);
    }

    public function testResumeThroughAResourceSendsAnEmptyJsonObject(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([]);

        $this->makeClient($http)->subscriptions->resume('nbo1sub');

        $this->assertSame('{}', $http->calls[0]->rawBody);
    }

    public function testNonEmptyBodySerializesAsAJsonObject(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([]);

        $this->makeClient($http)->send(new Request('POST', '/customers', body: ['email' => 'a@b.co', 'name' => 'A']));

        $this->assertSame('{"email":"a@b.co","name":"A"}', $http->calls[0]->rawBody);
    }

    public function testGetRequestSendsNoBody(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([]);

        $this->makeClient($http)->send(new Request('GET', '/customers'));

        $this->assertSame('', $http->calls[0]->rawBody);
        $this->assertNull($http->calls[0]->header('Content-Type'));
    }
}
