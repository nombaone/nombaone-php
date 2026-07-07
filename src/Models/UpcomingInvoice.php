<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** A preview of the next cycle's invoice — nothing is charged or stored. */
final class UpcomingInvoice extends Model
{
    /**
     * @param string                $billingReason `subscription_create` | `subscription_cycle` | `subscription_update` | `manual`
     * @param list<InvoiceLineItem> $lineItems
     * @param string                $currency      always `NGN`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $subscriptionId,
        public readonly int $periodIndex,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $billingReason,
        public readonly int $subtotalInKobo,
        public readonly int $totalInKobo,
        public readonly int $amountDueInKobo,
        public readonly string $currency,
        public readonly array $lineItems,
        public readonly string $mode,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'upcoming_invoice'),
            subscriptionId: Field::str($data, 'subscriptionId'),
            periodIndex: Field::int($data, 'periodIndex'),
            periodStart: Field::str($data, 'periodStart'),
            periodEnd: Field::str($data, 'periodEnd'),
            billingReason: Field::str($data, 'billingReason'),
            subtotalInKobo: Field::int($data, 'subtotalInKobo'),
            totalInKobo: Field::int($data, 'totalInKobo'),
            amountDueInKobo: Field::int($data, 'amountDueInKobo'),
            currency: Field::str($data, 'currency', 'NGN'),
            lineItems: array_map(
                static fn (array $row): InvoiceLineItem => InvoiceLineItem::fromArray($row),
                Field::objects($data, 'lineItems'),
            ),
            mode: Field::str($data, 'mode'),
            raw: $data,
        );
    }
}
