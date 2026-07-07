<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\Discount;
use NombaOne\Models\DomainEvent;
use NombaOne\Models\PaymentMethod;
use NombaOne\Models\Subscription;
use NombaOne\Models\UpcomingInvoice;
use NombaOne\Nombaone;
use NombaOne\Page;

/**
 * Subscriptions — the core object. Create one against a customer and a price;
 * the engine handles cycles, invoices, retries, and recovery.
 *
 * @example
 * ```php
 * $subscription = $nomba->subscriptions->create([
 *     'customerId'      => $customer->id,
 *     'priceId'         => $price->id,
 *     'paymentMethodId' => $paymentMethod->id,
 * ]);
 * echo $subscription->status; // "active"
 * ```
 */
final class Subscriptions extends Resource
{
    /** Scheduled (next-cycle) changes. */
    public readonly SubscriptionSchedules $schedule;

    /** Recovery/dunning state (read-only). */
    public readonly SubscriptionDunning $dunning;

    public function __construct(Nombaone $client)
    {
        parent::__construct($client);
        $this->schedule = new SubscriptionSchedules($client);
        $this->dunning = new SubscriptionDunning($client);
    }

    /**
     * Create a subscription. This can move money (the first charge), so the API
     * requires an `Idempotency-Key`; the SDK sends one automatically and reuses
     * it across its own retries.
     *
     * @param array{customerId: string, priceId: string, paymentMethodId?: string, collectionMethod?: string, trialDays?: int, quantity?: int, metadata?: array<string, mixed>} $params
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\ValidationException 422 — e.g. a missing payment method without a trial.
     * @throws \NombaOne\Exceptions\ConflictException   409 `SUBSCRIPTION_PAYMENT_METHOD_REQUIRED`
     */
    public function create(array $params, array $options = []): Subscription
    {
        return $this->requestModel(Subscription::class, new Request(
            'POST',
            '/subscriptions',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve a subscription by id.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `SUBSCRIPTION_NOT_FOUND`
     */
    public function retrieve(string $id, array $options = []): Subscription
    {
        return $this->requestModel(Subscription::class, new Request(
            'GET',
            '/subscriptions/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * Edit metadata or the default payment method. For price/quantity/interval
     * changes use {@see change()} — those prorate. At least one field required.
     *
     * @param array{defaultPaymentMethodId?: string, metadata?: array<string, mixed>} $params
     * @param array<string, mixed>                                                    $options
     */
    public function update(string $id, array $params, array $options = []): Subscription
    {
        return $this->requestModel(Subscription::class, new Request(
            'PATCH',
            '/subscriptions/' . self::seg($id),
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * List subscriptions, newest first.
     *
     * @param array{customerId?: string, status?: string, limit?: int, cursor?: string} $params
     * @param array<string, mixed>                                                       $options
     *
     * @return Page<Subscription>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(Subscription::class, new Request(
            'GET',
            '/subscriptions',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * The subscription's audit trail of domain events, newest first.
     *
     * @param array{limit?: int, cursor?: string} $params
     * @param array<string, mixed>                $options
     *
     * @return Page<DomainEvent>
     */
    public function listEvents(string $id, array $params = [], array $options = []): Page
    {
        return $this->requestPage(DomainEvent::class, new Request(
            'GET',
            '/subscriptions/' . self::seg($id) . '/events',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Pause billing. The subscription keeps its place in the cycle and resumes
     * cleanly.
     *
     * @param array{maxDays?: int}  $params auto-resume after this many days
     * @param array<string, mixed>  $options
     *
     * @throws \NombaOne\Exceptions\ConflictException 409 `SUBSCRIPTION_ILLEGAL_TRANSITION`
     */
    public function pause(string $id, array $params = [], array $options = []): Subscription
    {
        return $this->requestModel(Subscription::class, new Request(
            'POST',
            '/subscriptions/' . self::seg($id) . '/pause',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Resume a paused subscription.
     *
     * @param array<string, mixed> $options
     */
    public function resume(string $id, array $options = []): Subscription
    {
        return $this->requestModel(Subscription::class, new Request(
            'POST',
            '/subscriptions/' . self::seg($id) . '/resume',
            body: [],
            options: self::opts($options),
        ));
    }

    /**
     * Cancel a subscription — immediately (default) or at period end.
     *
     * @param array{mode?: string, comment?: string} $params `mode` is `now` (default) or `at_period_end`
     * @param array<string, mixed>                   $options
     *
     * @example
     * ```php
     * $nomba->subscriptions->cancel($subscription->id, ['mode' => 'at_period_end']);
     * ```
     */
    public function cancel(string $id, array $params = [], array $options = []): Subscription
    {
        return $this->requestModel(Subscription::class, new Request(
            'POST',
            '/subscriptions/' . self::seg($id) . '/cancel',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Start a fresh subscription for a canceled one's customer, reusing the old
     * price/payment method unless overridden. The subscription must be terminal.
     *
     * @param array{priceId?: string, paymentMethodId?: string} $params
     * @param array<string, mixed>                              $options
     *
     * @throws \NombaOne\Exceptions\ConflictException 409 `SUBSCRIPTION_NOT_TERMINAL`
     */
    public function resubscribe(string $id, array $params = [], array $options = []): Subscription
    {
        return $this->requestModel(Subscription::class, new Request(
            'POST',
            '/subscriptions/' . self::seg($id) . '/resubscribe',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Change price or quantity mid-cycle, prorating by default. Switching the
     * billing interval mid-cycle is unsupported
     * (`PRORATION_INTERVAL_SWITCH_UNSUPPORTED`) — queue it with
     * `$nomba->subscriptions->schedule->create(...)` instead.
     *
     * @param array{priceId?: string, quantity?: int, intervalSwitch?: bool, prorationBehavior?: string} $params
     * @param array<string, mixed> $options
     *
     * @example
     * ```php
     * $nomba->subscriptions->change($subscription->id, ['priceId' => $biggerPrice->id]);
     * ```
     */
    public function change(string $id, array $params, array $options = []): Subscription
    {
        return $this->requestModel(Subscription::class, new Request(
            'POST',
            '/subscriptions/' . self::seg($id) . '/change',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Swap the payment method that bills this subscription — the card-update
     * path during dunning. Provide exactly one of `paymentMethodReference` or
     * `checkoutToken`.
     *
     * Returns the attached **{@see PaymentMethod}**, not the subscription — the
     * wire returns the payment method here even though the OpenAPI spec labels
     * it a subscription (a backend quirk this SDK mirrors, verified live).
     *
     * @param array{paymentMethodReference?: string, checkoutToken?: string} $params
     * @param array<string, mixed>                                           $options
     */
    public function updatePaymentMethod(string $id, array $params, array $options = []): PaymentMethod
    {
        return $this->requestModel(PaymentMethod::class, new Request(
            'POST',
            '/subscriptions/' . self::seg($id) . '/payment-method',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Preview the next invoice without charging or storing anything.
     *
     * @param array<string, mixed> $options
     */
    public function retrieveUpcomingInvoice(string $id, array $options = []): UpcomingInvoice
    {
        return $this->requestModel(UpcomingInvoice::class, new Request(
            'GET',
            '/subscriptions/' . self::seg($id) . '/upcoming-invoice',
            options: self::opts($options),
        ));
    }

    /**
     * Apply a coupon to this subscription only.
     *
     * @param array{coupon: string} $params a coupon id (`nbo…cpn`) or its code
     * @param array<string, mixed>  $options
     */
    public function applyDiscount(string $id, array $params, array $options = []): Discount
    {
        return $this->requestModel(Discount::class, new Request(
            'POST',
            '/subscriptions/' . self::seg($id) . '/discount',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Remove the subscription's active discount. Returns the ended discount.
     *
     * @param array<string, mixed> $options
     */
    public function removeDiscount(string $id, array $options = []): Discount
    {
        return $this->requestModel(Discount::class, new Request(
            'DELETE',
            '/subscriptions/' . self::seg($id) . '/discount',
            options: self::opts($options),
        ));
    }
}
