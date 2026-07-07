<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** One attempt-tracked delivery of an event to one endpoint. */
final class WebhookDelivery extends Model
{
    /**
     * @param string $id      `nbo…whd`
     * @param string $eventId the domain event this delivery carries (`nbo…evt`) — the dedupe key
     * @param string $status  `pending` | `succeeded` | `failed` | `dead`
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $id,
        public readonly string $eventType,
        public readonly string $endpointId,
        public readonly string $eventId,
        public readonly string $status,
        public readonly int $attempts,
        public readonly ?string $nextAttemptAt,
        public readonly ?string $lastAttemptAt,
        public readonly ?int $responseStatus,
        public readonly ?string $replayedAt,
        public readonly int $replayCount,
        public readonly string $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'webhook_delivery'),
            id: Field::str($data, 'id'),
            eventType: Field::str($data, 'eventType'),
            endpointId: Field::str($data, 'endpointId'),
            eventId: Field::str($data, 'eventId'),
            status: Field::str($data, 'status'),
            attempts: Field::int($data, 'attempts'),
            nextAttemptAt: Field::nstr($data, 'nextAttemptAt'),
            lastAttemptAt: Field::nstr($data, 'lastAttemptAt'),
            responseStatus: Field::nint($data, 'responseStatus'),
            replayedAt: Field::nstr($data, 'replayedAt'),
            replayCount: Field::int($data, 'replayCount'),
            createdAt: Field::str($data, 'createdAt'),
            raw: $data,
        );
    }
}
