<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\TenantSettings;
use NombaOne\Nombaone;

/**
 * Organization settings — configuration, not a billing object. Billing +
 * dunning policy lives under `$nomba->organization->billing`.
 */
final class Organization extends Resource
{
    /** Billing + dunning policy. */
    public readonly OrganizationBilling $billing;

    public function __construct(Nombaone $client)
    {
        parent::__construct($client);
        $this->billing = new OrganizationBilling($client);
    }

    /**
     * Read org-level settings (limits, settlement mode, branding, statuses).
     *
     * @param array<string, mixed> $options
     */
    public function retrieve(array $options = []): TenantSettings
    {
        return $this->requestModel(TenantSettings::class, new Request(
            'GET',
            '/organization',
            options: self::opts($options),
        ));
    }

    /**
     * Update tenant-editable settings. PUT semantics; at least one field
     * required. Rate limits are operator-set (not here).
     *
     * @param array{monthlyRequestQuota?: int, settlementMode?: string, branding?: array{displayName?: string, supportEmail?: string, logoUrl?: string, primaryColorHex?: string}} $params
     * @param array<string, mixed> $options
     */
    public function update(array $params, array $options = []): TenantSettings
    {
        return $this->requestModel(TenantSettings::class, new Request(
            'PUT',
            '/organization',
            body: $params,
            options: self::opts($options),
        ));
    }
}
