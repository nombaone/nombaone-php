<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\ApiResponse;
use NombaOne\Http\Request;
use NombaOne\Http\RequestOptions;
use NombaOne\Models\Model;
use NombaOne\Nombaone;
use NombaOne\Page;

/**
 * Base class every API resource namespace extends. Provides the plumbing that
 * turns a {@see Request} into a hydrated model, a {@see Page}, or a raw
 * {@see ApiResponse}.
 *
 * @internal
 */
abstract class Resource
{
    public function __construct(protected readonly Nombaone $client)
    {
    }

    /**
     * Send a request and hydrate the response into a single model.
     *
     * @template T of Model
     *
     * @param class-string<T> $model
     *
     * @return T
     */
    protected function requestModel(string $model, Request $request): Model
    {
        $response = $this->client->send($request);

        return $model::fromArray($response->data)->withLastResponse($response);
    }

    /**
     * Send a list request and wrap the response in a paginator.
     *
     * @template T of Model
     *
     * @param class-string<T> $model
     *
     * @return Page<T>
     */
    protected function requestPage(string $model, Request $request): Page
    {
        return Page::fetch($this->client, $model, $request);
    }

    /** Send a request and return the raw response (for endpoints without a modeled shape). */
    protected function requestRaw(Request $request): ApiResponse
    {
        return $this->client->send($request);
    }

    /** Encode one path segment — ids come from user input, so never trust raw. */
    protected static function seg(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * Build {@see RequestOptions} from a per-call options array, or null when empty.
     *
     * @param array<string, mixed> $options
     */
    protected static function opts(array $options): ?RequestOptions
    {
        return $options === [] ? null : RequestOptions::fromArray($options);
    }
}
