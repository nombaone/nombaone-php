<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/**
 * 429 — slow down. Retry after {@see $retryAfter} seconds. The SDK already
 * retried automatically (honoring `Retry-After`); if this still surfaced, the
 * retry budget was exhausted.
 */
final class RateLimitException extends ApiException
{
    /**
     * @param array<string, list<string>>|null $fields
     */
    public function __construct(
        string $message,
        int $statusCode,
        string $errorCode,
        string $hint = '',
        string $docUrl = '',
        ?array $fields = null,
        ?string $requestId = null,
        /** Seconds until the current rate-limit window rolls over (`Retry-After`). */
        public readonly ?int $retryAfter = null,
        /** Your per-window request cap (`X-RateLimit-Limit`). */
        public readonly ?int $limit = null,
        /** Requests remaining in the current window (`X-RateLimit-Remaining`). */
        public readonly ?int $remaining = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $errorCode, $hint, $docUrl, $fields, $requestId, $previous);
    }
}
