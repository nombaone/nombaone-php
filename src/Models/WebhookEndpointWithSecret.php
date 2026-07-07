<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * Returned by `create` only — the one time the full signing secret is visible.
 * Store `signingSecret` now; it is not recoverable later (only rotatable).
 */
final class WebhookEndpointWithSecret extends Model
{
    /**
     * @param list<string> $enabledEvents
     * @param string       $signingSecret the full signing secret — shown exactly once
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $url,
        public readonly array $enabledEvents,
        public readonly string $signingSecret,
        public readonly string $signingSecretPrefix,
        public readonly ?string $disabledAt,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'webhook'),
            id: Field::str($data, 'id'),
            url: Field::str($data, 'url'),
            enabledEvents: Field::strList($data, 'enabledEvents'),
            signingSecret: Field::str($data, 'signingSecret'),
            signingSecretPrefix: Field::str($data, 'signingSecretPrefix'),
            disabledAt: Field::nstr($data, 'disabledAt'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
