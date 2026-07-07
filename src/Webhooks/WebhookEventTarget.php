<?php

declare(strict_types=1);

namespace NombaOne\Webhooks;

/**
 * The underlying domain event of a delivery. **Dedupe on `$id`** (`nbo…evt`) —
 * webhook delivery is at-least-once, and replays keep this id stable.
 */
final class WebhookEventTarget
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: is_string($data['id'] ?? null) ? $data['id'] : '',
            type: is_string($data['type'] ?? null) ? $data['type'] : '',
            createdAt: is_string($data['createdAt'] ?? null) ? $data['createdAt'] : '',
        );
    }
}
