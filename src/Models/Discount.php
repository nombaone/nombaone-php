<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A coupon applied to a customer or subscription — the *application*, not the
 * coupon itself. Shapes every future invoice for its target until it ends.
 */
final class Discount extends Model
{
    /**
     * @param string      $id              `nbo…dsc`
     * @param string      $status          `active` | `ended`
     * @param int|null    $cyclesRemaining cycles left for a `repeating` coupon; null for `once`/`forever`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $couponId,
        public readonly ?string $customerId,
        public readonly ?string $subscriptionId,
        public readonly string $status,
        public readonly ?int $cyclesRemaining,
        public readonly string $startAt,
        public readonly ?string $endAt,
        public readonly string $mode,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'discount'),
            id: Field::str($data, 'id'),
            couponId: Field::str($data, 'couponId'),
            customerId: Field::nstr($data, 'customerId'),
            subscriptionId: Field::nstr($data, 'subscriptionId'),
            status: Field::str($data, 'status'),
            cyclesRemaining: Field::nint($data, 'cyclesRemaining'),
            startAt: Field::str($data, 'startAt'),
            endAt: Field::nstr($data, 'endAt'),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
