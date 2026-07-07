<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** One retry in a recovery run. */
final class DunningAttempt extends Model
{
    /**
     * @param string $id     `nbo…dun`
     * @param string $status `scheduled` | `attempting` | `succeeded` | `rescheduled` | `card_update_required` | `exhausted`
     * @param string $branch `reschedule` | `card_update_required` | `short_path`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly int $attemptNumber,
        public readonly string $status,
        public readonly string $branch,
        public readonly ?string $railKey,
        public readonly ?string $failureReason,
        public readonly ?string $gatewayMessage,
        public readonly ?string $outcome,
        public readonly string $scheduledAt,
        public readonly ?string $executedAt,
        public readonly ?string $nextAttemptAt,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'dunning_attempt'),
            id: Field::str($data, 'id'),
            attemptNumber: Field::int($data, 'attemptNumber'),
            status: Field::str($data, 'status'),
            branch: Field::str($data, 'branch'),
            railKey: Field::nstr($data, 'railKey'),
            failureReason: Field::nstr($data, 'failureReason'),
            gatewayMessage: Field::nstr($data, 'gatewayMessage'),
            outcome: Field::nstr($data, 'outcome'),
            scheduledAt: Field::str($data, 'scheduledAt'),
            executedAt: Field::nstr($data, 'executedAt'),
            nextAttemptAt: Field::nstr($data, 'nextAttemptAt'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
