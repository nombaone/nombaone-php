<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** A withdrawal of settled funds to your bank account. */
final class Payout extends Model
{
    /**
     * @param string $id           `nbo…pay`
     * @param int    $amountInKobo integer kobo (₦1.00 = 100)
     * @param string $bankCode     CBN 3-digit bank code
     * @param string $status       `pending` | `ledger_posted` | `succeeded` | `failed`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $subAccountRef,
        public readonly int $amountInKobo,
        public readonly string $bankCode,
        public readonly string $accountNumber,
        public readonly ?string $resolvedAccountName,
        public readonly string $status,
        public readonly ?string $providerReference,
        public readonly ?string $failureReason,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'payout'),
            id: Field::str($data, 'id'),
            subAccountRef: Field::str($data, 'subAccountRef'),
            amountInKobo: Field::int($data, 'amountInKobo'),
            bankCode: Field::str($data, 'bankCode'),
            accountNumber: Field::str($data, 'accountNumber'),
            resolvedAccountName: Field::nstr($data, 'resolvedAccountName'),
            status: Field::str($data, 'status'),
            providerReference: Field::nstr($data, 'providerReference'),
            failureReason: Field::nstr($data, 'failureReason'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
