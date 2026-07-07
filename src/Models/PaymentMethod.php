<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * How a customer pays. Card and mandate are **pull** rails (the engine
 * initiates the debit); a virtual account is the **push** rail (the customer
 * sends a transfer and the engine matches it). Never contains a PAN or token.
 */
final class PaymentMethod extends Model
{
    /**
     * @param string $id     `nbo…pmt`
     * @param string $kind   `card` | `mandate` | `virtual_account`
     * @param string $status `setup_pending` | `consent_pending` | `active` | `removed` | `expired`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $customerId,
        public readonly string $kind,
        public readonly string $status,
        public readonly bool $isDefault,
        public readonly ?string $brand,
        public readonly ?string $last4,
        public readonly ?int $expMonth,
        public readonly ?int $expYear,
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
            domain: Field::str($data, 'domain', 'payment_method'),
            id: Field::str($data, 'id'),
            customerId: Field::str($data, 'customerId'),
            kind: Field::str($data, 'kind'),
            status: Field::str($data, 'status'),
            isDefault: Field::bool($data, 'isDefault'),
            brand: Field::nstr($data, 'brand'),
            last4: Field::nstr($data, 'last4'),
            expMonth: Field::nint($data, 'expMonth'),
            expYear: Field::nint($data, 'expYear'),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            updatedAt: Field::str($data, 'updatedAt'),
            raw: $data,
        );
    }
}
