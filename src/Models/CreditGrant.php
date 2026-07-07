<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A grant of credit that future invoices draw down (oldest grant first) before
 * charging any payment rail.
 */
final class CreditGrant extends Model
{
    /**
     * @param string  $id             `nbo…crg`
     * @param int     $amountInKobo   original granted amount, integer kobo (₦1.00 = 100)
     * @param int     $remainingInKobo what is left to consume, integer kobo
     * @param string  $source         `downgrade_proration` | `manual` | `goodwill` | `coupon`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $customerId,
        public readonly int $amountInKobo,
        public readonly int $remainingInKobo,
        public readonly string $source,
        public readonly ?string $sourceReference,
        public readonly string $mode,
        public readonly ?string $voidedAt,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'credit_grant'),
            id: Field::str($data, 'id'),
            customerId: Field::str($data, 'customerId'),
            amountInKobo: Field::int($data, 'amountInKobo'),
            remainingInKobo: Field::int($data, 'remainingInKobo'),
            source: Field::str($data, 'source'),
            sourceReference: Field::nstr($data, 'sourceReference'),
            mode: Field::str($data, 'mode'),
            voidedAt: Field::nstr($data, 'voidedAt'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
