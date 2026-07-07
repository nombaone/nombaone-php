<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** One priced line of a subscription. */
final class SubscriptionItem extends Model
{
    public function __construct(
        public readonly string $id,
        public readonly string $priceId,
        public readonly int $quantity,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: Field::str($data, 'id'),
            priceId: Field::str($data, 'priceId'),
            quantity: Field::int($data, 'quantity', 1),
            raw: $data,
        );
    }
}
