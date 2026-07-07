<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** A URL you registered to receive signed event deliveries. */
final class WebhookEndpoint extends Model
{
    /**
     * @param string       $id            `nbo…whk`
     * @param list<string> $enabledEvents event types fanned out to this endpoint; `['*']` means everything
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $url,
        public readonly array $enabledEvents,
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
            signingSecretPrefix: Field::str($data, 'signingSecretPrefix'),
            disabledAt: Field::nstr($data, 'disabledAt'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
