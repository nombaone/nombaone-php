<?php

declare(strict_types=1);

namespace NombaOne\Webhooks;

/**
 * A verified, parsed webhook delivery.
 *
 * Switch on {@see $type} (compare against {@see WebhookEventType}) and read the
 * payload from {@see $data}. **Dedupe on {@see dedupeKey()}** (the underlying
 * event id) — delivery is at-least-once, never exactly-once.
 */
final class WebhookEvent implements \JsonSerializable
{
    /**
     * @param string                  $id    the delivery reference (`nbo…whd`)
     * @param WebhookEventTarget       $event the underlying domain event — dedupe on `$event->id`
     * @param array<array-key, mixed>  $data  the event payload (e.g. `reference`, `reason`, `checkoutLink`)
     * @param array<array-key, mixed>  $raw   the full decoded delivery body
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly WebhookEventTarget $event,
        public readonly array $data,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<array-key, mixed> $body the decoded delivery body
     */
    public static function fromArray(array $body): self
    {
        $eventRaw = $body['event'] ?? null;
        if (is_array($eventRaw)) {
            $target = WebhookEventTarget::fromArray($eventRaw);
        } else {
            // Defensive: an older, flat delivery shape carries id/type/createdAt at
            // the top level with no nested `event`. Synthesize it so dedupe still works.
            $target = new WebhookEventTarget(
                id: is_string($body['id'] ?? null) ? $body['id'] : '',
                type: is_string($body['type'] ?? null) ? $body['type'] : '',
                createdAt: is_string($body['createdAt'] ?? null) ? $body['createdAt'] : '',
            );
        }

        return new self(
            id: is_string($body['id'] ?? null) ? $body['id'] : '',
            type: is_string($body['type'] ?? null) ? $body['type'] : '',
            event: $target,
            data: is_array($body['data'] ?? null) ? $body['data'] : [],
            raw: $body,
        );
    }

    /**
     * The id to dedupe on before acting — stable across replays. Equivalent to
     * `$event->event->id`.
     */
    public function dedupeKey(): string
    {
        return $this->event->id;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }
}
