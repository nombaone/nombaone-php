<?php

declare(strict_types=1);

namespace NombaOne\Http;

/**
 * Per-call options accepted by every SDK method as its last argument. Build one
 * from an associative array with {@see self::fromArray()}; unknown keys are
 * ignored.
 *
 * Recognized keys:
 * - `idempotencyKey` (string) — overrides the auto-generated `Idempotency-Key`
 *   on POSTs. The SDK generates a fresh UUID per call and reuses it across
 *   automatic retries, so a blip can never double-charge. Pass your own stable
 *   key when the operation must stay idempotent across process restarts (e.g. a
 *   payout keyed by your own transaction reference).
 * - `headers` (array<string, string|null>) — extra headers for this request,
 *   merged over the SDK defaults; a `null` value removes a default header.
 * - `timeout` (float) — per-attempt timeout in seconds for this call.
 * - `maxRetries` (int) — retry budget for this call (overrides the client default).
 */
final class RequestOptions
{
    /**
     * @param array<string, string|null>|null $headers
     */
    public function __construct(
        public readonly ?string $idempotencyKey = null,
        public readonly ?array $headers = null,
        public readonly ?float $timeout = null,
        public readonly ?int $maxRetries = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        $idempotencyKey = $options['idempotencyKey'] ?? null;
        $headers = $options['headers'] ?? null;
        $timeout = $options['timeout'] ?? null;
        $maxRetries = $options['maxRetries'] ?? null;

        return new self(
            idempotencyKey: is_string($idempotencyKey) ? $idempotencyKey : null,
            headers: self::normalizeHeaders($headers),
            timeout: is_int($timeout) || is_float($timeout) ? (float) $timeout : null,
            maxRetries: is_int($maxRetries) ? $maxRetries : null,
        );
    }

    /**
     * @return array<string, string|null>|null
     */
    private static function normalizeHeaders(mixed $headers): ?array
    {
        if (!is_array($headers)) {
            return null;
        }
        $out = [];
        foreach ($headers as $name => $value) {
            if ($value === null || is_string($value)) {
                $out[(string) $name] = $value;
            }
        }

        return $out;
    }
}
