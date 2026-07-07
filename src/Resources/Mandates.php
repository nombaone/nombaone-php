<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\MandateSetup;
use NombaOne\Models\PaymentMethod;

/**
 * Direct-debit mandates (NIBSS). Creation is **asynchronous**: the mandate
 * starts `consent_pending` and activates only after the customer authorizes it
 * with their bank. Don't poll — listen for the `payment_method.updated`
 * webhook — and don't charge before it is active (`MANDATE_NOT_ACTIVE` /
 * `MANDATE_CONSENT_PENDING`).
 */
final class Mandates extends Resource
{
    /**
     * Create a mandate. Requires an `Idempotency-Key` (sent automatically).
     *
     * @param array{customerRef: string, customerAccountNumber: string, bankCode: string, customerName: string, customerAccountName: string, customerPhoneNumber: string, customerAddress: string, narration: string, maxAmountInKobo: int, frequency?: string, startDate?: string, endDate?: string} $params
     * @param array<string, mixed> $options
     *
     * `maxAmountInKobo` is the integer-kobo per-debit ceiling (₦1.00 = 100);
     * charges above it fail with `MANDATE_MAX_AMOUNT_EXCEEDED`.
     *
     * @example
     * ```php
     * $mandate = $nomba->mandates->create([
     *     'customerRef'           => $customer->id,
     *     'customerAccountNumber' => '0123456789',
     *     'bankCode'              => '058',
     *     'customerName'          => 'Ada Lovelace',
     *     'customerAccountName'   => 'Ada Lovelace',
     *     'customerPhoneNumber'   => '+2348012345678',
     *     'customerAddress'       => '1 Marina, Lagos',
     *     'narration'             => 'Acme Pro subscription',
     *     'maxAmountInKobo'       => 500_000, // ₦5,000 ceiling per debit
     * ]);
     * // relay $mandate->consentInstruction to the customer, then wait for the webhook
     * ```
     */
    public function create(array $params, array $options = []): MandateSetup
    {
        return $this->requestModel(MandateSetup::class, new Request(
            'POST',
            '/mandates',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Check a mandate's current standing. Returns the underlying
     * **PaymentMethod** row (its `status` moves `consent_pending` → `active`) —
     * not a mandate object.
     *
     * @param array<string, mixed> $options
     */
    public function retrieve(string $id, array $options = []): PaymentMethod
    {
        return $this->requestModel(PaymentMethod::class, new Request(
            'GET',
            '/mandates/' . self::seg($id),
            options: self::opts($options),
        ));
    }
}
