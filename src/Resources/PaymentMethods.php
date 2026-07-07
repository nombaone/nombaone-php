<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\CheckoutSetup;
use NombaOne\Models\PaymentMethod;
use NombaOne\Models\VirtualAccount;
use NombaOne\Page;

/**
 * Payment methods — cards (via hosted checkout), direct-debit mandates (see
 * `$nomba->mandates`), and virtual accounts for the transfer rail.
 *
 * Note the filter and setup fields use `customerRef` (not `customerId`) — a
 * wire-name quirk this SDK mirrors faithfully.
 */
final class PaymentMethods extends Resource
{
    /**
     * Start a hosted-checkout card capture. Card entry happens on the PCI hosted
     * page — no card data ever touches your servers. The method appears as
     * `setup_pending` until the customer completes checkout.
     *
     * @param array{customerRef: string, amountInKobo: int, callbackUrl: string} $params
     * @param array<string, mixed> $options
     *
     * `amountInKobo` is the integer-kobo validation charge (₦1.00 = 100).
     *
     * @example
     * ```php
     * $setup = $nomba->paymentMethods->setup([
     *     'customerRef'  => $customer->id,
     *     'amountInKobo' => 5_000, // ₦50 validation charge
     *     'callbackUrl'  => 'https://example.com/billing/return',
     * ]);
     * // redirect the customer to $setup->checkoutLink
     * ```
     */
    public function setup(array $params, array $options = []): CheckoutSetup
    {
        return $this->requestModel(CheckoutSetup::class, new Request(
            'POST',
            '/payment-methods/setup',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Issue a dedicated virtual account (NUBAN) so the customer can pay by bank
     * transfer. The engine matches inbound transfers to invoices by reference
     * and exact integer-kobo amount.
     *
     * @param array{customerRef: string, expectedAmount?: int, expiryDate?: string} $params
     * @param array<string, mixed> $options
     */
    public function createVirtualAccount(array $params, array $options = []): VirtualAccount
    {
        return $this->requestModel(VirtualAccount::class, new Request(
            'POST',
            '/payment-methods/virtual-account',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve a payment method by id.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `PAYMENT_METHOD_NOT_FOUND`
     */
    public function retrieve(string $id, array $options = []): PaymentMethod
    {
        return $this->requestModel(PaymentMethod::class, new Request(
            'GET',
            '/payment-methods/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * List payment methods, newest first.
     *
     * @param array{customerRef?: string, limit?: int, cursor?: string} $params
     * @param array<string, mixed>                                      $options
     *
     * @return Page<PaymentMethod>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(PaymentMethod::class, new Request(
            'GET',
            '/payment-methods',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Make this the customer's default payment method.
     *
     * @param array<string, mixed> $options
     */
    public function setDefault(string $id, array $options = []): PaymentMethod
    {
        return $this->requestModel(PaymentMethod::class, new Request(
            'POST',
            '/payment-methods/' . self::seg($id) . '/default',
            body: [],
            options: self::opts($options),
        ));
    }

    /**
     * Detach a payment method. Subscriptions still billing against it will need
     * a replacement (`SUBSCRIPTION_PAYMENT_METHOD_REQUIRED` at next charge).
     *
     * @param array<string, mixed> $options
     */
    public function remove(string $id, array $options = []): PaymentMethod
    {
        return $this->requestModel(PaymentMethod::class, new Request(
            'DELETE',
            '/payment-methods/' . self::seg($id),
            options: self::opts($options),
        ));
    }
}
