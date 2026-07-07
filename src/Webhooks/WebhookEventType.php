<?php

declare(strict_types=1);

namespace NombaOne\Webhooks;

/**
 * The catalog of event types the platform delivers, as constants. Switch on a
 * {@see WebhookEvent::$type} against these rather than typing raw strings.
 *
 * The catalog is **kept open**: {@see WebhookEvent::$type} is a plain `string`,
 * so an event type the platform adds tomorrow still parses today.
 *
 * The `data` keys carried by the notable typed payloads:
 * - `invoice.payment_failed` → `reference`, `reason`
 * - `invoice.payment_partially_collected` → `reference`, `amountPaid`, `amountRemaining`
 * - `invoice.action_required` → `reference`, `reason`, `checkoutLink`
 * - `payment_method.attached` → `reference`, `kind`, `status`
 * - `payment_method.updated` → `reference`, `subscription`
 * - `subscription.created` → `reference`, `status`
 * - `coupon.created` → `reference`, `code`
 * - all others → `reference`
 */
final class WebhookEventType
{
    public const CUSTOMER_CREATED = 'customer.created';
    public const CUSTOMER_UPDATED = 'customer.updated';

    public const COUPON_CREATED = 'coupon.created';

    public const DISCOUNT_CREATED = 'discount.created';
    public const DISCOUNT_REMOVED = 'discount.removed';

    public const PLAN_CREATED = 'plan.created';
    public const PLAN_UPDATED = 'plan.updated';
    public const PLAN_ARCHIVED = 'plan.archived';

    public const PRICE_CREATED = 'price.created';
    public const PRICE_DEACTIVATED = 'price.deactivated';

    public const SUBSCRIPTION_CREATED = 'subscription.created';
    public const SUBSCRIPTION_UPDATED = 'subscription.updated';
    public const SUBSCRIPTION_TRIAL_WILL_END = 'subscription.trial_will_end';
    public const SUBSCRIPTION_ACTIVATED = 'subscription.activated';
    public const SUBSCRIPTION_PAUSED = 'subscription.paused';
    public const SUBSCRIPTION_RESUMED = 'subscription.resumed';
    public const SUBSCRIPTION_CANCELED = 'subscription.canceled';
    public const SUBSCRIPTION_CHURNED = 'subscription.churned';

    public const INVOICE_CREATED = 'invoice.created';
    public const INVOICE_FINALIZED = 'invoice.finalized';
    public const INVOICE_PAID = 'invoice.paid';
    public const INVOICE_PAYMENT_FAILED = 'invoice.payment_failed';
    public const INVOICE_PAYMENT_PARTIALLY_COLLECTED = 'invoice.payment_partially_collected';
    public const INVOICE_PAYMENT_RECOVERED = 'invoice.payment_recovered';
    public const INVOICE_ACTION_REQUIRED = 'invoice.action_required';
    public const INVOICE_VOIDED = 'invoice.voided';

    public const PAYMENT_METHOD_ATTACHED = 'payment_method.attached';
    public const PAYMENT_METHOD_UPDATED = 'payment_method.updated';
    public const PAYMENT_METHOD_EXPIRING = 'payment_method.expiring';

    public const SETTLEMENT_CREATED = 'settlement.created';
    public const SETTLEMENT_REFUNDED = 'settlement.refunded';
    public const SETTLEMENT_PAYOUT_CREATED = 'settlement.payout_created';

    /** @var array<string, true> */
    private const KNOWN = [
        self::CUSTOMER_CREATED => true,
        self::CUSTOMER_UPDATED => true,
        self::COUPON_CREATED => true,
        self::DISCOUNT_CREATED => true,
        self::DISCOUNT_REMOVED => true,
        self::PLAN_CREATED => true,
        self::PLAN_UPDATED => true,
        self::PLAN_ARCHIVED => true,
        self::PRICE_CREATED => true,
        self::PRICE_DEACTIVATED => true,
        self::SUBSCRIPTION_CREATED => true,
        self::SUBSCRIPTION_UPDATED => true,
        self::SUBSCRIPTION_TRIAL_WILL_END => true,
        self::SUBSCRIPTION_ACTIVATED => true,
        self::SUBSCRIPTION_PAUSED => true,
        self::SUBSCRIPTION_RESUMED => true,
        self::SUBSCRIPTION_CANCELED => true,
        self::SUBSCRIPTION_CHURNED => true,
        self::INVOICE_CREATED => true,
        self::INVOICE_FINALIZED => true,
        self::INVOICE_PAID => true,
        self::INVOICE_PAYMENT_FAILED => true,
        self::INVOICE_PAYMENT_PARTIALLY_COLLECTED => true,
        self::INVOICE_PAYMENT_RECOVERED => true,
        self::INVOICE_ACTION_REQUIRED => true,
        self::INVOICE_VOIDED => true,
        self::PAYMENT_METHOD_ATTACHED => true,
        self::PAYMENT_METHOD_UPDATED => true,
        self::PAYMENT_METHOD_EXPIRING => true,
        self::SETTLEMENT_CREATED => true,
        self::SETTLEMENT_REFUNDED => true,
        self::SETTLEMENT_PAYOUT_CREATED => true,
    ];

    private function __construct()
    {
    }

    /** Whether a type is one this SDK version knows about (an unknown type is still valid). */
    public static function isKnown(string $type): bool
    {
        return isset(self::KNOWN[$type]);
    }

    /**
     * Every public event type known to this SDK version.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::KNOWN);
    }
}
