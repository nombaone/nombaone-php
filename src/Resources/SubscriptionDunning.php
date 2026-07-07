<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\DunningAttempt;
use NombaOne\Models\DunningState;
use NombaOne\Page;

/**
 * Read-only view into a subscription's recovery state.
 *
 * Reached as `$nomba->subscriptions->dunning`.
 */
final class SubscriptionDunning extends Resource
{
    /**
     * Where the subscription stands in dunning. Check `graceAccessUntil` before
     * cutting access — `past_due` usually means "not yet", not "no".
     *
     * @param array<string, mixed> $options
     */
    public function retrieve(string $subscriptionId, array $options = []): DunningState
    {
        return $this->requestModel(DunningState::class, new Request(
            'GET',
            '/subscriptions/' . self::seg($subscriptionId) . '/dunning',
            options: self::opts($options),
        ));
    }

    /**
     * List every recovery attempt, newest first.
     *
     * @param array{limit?: int, cursor?: string} $params
     * @param array<string, mixed>                $options
     *
     * @return Page<DunningAttempt>
     */
    public function listAttempts(string $subscriptionId, array $params = [], array $options = []): Page
    {
        return $this->requestPage(DunningAttempt::class, new Request(
            'GET',
            '/subscriptions/' . self::seg($subscriptionId) . '/dunning/attempts',
            query: $params,
            options: self::opts($options),
        ));
    }
}
