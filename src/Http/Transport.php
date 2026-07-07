<?php

declare(strict_types=1);

namespace NombaOne\Http;

use NombaOne\ErrorCode;
use NombaOne\Exceptions\ApiException;
use NombaOne\Exceptions\ConnectionException;
use NombaOne\Version;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Executes one logical API call: builds the request, runs the retry loop,
 * parses the envelope, and either returns the unwrapped result or throws a
 * typed error.
 *
 * Money-safety invariants, enforced here and nowhere else:
 * - The `Idempotency-Key` for a POST is computed **once, before the retry
 *   loop**, so every automatic retry replays the same logical operation
 *   instead of creating a new one. A retry with a fresh key can double-move
 *   money — this is the single most important behavior in the SDK.
 * - Only transport failures, timeouts, 408/429/5xx, and our own in-flight
 *   idempotency conflict (409 `IDEMPOTENCY_IN_PROGRESS`) are retried; every
 *   other 4xx is surfaced immediately.
 *
 * @internal
 */
final class Transport
{
    private const API_PREFIX = '/v1';

    /** @var array<int, true> statuses retried unconditionally (plus 409 IDEMPOTENCY_IN_PROGRESS). */
    private const RETRYABLE_STATUSES = [408 => true, 429 => true, 500 => true, 502 => true, 503 => true, 504 => true];

    /** @var \Closure(int): void */
    private readonly \Closure $sleeper;

    public function __construct(
        private readonly TransportConfig $config,
        private readonly HttpClient $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        $this->sleeper = $config->sleeper ?? static function (int $milliseconds): void {
            if ($milliseconds > 0) {
                usleep($milliseconds * 1000);
            }
        };
    }

    public function send(Request $request): ApiResponse
    {
        $options = $request->options ?? new RequestOptions();
        $timeout = $options->timeout ?? $this->config->timeout;
        $maxRetries = max(0, $options->maxRetries ?? $this->config->maxRetries);

        $url = $this->config->baseUrl . self::API_PREFIX . $request->path . $this->buildQuery($request->query);

        // Headers — including the idempotency key — are computed ONCE, so every
        // automatic retry replays the same key. This is the money-safety invariant.
        $headers = $this->computeHeaders($request, $options);
        $body = $request->body !== null ? self::encodeJson($request->body) : null;

        for ($attempt = 0; ; $attempt++) {
            $psrRequest = $this->buildPsrRequest($request->method, $url, $headers, $body);

            try {
                $response = $this->httpClient->send($psrRequest, $timeout);
            } catch (ConnectionException $exception) {
                // Transport failures (including timeouts) are retried; a sync PHP
                // request has no user-cancellation concept to distinguish.
                if ($attempt >= $maxRetries) {
                    throw $exception;
                }
                ($this->sleeper)($this->backoffMs($attempt));

                continue;
            }

            $status = $response->getStatusCode();
            $decoded = self::decodeJson((string) $response->getBody());

            if ($status >= 200 && $status < 300) {
                return $this->parseSuccess($response, $decoded);
            }

            $error = ApiException::fromResponse($status, $decoded, $response);
            if ($attempt < $maxRetries && $this->isRetryable($status, $error)) {
                ($this->sleeper)($this->retryAfterMs($response) ?? $this->backoffMs($attempt));

                continue;
            }

            throw $error;
        }
    }

    /**
     * @param array<array-key, mixed>|null $decoded
     */
    private function parseSuccess(ResponseInterface $response, ?array $decoded): ApiResponse
    {
        if ($decoded === null || !array_key_exists('data', $decoded) || !is_array($decoded['data'])) {
            throw new ApiException(
                'The API returned a response that was not a valid NombaOne envelope.',
                $response->getStatusCode(),
                ErrorCode::SYSTEM_INTERNAL_ERROR,
                requestId: $this->headerRequestId($response),
            );
        }

        /** @var array<array-key, mixed> $data */
        $data = $decoded['data'];

        $pagination = null;
        if (isset($decoded['pagination']) && is_array($decoded['pagination'])) {
            $pagination = Pagination::fromArray($decoded['pagination']);
        }

        return new ApiResponse(
            $response,
            $this->successRequestId($decoded, $response),
            $data,
            $pagination,
        );
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private function successRequestId(array $decoded, ResponseInterface $response): string
    {
        if (isset($decoded['meta']) && is_array($decoded['meta'])) {
            $requestId = $decoded['meta']['requestId'] ?? null;
            if (is_string($requestId) && $requestId !== '') {
                return $requestId;
            }
        }

        return $this->headerRequestId($response) ?? '';
    }

    private function headerRequestId(ResponseInterface $response): ?string
    {
        $header = $response->getHeaderLine('X-Request-Id');

        return $header !== '' ? $header : null;
    }

    private function isRetryable(int $status, ApiException $error): bool
    {
        if (isset(self::RETRYABLE_STATUSES[$status])) {
            return true;
        }

        // Our own earlier attempt still holds the idempotency claim; replaying
        // the same key shortly resolves to that attempt's result.
        return $status === 409 && $error->errorCode === ErrorCode::IDEMPOTENCY_IN_PROGRESS;
    }

    /**
     * @return array<string, string>
     */
    private function computeHeaders(Request $request, RequestOptions $options): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->config->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'nombaone-php/' . Version::get(),
        ];
        if ($request->body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($request->method === 'POST') {
            $headers['Idempotency-Key'] = $options->idempotencyKey ?? self::generateIdempotencyKey();
        }

        return $this->mergeHeaders($headers, $this->config->defaultHeaders ?? [], $options->headers ?? []);
    }

    /**
     * Merge header layers left-to-right. Later layers win; a `null` value
     * deletes the header (letting callers strip an SDK default). Names are
     * case-insensitive, so they are de-duplicated on a lowercased key.
     *
     * @param array<string, string|null> ...$layers
     *
     * @return array<string, string>
     */
    private function mergeHeaders(array ...$layers): array
    {
        /** @var array<string, array{0: string, 1: string}> $merged */
        $merged = [];
        foreach ($layers as $layer) {
            foreach ($layer as $name => $value) {
                $key = strtolower($name);
                if ($value === null) {
                    unset($merged[$key]);
                } else {
                    $merged[$key] = [$name, $value];
                }
            }
        }

        $result = [];
        foreach ($merged as [$name, $value]) {
            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildQuery(array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $pairs[] = rawurlencode($key) . '=' . ($value ? 'true' : 'false');

                continue;
            }
            if (is_scalar($value)) {
                $pairs[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
            }
        }

        return $pairs === [] ? '' : '?' . implode('&', $pairs);
    }

    /**
     * @param array<string, string> $headers
     */
    private function buildPsrRequest(string $method, string $url, array $headers, ?string $body): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    /** A fresh UUID v4, used as the automatic `Idempotency-Key`. */
    private static function generateIdempotencyKey(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /** Honor `Retry-After` (delta-seconds or HTTP-date) as milliseconds, or null. */
    private function retryAfterMs(ResponseInterface $response): ?int
    {
        $raw = $response->getHeaderLine('Retry-After');
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $seconds = (float) $raw;

            return $seconds >= 0 ? (int) round($seconds * 1000) : null;
        }
        $timestamp = strtotime($raw);
        if ($timestamp !== false) {
            return max(0, ($timestamp - time()) * 1000);
        }

        return null;
    }

    /**
     * Full-jitter exponential backoff: a random delay in
     * `[0, min(8000, 500 * 2^attempt))` milliseconds. Jitter keeps a fleet of
     * retrying clients from stampeding the API in lockstep.
     */
    private function backoffMs(int $attempt): int
    {
        $cap = min(8000, (int) (500 * (2 ** $attempt)));

        return $cap <= 0 ? 0 : random_int(0, $cap);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encodeJson(array $data): string
    {
        // Every request body is a JSON object. PHP encodes an empty array as
        // `[]` (a JSON array), which the API rejects — force `{}` so no-body
        // POSTs (cancel/pause/resume/archive/…) send a valid empty object.
        if ($data === []) {
            return '{}';
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decodeJson(string $body): ?array
    {
        if ($body === '') {
            return null;
        }
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
