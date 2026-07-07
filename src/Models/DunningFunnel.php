<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** Recovery funnel counts inside a metrics window. */
final class DunningFunnel extends Model
{
    public function __construct(
        public readonly int $scheduled,
        public readonly int $attempting,
        public readonly int $cardUpdateRequired,
        public readonly int $rescheduled,
        public readonly int $succeeded,
        public readonly int $exhausted,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            scheduled: Field::int($data, 'scheduled'),
            attempting: Field::int($data, 'attempting'),
            cardUpdateRequired: Field::int($data, 'cardUpdateRequired'),
            rescheduled: Field::int($data, 'rescheduled'),
            succeeded: Field::int($data, 'succeeded'),
            exhausted: Field::int($data, 'exhausted'),
            raw: $data,
        );
    }
}
