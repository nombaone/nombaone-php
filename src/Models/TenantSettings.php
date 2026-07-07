<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * Org-level configuration: limits, settlement mode, branding, and webhook +
 * Nomba account status. The nested blocks are exposed as arrays (they are
 * configuration blobs); the full decoded payload is always on `$raw`.
 */
final class TenantSettings extends Model
{
    /**
     * @param array<string, mixed> $billing      `rateLimitPerMinute`, `monthlyRequestQuota`, `settlementMode`, `platformFee`, `grace`, `branding`
     * @param array<string, mixed> $webhook      `url`, `signingSecretPrefix`, `configured`
     * @param array<string, mixed> $nombaAccount `accountRef`, `status`
     */
    public function __construct(
        public readonly string $domain,
        public readonly array $billing,
        public readonly array $webhook,
        public readonly array $nombaAccount,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'organization'),
            billing: Field::map($data, 'billing'),
            webhook: Field::map($data, 'webhook'),
            nombaAccount: Field::map($data, 'nombaAccount'),
            raw: $data,
        );
    }
}
