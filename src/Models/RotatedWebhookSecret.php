<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** Returned by `rotateSecret` — the only time the new secret is visible. */
final class RotatedWebhookSecret extends Model
{
    /**
     * @param string $signingSecret the new full signing secret — shown exactly once
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $signingSecret,
        public readonly string $signingSecretPrefix,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'webhook_secret'),
            id: Field::str($data, 'id'),
            signingSecret: Field::str($data, 'signingSecret'),
            signingSecretPrefix: Field::str($data, 'signingSecretPrefix'),
            raw: $data,
        );
    }
}
