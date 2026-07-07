<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * An entry in the append-only domain-event log — the audit trail behind every
 * webhook. `payload` carries the same `data` your endpoints receive.
 */
final class DomainEvent extends Model
{
    /**
     * @param string               $id      `nbo…evt` — the id webhook receivers dedupe on
     * @param string               $type    catalog event type, e.g. `invoice.paid`
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $type,
        public readonly array $payload,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'event'),
            id: Field::str($data, 'id'),
            type: Field::str($data, 'type'),
            payload: Field::map($data, 'payload'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
