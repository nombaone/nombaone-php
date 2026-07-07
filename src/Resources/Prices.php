<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\Price;
use NombaOne\Page;

/**
 * Prices — immutable amounts and cadences. Create them under a plan
 * (`$nomba->plans->prices->create(...)`); here you read and deactivate.
 */
final class Prices extends Resource
{
    /**
     * Retrieve a price by id.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `PRICE_NOT_FOUND`
     */
    public function retrieve(string $id, array $options = []): Price
    {
        return $this->requestModel(Price::class, new Request(
            'GET',
            '/prices/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * List prices, newest first.
     *
     * Note the filter is `planRef` (not `planId`) — a wire-name quirk this SDK
     * mirrors faithfully.
     *
     * @param array{planRef?: string, active?: bool, limit?: int, cursor?: string} $params
     * @param array<string, mixed>                                                 $options
     *
     * @return Page<Price>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(Price::class, new Request(
            'GET',
            '/prices',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Deactivate a price — it stops being subscribable but its history stays.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\ConflictException 409 `PRICE_ALREADY_INACTIVE`
     */
    public function deactivate(string $id, array $options = []): Price
    {
        return $this->requestModel(Price::class, new Request(
            'POST',
            '/prices/' . self::seg($id) . '/deactivate',
            body: [],
            options: self::opts($options),
        ));
    }
}
