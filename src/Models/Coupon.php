<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A coupon — a reusable discount rule. Exactly one of `amountOffInKobo` or
 * `percentOff` is set.
 */
final class Coupon extends Model
{
    /**
     * @param string   $id              `nbo…cpn`
     * @param string   $duration        `once` | `repeating` | `forever`
     * @param int|null $amountOffInKobo integer kobo (₦1.00 = 100), or null for a percent coupon
     * @param int|null $percentOff      1–100, or null for a fixed-amount coupon
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $code,
        public readonly string $duration,
        public readonly ?int $amountOffInKobo,
        public readonly ?int $percentOff,
        public readonly ?int $durationInCycles,
        public readonly ?string $redeemBy,
        public readonly ?int $maxRedemptions,
        public readonly int $timesRedeemed,
        public readonly string $mode,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'coupon'),
            id: Field::str($data, 'id'),
            code: Field::str($data, 'code'),
            duration: Field::str($data, 'duration'),
            amountOffInKobo: Field::nint($data, 'amountOffInKobo'),
            percentOff: Field::nint($data, 'percentOff'),
            durationInCycles: Field::nint($data, 'durationInCycles'),
            redeemBy: Field::nstr($data, 'redeemBy'),
            maxRedemptions: Field::nint($data, 'maxRedemptions'),
            timesRedeemed: Field::int($data, 'timesRedeemed'),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
