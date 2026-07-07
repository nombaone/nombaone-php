<?php

declare(strict_types=1);

namespace NombaOne\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The full result of one API call: the unwrapped envelope `data`, the
 * `requestId`, the top-level pagination block (on list responses), and the raw
 * PSR-7 response for headers and status.
 *
 * Every model returned by the SDK carries the {@see ApiResponse} that produced
 * it, reachable via `getLastResponse()` — the PHP equivalent of "give me the
 * request id and raw headers, not just the resource".
 */
final class ApiResponse
{
    /**
     * @param array<array-key, mixed> $data the unwrapped envelope `data` (an object, or a list on list responses)
     */
    public function __construct(
        public readonly ResponseInterface $httpResponse,
        public readonly string $requestId,
        public readonly array $data,
        public readonly ?Pagination $pagination = null,
    ) {
    }

    /** The HTTP status code of the response. */
    public function statusCode(): int
    {
        return $this->httpResponse->getStatusCode();
    }

    /** A single response header, or null when absent. */
    public function header(string $name): ?string
    {
        $value = $this->httpResponse->getHeaderLine($name);

        return $value === '' ? null : $value;
    }
}
