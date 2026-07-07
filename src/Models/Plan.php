<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A plan — what you sell ("Pro", "Starter"). Holds the name and description;
 * its prices (amount + cadence) live underneath it. Plans archive, never delete.
 */
final class Plan extends Model
{
    /**
     * @param string               $id       `nbo…pln`
     * @param string               $status   `active` | `archived`
     * @param array<string, mixed> $metadata free-form annotations
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $status,
        public readonly array $metadata,
        public readonly string $mode,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'plan'),
            id: Field::str($data, 'id'),
            name: Field::str($data, 'name'),
            description: Field::nstr($data, 'description'),
            status: Field::str($data, 'status'),
            metadata: Field::map($data, 'metadata'),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            updatedAt: Field::str($data, 'updatedAt'),
            raw: $data,
        );
    }
}
