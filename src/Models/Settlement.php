<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** The integer-kobo split of one collection into platform fee + tenant share. */
final class Settlement extends Model
{
    /**
     * @param string $id                `nbo…stl`
     * @param int    $platformFeeInKobo non-refundable, integer kobo
     * @param string $status            `pending` | `settled` | `reconciled` | `failed` | `refunded`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly ?string $invoiceReference,
        public readonly string $subAccountRef,
        public readonly ?string $splitReference,
        public readonly string $merchantTxRef,
        public readonly int $grossInKobo,
        public readonly int $platformFeeInKobo,
        public readonly int $netToTenantInKobo,
        public readonly string $status,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'settlement'),
            id: Field::str($data, 'id'),
            invoiceReference: Field::nstr($data, 'invoiceReference'),
            subAccountRef: Field::str($data, 'subAccountRef'),
            splitReference: Field::nstr($data, 'splitReference'),
            merchantTxRef: Field::str($data, 'merchantTxRef'),
            grossInKobo: Field::int($data, 'grossInKobo'),
            platformFeeInKobo: Field::int($data, 'platformFeeInKobo'),
            netToTenantInKobo: Field::int($data, 'netToTenantInKobo'),
            status: Field::str($data, 'status'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
