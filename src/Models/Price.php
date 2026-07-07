<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A price — an immutable amount and cadence under a plan. Prices are immutable
 * once created; deactivate them, never edit.
 */
final class Price extends Model
{
    /**
     * @param string               $id               `nbo…prc`
     * @param int                  $unitAmountInKobo integer kobo (₦1.00 = 100)
     * @param string               $currency         always `NGN`
     * @param string               $interval         `day` | `week` | `month` | `year`
     * @param string               $usageType        `licensed` | `metered`
     * @param string               $billingScheme    `per_unit` | `tiered`
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $planId,
        public readonly int $unitAmountInKobo,
        public readonly string $currency,
        public readonly string $interval,
        public readonly int $intervalCount,
        public readonly string $usageType,
        public readonly string $billingScheme,
        public readonly int $trialPeriodDays,
        public readonly bool $active,
        public readonly array $metadata,
        public readonly string $mode,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'price'),
            id: Field::str($data, 'id'),
            planId: Field::str($data, 'planId'),
            unitAmountInKobo: Field::int($data, 'unitAmountInKobo'),
            currency: Field::str($data, 'currency', 'NGN'),
            interval: Field::str($data, 'interval'),
            intervalCount: Field::int($data, 'intervalCount', 1),
            usageType: Field::str($data, 'usageType'),
            billingScheme: Field::str($data, 'billingScheme'),
            trialPeriodDays: Field::int($data, 'trialPeriodDays'),
            active: Field::bool($data, 'active'),
            metadata: Field::map($data, 'metadata'),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
