<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** Billing KPIs, computed from the ledger on read — never stored, never stale. */
final class BillingMetrics extends Model
{
    /**
     * @param int $mrrInKobo monthly recurring revenue, integer kobo
     */
    public function __construct(
        public readonly string $domain,
        public readonly int $mrrInKobo,
        public readonly int $activeCount,
        public readonly int $voluntaryChurn,
        public readonly int $involuntaryChurn,
        public readonly float $failedChargeRate,
        public readonly float $dunningRecoveryRate,
        public readonly DunningFunnel $dunningFunnel,
        public readonly string $windowFrom,
        public readonly string $windowTo,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $funnel = $data['dunningFunnel'] ?? null;

        return new self(
            domain: Field::str($data, 'domain', 'billing_metrics'),
            mrrInKobo: Field::int($data, 'mrrInKobo'),
            activeCount: Field::int($data, 'activeCount'),
            voluntaryChurn: Field::int($data, 'voluntaryChurn'),
            involuntaryChurn: Field::int($data, 'involuntaryChurn'),
            failedChargeRate: Field::float($data, 'failedChargeRate'),
            dunningRecoveryRate: Field::float($data, 'dunningRecoveryRate'),
            dunningFunnel: DunningFunnel::fromArray(is_array($funnel) ? $funnel : []),
            windowFrom: Field::str($data, 'windowFrom'),
            windowTo: Field::str($data, 'windowTo'),
            raw: $data,
        );
    }
}
