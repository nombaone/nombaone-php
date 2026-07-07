<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\Price;
use NombaOne\Page;

/**
 * Prices nested under a plan (create + list). For reads and deactivation of an
 * individual price, see `$nomba->prices`.
 *
 * Reached as `$nomba->plans->prices`.
 */
final class PlanPrices extends Resource
{
    /**
     * Create a price under a plan. Prices are immutable once created.
     *
     * @param array{unitAmountInKobo: int, interval: string, intervalCount?: int, usageType?: string, billingScheme?: string, trialPeriodDays?: int, metadata?: array<string, mixed>} $params
     * @param array<string, mixed> $options
     *
     * `unitAmountInKobo` is integer kobo (₦1.00 = 100). `250_000` is ₦2,500 —
     * not ₦250,000.
     *
     * @example
     * ```php
     * $price = $nomba->plans->prices->create($plan->id, [
     *     'unitAmountInKobo' => 250_000, // ₦2,500.00 per month
     *     'interval'         => 'month',
     * ]);
     * ```
     */
    public function create(string $planId, array $params, array $options = []): Price
    {
        return $this->requestModel(Price::class, new Request(
            'POST',
            '/plans/' . self::seg($planId) . '/prices',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * List a plan's prices, newest first.
     *
     * @param array{limit?: int, cursor?: string} $params
     * @param array<string, mixed>                $options
     *
     * @return Page<Price>
     */
    public function list(string $planId, array $params = [], array $options = []): Page
    {
        return $this->requestPage(Price::class, new Request(
            'GET',
            '/plans/' . self::seg($planId) . '/prices',
            query: $params,
            options: self::opts($options),
        ));
    }
}
