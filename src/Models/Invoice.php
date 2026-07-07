<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * An invoice — what a billing cycle produced. Read + void only; the engine
 * creates invoices, you never do. Every amount is integer kobo.
 */
final class Invoice extends Model
{
    /**
     * @param string                $id            `nbo…inv`
     * @param string                $status        `draft` | `open` | `partially_paid` | `paid` | `void` | `uncollectible`
     * @param string                $billingReason `subscription_create` | `subscription_cycle` | `subscription_update` | `manual`
     * @param list<InvoiceLineItem> $lineItems
     * @param string                $currency      always `NGN`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $customerId,
        public readonly ?string $subscriptionId,
        public readonly string $status,
        public readonly string $billingReason,
        public readonly int $subtotalInKobo,
        public readonly int $discountTotalInKobo,
        public readonly int $creditTotalInKobo,
        public readonly int $totalInKobo,
        public readonly int $amountDueInKobo,
        public readonly int $amountPaidInKobo,
        public readonly int $amountRemainingInKobo,
        public readonly string $currency,
        public readonly ?string $periodStart,
        public readonly ?string $periodEnd,
        public readonly ?string $dueDate,
        public readonly array $lineItems,
        public readonly ?string $finalizedAt,
        public readonly ?string $paidAt,
        public readonly ?string $voidedAt,
        public readonly string $mode,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'invoice'),
            id: Field::str($data, 'id'),
            customerId: Field::str($data, 'customerId'),
            subscriptionId: Field::nstr($data, 'subscriptionId'),
            status: Field::str($data, 'status'),
            billingReason: Field::str($data, 'billingReason'),
            subtotalInKobo: Field::int($data, 'subtotalInKobo'),
            discountTotalInKobo: Field::int($data, 'discountTotalInKobo'),
            creditTotalInKobo: Field::int($data, 'creditTotalInKobo'),
            totalInKobo: Field::int($data, 'totalInKobo'),
            amountDueInKobo: Field::int($data, 'amountDueInKobo'),
            amountPaidInKobo: Field::int($data, 'amountPaidInKobo'),
            amountRemainingInKobo: Field::int($data, 'amountRemainingInKobo'),
            currency: Field::str($data, 'currency', 'NGN'),
            periodStart: Field::nstr($data, 'periodStart'),
            periodEnd: Field::nstr($data, 'periodEnd'),
            dueDate: Field::nstr($data, 'dueDate'),
            lineItems: array_map(
                static fn (array $row): InvoiceLineItem => InvoiceLineItem::fromArray($row),
                Field::objects($data, 'lineItems'),
            ),
            finalizedAt: Field::nstr($data, 'finalizedAt'),
            paidAt: Field::nstr($data, 'paidAt'),
            voidedAt: Field::nstr($data, 'voidedAt'),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
