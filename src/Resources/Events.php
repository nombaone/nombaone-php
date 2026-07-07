<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\DomainEvent;
use NombaOne\Page;

/**
 * Events — the append-only log behind every webhook. Webhook delivery is
 * at-least-once; this log is your reconciliation backstop when a delivery was
 * missed or you need to backfill.
 */
final class Events extends Resource
{
    /**
     * List events, newest first.
     *
     * @param array{type?: string, limit?: int, cursor?: string} $params
     * @param array<string, mixed>                               $options
     *
     * @return Page<DomainEvent>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(DomainEvent::class, new Request(
            'GET',
            '/events',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve one event by id (`nbo…evt`).
     *
     * @param array<string, mixed> $options
     */
    public function retrieve(string $id, array $options = []): DomainEvent
    {
        return $this->requestModel(DomainEvent::class, new Request(
            'GET',
            '/events/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * The machine-readable event catalog — every event type the platform can
     * emit, each with a `when` description and its `data` `payload` keys.
     * Returned as a map keyed by event type.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function catalog(array $options = []): array
    {
        $data = $this->requestRaw(new Request(
            'GET',
            '/events/catalog',
            options: self::opts($options),
        ))->data;

        $catalog = [];
        foreach ($data as $key => $value) {
            $catalog[(string) $key] = $value;
        }

        return $catalog;
    }
}
