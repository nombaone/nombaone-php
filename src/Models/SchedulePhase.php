<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** One phase of a subscription schedule — a change queued for a period boundary. */
final class SchedulePhase extends Model
{
    public function __construct(
        public readonly int $startIndex,
        public readonly string $priceId,
        public readonly ?int $quantity,
        public readonly ?string $consumedAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            startIndex: Field::int($data, 'startIndex'),
            priceId: Field::str($data, 'priceId'),
            quantity: Field::nint($data, 'quantity'),
            consumedAt: Field::nstr($data, 'consumedAt'),
            raw: $data,
        );
    }
}
