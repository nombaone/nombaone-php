<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\BillingMetrics;

/** Metrics — MRR, churn, and the dunning funnel, computed from the ledger. */
final class Metrics extends Resource
{
    /**
     * Billing KPIs over a window (defaults to a recent window server-side).
     *
     * @param array{from?: string, to?: string} $params ISO-8601 date-time bounds
     * @param array<string, mixed>              $options
     *
     * @example
     * ```php
     * $metrics = $nomba->metrics->billing();
     * echo 'MRR ₦' . ($metrics->mrrInKobo / 100);
     * ```
     */
    public function billing(array $params = [], array $options = []): BillingMetrics
    {
        return $this->requestModel(BillingMetrics::class, new Request(
            'GET',
            '/metrics/billing',
            query: $params,
            options: self::opts($options),
        ));
    }
}
