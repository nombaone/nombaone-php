<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\CreditBalance;
use NombaOne\Models\CreditGrant;
use NombaOne\Models\Customer;
use NombaOne\Models\Discount;
use NombaOne\Page;

/**
 * Customers — the people and businesses you bill.
 *
 * @example
 * ```php
 * $customer = $nomba->customers->create([
 *     'email' => 'ada@example.com',
 *     'name'  => 'Ada Lovelace',
 * ]);
 * echo $customer->id; // "nbo…cus"
 * ```
 */
final class Customers extends Resource
{
    /**
     * Create a customer.
     *
     * @param array{email: string, name: string, phone?: string, metadata?: array<string, mixed>} $params
     * @param array<string, mixed>                                                                 $options per-call options
     *
     * @throws \NombaOne\Exceptions\ValidationException 422 `CLIENT_VALIDATION_FAILED` — see `$e->fields`.
     * @throws \NombaOne\Exceptions\ConflictException   409 `CUSTOMER_EMAIL_TAKEN` — reuse the existing customer instead.
     *
     * @example
     * ```php
     * $customer = $nomba->customers->create([
     *     'email'    => 'ada@example.com',
     *     'name'     => 'Ada Lovelace',
     *     'metadata' => ['crmId' => 'crm_812'],
     * ]);
     * ```
     */
    public function create(array $params, array $options = []): Customer
    {
        return $this->requestModel(Customer::class, new Request(
            'POST',
            '/customers',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve a customer by id.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `CUSTOMER_NOT_FOUND` — check the id and that your key
     *                                                matches the environment the customer was created in.
     */
    public function retrieve(string $id, array $options = []): Customer
    {
        return $this->requestModel(Customer::class, new Request(
            'GET',
            '/customers/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * Update a customer's mutable fields. At least one field is required. Pass
     * `'phone' => null` to clear the phone number.
     *
     * @param array{name?: string, phone?: string|null, metadata?: array<string, mixed>} $params
     * @param array<string, mixed>                                                        $options
     *
     * @example
     * ```php
     * $nomba->customers->update($customer->id, ['phone' => '+2348012345678']);
     * ```
     */
    public function update(string $id, array $params, array $options = []): Customer
    {
        return $this->requestModel(Customer::class, new Request(
            'PATCH',
            '/customers/' . self::seg($id),
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * List customers, newest first.
     *
     * @param array{email?: string, limit?: int, cursor?: string} $params filter + pagination
     * @param array<string, mixed>                                 $options
     *
     * @return Page<Customer>
     *
     * @example
     * ```php
     * foreach ($nomba->customers->list() as $customer) {
     *     echo $customer->email, "\n"; // pages fetched for you
     * }
     * ```
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(Customer::class, new Request(
            'GET',
            '/customers',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Apply a coupon to a customer. The resulting discount shapes every future
     * invoice for the customer until it ends or is removed.
     *
     * @param array{coupon: string} $params a coupon id (`nbo…cpn`) or its code (e.g. `LAUNCH20`)
     * @param array<string, mixed>  $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `COUPON_NOT_FOUND`
     * @throws \NombaOne\Exceptions\ConflictException 409 `COUPON_ALREADY_APPLIED`
     *
     * @example
     * ```php
     * $discount = $nomba->customers->applyDiscount($customer->id, ['coupon' => 'LAUNCH20']);
     * ```
     */
    public function applyDiscount(string $id, array $params, array $options = []): Discount
    {
        return $this->requestModel(Discount::class, new Request(
            'POST',
            '/customers/' . self::seg($id) . '/discount',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Remove the customer's active discount. Returns the ended discount.
     *
     * @param array<string, mixed> $options
     */
    public function removeDiscount(string $id, array $options = []): Discount
    {
        return $this->requestModel(Discount::class, new Request(
            'DELETE',
            '/customers/' . self::seg($id) . '/discount',
            options: self::opts($options),
        ));
    }

    /**
     * Grant credit to a customer. Credit is drawn down oldest-grant-first by
     * future invoices **before** any payment rail is charged.
     *
     * This moves money-shaped state, so the API requires an `Idempotency-Key`;
     * the SDK sends one automatically (pass `idempotencyKey` in `$options` to
     * control it across process restarts).
     *
     * @param array{amountInKobo: int, source?: string, sourceReference?: string, metadata?: array<string, mixed>} $params
     * @param array<string, mixed> $options
     *
     * `amountInKobo` is integer kobo (₦1.00 = 100). `250_000` is ₦2,500 — not
     * ₦250,000. Multiply naira by 100 exactly once, at the edge of your system.
     *
     * @example
     * ```php
     * $nomba->customers->grantCredit($customer->id, [
     *     'amountInKobo' => 250_000, // ₦2,500.00
     *     'source'       => 'goodwill',
     * ]);
     * ```
     */
    public function grantCredit(string $id, array $params, array $options = []): CreditGrant
    {
        return $this->requestModel(CreditGrant::class, new Request(
            'POST',
            '/customers/' . self::seg($id) . '/credit',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve the customer's credit balance and the grants behind it.
     *
     * @param array<string, mixed> $options
     */
    public function retrieveCreditBalance(string $id, array $options = []): CreditBalance
    {
        return $this->requestModel(CreditBalance::class, new Request(
            'GET',
            '/customers/' . self::seg($id) . '/credit',
            options: self::opts($options),
        ));
    }

    /**
     * Void a credit grant — its remaining balance becomes unusable. Already
     * consumed credit is untouched.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\ConflictException 409 `CREDIT_GRANT_ALREADY_VOIDED`
     */
    public function voidCredit(string $id, string $grantId, array $options = []): CreditGrant
    {
        return $this->requestModel(CreditGrant::class, new Request(
            'DELETE',
            '/customers/' . self::seg($id) . '/credit/' . self::seg($grantId),
            options: self::opts($options),
        ));
    }
}
