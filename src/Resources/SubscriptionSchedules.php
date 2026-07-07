<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\SubscriptionSchedule;

/**
 * Scheduled (next-cycle) changes queued against a subscription.
 *
 * Reached as `$nomba->subscriptions->schedule`.
 */
final class SubscriptionSchedules extends Resource
{
    /**
     * Queue a change for the next cycle boundary — the safe way to switch
     * billing intervals (mid-cycle interval proration is unsupported).
     *
     * @param array{priceId: string, quantity?: int, effectiveAt?: string} $params
     * @param array<string, mixed>                                         $options
     *
     * @throws \NombaOne\Exceptions\ConflictException 409 `SUBSCRIPTION_SCHEDULE_CONFLICT`
     */
    public function create(string $subscriptionId, array $params, array $options = []): SubscriptionSchedule
    {
        return $this->requestModel(SubscriptionSchedule::class, new Request(
            'POST',
            '/subscriptions/' . self::seg($subscriptionId) . '/schedule',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve the subscription's schedule.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `SUBSCRIPTION_SCHEDULE_NOT_FOUND`
     */
    public function retrieve(string $subscriptionId, array $options = []): SubscriptionSchedule
    {
        return $this->requestModel(SubscriptionSchedule::class, new Request(
            'GET',
            '/subscriptions/' . self::seg($subscriptionId) . '/schedule',
            options: self::opts($options),
        ));
    }

    /**
     * Cancel the pending schedule before it applies.
     *
     * @param array<string, mixed> $options
     */
    public function release(string $subscriptionId, array $options = []): SubscriptionSchedule
    {
        return $this->requestModel(SubscriptionSchedule::class, new Request(
            'DELETE',
            '/subscriptions/' . self::seg($subscriptionId) . '/schedule',
            options: self::opts($options),
        ));
    }
}
