<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * What mandate creation hands back — consent is still pending at this point.
 * Relay `consentInstruction` to the customer, then wait for the
 * `payment_method.attached`/`payment_method.updated` webhook.
 */
final class MandateSetup extends Model
{
    /**
     * @param string $status `consent_pending` until the customer's bank confirms
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $reference,
        public readonly string $mandateRef,
        public readonly string $status,
        public readonly string $consentInstruction,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'mandate_setup'),
            reference: Field::str($data, 'reference'),
            mandateRef: Field::str($data, 'mandateRef'),
            status: Field::str($data, 'status'),
            consentInstruction: Field::str($data, 'consentInstruction'),
            raw: $data,
        );
    }
}
