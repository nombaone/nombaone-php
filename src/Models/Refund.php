<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** A refund of a settlement's tenant share (the platform fee is never refunded). */
final class Refund extends Model
{
    /**
     * @param string $id     `nbo…ref`
     * @param int    $amountInKobo integer kobo (₦1.00 = 100)
     * @param string $status `pending` | `ledger_only` | `succeeded` | `failed`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $settlementReference,
        public readonly string $subAccountRef,
        public readonly int $amountInKobo,
        public readonly string $status,
        public readonly ?string $providerReference,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'refund'),
            id: Field::str($data, 'id'),
            settlementReference: Field::str($data, 'settlementReference'),
            subAccountRef: Field::str($data, 'subAccountRef'),
            amountInKobo: Field::int($data, 'amountInKobo'),
            status: Field::str($data, 'status'),
            providerReference: Field::nstr($data, 'providerReference'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
