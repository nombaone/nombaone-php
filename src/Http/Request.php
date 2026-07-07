<?php

declare(strict_types=1);

namespace NombaOne\Http;

/**
 * Internal description of one HTTP call. Resource methods produce these; the
 * {@see Transport} turns them into wire requests.
 *
 * @internal
 */
final class Request
{
    /**
     * @param string                    $method uppercase HTTP verb (GET, POST, PATCH, PUT, DELETE)
     * @param string                    $path   path below `/v1`, with already-encoded segments (e.g. `/customers/nbo…cus`)
     * @param array<string, mixed>      $query  query params; `null` and non-scalar values are dropped
     * @param array<string, mixed>|null $body   JSON request body, or null for no body
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly ?array $body = null,
        public readonly ?RequestOptions $options = null,
    ) {
    }
}
