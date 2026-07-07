<?php

declare(strict_types=1);

namespace NombaOne;

/**
 * Every error `code` the public API can emit, vendored verbatim from the
 * platform's `PUBLIC_ERROR_CODES` (72 codes). Branch on these constants rather
 * than on an error's `message`, which may be reworded.
 *
 * The list is a **closed catalog kept open**: {@see NombaOne\Exceptions\ApiException::$code}
 * is a plain `string`, so a code the API adds tomorrow still parses today —
 * these constants merely name the ones known at build time. Anything the
 * backend does not consider public is collapsed to {@see self::SYSTEM_INTERNAL_ERROR}
 * before it reaches you.
 *
 * @example
 * ```php
 * try {
 *     $nomba->customers->retrieve($id);
 * } catch (\NombaOne\Exceptions\NotFoundException $e) {
 *     if ($e->code === \NombaOne\ErrorCode::CUSTOMER_NOT_FOUND) {
 *         // ...
 *     }
 * }
 * ```
 */
final class ErrorCode
{
    // ---- Generic request errors ----
    public const CLIENT_INVALID_REQUEST = 'CLIENT_INVALID_REQUEST';
    public const CLIENT_VALIDATION_FAILED = 'CLIENT_VALIDATION_FAILED';
    public const CLIENT_FORBIDDEN = 'CLIENT_FORBIDDEN';
    public const CLIENT_ROUTE_NOT_FOUND = 'CLIENT_ROUTE_NOT_FOUND';
    public const CLIENT_RESOURCE_NOT_FOUND = 'CLIENT_RESOURCE_NOT_FOUND';
    public const CLIENT_CONFLICT = 'CLIENT_CONFLICT';
    public const INVALID_CURSOR = 'INVALID_CURSOR';

    // ---- API-key auth ----
    public const API_KEY_MISSING = 'API_KEY_MISSING';
    public const API_KEY_INVALID = 'API_KEY_INVALID';
    public const API_KEY_SCOPE_FORBIDDEN = 'API_KEY_SCOPE_FORBIDDEN';
    public const API_KEY_ENVIRONMENT_MISMATCH = 'API_KEY_ENVIRONMENT_MISMATCH';
    public const API_KEY_HOST_MISMATCH = 'API_KEY_HOST_MISMATCH';

    // ---- Idempotency ----
    public const IDEMPOTENCY_KEY_MISSING = 'IDEMPOTENCY_KEY_MISSING';
    public const IDEMPOTENCY_KEY_REUSED = 'IDEMPOTENCY_KEY_REUSED';
    public const IDEMPOTENCY_IN_PROGRESS = 'IDEMPOTENCY_IN_PROGRESS';

    // ---- Rate limiting / platform ----
    public const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    public const QUOTA_EXCEEDED = 'QUOTA_EXCEEDED';
    public const PLATFORM_MAINTENANCE = 'PLATFORM_MAINTENANCE';

    // ---- Webhooks ----
    public const WEBHOOK_SIGNATURE_INVALID = 'WEBHOOK_SIGNATURE_INVALID';

    // ---- Customers ----
    public const CUSTOMER_NOT_FOUND = 'CUSTOMER_NOT_FOUND';
    public const CUSTOMER_EMAIL_TAKEN = 'CUSTOMER_EMAIL_TAKEN';

    // ---- Plans & prices ----
    public const PLAN_NOT_FOUND = 'PLAN_NOT_FOUND';
    public const PLAN_NAME_TAKEN = 'PLAN_NAME_TAKEN';
    public const PLAN_ALREADY_ARCHIVED = 'PLAN_ALREADY_ARCHIVED';
    public const PLAN_HAS_ACTIVE_SUBSCRIBERS = 'PLAN_HAS_ACTIVE_SUBSCRIBERS';
    public const PRICE_NOT_FOUND = 'PRICE_NOT_FOUND';
    public const PRICE_PLAN_MISMATCH = 'PRICE_PLAN_MISMATCH';
    public const PRICE_ALREADY_INACTIVE = 'PRICE_ALREADY_INACTIVE';
    public const PRICE_TIERED_NOT_SUPPORTED = 'PRICE_TIERED_NOT_SUPPORTED';

    // ---- Payment methods & mandates ----
    public const PAYMENT_METHOD_NOT_FOUND = 'PAYMENT_METHOD_NOT_FOUND';
    public const PAYMENT_METHOD_NOT_ACTIVE = 'PAYMENT_METHOD_NOT_ACTIVE';
    public const PAYMENT_METHOD_KIND_MISMATCH = 'PAYMENT_METHOD_KIND_MISMATCH';
    public const MANDATE_NOT_ACTIVE = 'MANDATE_NOT_ACTIVE';
    public const MANDATE_MAX_AMOUNT_EXCEEDED = 'MANDATE_MAX_AMOUNT_EXCEEDED';
    public const MANDATE_CONSENT_PENDING = 'MANDATE_CONSENT_PENDING';

    // ---- Subscriptions & invoices ----
    public const SUBSCRIPTION_NOT_FOUND = 'SUBSCRIPTION_NOT_FOUND';
    public const SUBSCRIPTION_ILLEGAL_TRANSITION = 'SUBSCRIPTION_ILLEGAL_TRANSITION';
    public const SUBSCRIPTION_VERSION_CONFLICT = 'SUBSCRIPTION_VERSION_CONFLICT';
    public const SUBSCRIPTION_NOT_TERMINAL = 'SUBSCRIPTION_NOT_TERMINAL';
    public const SUBSCRIPTION_PAYMENT_METHOD_REQUIRED = 'SUBSCRIPTION_PAYMENT_METHOD_REQUIRED';
    public const INVOICE_NOT_FOUND = 'INVOICE_NOT_FOUND';
    public const INVOICE_ALREADY_FINALIZED = 'INVOICE_ALREADY_FINALIZED';
    public const INVOICE_ALREADY_PAID = 'INVOICE_ALREADY_PAID';
    public const INVOICE_NOT_VOIDABLE = 'INVOICE_NOT_VOIDABLE';

    // ---- Schedules & proration ----
    public const SUBSCRIPTION_SCHEDULE_NOT_FOUND = 'SUBSCRIPTION_SCHEDULE_NOT_FOUND';
    public const SUBSCRIPTION_SCHEDULE_CONFLICT = 'SUBSCRIPTION_SCHEDULE_CONFLICT';
    public const SUBSCRIPTION_SCHEDULE_INVALID_EFFECTIVE_AT = 'SUBSCRIPTION_SCHEDULE_INVALID_EFFECTIVE_AT';
    public const PRORATION_NOT_APPLICABLE = 'PRORATION_NOT_APPLICABLE';
    public const PRORATION_INTERVAL_SWITCH_UNSUPPORTED = 'PRORATION_INTERVAL_SWITCH_UNSUPPORTED';

    // ---- Coupons, discounts & credits ----
    public const COUPON_NOT_FOUND = 'COUPON_NOT_FOUND';
    public const COUPON_EXPIRED = 'COUPON_EXPIRED';
    public const COUPON_MAX_REDEMPTIONS_REACHED = 'COUPON_MAX_REDEMPTIONS_REACHED';
    public const COUPON_INVALID_DEFINITION = 'COUPON_INVALID_DEFINITION';
    public const COUPON_ALREADY_APPLIED = 'COUPON_ALREADY_APPLIED';
    public const DISCOUNT_NOT_FOUND = 'DISCOUNT_NOT_FOUND';
    public const CREDIT_GRANT_NOT_FOUND = 'CREDIT_GRANT_NOT_FOUND';
    public const CREDIT_GRANT_ALREADY_VOIDED = 'CREDIT_GRANT_ALREADY_VOIDED';
    public const CREDIT_INSUFFICIENT_BALANCE = 'CREDIT_INSUFFICIENT_BALANCE';
    public const CREDIT_INVALID_AMOUNT = 'CREDIT_INVALID_AMOUNT';

    // ---- Dunning ----
    public const DUNNING_NO_OPEN_INVOICE = 'DUNNING_NO_OPEN_INVOICE';
    public const DUNNING_ATTEMPT_NOT_FOUND = 'DUNNING_ATTEMPT_NOT_FOUND';
    public const DUNNING_CARD_UPDATE_REQUIRED = 'DUNNING_CARD_UPDATE_REQUIRED';
    public const DUNNING_ALREADY_TERMINAL = 'DUNNING_ALREADY_TERMINAL';

    // ---- Settlement, refunds & payouts ----
    public const SETTLEMENT_NOT_FOUND = 'SETTLEMENT_NOT_FOUND';
    public const SETTLEMENT_SUBACCOUNT_NOT_FOUND = 'SETTLEMENT_SUBACCOUNT_NOT_FOUND';
    public const REFUND_ALREADY_REFUNDED = 'REFUND_ALREADY_REFUNDED';
    public const REFUND_AMOUNT_EXCEEDS_NET = 'REFUND_AMOUNT_EXCEEDS_NET';
    public const ESCROW_LOCKED = 'ESCROW_LOCKED';
    public const PAYOUT_EXCEEDS_AVAILABLE = 'PAYOUT_EXCEEDS_AVAILABLE';

    // ---- Example scaffold ----
    public const EXAMPLE_NOT_FOUND = 'EXAMPLE_NOT_FOUND';

    // ---- System fallbacks ----
    public const SYSTEM_INTERNAL_ERROR = 'SYSTEM_INTERNAL_ERROR';
    public const SYSTEM_UPSTREAM_ERROR = 'SYSTEM_UPSTREAM_ERROR';

    /**
     * Every public code, as a set keyed by value for O(1) membership checks.
     *
     * @var array<string, true>
     */
    private const KNOWN = [
        self::CLIENT_INVALID_REQUEST => true,
        self::CLIENT_VALIDATION_FAILED => true,
        self::CLIENT_FORBIDDEN => true,
        self::CLIENT_ROUTE_NOT_FOUND => true,
        self::CLIENT_RESOURCE_NOT_FOUND => true,
        self::CLIENT_CONFLICT => true,
        self::INVALID_CURSOR => true,
        self::API_KEY_MISSING => true,
        self::API_KEY_INVALID => true,
        self::API_KEY_SCOPE_FORBIDDEN => true,
        self::API_KEY_ENVIRONMENT_MISMATCH => true,
        self::API_KEY_HOST_MISMATCH => true,
        self::IDEMPOTENCY_KEY_MISSING => true,
        self::IDEMPOTENCY_KEY_REUSED => true,
        self::IDEMPOTENCY_IN_PROGRESS => true,
        self::RATE_LIMIT_EXCEEDED => true,
        self::QUOTA_EXCEEDED => true,
        self::PLATFORM_MAINTENANCE => true,
        self::WEBHOOK_SIGNATURE_INVALID => true,
        self::CUSTOMER_NOT_FOUND => true,
        self::CUSTOMER_EMAIL_TAKEN => true,
        self::PLAN_NOT_FOUND => true,
        self::PLAN_NAME_TAKEN => true,
        self::PLAN_ALREADY_ARCHIVED => true,
        self::PLAN_HAS_ACTIVE_SUBSCRIBERS => true,
        self::PRICE_NOT_FOUND => true,
        self::PRICE_PLAN_MISMATCH => true,
        self::PRICE_ALREADY_INACTIVE => true,
        self::PRICE_TIERED_NOT_SUPPORTED => true,
        self::PAYMENT_METHOD_NOT_FOUND => true,
        self::PAYMENT_METHOD_NOT_ACTIVE => true,
        self::PAYMENT_METHOD_KIND_MISMATCH => true,
        self::MANDATE_NOT_ACTIVE => true,
        self::MANDATE_MAX_AMOUNT_EXCEEDED => true,
        self::MANDATE_CONSENT_PENDING => true,
        self::SUBSCRIPTION_NOT_FOUND => true,
        self::SUBSCRIPTION_ILLEGAL_TRANSITION => true,
        self::SUBSCRIPTION_VERSION_CONFLICT => true,
        self::SUBSCRIPTION_NOT_TERMINAL => true,
        self::SUBSCRIPTION_PAYMENT_METHOD_REQUIRED => true,
        self::INVOICE_NOT_FOUND => true,
        self::INVOICE_ALREADY_FINALIZED => true,
        self::INVOICE_ALREADY_PAID => true,
        self::INVOICE_NOT_VOIDABLE => true,
        self::SUBSCRIPTION_SCHEDULE_NOT_FOUND => true,
        self::SUBSCRIPTION_SCHEDULE_CONFLICT => true,
        self::SUBSCRIPTION_SCHEDULE_INVALID_EFFECTIVE_AT => true,
        self::PRORATION_NOT_APPLICABLE => true,
        self::PRORATION_INTERVAL_SWITCH_UNSUPPORTED => true,
        self::COUPON_NOT_FOUND => true,
        self::COUPON_EXPIRED => true,
        self::COUPON_MAX_REDEMPTIONS_REACHED => true,
        self::COUPON_INVALID_DEFINITION => true,
        self::COUPON_ALREADY_APPLIED => true,
        self::DISCOUNT_NOT_FOUND => true,
        self::CREDIT_GRANT_NOT_FOUND => true,
        self::CREDIT_GRANT_ALREADY_VOIDED => true,
        self::CREDIT_INSUFFICIENT_BALANCE => true,
        self::CREDIT_INVALID_AMOUNT => true,
        self::DUNNING_NO_OPEN_INVOICE => true,
        self::DUNNING_ATTEMPT_NOT_FOUND => true,
        self::DUNNING_CARD_UPDATE_REQUIRED => true,
        self::DUNNING_ALREADY_TERMINAL => true,
        self::SETTLEMENT_NOT_FOUND => true,
        self::SETTLEMENT_SUBACCOUNT_NOT_FOUND => true,
        self::REFUND_ALREADY_REFUNDED => true,
        self::REFUND_AMOUNT_EXCEEDS_NET => true,
        self::ESCROW_LOCKED => true,
        self::PAYOUT_EXCEEDS_AVAILABLE => true,
        self::EXAMPLE_NOT_FOUND => true,
        self::SYSTEM_INTERNAL_ERROR => true,
        self::SYSTEM_UPSTREAM_ERROR => true,
    ];

    /** This class is a constant catalog; it is never instantiated. */
    private function __construct()
    {
    }

    /** Whether a code is one this SDK version knows about (an unknown code is still valid). */
    public static function isKnown(string $code): bool
    {
        return isset(self::KNOWN[$code]);
    }

    /**
     * The full set of public codes known to this SDK version.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::KNOWN);
    }
}
