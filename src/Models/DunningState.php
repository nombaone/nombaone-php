<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * Where a subscription stands in recovery. `past_due` is **not** canceled —
 * read `graceAccessUntil` before cutting a subscriber off.
 */
final class DunningState extends Model
{
    /**
     * @param string               $status   `scheduled` | `attempting` | `succeeded` | `rescheduled` | `card_update_required` | `exhausted` | `none`
     * @param list<DunningAttempt> $attempts
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $subscriptionRef,
        public readonly ?string $invoiceRef,
        public readonly string $status,
        public readonly int $attemptsUsed,
        public readonly int $maxAttempts,
        public readonly ?string $nextAttemptAt,
        public readonly ?string $graceAccessUntil,
        public readonly array $attempts,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'dunning_state'),
            subscriptionRef: Field::str($data, 'subscriptionRef'),
            invoiceRef: Field::nstr($data, 'invoiceRef'),
            status: Field::str($data, 'status'),
            attemptsUsed: Field::int($data, 'attemptsUsed'),
            maxAttempts: Field::int($data, 'maxAttempts'),
            nextAttemptAt: Field::nstr($data, 'nextAttemptAt'),
            graceAccessUntil: Field::nstr($data, 'graceAccessUntil'),
            attempts: array_map(
                static fn (array $row): DunningAttempt => DunningAttempt::fromArray($row),
                Field::objects($data, 'attempts'),
            ),
            raw: $data,
        );
    }
}
