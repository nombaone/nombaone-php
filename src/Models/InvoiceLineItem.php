<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** One line on an invoice. Amounts are integer kobo; discounts/credits are negative. */
final class InvoiceLineItem extends Model
{
    /**
     * @param string $kind         `subscription` | `proration` | `discount` | `credit` | `adjustment`
     * @param int    $amountInKobo integer kobo (₦1.00 = 100); negative for discount/credit lines
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly string $description,
        public readonly int $amountInKobo,
        public readonly int $quantity,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: Field::str($data, 'id'),
            kind: Field::str($data, 'kind'),
            description: Field::str($data, 'description'),
            amountInKobo: Field::int($data, 'amountInKobo'),
            quantity: Field::int($data, 'quantity', 1),
            raw: $data,
        );
    }
}
