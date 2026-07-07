<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A queued change that applies at a period boundary instead of mid-cycle — the
 * safe way to switch billing intervals.
 */
final class SubscriptionSchedule extends Model
{
    /**
     * @param string              $id     `nbo…sch`
     * @param string              $status `active` | `released` | `canceled`
     * @param list<SchedulePhase> $phases
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $subscriptionId,
        public readonly string $status,
        public readonly array $phases,
        public readonly string $mode,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'subscription_schedule'),
            id: Field::str($data, 'id'),
            subscriptionId: Field::str($data, 'subscriptionId'),
            status: Field::str($data, 'status'),
            phases: array_map(
                static fn (array $row): SchedulePhase => SchedulePhase::fromArray($row),
                Field::objects($data, 'phases'),
            ),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            updatedAt: Field::str($data, 'updatedAt'),
            raw: $data,
        );
    }
}
