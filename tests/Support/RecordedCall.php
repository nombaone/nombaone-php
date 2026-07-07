<?php

declare(strict_types=1);

namespace NombaOne\Tests\Support;

/**
 * One request captured by {@see RecordingHttpClient}, decoded for easy assertion.
 */
final class RecordedCall
{
    /**
     * @param array<string, string>        $headers header names lowercased
     * @param array<array-key, mixed>|null $body    decoded JSON body, or null
     * @param string                       $rawBody the exact serialized request body
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $headers,
        public readonly ?array $body,
        public readonly float $timeout,
        public readonly string $rawBody = '',
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** The path + query portion of the URI (everything after the origin). */
    public function pathWithQuery(): string
    {
        $parts = parse_url($this->uri);
        $path = is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : '';
        $query = is_array($parts) && isset($parts['query']) ? '?' . (string) $parts['query'] : '';

        return $path . $query;
    }
}
