<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** A hosted-checkout handoff: send the customer to `checkoutLink`. */
final class CheckoutSetup extends Model
{
    public function __construct(
        public readonly string $domain,
        public readonly string $reference,
        /** The PCI-scoped hosted page where the customer enters their card. */
        public readonly string $checkoutLink,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'checkout_setup'),
            reference: Field::str($data, 'reference'),
            checkoutLink: Field::str($data, 'checkoutLink'),
            raw: $data,
        );
    }
}
