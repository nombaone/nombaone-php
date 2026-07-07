<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\Plan;
use NombaOne\Nombaone;
use NombaOne\Page;

/**
 * Plans — your catalog. A plan holds the name and description; its prices live
 * underneath it via `$nomba->plans->prices`.
 *
 * @example
 * ```php
 * $plan = $nomba->plans->create(['name' => 'Pro']);
 * $price = $nomba->plans->prices->create($plan->id, [
 *     'unitAmountInKobo' => 250_000,
 *     'interval'         => 'month',
 * ]);
 * ```
 */
final class Plans extends Resource
{
    /** Prices nested under a plan (create + list). */
    public readonly PlanPrices $prices;

    public function __construct(Nombaone $client)
    {
        parent::__construct($client);
        $this->prices = new PlanPrices($client);
    }

    /**
     * Create a plan.
     *
     * @param array{name: string, description?: string, metadata?: array<string, mixed>} $params
     * @param array<string, mixed>                                                       $options
     *
     * @throws \NombaOne\Exceptions\ConflictException 409 `PLAN_NAME_TAKEN`
     */
    public function create(array $params, array $options = []): Plan
    {
        return $this->requestModel(Plan::class, new Request(
            'POST',
            '/plans',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve a plan by id.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `PLAN_NOT_FOUND`
     */
    public function retrieve(string $id, array $options = []): Plan
    {
        return $this->requestModel(Plan::class, new Request(
            'GET',
            '/plans/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * Update a plan's mutable fields. At least one field is required. Pass
     * `'description' => null` to clear the description.
     *
     * @param array{name?: string, description?: string|null, metadata?: array<string, mixed>} $params
     * @param array<string, mixed>                                                              $options
     */
    public function update(string $id, array $params, array $options = []): Plan
    {
        return $this->requestModel(Plan::class, new Request(
            'PATCH',
            '/plans/' . self::seg($id),
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * List plans, newest first.
     *
     * @param array{status?: string, limit?: int, cursor?: string} $params
     * @param array<string, mixed>                                 $options
     *
     * @return Page<Plan>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(Plan::class, new Request(
            'GET',
            '/plans',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Archive a plan — it stops being subscribable but its history stays.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\ConflictException 409 `PLAN_ALREADY_ARCHIVED`
     * @throws \NombaOne\Exceptions\ConflictException 409 `PLAN_HAS_ACTIVE_SUBSCRIBERS` — migrate or cancel those subscriptions first.
     */
    public function archive(string $id, array $options = []): Plan
    {
        return $this->requestModel(Plan::class, new Request(
            'POST',
            '/plans/' . self::seg($id) . '/archive',
            body: [],
            options: self::opts($options),
        ));
    }
}
