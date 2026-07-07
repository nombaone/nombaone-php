<?php

declare(strict_types=1);

namespace NombaOne\Tests\Support;

use Http\Discovery\Psr17FactoryDiscovery;
use NombaOne\Http\HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * An {@see HttpClient} that answers every request with a generic, valid
 * envelope and records the `method` + `path` of each call. Used by the
 * conformance suite to exercise every SDK method without scripting responses.
 */
final class AutoRespondingHttpClient implements HttpClient
{
    /** @var list<array{method: string, path: string}> */
    public array $calls = [];

    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct()
    {
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
    }

    public function send(RequestInterface $request, float $timeout): ResponseInterface
    {
        $this->calls[] = [
            'method' => strtolower($request->getMethod()),
            'path' => $request->getUri()->getPath(),
        ];

        $body = (string) json_encode([
            'success' => true,
            'statusCode' => 200,
            'data' => [],
            'pagination' => ['limit' => 20, 'hasMore' => false, 'nextCursor' => null],
            'meta' => ['requestId' => 'req_conformance'],
        ]);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));
    }
}
