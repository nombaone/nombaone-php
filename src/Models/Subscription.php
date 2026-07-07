<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A subscription — one customer's recurring relationship with one price. The
 * engine bills it every cycle, retries failures through dunning, and reports
 * every transition as a webhook event.
 *
 * Involuntary churn is `status: 'canceled'` with
 * `cancellationReason: 'involuntary'` — there is no separate `churned` status
 * (there is a `subscription.churned` event).
 */
final class Subscription extends Model
{
    /**
     * @param string                 $id                  `nbo…sub`
     * @param string                 $status              `incomplete` | `incomplete_expired` | `trialing` | `active` | `past_due` | `paused` | `canceled`
     * @param string                 $collectionMethod    `charge_automatically` | `send_invoice`
     * @param string|null            $cancellationReason  `voluntary` | `involuntary` | null
     * @param list<SubscriptionItem> $items
     * @param string                 $currency            always `NGN`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $customerId,
        public readonly string $priceId,
        public readonly string $status,
        public readonly string $collectionMethod,
        public readonly int $currentPeriodIndex,
        public readonly ?string $currentPeriodStart,
        public readonly ?string $currentPeriodEnd,
        public readonly ?string $trialStart,
        public readonly ?string $trialEnd,
        public readonly bool $cancelAtPeriodEnd,
        public readonly ?string $canceledAt,
        public readonly ?string $endedAt,
        public readonly ?string $cancellationReason,
        public readonly ?string $defaultPaymentMethodId,
        public readonly array $items,
        public readonly ?string $latestInvoiceId,
        public readonly string $currency,
        public readonly string $mode,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'subscription'),
            id: Field::str($data, 'id'),
            customerId: Field::str($data, 'customerId'),
            priceId: Field::str($data, 'priceId'),
            status: Field::str($data, 'status'),
            collectionMethod: Field::str($data, 'collectionMethod'),
            currentPeriodIndex: Field::int($data, 'currentPeriodIndex'),
            currentPeriodStart: Field::nstr($data, 'currentPeriodStart'),
            currentPeriodEnd: Field::nstr($data, 'currentPeriodEnd'),
            trialStart: Field::nstr($data, 'trialStart'),
            trialEnd: Field::nstr($data, 'trialEnd'),
            cancelAtPeriodEnd: Field::bool($data, 'cancelAtPeriodEnd'),
            canceledAt: Field::nstr($data, 'canceledAt'),
            endedAt: Field::nstr($data, 'endedAt'),
            cancellationReason: Field::nstr($data, 'cancellationReason'),
            defaultPaymentMethodId: Field::nstr($data, 'defaultPaymentMethodId'),
            items: array_map(
                static fn (array $row): SubscriptionItem => SubscriptionItem::fromArray($row),
                Field::objects($data, 'items'),
            ),
            latestInvoiceId: Field::nstr($data, 'latestInvoiceId'),
            currency: Field::str($data, 'currency', 'NGN'),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
