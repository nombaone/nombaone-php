<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\WebhookDelivery;
use NombaOne\Page;

/**
 * Deliveries under an endpoint: inspect and replay.
 *
 * Reached as `$nomba->webhookEndpoints->deliveries`.
 */
final class WebhookEndpointDeliveries extends Resource
{
    /**
     * List an endpoint's deliveries, newest first.
     *
     * @param array{status?: string, eventType?: string, endpoint?: string, limit?: int, cursor?: string} $params
     * @param array<string, mixed> $options
     *
     * @return Page<WebhookDelivery>
     */
    public function list(string $endpointId, array $params = [], array $options = []): Page
    {
        return $this->requestPage(WebhookDelivery::class, new Request(
            'GET',
            '/webhooks/' . self::seg($endpointId) . '/deliveries',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve one delivery.
     *
     * @param array<string, mixed> $options
     */
    public function retrieve(string $endpointId, string $deliveryId, array $options = []): WebhookDelivery
    {
        return $this->requestModel(WebhookDelivery::class, new Request(
            'GET',
            '/webhooks/' . self::seg($endpointId) . '/deliveries/' . self::seg($deliveryId),
            options: self::opts($options),
        ));
    }

    /**
     * Redeliver a past delivery. The **original event id is kept**, so a
     * receiver that dedupes on the event id correctly treats it as already-seen.
     *
     * @param array<string, mixed> $options
     */
    public function replay(string $endpointId, string $deliveryId, array $options = []): WebhookDelivery
    {
        return $this->requestModel(WebhookDelivery::class, new Request(
            'POST',
            '/webhooks/' . self::seg($endpointId) . '/deliveries/' . self::seg($deliveryId) . '/replay',
            body: [],
            options: self::opts($options),
        ));
    }
}
