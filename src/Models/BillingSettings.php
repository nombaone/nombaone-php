<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * Your org-wide billing + dunning policy — how hard and when the engine
 * retries, payday bias, grace windows, and collection defaults.
 */
final class BillingSettings extends Model
{
    /**
     * @param string    $prorationCreditPolicy   `credit_next_cycle` | `none`
     * @param list<int> $dunningIntervalsHours
     * @param list<int> $paydayDays              days of month treated as paydays
     * @param string    $defaultCollectionMethod `charge_automatically` | `send_invoice`
     */
    public function __construct(
        public readonly string $domain,
        public readonly bool $partialCollectionEnabled,
        public readonly string $prorationCreditPolicy,
        public readonly int $dunningMaxAttempts,
        public readonly array $dunningIntervalsHours,
        public readonly int $dunningMaxWindowHours,
        public readonly int $gracePeriodHours,
        public readonly array $paydayDays,
        public readonly int $paydayPullForwardDays,
        public readonly bool $paydayBiasEnabled,
        public readonly string $defaultCollectionMethod,
        public readonly bool $commsEnabled,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'billing_settings'),
            partialCollectionEnabled: Field::bool($data, 'partialCollectionEnabled'),
            prorationCreditPolicy: Field::str($data, 'prorationCreditPolicy'),
            dunningMaxAttempts: Field::int($data, 'dunningMaxAttempts'),
            dunningIntervalsHours: Field::intList($data, 'dunningIntervalsHours'),
            dunningMaxWindowHours: Field::int($data, 'dunningMaxWindowHours'),
            gracePeriodHours: Field::int($data, 'gracePeriodHours'),
            paydayDays: Field::intList($data, 'paydayDays'),
            paydayPullForwardDays: Field::int($data, 'paydayPullForwardDays'),
            paydayBiasEnabled: Field::bool($data, 'paydayBiasEnabled'),
            defaultCollectionMethod: Field::str($data, 'defaultCollectionMethod'),
            commsEnabled: Field::bool($data, 'commsEnabled'),
            raw: $data,
        );
    }
}
