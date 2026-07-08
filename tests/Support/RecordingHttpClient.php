<?php

declare(strict_types=1);

namespace NombaOne\Tests\Support;

use Http\Discovery\Psr17FactoryDiscovery;
use NombaOne\Exceptions\ConnectionException;
use NombaOne\Http\HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * A scripted {@see HttpClient} double. Every call is recorded (method, uri,
 * headers, decoded body, timeout) and answered from a FIFO queue of scripted
 * responses, so tests assert on exactly what the SDK put on the wire.
 */
final class RecordingHttpClient implements HttpClient
{
    /** @var list<RecordedCall> */
    public array $calls = [];

    /** @var list<\Closure(): ResponseInterface|\Throwable> */
    private array $queue = [];

    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct()
    {
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
    }

    public function send(RequestInterface $request, float $timeout): ResponseInterface
    {
        $headers = [];
        foreach (array_keys($request->getHeaders()) as $name) {
            $headers[strtolower((string) $name)] = $request->getHeaderLine((string) $name);
        }
        $rawBody = (string) $request->getBody();
        $body = null;
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            $body = is_array($decoded) ? $decoded : null;
        }

        $this->calls[] = new RecordedCall(
            $request->getMethod(),
            (string) $request->getUri(),
            $headers,
            $body,
            $timeout,
            $rawBody,
        );

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \RuntimeException(
                "RecordingHttpClient: no scripted response for {$request->getMethod()} {$request->getUri()}",
            );
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return ($next)();
    }

    /** Queue a NombaOne success envelope wrapping `$data`. */
    public function ok(mixed $data, int $status = 200, string $requestId = 'req_test'): self
    {
        return $this->respond($status, [
            'success' => true,
            'statusCode' => $status,
            'data' => $data,
            'meta' => ['requestId' => $requestId],
        ]);
    }

    /**
     * Queue a paginated success envelope.
     *
     * @param list<mixed>          $data
     * @param array<string, mixed> $pagination
     */
    public function page(array $data, array $pagination, string $requestId = 'req_test'): self
    {
        return $this->respond(200, [
            'success' => true,
            'statusCode' => 200,
            'data' => $data,
            'pagination' => array_merge(['limit' => count($data)], $pagination),
            'meta' => ['requestId' => $requestId],
        ]);
    }

    /**
     * Queue a NombaOne error envelope.
     *
     * @param array{code: string, message?: string, hint?: string, docUrl?: string, fields?: array<string, list<string>>} $error
     * @param array<string, string> $headers
     */
    public function fail(int $status, array $error, array $headers = []): self
    {
        $envelope = [
            'success' => false,
            'statusCode' => $status,
            'error' => array_merge([
                'message' => 'Something went wrong',
                'hint' => 'Try again.',
                'docUrl' => 'https://docs.nombaone.xyz/errors#' . $error['code'],
            ], $error),
            'meta' => ['requestId' => 'req_test'],
        ];

        return $this->respond($status, $envelope, $headers);
    }

    /**
     * Queue a raw response (body encoded to JSON unless already a string).
     *
     * @param array<string, string> $headers
     */
    public function respond(int $status, mixed $body, array $headers = []): self
    {
        $payload = is_string($body) ? $body : (string) json_encode($body);
        $this->queue[] = function () use ($status, $payload, $headers): ResponseInterface {
            $response = $this->responseFactory->createResponse($status)
                ->withHeader('Content-Type', 'application/json');
            foreach ($headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            return $response->withBody($this->streamFactory->createStream($payload));
        };

        return $this;
    }

    /** Queue a transport-level failure. */
    public function networkError(?\Throwable $error = null): self
    {
        $this->queue[] = $error ?? new ConnectionException('Simulated network failure');

        return $this;
    }
}
