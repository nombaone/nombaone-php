<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** What forcing one billing cycle produced in the sandbox. */
final class AdvanceCycleResult extends Model
{
    /**
     * @param string  $outcome the cycle's billing outcome: `paid` | `past_due` | `pending` | `open`
     * @param Invoice $invoice the invoice the cycle produced (or the existing one if already billed)
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $subscriptionId,
        public readonly string $outcome,
        public readonly Invoice $invoice,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $invoice = $data['invoice'] ?? null;

        return new self(
            domain: Field::str($data, 'domain', 'advance_cycle_result'),
            subscriptionId: Field::str($data, 'subscriptionId'),
            outcome: Field::str($data, 'outcome'),
            invoice: Invoice::fromArray(is_array($invoice) ? $invoice : []),
            raw: $data,
        );
    }
}
