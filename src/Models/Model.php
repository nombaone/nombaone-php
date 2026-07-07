<?php

declare(strict_types=1);

namespace NombaOne\Models;

use NombaOne\Http\ApiResponse;

/**
 * Base class for every resource object the SDK returns.
 *
 * Models are hydrated tolerantly: known fields become typed readonly
 * properties, and the complete decoded payload is kept on {@see $raw} so a
 * field the API adds tomorrow is still reachable today. Each returned model
 * also carries the {@see ApiResponse} that produced it (request id + raw
 * headers), via {@see getLastResponse()}.
 */
abstract class Model implements \JsonSerializable
{
    private ?ApiResponse $lastResponse = null;

    /**
     * @param array<array-key, mixed> $raw the full decoded envelope `data`
     */
    public function __construct(public readonly array $raw = [])
    {
    }

    /**
     * Hydrate a model from a decoded envelope `data` object.
     *
     * @param array<array-key, mixed> $data
     */
    abstract public static function fromArray(array $data): static;

    /** The full response (request id, headers, status) that produced this object. */
    public function getLastResponse(): ?ApiResponse
    {
        return $this->lastResponse;
    }

    /** The request id that produced this object — quote it to support. */
    public function requestId(): ?string
    {
        return $this->lastResponse?->requestId;
    }

    /**
     * @internal Attach the originating response. Called by the SDK after hydration.
     */
    public function withLastResponse(ApiResponse $response): static
    {
        $this->lastResponse = $response;

        return $this;
    }

    /**
     * The exact decoded payload the API sent — including any fields this SDK
     * version does not model yet.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }
}
