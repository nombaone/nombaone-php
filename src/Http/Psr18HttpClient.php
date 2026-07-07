<?php

declare(strict_types=1);

namespace NombaOne\Http;

use NombaOne\Exceptions\ConnectionException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Adapts any PSR-18 HTTP client (Guzzle, Symfony HttpClient, …) to the SDK's
 * {@see HttpClient} seam.
 *
 * Note: PSR-18 has no per-request timeout, so the `$timeout` argument is
 * ignored here — the wrapped client's own configured timeout applies. Use the
 * default {@see CurlHttpClient} if you need the SDK's per-call `timeout` option
 * to take effect.
 */
final class Psr18HttpClient implements HttpClient
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function send(RequestInterface $request, float $timeout): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new ConnectionException(
                "Request failed to reach the NombaOne API: {$exception->getMessage()}",
                0,
                $exception,
            );
        }
    }
}
