<?php

declare(strict_types=1);

namespace NombaOne\Http;

/**
 * Immutable transport settings resolved from the client options.
 *
 * @internal
 */
final class TransportConfig
{
    /**
     * @param string                           $baseUrl        origin only (e.g. `https://sandbox.api.nombaone.xyz`) — no `/v1`
     * @param float                            $timeout        per-attempt timeout in seconds
     * @param array<string, string|null>|null  $defaultHeaders headers sent on every request
     * @param (\Closure(int): void)|null       $sleeper        test seam for the retry backoff sleep; defaults to `usleep`
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly float $timeout,
        public readonly int $maxRetries,
        public readonly ?array $defaultHeaders = null,
        public readonly ?\Closure $sleeper = null,
    ) {
    }
}
