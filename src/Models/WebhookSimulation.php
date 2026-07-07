<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** The minted event and how many endpoint deliveries fired (sandbox). */
final class WebhookSimulation extends Model
{
    /**
     * @param string $event the emitted event's reference (`nbo…evt`)
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $event,
        public readonly string $type,
        public readonly int $deliveredCount,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'webhook_simulation'),
            event: Field::str($data, 'event'),
            type: Field::str($data, 'type'),
            deliveredCount: Field::int($data, 'deliveredCount'),
            raw: $data,
        );
    }
}
