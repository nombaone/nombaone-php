<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\BillingSettings;

/**
 * Billing + dunning policy under `/organization/billing`.
 *
 * Reached as `$nomba->organization->billing`.
 */
final class OrganizationBilling extends Resource
{
    /**
     * Read the org's billing + dunning policy.
     *
     * @param array<string, mixed> $options
     */
    public function retrieve(array $options = []): BillingSettings
    {
        return $this->requestModel(BillingSettings::class, new Request(
            'GET',
            '/organization/billing',
            options: self::opts($options),
        ));
    }

    /**
     * Update the billing policy. PUT semantics, but only supplied keys change.
     *
     * @param array{partialCollectionEnabled?: bool, prorationCreditPolicy?: string, dunningMaxAttempts?: int, dunningIntervalsHours?: list<int>, dunningMaxWindowHours?: int, gracePeriodHours?: int, paydayDays?: list<int>, paydayPullForwardDays?: int, paydayBiasEnabled?: bool, defaultCollectionMethod?: string, commsEnabled?: bool} $params
     * @param array<string, mixed> $options
     *
     * @example
     * ```php
     * $nomba->organization->billing->update([
     *     'paydayBiasEnabled' => true,
     *     'paydayDays'        => [25, 28, 30],
     * ]);
     * ```
     */
    public function update(array $params, array $options = []): BillingSettings
    {
        return $this->requestModel(BillingSettings::class, new Request(
            'PUT',
            '/organization/billing',
            body: $params,
            options: self::opts($options),
        ));
    }
}
