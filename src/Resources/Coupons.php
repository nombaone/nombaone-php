<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\Coupon;
use NombaOne\Page;

/**
 * Coupons — reusable discount rules. Apply one to a customer or subscription
 * with `applyDiscount(...)` on those resources.
 */
final class Coupons extends Resource
{
    /**
     * Create a coupon. Provide exactly one of `amountOffInKobo` or `percentOff`.
     *
     * @param array{code: string, duration: string, amountOffInKobo?: int, percentOff?: int, durationInCycles?: int, redeemBy?: string, maxRedemptions?: int, metadata?: array<string, mixed>} $params
     * @param array<string, mixed> $options
     *
     * `amountOffInKobo` is integer kobo (₦1.00 = 100). `durationInCycles` is
     * required when `duration` is `repeating`.
     *
     * @throws \NombaOne\Exceptions\ValidationException 422 `COUPON_INVALID_DEFINITION`
     *
     * @example
     * ```php
     * $coupon = $nomba->coupons->create([
     *     'code'       => 'LAUNCH20',
     *     'percentOff' => 20,
     *     'duration'   => 'once',
     * ]);
     * ```
     */
    public function create(array $params, array $options = []): Coupon
    {
        return $this->requestModel(Coupon::class, new Request(
            'POST',
            '/coupons',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve a coupon by id.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `COUPON_NOT_FOUND`
     */
    public function retrieve(string $id, array $options = []): Coupon
    {
        return $this->requestModel(Coupon::class, new Request(
            'GET',
            '/coupons/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * Update a coupon's mutable fields (redemption window and cap).
     *
     * @param array{redeemBy?: string, maxRedemptions?: int, metadata?: array<string, mixed>} $params
     * @param array<string, mixed>                                                            $options
     */
    public function update(string $id, array $params, array $options = []): Coupon
    {
        return $this->requestModel(Coupon::class, new Request(
            'PATCH',
            '/coupons/' . self::seg($id),
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * List coupons, newest first.
     *
     * @param array{limit?: int, cursor?: string} $params
     * @param array<string, mixed>                $options
     *
     * @return Page<Coupon>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(Coupon::class, new Request(
            'GET',
            '/coupons',
            query: $params,
            options: self::opts($options),
        ));
    }
}
