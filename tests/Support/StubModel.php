<?php

declare(strict_types=1);

namespace NombaOne\Tests\Support;

use NombaOne\Models\Model;

/**
 * A minimal {@see Model} used to exercise pagination without depending on any
 * real resource DTO.
 */
final class StubModel extends Model
{
    public function __construct(public readonly string $id, array $raw = [])
    {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        $id = $data['id'] ?? '';

        return new self(is_string($id) ? $id : '', $data);
    }
}
