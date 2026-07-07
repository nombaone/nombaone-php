<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** A dedicated NUBAN the customer pushes transfers to (the bank-transfer rail). */
final class VirtualAccount extends Model
{
    public function __construct(
        public readonly string $domain,
        public readonly string $reference,
        public readonly string $bankName,
        public readonly string $accountNumber,
        public readonly string $accountName,
        public readonly string $accountRef,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'virtual_account'),
            reference: Field::str($data, 'reference'),
            bankName: Field::str($data, 'bankName'),
            accountNumber: Field::str($data, 'accountNumber'),
            accountName: Field::str($data, 'accountName'),
            accountRef: Field::str($data, 'accountRef'),
            raw: $data,
        );
    }
}
