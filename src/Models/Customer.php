<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A subscriber — the person or business you bill.
 */
final class Customer extends Model
{
    /**
     * @param string               $id        `nbo…cus`
     * @param string               $email     unique within your organization and environment
     * @param array<string, mixed> $metadata  free-form annotations you attached
     * @param string               $mode      `sandbox` or `live`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $phone,
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
            domain: Field::str($data, 'domain', 'customer'),
            id: Field::str($data, 'id'),
            email: Field::str($data, 'email'),
            name: Field::str($data, 'name'),
            phone: Field::nstr($data, 'phone'),
            metadata: Field::map($data, 'metadata'),
            mode: Field::str($data, 'mode'),
            createdAt: Field::str($data, 'createdAt'),
            updatedAt: Field::str($data, 'updatedAt'),
            raw: $data,
        );
    }
}
