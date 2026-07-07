<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\Escrow;
use NombaOne\Models\Payout;
use NombaOne\Models\Refund;
use NombaOne\Models\Settlement;
use NombaOne\Page;

/**
 * Settlements — where collected money lands, and how it leaves (refunds,
 * payouts) under the escrow lock.
 */
final class Settlements extends Resource
{
    /**
     * Retrieve a settlement by id.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `SETTLEMENT_NOT_FOUND`
     */
    public function retrieve(string $id, array $options = []): Settlement
    {
        return $this->requestModel(Settlement::class, new Request(
            'GET',
            '/settlements/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * List settlements, newest first.
     *
     * @param array{status?: string, limit?: int, cursor?: string} $params
     * @param array<string, mixed>                                 $options
     *
     * @return Page<Settlement>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(Settlement::class, new Request(
            'GET',
            '/settlements',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Your escrow lock and available-to-withdraw balance.
     *
     * @param array<string, mixed> $options
     */
    public function retrieveEscrow(array $options = []): Escrow
    {
        return $this->requestModel(Escrow::class, new Request(
            'GET',
            '/settlements/escrow',
            options: self::opts($options),
        ));
    }

    /**
     * Refund a settlement's tenant share. The platform fee is never refunded.
     * Defaults to the full remaining refundable amount.
     *
     * **Money moves here.** The API requires an `Idempotency-Key`; the SDK sends
     * one automatically, but pass your own stable `idempotencyKey` in `$options`
     * so a retry from a *new process* cannot refund twice.
     *
     * @param array{amountInKobo?: int} $params
     * @param array<string, mixed>      $options
     *
     * @throws \NombaOne\Exceptions\ConflictException   409 `REFUND_ALREADY_REFUNDED`
     * @throws \NombaOne\Exceptions\ValidationException 422 `REFUND_AMOUNT_EXCEEDS_NET`
     */
    public function refund(string $id, array $params = [], array $options = []): Refund
    {
        return $this->requestModel(Refund::class, new Request(
            'POST',
            '/settlements/' . self::seg($id) . '/refund',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Withdraw settled funds to your bank account.
     *
     * **Money moves here, and the `Idempotency-Key` doubles as the payout's
     * durable `merchantTxRef`.** Always pass an explicit, stable `idempotencyKey`
     * in `$options` (e.g. your own payout id) — an auto-generated key protects
     * SDK-level retries, but a brand-new process retrying with a fresh key would
     * create a second payout.
     *
     * @param array{amountInKobo: int, bankCode: string, accountNumber: string} $params
     * @param array<string, mixed> $options
     *
     * `amountInKobo` is integer kobo (₦1.00 = 100).
     *
     * @throws \NombaOne\Exceptions\ConflictException   409 `ESCROW_LOCKED`
     * @throws \NombaOne\Exceptions\ValidationException 422 `PAYOUT_EXCEEDS_AVAILABLE`
     *
     * @example
     * ```php
     * $payout = $nomba->settlements->createPayout(
     *     ['amountInKobo' => 5_000_000, 'bankCode' => '058', 'accountNumber' => '0123456789'],
     *     ['idempotencyKey' => 'payout-' . $myPayoutRow->id],
     * );
     * ```
     */
    public function createPayout(array $params, array $options = []): Payout
    {
        return $this->requestModel(Payout::class, new Request(
            'POST',
            '/settlements/payout',
            body: $params,
            options: self::opts($options),
        ));
    }
}
